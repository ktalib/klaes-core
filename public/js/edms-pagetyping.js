(function () {
    'use strict';

    const config = window.pageTypingConfig || {};
    const lucide = window.lucide || { createIcons: () => {} };
    const Swal = window.Swal || { fire: () => {}, update: () => {}, close: () => {} };
    const routes = config.routes || {};
    const saveSingleUrl = routes.saveSingle || '';
    const typingDataUrl = routes.typingData || '';
    const replacePageUrl = routes.replacePage || '';
    const defaultEndpoints = {
        saveSingle: '/pagetyping/save-single',
        typingData: '/pagetyping/api/typing-data',
        replacePage: '/pagetyping/api/replace-page'
    };
    const savePageEndpoint = saveSingleUrl || defaultEndpoints.saveSingle;
    const typingDataEndpoint = typingDataUrl || defaultEndpoints.typingData;
    const replacePageEndpoint = replacePageUrl || defaultEndpoints.replacePage;
    const pdfWorkerUrl = (config.pdf && config.pdf.workerUrl) ? config.pdf.workerUrl : 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function getCsrfToken() {
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        if (metaCsrf) {
            return metaCsrf.getAttribute('content');
        }
        if (window.Laravel && window.Laravel.csrfToken) {
            return window.Laravel.csrfToken;
        }
        return null;
    }

    // Safely initialize lucide icons
    try { if (window.lucide && typeof lucide.createIcons === 'function') { lucide.createIcons(); } } catch (e) { console.warn('Lucide init failed:', e); }
    
    document.addEventListener('DOMContentLoaded', () => {
        const folderGroupToggle = document.querySelector('[data-folder-group-toggle]');
        const folderTree = document.querySelector('[data-folder-tree]');

        if (!folderGroupToggle || !folderTree) {
            return;
        }

        const toggleTextEl = folderGroupToggle.querySelector('.folder-group-toggle-text');

        const setCollapsedState = (collapsed) => {
            folderTree.dataset.collapsed = collapsed ? 'true' : 'false';
            folderGroupToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            folderGroupToggle.dataset.state = collapsed ? 'collapsed' : 'expanded';
            folderGroupToggle.setAttribute('title', collapsed ? 'Show document groups' : 'Hide document groups');

            if (toggleTextEl) {
                toggleTextEl.textContent = collapsed ? 'Show' : 'Hide';
            }
        };

        setCollapsedState(folderTree.dataset.collapsed === 'true');

        folderGroupToggle.addEventListener('click', () => {
            const isCurrentlyCollapsed = folderTree.dataset.collapsed === 'true';
            setCollapsedState(!isCurrentlyCollapsed);
        });
    });

    // Configure PDF.js safely (guard if CDN didn't load)
    if (window.pdfjsLib && pdfjsLib.GlobalWorkerOptions) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
    } else {
        console.warn('pdfjsLib not available; viewer functionality may be limited.');
    }
    
    // Store page data for easy access  
    const pageData = Array.isArray(config.pageData) ? config.pageData : [];
    const totalPages = typeof config.totalPages === 'number' ? config.totalPages : pageData.length;
    const fileIndexingId = typeof config.fileIndexingId !== 'undefined' ? config.fileIndexingId : null;
    let currentPageIndex = 0;
    
    // Enhanced page classifications with full feature support
    const lockedPageMetadata = {};
    const pageDataMap = {};
    // Track saved pages; was referenced but missing
    const savedPages = new Set();
    
    // Enhanced state management from working pagetyping implementation
    let state = {
        // Multi-select and booklet management
        isMultiSelectMode: false,
        selectedPages: new Set(),
        bookletMode: false,
        currentBooklet: null,
        bookletStartPage: null,
        bookletCounter: 'a',
        bookletPages: {},
        processedPages: {},
        
        // Current page state
        typingState: {
            coverType: '1', // Default to Front Cover
            pageType: null,
            pageTypeOthers: '',
            pageSubType: null,
            pageSubTypeOthers: '',
            serialNo: '01'
        }
    };

    let isDraggingCard = false;

    // Cover types, page types and subtypes - loaded from backend
    let coverTypes = [];
    let pageTypes = [];
    let pageSubTypes = {};

    function updateSerialNumberForSpecialCases(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return;

        const serialInput = form.querySelector('.serial-input');
        if (!serialInput) return;

        const coverTypeSelect = form.querySelector('.cover-type-select');
        const pageTypeSelect = form.querySelector('.page-type-select');

        const coverTypeId = coverTypeSelect ? coverTypeSelect.value : null;
        const pageTypeId = pageTypeSelect ? pageTypeSelect.value : null;

        const coverType = coverTypeId ? getCoverTypeById(coverTypeId) : null;
        const pageType = pageTypeId ? pageTypes.find(pt => pt.id == pageTypeId) : null;

        const coverCode = coverType?.code || coverTypeId || '—';
        const pageTypeCode = pageType?.code || pageTypeId || '—';

        const shouldSetToZero = shouldSerialBeZero(form);

        if (shouldSetToZero) {
            serialInput.value = '0';
            serialInput.readOnly = true;
            serialInput.style.backgroundColor = '#f3f4f6';
            serialInput.style.color = '#6b7280';
            serialInput.style.cursor = 'not-allowed';
            
            // Show lock indicator and help text
            const lockIndicator = form.querySelector('.serial-lock-indicator');
            const lockedHelp = form.querySelector('.serial-locked-help');
            if (lockIndicator) {
                lockIndicator.style.display = 'block';
                // Re-render lucide icons for the lock indicator
                setTimeout(() => lucide.createIcons(), 10);
            }
            if (lockedHelp) lockedHelp.style.display = 'inline';
            
            const coverLabel = coverType?.name || coverCode;
            const pageLabel = pageType?.name || pageTypeCode;
            console.log(`Auto-set serial number to 0 for Cover Type: ${coverLabel} (${coverCode}) + Page Type: ${pageLabel} (${pageTypeCode})`);
        } else {
            // Re-enable the field if it was previously disabled
            console.log('Re-enabling serial number field...');
            serialInput.readOnly = false;
            serialInput.style.backgroundColor = '';
            serialInput.style.color = '';
            serialInput.style.cursor = '';
            
            // Hide lock indicator and help text
            const lockIndicator = form.querySelector('.serial-lock-indicator');
            const lockedHelp = form.querySelector('.serial-locked-help');
            if (lockIndicator) lockIndicator.style.display = 'none';
            if (lockedHelp) lockedHelp.style.display = 'none';
            
            // If the current value is "0" and we're not in a special case, reset to a normal value
            if (serialInput.value === '0') {
                console.log('Resetting serial number from "0" to a normal value...');
                // Calculate what the serial number should be for this page
                const currentIndex = parseInt(form.dataset.pageIndex ?? pageIndex);
                
                // Try to use existing data first
                const existingData = state.processedPages && state.processedPages[currentIndex];
                if (existingData && existingData.serialNo && existingData.serialNo !== '0') {
                    serialInput.value = existingData.serialNo;
                    console.log('Restored serial number from existing data:', serialInput.value);
                } else {
                    // Calculate a default serial number
                    const defaultSerial = String(currentIndex + 1).padStart(2, '0');
                    serialInput.value = defaultSerial;
                    console.log('Set default serial number:', serialInput.value);
                }
            }
            
            console.log('Serial number field re-enabled');
        }
    }

    // Helper function to check if serial number should be "0" for a given form
    function shouldSerialBeZero(form) {
        const coverTypeSelect = form.querySelector('.cover-type-select');
        const pageTypeSelect = form.querySelector('.page-type-select');
        
        if (!coverTypeSelect || !pageTypeSelect) return false;
        
        // Make sure we have loaded the dropdown data
        if (coverTypes.length === 0 || pageTypes.length === 0) {
            return false;
        }
        
        const coverTypeValue = coverTypeSelect.value;
        const pageTypeValue = pageTypeSelect.value;
        
        // Don't proceed if required values are not selected
        if (!coverTypeValue || !pageTypeValue) return false;
        
    const coverType = getCoverTypeById(coverTypeValue);
    const pageType = pageTypes.find(pt => pt.id == pageTypeValue);
        
        if (!coverType || !pageType) return false;
        
        const coverCode = coverType.code;
        const pageTypeCode = pageType.code;
        
        // SPECIAL CASE 1: Front Cover (FC) + File Cover (FC) = 0
        if (coverCode === 'FC' && pageTypeCode === 'FC') {
            return true;
        }
        
        // SPECIAL CASE 2: Back Cover (BC) + File Back Page (FBP) = 0
        // Only check for direct FBP page type
        if (coverCode === 'BC' && pageTypeCode === 'FBP') {
            return true;
        }
        
        return false;
    }

    // Helper function to get page subtype by ID
    function getPageSubTypeById(pageTypeId, subtypeId) {
        if (!pageTypeId || !subtypeId) return null;
        if (pageSubTypes[pageTypeId]) {
            return pageSubTypes[pageTypeId].find(st => st.id == subtypeId);
        }
        return null;
    }
    
    // Helper function to get cover type by ID
    function getCoverTypeById(id) {
        return coverTypes.find(ct => ct.id == id);
    }
    
    // Helper function to get page type code (handles "other" option)
    function getPageTypeCode(pageTypeId, othersValue) {
        // If "other" is selected, use the custom text to generate code
        if (pageTypeId === 'other' || pageTypeId === 'others') {
            if (othersValue && othersValue.trim()) {
                // Generate code from custom text (first 4 chars uppercase)
                return othersValue.trim().substring(0, 4).toUpperCase();
            }
            return 'OTH';
        }
        
        const pageType = pageTypes.find(pt => pt.id == pageTypeId);
        return pageType ? pageType.code : 'XX';
    }
    
    // Helper function to get page subtype code (handles "other" option)
    function getPageSubTypeCode(pageTypeId, pageSubtypeId, othersValue) {
        if (!pageSubtypeId) return '';
        
        // If "other" is selected, use the custom text to generate code
        if (pageSubtypeId === 'other' || pageSubtypeId === 'others') {
            if (othersValue && othersValue.trim()) {
                // Generate code from custom text (first 4 chars uppercase)
                return othersValue.trim().substring(0, 4).toUpperCase();
            }
            return 'OTH';
        }
        
        const subtypes = pageSubTypes[pageTypeId];
        if (subtypes) {
            const subtype = subtypes.find(st => st.id == pageSubtypeId);
            return subtype ? subtype.code : '';
        }
        return '';
    }

    // Update page subtypes based on selected page type
    function updatePageSubtypes(pageIndex) {
        const subtypeSelect = document.querySelector(`.page-subtype-select[data-page-index="${pageIndex}"]`);
        const pageTypeSelect = document.querySelector(`.page-type-select[data-page-index="${pageIndex}"]`);
        
        if (!subtypeSelect || !pageTypeSelect) return;

        const selectedPageType = pageTypeSelect.value;
        const initialValue = subtypeSelect.dataset.initialValue ?? '';
        let selectedValue = subtypeSelect.value || initialValue;
        subtypeSelect.innerHTML = '<option value="">Select page subtype</option>';

        if (selectedPageType && pageSubTypes[selectedPageType]) {
            pageSubTypes[selectedPageType].forEach(st => {
                const option = document.createElement('option');
                option.value = st.id;
                option.textContent = `${st.code} - ${st.name}`;
                subtypeSelect.appendChild(option);
            });
        }

        if (selectedValue) {
            subtypeSelect.value = selectedValue;
        }

        // Ensure the "others" input visibility is synced after value assignment
        toggleOthersInput(subtypeSelect, '.page-subtype-others-container');
    }
    
    // Re-check all dropdowns and toggle "other" inputs as needed
    function recheckAllDropdowns() {
        document.querySelectorAll('.page-type-select').forEach(select => {
            if (select.value) {
                toggleOthersInput(select, '.page-type-others-container');
            }
        });
        
        document.querySelectorAll('.page-subtype-select').forEach(select => {
            if (select.value) {
                toggleOthersInput(select, '.page-subtype-others-container');
            }
        });
    }

    // Toggle others input field
    function toggleOthersInput(selectElement, containerSelector) {
        if (!selectElement) {
            return;
        }

        const form = selectElement.closest('.page-form');
        let container = form ? form.querySelector(containerSelector) : null;

        if (!container) {
            const fieldGroup = selectElement.closest('.field-group');
            if (fieldGroup) {
                container = fieldGroup.querySelector(containerSelector);
            }
        }

        if (!container) {
            container = selectElement.parentElement
                ? selectElement.parentElement.querySelector(containerSelector)
                : null;
        }

        if (!container) {
            return;
        }

        const rawValue = (selectElement.value || '').toString().trim().toLowerCase();
        const isOtherSelection = rawValue === 'other' || rawValue === 'others' || rawValue === 'oth';
        const input = container.querySelector('input, textarea');

        if (isOtherSelection) {
            container.classList.remove('hidden');
            container.style.display = 'block';
            if (input) {
                input.setAttribute('required', 'required');
            }
        } else {
            container.classList.add('hidden');
            container.style.display = 'none';
            if (input) {
                input.value = '';
                input.removeAttribute('required');
            }
        }
    }
    
    // Setup change event listeners for page type and subtype selects
    function setupDropdownListeners() {
        // Use event delegation on the document for better reliability
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('page-type-select')) {
                toggleOthersInput(e.target, '.page-type-others-container');
                const pageIndex = parseInt(e.target.dataset.pageIndex);
                if (!isNaN(pageIndex)) {
                    updatePageSubtypes(pageIndex);
                    updatePageCode(pageIndex);
                }
            }
            
            if (e.target.classList.contains('page-subtype-select')) {
                toggleOthersInput(e.target, '.page-subtype-others-container');
                const pageIndex = parseInt(e.target.dataset.pageIndex);
                if (!isNaN(pageIndex)) {
                    updatePageCode(pageIndex);
                }
            }
        });
        
        // Also listen for input events on "other" text fields
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('page-type-others-input') || 
                e.target.classList.contains('page-subtype-others-input')) {
                const pageIndex = parseInt(e.target.dataset.pageIndex);
                if (!isNaN(pageIndex)) {
                    updatePageCode(pageIndex);
                }
            }
        });
        
        // Trigger initial check for all existing dropdowns
        document.querySelectorAll('.page-type-select, .page-subtype-select').forEach(select => {
            if (select.value) {
                const containerClass = select.classList.contains('page-type-select') 
                    ? '.page-type-others-container' 
                    : '.page-subtype-others-container';
                toggleOthersInput(select, containerClass);
            }
        });
    }

    // Update page code
    function updatePageCode(pageIndex) {
        console.log('updatePageCode called for page index:', pageIndex);
        
        const codeInput = document.querySelector(`.page-code-input[data-page-index="${pageIndex}"]`);
        const codePreview = document.querySelector(`#page-code-preview-${pageIndex}`);
        
        if (!codeInput) {
            console.warn('Code input not found for page index:', pageIndex);
            return;
        }

        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) {
            console.warn('Form not found for page index:', pageIndex);
            return;
        }

        const coverType = form.querySelector('.cover-type-select').value;
        const pageType = form.querySelector('.page-type-select').value;
        const pageSubtype = form.querySelector('.page-subtype-select').value;
        const serialNo = form.querySelector('.serial-input').value;

        console.log('Form values for page', pageIndex, ':', { coverType, pageType, pageSubtype, serialNo });
        console.log('Available coverTypes:', coverTypes);
        console.log('Available pageTypes:', pageTypes);

        if (coverType && pageType && serialNo) {
            const coverCode = getCoverTypeById(coverType)?.code || 'XX';
            const pageCode = getPageTypeCode(pageType, form.querySelector('.page-type-others-input')?.value);
            const subtypeCode = getPageSubTypeCode(pageType, pageSubtype, form.querySelector('.page-subtype-others-input')?.value);
            
            console.log('Generated codes:', { coverCode, pageCode, subtypeCode });
            
            const fullCode = `${coverCode}-${pageCode}${subtypeCode ? '-' + subtypeCode : ''}-${serialNo}`;
            codeInput.value = fullCode;
            
            // Also update the visible badge preview
            if (codePreview) {
                codePreview.textContent = fullCode;
            }
            
            console.log('Final reference code:', fullCode);
        } else {
            // Clear the preview if form is incomplete
            if (codePreview) {
                codePreview.textContent = 'Incomplete form';
            }
            console.log('Form incomplete - missing required fields');
        }
    }

    // Update form for current page
    function updateFormForPage(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return;

        const classification = populateFormWithClassification(pageIndex);

        if (classification && form.dataset.saved === '1') {
            if (!lockedPageMetadata[pageIndex]) {
                lockedPageMetadata[pageIndex] = {
                    coverTypeId: classification.coverTypeId || null,
                    pageTypeId: classification.pageTypeId || (classification.pageTypeOthers ? 'others' : null),
                    pageTypeName: classification.pageTypeName || null,
                    pageTypeOthers: classification.pageTypeOthers || null,
                    pageSubTypeId: classification.pageSubTypeId || (classification.pageSubTypeOthers ? 'others' : null),
                    pageSubTypeName: classification.pageSubTypeName || null,
                    pageSubTypeOthers: classification.pageSubTypeOthers || null,
                    serialNumber: classification.serialNumber || null,
                    pageCode: classification.pageCode || null,
                    updatedAt: classification.updatedAt || null,
                    displayName: classification.displayName || null
                };
            }

                      
            if (form.dataset.locked !== 'true') {
                lockPageForm(form, lockedPageMetadata[pageIndex], { skipStatusUpdate: true });
            }
        } else if (!classification && form.dataset.locked === 'true' && form.dataset.saved !== '1') {
            form.dataset.locked = 'false';
            const inputs = form.querySelectorAll('input, select, textarea, button');
            inputs.forEach(element => {
                if (element.classList.contains('page-select-checkbox')) {
                    element.disabled = false;
                    return;
                }
                if (element.type !== 'button') {
                    element.disabled = false;
                }
                if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    element.removeAttribute('readonly');
                }
            });
        }
    }

    function normalizeDatasetValue(value) {
        if (value === undefined || value === null) {
            return null;
        }

        const trimmed = String(value).trim();
        if (trimmed === '' || trimmed.toLowerCase() === 'null' || trimmed.toLowerCase() === 'undefined') {
            return null;
        }

        return trimmed;
    }

    function resolveClassificationValue(primaryId, primaryOthers, secondaryId, secondaryOthers, fallbackId, fallbackOthers) {
        const candidates = [
            { id: normalizeDatasetValue(primaryId), others: normalizeDatasetValue(primaryOthers) },
            { id: normalizeDatasetValue(secondaryId), others: normalizeDatasetValue(secondaryOthers) },
            { id: normalizeDatasetValue(fallbackId), others: normalizeDatasetValue(fallbackOthers) }
        ];

        for (const candidate of candidates) {
            if (candidate.id) {
                if (candidate.id === 'others') {
                    return {
                        id: null,
                        others: candidate.others ||
                            normalizeDatasetValue(primaryOthers) ||
                            normalizeDatasetValue(secondaryOthers) ||
                            normalizeDatasetValue(fallbackOthers) ||
                            null
                    };
                }

                return {
                    id: candidate.id,
                    others: candidate.others || null
                };
            }

            if (candidate.others) {
                return {
                    id: null,
                    others: candidate.others
                };
            }
        }

        const combinedOthers = normalizeDatasetValue(primaryOthers) ||
            normalizeDatasetValue(secondaryOthers) ||
            normalizeDatasetValue(fallbackOthers) ||
            null;

        return { id: null, others: combinedOthers };
    }

    function getClassificationSnapshot(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return null;

        const dataset = form.dataset || {};
        const processed = state.processedPages[pageIndex] || {};
        const metadata = lockedPageMetadata[pageIndex] || {};
        const pageInfo = pageData[pageIndex] || {};

        const datasetCoverType = normalizeDatasetValue(dataset.existingCoverType);
        const datasetPageType = normalizeDatasetValue(dataset.existingPageType);
        const datasetPageTypeOthers = normalizeDatasetValue(dataset.existingPageTypeOthers);
        const datasetPageTypeName = normalizeDatasetValue(dataset.existingPageTypeName);
        const datasetPageSubType = normalizeDatasetValue(dataset.existingPageSubtype);
        const datasetPageSubTypeOthers = normalizeDatasetValue(dataset.existingPageSubtypeOthers);
        const datasetPageSubTypeName = normalizeDatasetValue(dataset.existingPageSubtypeName);
        const datasetSerialNumber = normalizeDatasetValue(dataset.existingSerialNumber);
        const datasetPageCode = normalizeDatasetValue(dataset.existingPageCode);
        const datasetUpdatedAt = normalizeDatasetValue(dataset.existingUpdatedAt);

        const processedPageTypeId = processed.pageType
            ? processed.pageType
            : (processed.pageTypeOthers ? 'others' : null);

        const processedPageSubTypeId = processed.pageSubType
            ? processed.pageSubType
            : (processed.pageSubTypeOthers ? 'others' : null);

        const pageTypeResolved = resolveClassificationValue(
            metadata.pageTypeId,
            metadata.pageTypeOthers,
            processedPageTypeId,
            processed.pageTypeOthers,
            datasetPageType,
            datasetPageTypeOthers
        );

        const pageSubTypeResolved = resolveClassificationValue(
            metadata.pageSubTypeId,
            metadata.pageSubTypeOthers,
            processedPageSubTypeId,
            processed.pageSubTypeOthers,
            datasetPageSubType,
            datasetPageSubTypeOthers
        );

        const coverTypeId = normalizeDatasetValue(metadata.coverTypeId) || processed.coverType || datasetCoverType || null;
        const serialNumber = normalizeDatasetValue(metadata.serialNumber) ?? processed.serialNo ?? datasetSerialNumber ?? '';
        const pageCode = normalizeDatasetValue(metadata.pageCode) || processed.page_code || datasetPageCode || '';
        const updatedAt = metadata.updatedAt || metadata.lockedAt || datasetUpdatedAt || null;
        const pageTypeName = metadata.pageTypeName || datasetPageTypeName || null;
        const pageSubTypeName = metadata.pageSubTypeName || datasetPageSubTypeName || null;
        const displayName = metadata.displayName || pageInfo.display_name || null;

        const hasClassification = Boolean(
            (coverTypeId && coverTypeId !== '') ||
            pageTypeResolved.id ||
            pageTypeResolved.others ||
            pageSubTypeResolved.id ||
            pageSubTypeResolved.others ||
            (serialNumber !== null && serialNumber !== undefined && serialNumber !== '') ||
            pageCode
        );

        if (!hasClassification) {
            return null;
        }

        return {
            coverTypeId: coverTypeId || null,
            pageTypeId: pageTypeResolved.id,
            pageTypeOthers: pageTypeResolved.others || null,
            pageSubTypeId: pageSubTypeResolved.id,
            pageSubTypeOthers: pageSubTypeResolved.others || null,
            serialNumber,
            pageCode,
            updatedAt,
            pageTypeName,
            pageSubTypeName,
            displayName
        };
    }

    function populateFormWithClassification(pageIndex, classification = null) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return null;

        const coverSelect = form.querySelector('.cover-type-select');
        const pageTypeSelect = form.querySelector('.page-type-select');
        const subtypeSelect = form.querySelector('.page-subtype-select');
        const serialInput = form.querySelector('.serial-input');
        const pageTypeOthersInput = form.querySelector('.page-type-others-input');
        const pageSubtypeOthersInput = form.querySelector('.page-subtype-others-input');
        const codeInput = form.querySelector('.page-code-input');
        const codePreview = document.querySelector(`#page-code-preview-${pageIndex}`);

        classification = classification || getClassificationSnapshot(pageIndex);
        const hasClassification = Boolean(classification);

        if (hasClassification) {
            if (coverSelect) {
                const coverValue = classification.coverTypeId || coverSelect.dataset.initialValue || (coverTypes[0]?.id || '');
                coverSelect.dataset.initialValue = coverValue || '';
                coverSelect.value = coverValue || '';
            }

            if (pageTypeSelect) {
                const pageTypeValue = classification.pageTypeId || (classification.pageTypeOthers ? 'others' : '');
                pageTypeSelect.dataset.initialValue = pageTypeValue || '';
                pageTypeSelect.value = pageTypeValue || '';
            }

            if (pageTypeOthersInput) {
                pageTypeOthersInput.value = classification.pageTypeOthers || '';
            }

            if (subtypeSelect) {
                const subtypeValue = classification.pageSubTypeId || (classification.pageSubTypeOthers ? 'others' : '');
                subtypeSelect.dataset.initialValue = subtypeValue || '';
                updatePageSubtypes(pageIndex);
                subtypeSelect.value = subtypeValue || '';
            } else {
                updatePageSubtypes(pageIndex);
            }

            if (pageSubtypeOthersInput) {
                pageSubtypeOthersInput.value = classification.pageSubTypeOthers || '';
            }

            if (pageTypeSelect) {
                toggleOthersInput(pageTypeSelect, '.page-type-others-container');
            }

            if (subtypeSelect) {
                toggleOthersInput(subtypeSelect, '.page-subtype-others-container');
            }

            if (serialInput && classification.serialNumber !== undefined && classification.serialNumber !== null && classification.serialNumber !== '') {
                serialInput.value = classification.serialNumber;
            }

            if (codeInput) {
                if (classification.pageCode) {
                    codeInput.value = classification.pageCode;
                    if (codePreview) {
                        codePreview.textContent = classification.pageCode;
                    }
                } else {
                    updatePageCode(pageIndex);
                }
            }

            form.dataset.existingCoverType = classification.coverTypeId || '';
            form.dataset.existingPageType = classification.pageTypeId ? classification.pageTypeId : (classification.pageTypeOthers ? 'others' : '');
            form.dataset.existingPageTypeOthers = classification.pageTypeOthers || '';
            form.dataset.existingPageSubtype = classification.pageSubTypeId ? classification.pageSubTypeId : (classification.pageSubTypeOthers ? 'others' : '');
            form.dataset.existingPageSubtypeOthers = classification.pageSubTypeOthers || '';
            form.dataset.existingSerialNumber = classification.serialNumber ?? form.dataset.existingSerialNumber ?? '';
            form.dataset.existingPageCode = classification.pageCode || form.dataset.existingPageCode || '';

            if (classification.pageTypeName) {
                form.dataset.existingPageTypeName = classification.pageTypeName;
            }

            if (classification.pageSubTypeName) {
                form.dataset.existingPageSubTypeName = classification.pageSubTypeName;
            }

            if (classification.updatedAt) {
                form.dataset.existingUpdatedAt = classification.updatedAt;
            }

            if (classification.displayName) {
                const pageInfo = pageData[pageIndex] || {};
                pageInfo.display_name = classification.displayName;
                pageData[pageIndex] = pageInfo;
                if (pageInfo.scanning_id) {
                    pageDataMap[pageInfo.scanning_id] = pageInfo;
                }
            }
        } else {
            if (coverSelect) {
                const coverValue = coverSelect.dataset.initialValue || coverSelect.value || (coverTypes[0]?.id || '');
                coverSelect.value = coverValue || '';
            }

            if (pageTypeSelect) {
                const pageTypeValue = pageTypeSelect.dataset.initialValue || pageTypeSelect.value || '';
                pageTypeSelect.value = pageTypeValue || '';
            }

            updatePageSubtypes(pageIndex);

            if (serialInput && !serialInput.value) {
                serialInput.value = calculateNextSerialNumber();
            }

            updateSerialNumberForSpecialCases(pageIndex);
            updatePageCode(pageIndex);

            if (pageTypeSelect) {
                toggleOthersInput(pageTypeSelect, '.page-type-others-container');
            }

            if (subtypeSelect) {
                toggleOthersInput(subtypeSelect, '.page-subtype-others-container');
            }
        }

        return classification;
    }

    function initializeExistingClassifications() {
        document.querySelectorAll('.page-form').forEach(form => {
            const pageIndex = parseInt(form.dataset.pageIndex ?? '-1', 10);
            if (Number.isNaN(pageIndex) || pageIndex < 0) {
                return;
            }

            const classification = populateFormWithClassification(pageIndex);

            const coverSelect = form.querySelector('.cover-type-select');
            const pageTypeSelect = form.querySelector('.page-type-select');
            const subtypeSelect = form.querySelector('.page-subtype-select');

            if (coverSelect && !coverSelect.dataset.initialValue) {
                coverSelect.dataset.initialValue = normalizeDatasetValue(form.dataset.existingCoverType) || coverSelect.value || '';
            }
            if (pageTypeSelect && !pageTypeSelect.dataset.initialValue) {
                pageTypeSelect.dataset.initialValue = normalizeDatasetValue(form.dataset.existingPageType) || pageTypeSelect.value || '';
            }
            if (subtypeSelect && !subtypeSelect.dataset.initialValue) {
                subtypeSelect.dataset.initialValue = normalizeDatasetValue(form.dataset.existingPageSubtype) || subtypeSelect.value || '';
            }

            if (classification) {
                state.processedPages[pageIndex] = {
                    coverType: classification.coverTypeId || null,
                    pageType: classification.pageTypeId || null,
                    pageTypeOthers: classification.pageTypeOthers || null,
                    pageSubType: classification.pageSubTypeId || null,
                    pageSubTypeOthers: classification.pageSubTypeOthers || null,
                    serialNo: classification.serialNumber || null,
                    page_code: classification.pageCode || null
                };

                const metadata = Object.assign({}, lockedPageMetadata[pageIndex] || {}, {
                    coverTypeId: classification.coverTypeId || null,
                    pageTypeId: classification.pageTypeId || (classification.pageTypeOthers ? 'others' : null),
                    pageTypeName: classification.pageTypeName || form.dataset.existingPageTypeName || null,
                    pageTypeOthers: classification.pageTypeOthers || null,
                    pageSubTypeId: classification.pageSubTypeId || (classification.pageSubTypeOthers ? 'others' : null),
                    pageSubTypeName: classification.pageSubTypeName || form.dataset.existingPageSubTypeName || null,
                    pageSubTypeOthers: classification.pageSubTypeOthers || null,
                    serialNumber: classification.serialNumber || null,
                    pageCode: classification.pageCode || null,
                    updatedAt: classification.updatedAt || form.dataset.existingUpdatedAt || null,
                    displayName: classification.displayName || null
                });

                lockedPageMetadata[pageIndex] = metadata;

                if (form.dataset.saved === '1' || form.dataset.locked === 'true' || metadata.pageCode) {
                    savedPages.add(pageIndex);
                    lockPageForm(form, metadata);
                } else {
                    markThumbnailCompleted(pageIndex, metadata);
                }

                if (classification.displayName) {
                    const pageInfo = pageData[pageIndex] || {};
                    pageInfo.display_name = classification.displayName;
                    pageData[pageIndex] = pageInfo;
                    if (pageInfo.scanning_id) {
                        pageDataMap[pageInfo.scanning_id] = pageInfo;
                    }
                }
            } else {
                if (form.dataset.saved === '1') {
                    savedPages.add(pageIndex);
                }

                updatePageSubtypes(pageIndex);
                updateSerialNumberForSpecialCases(pageIndex);
                updatePageCode(pageIndex);
            }
        });

    updateProgress();
    try { if (window.lucide && typeof lucide.createIcons === 'function') { lucide.createIcons(); } } catch {}
    }

    // Viewer globals (guarded)
    let viewerState = window.viewerState || {
        panEnabled: false,
        translateX: 0,
        translateY: 0,
        rotation: 0,
        scale: 1,
        cropEnabled: false,
        cropArea: null
    };
    window.viewerState = viewerState;
    let viewerWrapperElement = window.viewerWrapperElement || null;
    let viewerMediaElement = window.viewerMediaElement || null;
    let viewerPlaceholderElement = window.viewerPlaceholderElement || null;
    let panPointerId = null;
    let panMoved = false;
    let panStart = { x: 0, y: 0, translateX: 0, translateY: 0 };
    let viewerToolsInitialized = false;
    
    // Crop functionality variables
    let cropOverlay = null;
    let cropSelection = null;
    let cropStarted = false;
    let cropStart = { x: 0, y: 0 };

    function ensureViewerElements() {
        if (!viewerWrapperElement) {
            viewerWrapperElement = document.getElementById('viewer-media-wrapper');
            if (viewerWrapperElement) {
                window.viewerWrapperElement = viewerWrapperElement;
            }
        }

        if (!viewerMediaElement) {
            viewerMediaElement = document.getElementById('viewer-media');
            if (viewerMediaElement) {
                window.viewerMediaElement = viewerMediaElement;
            }
        }

        if (!viewerPlaceholderElement) {
            viewerPlaceholderElement = document.getElementById('viewer-placeholder');
            if (viewerPlaceholderElement) {
                window.viewerPlaceholderElement = viewerPlaceholderElement;
            }
        }

        return {
            wrapper: viewerWrapperElement,
            media: viewerMediaElement,
            placeholder: viewerPlaceholderElement
        };
    }

    function updateViewerPlaceholder(show) {
        ensureViewerElements();
        if (!viewerPlaceholderElement) return;
        if (show) {
            viewerPlaceholderElement.classList.remove('hidden');
        } else {
            viewerPlaceholderElement.classList.add('hidden');
        }
    }

    function applyViewerTransform(options = {}) {
        ensureViewerElements();
        if (!viewerMediaElement) {
            return;
        }

        const transform = `translate(${viewerState.translateX}px, ${viewerState.translateY}px) rotate(${viewerState.rotation}deg) scale(${viewerState.scale})`;
        viewerMediaElement.style.transform = transform;

        if (options.immediate) {
            viewerMediaElement.style.transition = 'none';
            requestAnimationFrame(() => {
                viewerMediaElement.style.transition = 'transform 0.18s ease';
            });
        }
    }

    function updatePanClasses(isActive) {
        ensureViewerElements();
        if (!viewerWrapperElement) {
            return;
        }

        viewerWrapperElement.classList.toggle('pan-mode', !!viewerState.panEnabled);
        viewerWrapperElement.classList.toggle('pan-active', !!isActive);
    }

    function resetViewerState() {
        viewerState.translateX = 0;
        viewerState.translateY = 0;
        viewerState.rotation = 0;
        viewerState.scale = 1;
        viewerState.panEnabled = false;
        viewerState.cropEnabled = false;
        viewerState.cropArea = null;
        updatePanClasses(false);
        updateCropClasses(false);
        removeCropOverlay();
        applyViewerTransform({ immediate: true });
    }

    // Crop functionality
    function toggleCropMode(button) {
        viewerState.cropEnabled = !viewerState.cropEnabled;
        
        if (button) {
            button.setAttribute('aria-pressed', viewerState.cropEnabled ? 'true' : 'false');
        }
        
        updateCropClasses();
        
        if (viewerState.cropEnabled) {
            createCropOverlay();
            // Disable pan mode when crop is enabled
            if (viewerState.panEnabled) {
                viewerState.panEnabled = false;
                updatePanClasses(false);
                const panButton = document.querySelector('[data-tool="pan"]');
                if (panButton) {
                    panButton.setAttribute('aria-pressed', 'false');
                }
            }
        } else {
            cropStarted = false;
            viewerState.cropArea = null;
            removeCropOverlay();
        }
    }

    function updateCropClasses() {
        ensureViewerElements();
        if (!viewerWrapperElement) return;
        
        viewerWrapperElement.classList.toggle('crop-mode', !!viewerState.cropEnabled);
    }

    function createCropOverlay() {
        removeCropOverlay();
        
        if (!viewerWrapperElement) return;
        
        cropOverlay = document.createElement('div');
        cropOverlay.className = 'crop-overlay';
        cropOverlay.innerHTML = `
            <div class="crop-selection" style="display: none;">
                <div class="crop-handle crop-handle-nw"></div>
                <div class="crop-handle crop-handle-ne"></div>
                <div class="crop-handle crop-handle-sw"></div>
                <div class="crop-handle crop-handle-se"></div>
                <div class="crop-actions">
                    <button class="crop-action-btn crop-apply" title="Apply Crop">
                        <i data-lucide="check"></i>
                    </button>
                    <button class="crop-action-btn crop-cancel" title="Cancel Crop">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            </div>
        `;
        
        viewerWrapperElement.appendChild(cropOverlay);
        cropSelection = cropOverlay.querySelector('.crop-selection');
        
        // Initialize Lucide icons for crop buttons
        try { if (window.lucide && typeof lucide.createIcons === 'function') { lucide.createIcons(); } } catch {}
        
        // Add event listeners for crop functionality
        setupCropEvents();
    }

    function removeCropOverlay() {
        if (cropOverlay) {
            cropOverlay.remove();
            cropOverlay = null;
            cropSelection = null;
        }
    }

    function setupCropEvents() {
        if (!cropOverlay) return;
        
        // Mouse/touch events for creating crop area
        cropOverlay.addEventListener('pointerdown', handleCropStart);
        cropOverlay.addEventListener('pointermove', handleCropMove);
        cropOverlay.addEventListener('pointerup', handleCropEnd);
        
        // Crop action buttons
        const applyBtn = cropOverlay.querySelector('.crop-apply');
        const cancelBtn = cropOverlay.querySelector('.crop-cancel');
        
        if (applyBtn) {
            applyBtn.addEventListener('click', applyCrop);
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', cancelCrop);
        }
    }

    function handleCropStart(event) {
        if (!viewerState.cropEnabled) return;
        
        event.preventDefault();
        event.stopPropagation();
        
        if (event.target.closest('.crop-actions') || event.target.closest('.crop-handle')) {
            return;
        }
        
        const rect = cropOverlay.getBoundingClientRect();
        cropStart = {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top
        };
        
        cropStarted = true;
        cropSelection.style.display = 'block';
        cropSelection.style.left = cropStart.x + 'px';
        cropSelection.style.top = cropStart.y + 'px';
        cropSelection.style.width = '0px';
        cropSelection.style.height = '0px';
        
        cropOverlay.setPointerCapture(event.pointerId);
    }

    function handleCropMove(event) {
        if (!cropStarted) return;
        
        event.preventDefault();
        
        const rect = cropOverlay.getBoundingClientRect();
        const currentX = event.clientX - rect.left;
        const currentY = event.clientY - rect.top;
        
        const width = Math.abs(currentX - cropStart.x);
        const height = Math.abs(currentY - cropStart.y);
        const left = Math.min(currentX, cropStart.x);
        const top = Math.min(currentY, cropStart.y);
        
        cropSelection.style.left = left + 'px';
        cropSelection.style.top = top + 'px';
        cropSelection.style.width = width + 'px';
        cropSelection.style.height = height + 'px';
    }

    function handleCropEnd(event) {
        if (!cropStarted) return;
        
        cropStarted = false;
        
        // Store crop area for potential application
        const rect = cropSelection.getBoundingClientRect();
        const overlayRect = cropOverlay.getBoundingClientRect();
        
        viewerState.cropArea = {
            left: (rect.left - overlayRect.left) / overlayRect.width,
            top: (rect.top - overlayRect.top) / overlayRect.height,
            width: rect.width / overlayRect.width,
            height: rect.height / overlayRect.height
        };
        
        cropOverlay.releasePointerCapture(event.pointerId);
    }

    function applyCrop() {
        if (!viewerState.cropArea) return;
        
        // Here you would implement the actual cropping logic
        // For now, we'll just show a confirmation and reset
        alert('Crop applied! (This would normally process the image)');
        
        // Reset crop mode
        const cropButton = document.querySelector('[data-tool="crop"]');
        if (cropButton) {
            toggleCropMode(cropButton);
        }
    }

    function cancelCrop() {
        const cropButton = document.querySelector('[data-tool="crop"]');
        if (cropButton) {
            toggleCropMode(cropButton);
        }
    }

    function renderPageInViewer(pageIndex, options = {}) {
        const { media } = ensureViewerElements();
        const page = pageData[pageIndex];

        if (!media || !page) {
            updateViewerPlaceholder(true);
            return;
        }

        media.innerHTML = '';

        if (!page.url) {
            updateViewerPlaceholder(true);
            return;
        }

        updateViewerPlaceholder(false);

        const cacheBuster = page.url.includes('?') ? `&t=${Date.now()}` : `?t=${Date.now()}`;
        const pageUrl = `${page.url}${cacheBuster}`;
        const extension = (page.file_extension || page.type || '').toString().toLowerCase();
        const isPdf = extension === 'pdf';

        if (isPdf) {
            const iframe = document.createElement('iframe');
            iframe.src = pageUrl;
            iframe.title = page.display_name || 'Document preview';
            iframe.className = 'viewer-media-content';
            iframe.loading = 'lazy';
            media.appendChild(iframe);
        } else {
            const img = document.createElement('img');
            img.src = pageUrl;
            img.alt = page.display_name || 'Document preview';
            img.className = 'viewer-media-content';
            img.loading = 'lazy';
            media.appendChild(img);
        }

        resetViewerState();

        if (options.focusViewer && viewerWrapperElement) {
            viewerWrapperElement.focus({ preventScroll: true });
        }
    }

    function onViewerPointerDown(event) {
        if (!viewerState.panEnabled) {
            return;
        }

        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        ensureViewerElements();

        panPointerId = event.pointerId;
        panStart = {
            x: event.clientX,
            y: event.clientY,
            translateX: viewerState.translateX,
            translateY: viewerState.translateY
        };
        panMoved = false;

        if (viewerWrapperElement && typeof viewerWrapperElement.setPointerCapture === 'function') {
            viewerWrapperElement.setPointerCapture(panPointerId);
        }

        event.preventDefault();
    }

    function handleToolAction(action, button) {
        switch (action) {
            case 'rotate-left':
                viewerState.rotation = (viewerState.rotation - 90) % 360;
                break;
            case 'rotate-right':
                viewerState.rotation = (viewerState.rotation + 90) % 360;
                break;
            case 'zoom-in':
                viewerState.scale = Math.min(viewerState.scale + 0.1, 3);
                break;
            case 'zoom-out':
                viewerState.scale = Math.max(viewerState.scale - 0.1, 0.2);
                break;
            case 'crop':
                toggleCropMode(button);
                logToolAction('crop');
                return;
            case 'pan':
                viewerState.panEnabled = !viewerState.panEnabled;
                if (button) {
                    button.setAttribute('aria-pressed', viewerState.panEnabled ? 'true' : 'false');
                }
                break;
            case 'reset':
                resetViewerState();
                logToolAction('reset');
                return;
            default:
                return;
        }

        updatePanClasses(viewerState.panEnabled && panPointerId !== null);
        applyViewerTransform();
        logToolAction(action, { scale: viewerState.scale, rotation: viewerState.rotation });
    }

    function lockPageForm(form, metadata = {}, options = {}) {
        if (!form) return;
        form.dataset.locked = 'true';

        const inputs = form.querySelectorAll('input, select, textarea, button');
        inputs.forEach(element => {
            if (element.classList.contains('page-select-checkbox')) {
                element.disabled = true;
                return;
            }
            if (element.type !== 'button') {
                element.disabled = true;
            }
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.setAttribute('readonly', 'readonly');
            }
        });

        const meta = {
            pageTypeName: metadata.pageTypeName || metadata.pageTypeOthers || null,
            pageTypeOthers: metadata.pageTypeOthers || null,
            pageSubTypeName: metadata.pageSubTypeName || metadata.pageSubTypeOthers || null,
            pageSubTypeOthers: metadata.pageSubTypeOthers || null,
            updatedAt: metadata.updatedAt || metadata.lockedAt || null,
            displayName: metadata.displayName || null,
            pageCode: metadata.pageCode || null,
            serialNumber: metadata.serialNumber || null
        };
        showLockBanner(form, meta);

        if (!options.skipStatusUpdate) {
            const pageIndex = parseInt(form.dataset.pageIndex ?? '-1', 10);
            if (!Number.isNaN(pageIndex) && pageIndex >= 0) {
                markThumbnailCompleted(pageIndex, meta);
                lockedPageMetadata[pageIndex] = Object.assign({}, lockedPageMetadata[pageIndex] || {}, metadata, meta);
            }
        }
    }

    function onViewerPointerMove(event) {
        if (!viewerState.panEnabled || panPointerId === null || panPointerId !== event.pointerId) return;

        const deltaX = event.clientX - panStart.x;
        const deltaY = event.clientY - panStart.y;
        viewerState.translateX = panStart.translateX + deltaX;
        viewerState.translateY = panStart.translateY + deltaY;
        applyViewerTransform({ immediate: true });

        if (!panMoved && (Math.abs(deltaX) > 2 || Math.abs(deltaY) > 2)) {
            panMoved = true;
        }

        updatePanClasses(true);
    }

    function onViewerPointerUp(event) {
        if (panPointerId === null || panPointerId !== event.pointerId) return;
        if (viewerWrapperElement) {
            viewerWrapperElement.releasePointerCapture(panPointerId);
        }
        panPointerId = null;
        updatePanClasses(false);
        applyViewerTransform();

        if (viewerState.panEnabled && panMoved) {
            logToolAction('reposition', {
                translate_x: viewerState.translateX,
                translate_y: viewerState.translateY
            });
        }
    }

    function initializeViewerTools() {
        if (viewerToolsInitialized) {
            return;
        }

        const toolContainer = document.getElementById('viewer-tools');
        if (typeof ensureViewerElements === 'function') {
            ensureViewerElements();
        }

        if (toolContainer) {
            toolContainer.querySelectorAll('.tool-btn').forEach(button => {
                if (button.dataset.initialized === 'true') {
                    return;
                }

                button.addEventListener('click', () => {
                    if (typeof handleToolAction === 'function') {
                        handleToolAction(button.dataset.tool, button);
                    }
                });
                button.dataset.initialized = 'true';
            });
        }

        if (viewerWrapperElement) {
            if (!viewerWrapperElement.dataset.toolsBound) {
                viewerWrapperElement.addEventListener('pointerdown', onViewerPointerDown);
                viewerWrapperElement.addEventListener('pointermove', onViewerPointerMove);
                viewerWrapperElement.addEventListener('pointerup', onViewerPointerUp);
                viewerWrapperElement.addEventListener('pointercancel', onViewerPointerUp);
                viewerWrapperElement.dataset.toolsBound = 'true';
            }
        }

        viewerToolsInitialized = true;
    }

    async function logToolAction(action, details = {}) {
        try {
            const current = pageData[currentPageIndex];
            if (!current || !toolLogEndpoint) {
                return;
            }

            const payload = {
                action,
                file_indexing_id: fileIndexingId,
                scanning_id: current.scanning_id,
                file_path: current.file_path,
                rotation: viewerState.rotation,
                scale: viewerState.scale,
                translate_x: viewerState.translateX,
                translate_y: viewerState.translateY,
                details
            };

            await fetch(toolLogEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });
        } catch (error) {
            console.warn('Tool action logging failed', error);
        }
    }

    

    function getClassificationSnapshot(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return null;

        const dataset = form.dataset || {};
        const processed = state.processedPages[pageIndex] || {};
        const metadata = lockedPageMetadata[pageIndex] || {};
        const pageInfo = pageData[pageIndex] || {};

        const datasetCoverType = normalizeDatasetValue(dataset.existingCoverType);
        const datasetPageType = normalizeDatasetValue(dataset.existingPageType);
        const datasetPageTypeOthers = normalizeDatasetValue(dataset.existingPageTypeOthers);
        const datasetPageTypeName = normalizeDatasetValue(dataset.existingPageTypeName);
        const datasetPageSubType = normalizeDatasetValue(dataset.existingPageSubtype);
        const datasetPageSubTypeOthers = normalizeDatasetValue(dataset.existingPageSubtypeOthers);
        const datasetPageSubTypeName = normalizeDatasetValue(dataset.existingPageSubtypeName);
        const datasetSerialNumber = normalizeDatasetValue(dataset.existingSerialNumber);
        const datasetPageCode = normalizeDatasetValue(dataset.existingPageCode);
        const datasetUpdatedAt = normalizeDatasetValue(dataset.existingUpdatedAt);

        const processedPageTypeId = processed.pageType
            ? processed.pageType
            : (processed.pageTypeOthers ? 'others' : null);

        const processedPageSubTypeId = processed.pageSubType
            ? processed.pageSubType
            : (processed.pageSubTypeOthers ? 'others' : null);

        const pageTypeResolved = resolveClassificationValue(
            metadata.pageTypeId,
            metadata.pageTypeOthers,
            processedPageTypeId,
            processed.pageTypeOthers,
            datasetPageType,
            datasetPageTypeOthers
        );

        const pageSubTypeResolved = resolveClassificationValue(
            metadata.pageSubTypeId,
            metadata.pageSubTypeOthers,
            processedPageSubTypeId,
            processed.pageSubTypeOthers,
            datasetPageSubType,
            datasetPageSubTypeOthers
        );

        const coverTypeId = normalizeDatasetValue(metadata.coverTypeId) || processed.coverType || datasetCoverType || null;
        const serialNumber = normalizeDatasetValue(metadata.serialNumber) ?? processed.serialNo ?? datasetSerialNumber ?? '';
        const pageCode = normalizeDatasetValue(metadata.pageCode) || processed.page_code || datasetPageCode || '';
        const updatedAt = metadata.updatedAt || metadata.lockedAt || datasetUpdatedAt || null;
        const pageTypeName = metadata.pageTypeName || datasetPageTypeName || null;
        const pageSubTypeName = metadata.pageSubTypeName || datasetPageSubTypeName || null;
        const displayName = metadata.displayName || pageInfo.display_name || null;

        const hasClassification = Boolean(
            (coverTypeId && coverTypeId !== '') ||
            pageTypeResolved.id ||
            pageTypeResolved.others ||
            pageSubTypeResolved.id ||
            pageSubTypeResolved.others ||
            (serialNumber !== null && serialNumber !== undefined && serialNumber !== '') ||
            pageCode
        );

        if (!hasClassification) {
            return null;
        }

        return {
            coverTypeId: coverTypeId || null,
            pageTypeId: pageTypeResolved.id,
            pageTypeOthers: pageTypeResolved.others || null,
            pageSubTypeId: pageSubTypeResolved.id,
            pageSubTypeOthers: pageSubTypeResolved.others || null,
            serialNumber: serialNumber,
            pageCode: pageCode,
            updatedAt,
            pageTypeName,
            pageSubTypeName,
            displayName
        };
    }

    function populateFormWithClassification(pageIndex, classification = null) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return null;

        const coverSelect = form.querySelector('.cover-type-select');
        const pageTypeSelect = form.querySelector('.page-type-select');
        const subtypeSelect = form.querySelector('.page-subtype-select');
        const serialInput = form.querySelector('.serial-input');
        const pageTypeOthersInput = form.querySelector('.page-type-others-input');
        const pageSubtypeOthersInput = form.querySelector('.page-subtype-others-input');
        const codeInput = form.querySelector('.page-code-input');
        const codePreview = document.querySelector(`#page-code-preview-${pageIndex}`);

        classification = classification || getClassificationSnapshot(pageIndex);
        const hasClassification = Boolean(classification);

        if (hasClassification) {
            if (coverSelect) {
                const coverValue = classification.coverTypeId || coverSelect.dataset.initialValue || (coverTypes[0]?.id || '');
                coverSelect.dataset.initialValue = coverValue || '';
                coverSelect.value = coverValue || '';
            }

            if (pageTypeSelect) {
                const pageTypeValue = classification.pageTypeId || (classification.pageTypeOthers ? 'others' : '');
                pageTypeSelect.dataset.initialValue = pageTypeValue || '';
                pageTypeSelect.value = pageTypeValue || '';
            }

            if (pageTypeOthersInput) {
                pageTypeOthersInput.value = classification.pageTypeOthers || '';
            }

            if (subtypeSelect) {
                const subtypeValue = classification.pageSubTypeId || (classification.pageSubTypeOthers ? 'others' : '');
                subtypeSelect.dataset.initialValue = subtypeValue || '';
                updatePageSubtypes(pageIndex);
                subtypeSelect.value = subtypeValue || '';
            } else {
                updatePageSubtypes(pageIndex);
            }

            if (pageSubtypeOthersInput) {
                pageSubtypeOthersInput.value = classification.pageSubTypeOthers || '';
            }

            if (pageTypeSelect) {
                toggleOthersInput(pageTypeSelect, '.page-type-others-container');
            }

            if (subtypeSelect) {
                toggleOthersInput(subtypeSelect, '.page-subtype-others-container');
            }

            if (serialInput) {
                if (classification.serialNumber !== null && classification.serialNumber !== undefined && classification.serialNumber !== '') {
                    serialInput.value = classification.serialNumber;
                }
            }

            if (codeInput) {
                if (classification.pageCode) {
                    codeInput.value = classification.pageCode;
                    if (codePreview) {
                        codePreview.textContent = classification.pageCode;
                    }
                } else {
                    updatePageCode(pageIndex);
                }
            }

            form.dataset.existingCoverType = classification.coverTypeId || '';
            form.dataset.existingPageType = classification.pageTypeId ? classification.pageTypeId : (classification.pageTypeOthers ? 'others' : '');
            form.dataset.existingPageTypeOthers = classification.pageTypeOthers || '';
            form.dataset.existingPageSubtype = classification.pageSubTypeId ? classification.pageSubTypeId : (classification.pageSubTypeOthers ? 'others' : '');
            form.dataset.existingPageSubtypeOthers = classification.pageSubTypeOthers || '';
            form.dataset.existingSerialNumber = classification.serialNumber ?? form.dataset.existingSerialNumber ?? '';
            form.dataset.existingPageCode = classification.pageCode || form.dataset.existingPageCode || '';

            if (classification.pageTypeName) {
                form.dataset.existingPageTypeName = classification.pageTypeName;
            }

            if (classification.pageSubTypeName) {
                form.dataset.existingPageSubTypeName = classification.pageSubTypeName;
            }

            if (classification.updatedAt) {
                form.dataset.existingUpdatedAt = classification.updatedAt;
            }

            if (classification.displayName) {
                const pageInfo = pageData[pageIndex] || {};
                pageInfo.display_name = classification.displayName;
                pageData[pageIndex] = pageInfo;
                if (pageInfo.scanning_id) {
                    pageDataMap[pageInfo.scanning_id] = pageInfo;
                }
            }
        } else {
            if (coverSelect) {
                const coverValue = coverSelect.dataset.initialValue || coverSelect.value || (coverTypes[0]?.id || '');
                coverSelect.value = coverValue || '';
            }

            if (pageTypeSelect) {
                const pageTypeValue = pageTypeSelect.dataset.initialValue || pageTypeSelect.value || '';
                pageTypeSelect.value = pageTypeValue || '';
            }

            updatePageSubtypes(pageIndex);

            if (serialInput && !serialInput.value) {
                serialInput.value = calculateNextSerialNumber();
            }

            updateSerialNumberForSpecialCases(pageIndex);
            updatePageCode(pageIndex);

            if (pageTypeSelect) {
                toggleOthersInput(pageTypeSelect, '.page-type-others-container');
            }

            if (subtypeSelect) {
                toggleOthersInput(subtypeSelect, '.page-subtype-others-container');
            }
        }

        return classification;
    }

    function initializeExistingClassifications() {
        document.querySelectorAll('.page-form').forEach(form => {
            const pageIndex = parseInt(form.dataset.pageIndex ?? '-1', 10);
            if (Number.isNaN(pageIndex) || pageIndex < 0) {
                return;
            }

            const classification = populateFormWithClassification(pageIndex);

            const coverSelect = form.querySelector('.cover-type-select');
            const pageTypeSelect = form.querySelector('.page-type-select');
            const subtypeSelect = form.querySelector('.page-subtype-select');

            if (coverSelect && !coverSelect.dataset.initialValue) {
                coverSelect.dataset.initialValue = normalizeDatasetValue(form.dataset.existingCoverType) || coverSelect.value || '';
            }
            if (pageTypeSelect && !pageTypeSelect.dataset.initialValue) {
                pageTypeSelect.dataset.initialValue = normalizeDatasetValue(form.dataset.existingPageType) || pageTypeSelect.value || '';
            }
            if (subtypeSelect && !subtypeSelect.dataset.initialValue) {
                subtypeSelect.dataset.initialValue = normalizeDatasetValue(form.dataset.existingPageSubtype) || subtypeSelect.value || '';
            }

            if (classification) {
                state.processedPages[pageIndex] = {
                    coverType: classification.coverTypeId || null,
                    pageType: classification.pageTypeId || null,
                    pageTypeOthers: classification.pageTypeOthers || null,
                    pageSubType: classification.pageSubTypeId || null,
                    pageSubTypeOthers: classification.pageSubTypeOthers || null,
                    serialNo: classification.serialNumber || null,
                    page_code: classification.pageCode || null
                };

                const metadata = Object.assign({}, lockedPageMetadata[pageIndex] || {}, {
                    coverTypeId: classification.coverTypeId || null,
                    pageTypeId: classification.pageTypeId || (classification.pageTypeOthers ? 'others' : null),
                    pageTypeName: classification.pageTypeName || form.dataset.existingPageTypeName || null,
                    pageTypeOthers: classification.pageTypeOthers || null,
                    pageSubTypeId: classification.pageSubTypeId || (classification.pageSubTypeOthers ? 'others' : null),
                    pageSubTypeName: classification.pageSubTypeName || form.dataset.existingPageSubTypeName || null,
                    pageSubTypeOthers: classification.pageSubTypeOthers || null,
                    serialNumber: classification.serialNumber || null,
                    pageCode: classification.pageCode || null,
                    updatedAt: classification.updatedAt || form.dataset.existingUpdatedAt || null,
                    displayName: classification.displayName || null
                });

                lockedPageMetadata[pageIndex] = metadata;

                if (form.dataset.saved === '1' || form.dataset.locked === 'true' || metadata.pageCode) {
                    savedPages.add(pageIndex);
                    lockPageForm(form, metadata);
                } else {
                    markThumbnailCompleted(pageIndex, metadata);
                }

                if (classification.displayName) {
                    const pageInfo = pageData[pageIndex] || {};
                    pageInfo.display_name = classification.displayName;
                    pageData[pageIndex] = pageInfo;
                    if (pageInfo.scanning_id) {
                        pageDataMap[pageInfo.scanning_id] = pageInfo;
                    }
                }
            } else {
                if (form.dataset.saved === '1') {
                    savedPages.add(pageIndex);
                }

                updatePageSubtypes(pageIndex);
                updateSerialNumberForSpecialCases(pageIndex);
                updatePageCode(pageIndex);
            }
        });

        updateProgress();
        lucide.createIcons();
    }

    function lockPageForm(form, metadata = {}, options = {}) {
        if (!form) return;
        form.dataset.locked = 'true';

        const inputs = form.querySelectorAll('input, select, textarea, button');
        inputs.forEach(element => {
            if (element.classList.contains('page-select-checkbox')) {
                element.disabled = true;
                return;
            }
            if (element.type !== 'button') {
                element.disabled = true;
            }
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.setAttribute('readonly', 'readonly');
            }
        });

        const meta = {
            pageTypeName: metadata.pageTypeName || metadata.pageTypeOthers || null,
            pageTypeOthers: metadata.pageTypeOthers || null,
            pageSubTypeName: metadata.pageSubTypeName || metadata.pageSubTypeOthers || null,
            pageSubTypeOthers: metadata.pageSubTypeOthers || null,
            updatedAt: metadata.updatedAt || metadata.lockedAt || null,
            displayName: metadata.displayName || null,
            pageCode: metadata.pageCode || null,
            serialNumber: metadata.serialNumber || null
        };
        showLockBanner(form, meta);

        if (!options.skipStatusUpdate) {
            const pageIndex = parseInt(form.dataset.pageIndex ?? '-1', 10);
            if (!Number.isNaN(pageIndex) && pageIndex >= 0) {
                markThumbnailCompleted(pageIndex, meta);
                lockedPageMetadata[pageIndex] = Object.assign({}, lockedPageMetadata[pageIndex] || {}, metadata, meta);
            }
        }
    }

    function showLockBanner(form, metadata = {}) {
        const banner = form.querySelector('.classification-lock-banner');
        if (!banner) return;

        banner.style.display = 'flex';
        const title = banner.querySelector('.lock-title');
        const detail = banner.querySelector('.lock-meta');

        const typeText = metadata.pageTypeName || metadata.pageTypeOthers || 'Classified';
        const subtypeLabel = metadata.pageSubTypeName || metadata.pageSubTypeOthers || '';
        const subtypeText = subtypeLabel ? ` • ${subtypeLabel}` : '';
        if (title) {
            title.textContent = `Classified as ${typeText}${subtypeText}`;
        }

        if (detail) {
            const timestamp = formatTimestamp(metadata.updatedAt);
            detail.textContent = timestamp ? `Saved ${timestamp}` : 'Editing has been disabled for this page.';
        }
    }

    function markThumbnailCompleted(pageIndex, metadata = {}) {
        const thumbnail = document.querySelector(`.document-thumbnail[data-page-index="${pageIndex}"]`);
        if (!thumbnail) return;

        thumbnail.classList.add('classified');
        thumbnail.dataset.classified = '1';
        const typeLabel = metadata.pageTypeName || metadata.pageTypeOthers || '';
        const subtypeLabel = metadata.pageSubTypeName || metadata.pageSubTypeOthers || '';
        if (typeLabel) {
            thumbnail.dataset.pageTypeName = typeLabel;
        }
        if (subtypeLabel) {
            thumbnail.dataset.pageSubtypeName = subtypeLabel;
        }
        if (metadata.updatedAt) {
            thumbnail.dataset.classifiedAt = metadata.updatedAt;
        }
        if (metadata.displayName) {
            thumbnail.dataset.displayName = metadata.displayName;
            const nameElement = thumbnail.querySelector('.folder-item-name');
            if (nameElement) {
                nameElement.textContent = metadata.displayName;
            }
        }

        const tagsContainer = thumbnail.querySelector('.folder-item-tags');
        if (tagsContainer) {
            let classificationTag = tagsContainer.querySelector('.folder-tag-classification');
            if (!classificationTag) {
                classificationTag = document.createElement('span');
                classificationTag.className = 'folder-tag folder-tag-classification';
                tagsContainer.appendChild(classificationTag);
            }

            const labelPieces = [];
            if (typeLabel) {
                labelPieces.push(typeLabel);
            }
            if (subtypeLabel) {
                labelPieces.push(subtypeLabel);
            }
            const labelText = labelPieces.join(' • ') || metadata.pageCode || 'Classified';
            classificationTag.textContent = labelText;
        }

        if (metadata.pageCode) {
            thumbnail.dataset.pageCode = metadata.pageCode;
        }

        const codeBadge = thumbnail.querySelector('.folder-item-code');
        if (codeBadge) {
            const codeValue = metadata.pageCode || '—';
            codeBadge.textContent = codeValue;
            codeBadge.dataset.pageCodeValue = metadata.pageCode || '';
            codeBadge.setAttribute('title', metadata.pageCode || 'Not assigned');
        }

        const statusIndicator = thumbnail.querySelector('.page-status');
        if (statusIndicator) {
            statusIndicator.classList.add('completed');
            statusIndicator.innerHTML = '<i data-lucide="check"></i>';
            lucide.createIcons();
        }
    }

    function formatTimestamp(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString();
    }

    function isPageLocked(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        return form ? form.dataset.locked === 'true' : false;
    }

    function applyLockStateForPage(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return;

        if (form.dataset.saved === '1' || form.dataset.locked === 'true') {
            const metadata = lockedPageMetadata[pageIndex] || {};
            lockPageForm(form, metadata, { skipStatusUpdate: true });
        }
    }

    function updateClassificationHeader(pageIndex) {
        const subtitle = document.getElementById('current-page-title');
        if (!subtitle) return;

        const pageNumber = pageIndex + 1;

        if (isPageLocked(pageIndex)) {
            const metadata = lockedPageMetadata[pageIndex] || {};
            const typeLabel = metadata.pageTypeName || 'Classified';
            const subtypeLabel = metadata.pageSubTypeName ? ` • ${metadata.pageSubTypeName}` : '';
            subtitle.textContent = `Classified Page ${pageNumber}: ${typeLabel}${subtypeLabel}`;
        } else {
            subtitle.textContent = `Classify Page ${pageNumber}`;
        }
    }

    function updateSaveButtonState() {
        const saveButton = document.getElementById('save-current-btn');
        if (!saveButton) return;

        const locked = isPageLocked(currentPageIndex);
        saveButton.disabled = locked;
        saveButton.classList.toggle('is-locked', locked);
    }

    // Calculate next serial number
    function calculateNextSerialNumber() {
        const existingSerials = Object.values(state.processedPages)
            .map(p => p.serialNo)
            .filter(s => s && !isNaN(parseInt(s.match(/^\d+/)?.[0])))
            .map(s => parseInt(s.match(/^\d+/)?.[0]));

        const maxSerial = existingSerials.length > 0 ? Math.max(...existingSerials) : 0;
        return (maxSerial + 1).toString().padStart(2, '0');
    }

    // Save current page
    function saveCurrentPage() {
        const form = document.querySelector(`.page-form[data-page-index="${currentPageIndex}"]`);
        if (!form) return false;

        if (form.dataset.locked === 'true') {
            Swal.fire({
                icon: 'info',
                title: 'Already Classified',
                text: 'This page has already been classified and cannot be modified.',
                confirmButtonColor: '#6366f1'
            });
            return false;
        }

        const coverTypeSelect = form.querySelector('.cover-type-select');
        const pageTypeSelect = form.querySelector('.page-type-select');
        const pageSubtypeSelect = form.querySelector('.page-subtype-select');
        const serialInput = form.querySelector('.serial-input');

        // Validation
        if (!coverTypeSelect.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Cover Type',
                text: 'Please select a cover type.',
                confirmButtonColor: '#f59e0b'
            });
            return false;
        }

        if (!pageTypeSelect.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Page Type',
                text: 'Please select a page type.',
                confirmButtonColor: '#f59e0b'
            });
            return false;
        }

        if (!serialInput.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Serial Number',
                text: 'Please provide a serial number.',
                confirmButtonColor: '#f59e0b'
            });
            return false;
        }

        const pageInfo = pageData[currentPageIndex];
        const pageTypeValue = pageTypeSelect.value;
        const pageSubtypeValue = pageSubtypeSelect.value;
        
        // Validate "Other" inputs if selected
        const normalizedPageType = (pageTypeValue || '').toString().trim().toLowerCase();
        const normalizedPageSubtype = (pageSubtypeValue || '').toString().trim().toLowerCase();

        if (normalizedPageType === 'other' || normalizedPageType === 'others' || normalizedPageType === 'oth') {
            const othersInput = form.querySelector('.page-type-others-input');
            if (!othersInput || !othersInput.value.trim()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Page Type',
                    text: 'Please specify the page type in the text field.',
                    confirmButtonColor: '#f59e0b'
                });
                return false;
            }
        }
        
        if (normalizedPageSubtype === 'other' || normalizedPageSubtype === 'others' || normalizedPageSubtype === 'oth') {
            const othersInput = form.querySelector('.page-subtype-others-input');
            if (!othersInput || !othersInput.value.trim()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Page Subtype',
                    text: 'Please specify the page subtype in the text field.',
                    confirmButtonColor: '#f59e0b'
                });
                return false;
            }
        }
        
        const pageTypingData = {
            file_indexing_id: fileIndexingId,
            scanning_id: pageInfo.scanning_id,
            page_number: pageInfo.page_number,
            cover_type_id: coverTypeSelect.value ? coverTypeSelect.value : null,
            page_type: pageTypeValue,
            page_type_other: (normalizedPageType === 'other' || normalizedPageType === 'others' || normalizedPageType === 'oth')
                ? (form.querySelector('.page-type-others-input')?.value.trim() || '')
                : null,
            page_subtype: pageSubtypeValue || null,
            page_subtype_other: (normalizedPageSubtype === 'other' || normalizedPageSubtype === 'others' || normalizedPageSubtype === 'oth')
                ? (form.querySelector('.page-subtype-others-input')?.value.trim() || '')
                : null,
            serial_number: serialInput.value.trim(),
            page_code: (form.querySelector('.page-code-input').value || '').trim(),
            file_path: pageInfo.file_path,
            page_index: currentPageIndex
        };

        if (pageTypingData.page_subtype === 'others') {
            pageTypingData.page_subtype = 'others';
        }

        // Save to backend
        return savePageTypingToBackend(pageTypingData).then(response => {
            if (response?.success) {
                const metadata = response.metadata || {};

                savedPages.add(currentPageIndex);
                form.dataset.saved = '1';

                // Persist metadata for quick lookups
                lockedPageMetadata[currentPageIndex] = metadata;

                // Update datasets for future loads
                form.dataset.existingCoverType = metadata.coverTypeId || '';
                form.dataset.existingPageType = metadata.pageTypeId && metadata.pageTypeId !== 'others' ? metadata.pageTypeId : (metadata.pageTypeOthers ? 'others' : '');
                form.dataset.existingPageTypeOthers = metadata.pageTypeOthers || '';
                form.dataset.existingPageTypeName = metadata.pageTypeName || '';
                form.dataset.existingPageSubtype = metadata.pageSubTypeId && metadata.pageSubTypeId !== 'others' ? metadata.pageSubTypeId : (metadata.pageSubTypeOthers ? 'others' : '');
                form.dataset.existingPageSubtypeOthers = metadata.pageSubTypeOthers || '';
                form.dataset.existingPageSubTypeName = metadata.pageSubTypeName || '';
                form.dataset.existingUpdatedAt = metadata.updatedAt || '';
                form.dataset.existingSerialNumber = metadata.serialNumber || '';
                form.dataset.existingPageCode = metadata.pageCode || '';

                // Store in processed pages for code generation consistency
                state.processedPages[currentPageIndex] = {
                    coverType: metadata.coverTypeId,
                    pageType: metadata.pageTypeId && metadata.pageTypeId !== 'others' ? metadata.pageTypeId : null,
                    pageTypeOthers: metadata.pageTypeOthers,
                    pageSubType: metadata.pageSubTypeId && metadata.pageSubTypeId !== 'others' ? metadata.pageSubTypeId : null,
                    pageSubTypeOthers: metadata.pageSubTypeOthers,
                    serialNo: metadata.serialNumber,
                    page_code: metadata.pageCode
                };

                // Update page data references
                const currentPage = pageData[currentPageIndex] || {};
                currentPage.file_path = metadata.filePath || currentPage.file_path;
                if (metadata.fileUrl) {
                    currentPage.url = metadata.fileUrl;
                }
                if (metadata.displayName) {
                    currentPage.display_name = metadata.displayName;
                }
                pageData[currentPageIndex] = currentPage;
                pageDataMap[currentPage.scanning_id] = currentPage;

                // Update thumbnail attributes
                const thumb = document.querySelector(`.document-thumbnail[data-page-index="${currentPageIndex}"]`);
                metadata.displayName = metadata.displayName || currentPage.display_name || thumb?.dataset.displayName || null;
                if (thumb) {
                    thumb.dataset.filePath = currentPage.file_path;
                    if (metadata.displayName) {
                        thumb.dataset.displayName = metadata.displayName;
                    }
                    if (metadata.displayName) {
                        const nameEl = thumb.querySelector('.folder-item-name');
                        if (nameEl) {
                            nameEl.textContent = metadata.displayName;
                        }
                    }
                }

                lockPageForm(form, metadata);
                updateProgress();
                updateClassificationHeader(currentPageIndex);
                updateSaveButtonState();

                return true;
            }
            return false;
        });
    }

    // Save to backend
    async function savePageTypingToBackend(pageTypingData) {
        try {
            const csrfToken = getCsrfToken();
            const response = await fetch(savePageEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(pageTypingData)
            });

            const result = await response.json();

            if (result.success) {
                const pageIndex = pageTypingData.page_index ?? currentPageIndex;
                const metadata = {
                    pageTypeId: pageTypingData.page_type,
                    pageTypeName: result.page_type_name || null,
                    pageTypeOthers: pageTypingData.page_type === 'others' ? pageTypingData.page_type_others : null,
                    pageSubTypeId: pageTypingData.page_subtype,
                    pageSubTypeName: result.page_subtype_name || null,
                    pageSubTypeOthers: pageTypingData.page_subtype === 'others' ? pageTypingData.page_subtype_others : null,
                    coverTypeId: pageTypingData.cover_type_id,
                    serialNumber: pageTypingData.serial_number,
                    pageCode: pageTypingData.page_code,
                    updatedAt: result.locked_at || null,
                    displayName: result.updated_display_name || null,
                    filePath: result.updated_file_path || pageTypingData.file_path,
                    fileUrl: result.updated_file_url || null
                };

                return { success: true, metadata, result, pageIndex };
            }

            console.error('Backend error:', result.message);
            Swal.fire({
                icon: 'error',
                title: 'Save Failed',
                text: result.message || 'Failed to save page classification.',
                confirmButtonColor: '#dc3545'
            });
            return { success: false, message: result.message };
        } catch (error) {
            console.error('Network error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Failed to connect to server. Please check your connection.',
                confirmButtonColor: '#dc3545'
            });
            return { success: false, message: error.message };
        }
    }

    // Update progress
    function updateProgress() {
        const completed = savedPages.size;
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        
        if (progressFill) {
            const percentage = totalPages > 0 ? (completed / totalPages) * 100 : 0;
            progressFill.style.width = percentage + '%';
        }
        
        if (progressText) {
            progressText.textContent = `${completed} of ${totalPages} pages completed`;
        }
    }

    // Multi-Select Mode functions
    function toggleMultiSelectMode() {
        state.isMultiSelectMode = !state.isMultiSelectMode;
        updateMultiSelectUI();
        
        if (state.isMultiSelectMode) {
            // Add checkboxes to thumbnails
            addMultiSelectCheckboxes();
            // Update thumbnail click behavior
            updateThumbnailClickBehavior();
        } else {
            // Clear selections
            state.selectedPages.clear();
            removeMultiSelectCheckboxes();
            updateThumbnailClickBehavior();
        }
    }

    function updateMultiSelectUI() {
        const toggleBtn = document.querySelector('.toggle-multi-select');
        const activeState = document.querySelector('.multi-select-active');
        const controlText = toggleBtn?.querySelector('.control-text');
        
        if (state.isMultiSelectMode) {
            toggleBtn?.setAttribute('data-active', 'true');
            if (controlText) controlText.textContent = 'Disable';
            activeState?.style.setProperty('display', 'block');
            document.body.classList.add('multi-select-mode');
        } else {
            toggleBtn?.setAttribute('data-active', 'false');
            if (controlText) controlText.textContent = 'Enable';
            activeState?.style.setProperty('display', 'none');
            document.body.classList.remove('multi-select-mode');
        }
        
        updateSelectedCount();
    }

    function addMultiSelectCheckboxes() {
        document.querySelectorAll('.document-thumbnail').forEach(thumbnail => {
            if (thumbnail.dataset.classified === '1') {
                thumbnail.classList.remove('selected');
                return;
            }

            if (!thumbnail.querySelector('.page-select-checkbox')) {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'page-select-checkbox';
                checkbox.dataset.pageIndex = thumbnail.dataset.pageIndex;
                checkbox.draggable = false;
                checkbox.addEventListener('click', event => event.stopPropagation());
                thumbnail.appendChild(checkbox);
                
                checkbox.addEventListener('change', function() {
                    const pageIndex = parseInt(this.dataset.pageIndex);
                    if (this.checked) {
                        state.selectedPages.add(pageIndex);
                        thumbnail.classList.add('selected');
                    } else {
                        state.selectedPages.delete(pageIndex);
                        thumbnail.classList.remove('selected');
                    }
                    updateSelectedCount();
                });
            }
        });
    }

    function removeMultiSelectCheckboxes() {
        document.querySelectorAll('.page-select-checkbox').forEach(checkbox => {
            checkbox.remove();
        });
        document.querySelectorAll('.document-thumbnail').forEach(thumbnail => {
            thumbnail.classList.remove('selected');
        });
    }

    function updateThumbnailClickBehavior() {
        document.querySelectorAll('.document-thumbnail').forEach(thumbnail => {
            if (thumbnail.__clickHandler) {
                thumbnail.removeEventListener('click', thumbnail.__clickHandler);
            }

            const handler = function (event) {
                if (isDraggingCard) {
                    event.preventDefault();
                    return;
                }
                const pageIndex = parseInt(this.dataset.pageIndex || '0', 10);

                if (state.isMultiSelectMode) {
                    event.preventDefault();
                    const checkbox = this.querySelector('.page-select-checkbox');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                } else {
                    navigateToPage(pageIndex);
                }
            };

            thumbnail.addEventListener('click', handler);
            thumbnail.__clickHandler = handler;
        });

        setupDocumentDropHandlers();
    }

    function updateSelectedCount() {
        const countElement = document.querySelector('.selected-count');
        if (countElement) {
            const count = state.selectedPages.size;
            countElement.textContent = `${count} page${count !== 1 ? 's' : ''} selected`;
        }
    }

    function selectAllPages() {
        state.selectedPages.clear();
        for (let i = 0; i < totalPages; i++) {
            state.selectedPages.add(i);
        }
        
        document.querySelectorAll('.page-select-checkbox').forEach(checkbox => {
            checkbox.checked = true;
            const thumbnail = checkbox.closest('.document-thumbnail');
            thumbnail.classList.add('selected');
        });
        
        updateSelectedCount();
    }

    function clearSelection() {
        state.selectedPages.clear();
        
        document.querySelectorAll('.page-select-checkbox').forEach(checkbox => {
            checkbox.checked = false;
            const thumbnail = checkbox.closest('.document-thumbnail');
            thumbnail.classList.remove('selected');
        });
        
        updateSelectedCount();
    }

    // Booklet Management functions
    function startBooklet() {
        state.bookletMode = true;
        state.currentBooklet = Date.now().toString(); // Unique booklet ID
        state.bookletStartPage = calculateNextSerialNumber();
        state.bookletCounter = 'a';
        updateBookletUI();
        
        // Update current page serial to booklet format
        const serialInput = document.querySelector(`.serial-input[data-page-index="${currentPageIndex}"]`);
        if (serialInput) {
            serialInput.value = state.bookletStartPage + state.bookletCounter;
        }
    }

    function endBooklet() {
        state.bookletMode = false;
        state.currentBooklet = null;
        state.bookletStartPage = null;
        state.bookletCounter = 'a';
        updateBookletUI();
        
        // Remove booklet styling from thumbnails
        document.querySelectorAll('.document-thumbnail').forEach(thumbnail => {
            thumbnail.classList.remove('booklet-page');
        });
    }

    function updateBookletUI() {
        const startBtn = document.querySelector('.start-booklet');
        const activeState = document.querySelector('.booklet-active');
        const controlText = startBtn?.querySelector('.control-text');
        const bookletInfo = document.querySelector('.booklet-info-text');
        const nextNumber = document.querySelector('.next-booklet-number');
        
        if (state.bookletMode) {
            startBtn?.setAttribute('data-active', 'true');
            if (controlText) controlText.textContent = 'Active';
            activeState?.style.setProperty('display', 'block');
            
            if (bookletInfo && state.bookletPages[state.currentBooklet]) {
                const pages = state.bookletPages[state.currentBooklet];
                const pageNumbers = pages.map(p => p.serialNumber).join(', ');
                bookletInfo.textContent = `Pages ${pageNumbers}`;
            }
            
            if (nextNumber) {
                nextNumber.textContent = state.bookletStartPage + state.bookletCounter;
            }
        } else {
            startBtn?.setAttribute('data-active', 'false');
            if (controlText) controlText.textContent = 'Start Booklet';
            activeState?.style.setProperty('display', 'none');
        }
    }

    // Process multiple selected pages
    async function processMultiplePages() {
        if (state.selectedPages.size === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Pages Selected',
                text: 'Please select pages to process.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        // Get current page values for batch processing
        const currentForm = document.querySelector(`.page-form[data-page-index="${currentPageIndex}"]`);
        if (!currentForm) return;

        const coverType = currentForm.querySelector('.cover-type-select').value;
        const pageType = currentForm.querySelector('.page-type-select').value;
        const pageSubtype = currentForm.querySelector('.page-subtype-select').value || null;
        const pageTypeOthersValue = pageType === 'others' ? (currentForm.querySelector('.page-type-others-input')?.value.trim() || '') : null;
        const pageSubtypeOthersValue = pageSubtype === 'others' ? (currentForm.querySelector('.page-subtype-others-input')?.value.trim() || '') : null;

        if (!coverType || !pageType) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Required Fields',
                text: 'Please fill in cover type and page type before processing multiple pages.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        const selectedPagesArray = Array.from(state.selectedPages).sort((a, b) => a - b);
        const filteredPages = selectedPagesArray.filter(index => !isPageLocked(index));
        const skippedPages = selectedPagesArray.length - filteredPages.length;

        if (filteredPages.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Pages Already Classified',
                text: 'All selected pages have already been classified.',
                confirmButtonColor: '#6366f1'
            });
            toggleMultiSelectMode();
            return;
        }

        // Show progress
        Swal.fire({
            title: 'Processing Selected Pages',
            html: `Processing 0 of ${filteredPages.length} pages...`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        let processedCount = 0;
        let failedCount = 0;

        for (const pageIndex of filteredPages) {
            try {
                const pageInfo = pageData[pageIndex];
                const serialNumber = state.bookletMode 
                    ? state.bookletStartPage + String.fromCharCode('a'.charCodeAt(0) + processedCount)
                    : (parseInt(calculateNextSerialNumber()) + processedCount).toString().padStart(2, '0');

                const pageCode = generatePageCode(coverType, pageType, pageSubtype, serialNumber, pageTypeOthersValue, pageSubtypeOthersValue);

                const pageTypingData = {
                    file_indexing_id: fileIndexingId,
                    scanning_id: pageInfo.scanning_id,
                    page_number: pageInfo.page_number,
                    cover_type_id: coverType,
                    page_type: pageType,
                    page_type_others: pageTypeOthersValue,
                    page_subtype: pageSubtype,
                    page_subtype_others: pageSubtypeOthersValue,
                    serial_number: serialNumber,
                    page_code: pageCode,
                    file_path: pageInfo.file_path,
                    page_index: pageIndex
                };

                const response = await savePageTypingToBackend(pageTypingData);
                
                if (response?.success) {
                    const metadata = response.metadata || {};
                    processedCount++;
                    savedPages.add(pageIndex);
                    lockedPageMetadata[pageIndex] = metadata;

                    state.processedPages[pageIndex] = {
                        coverType: metadata.coverTypeId || coverType,
                        pageType: metadata.pageTypeId && metadata.pageTypeId !== 'others' ? metadata.pageTypeId : null,
                        pageTypeOthers: metadata.pageTypeOthers,
                        pageSubType: metadata.pageSubTypeId && metadata.pageSubTypeId !== 'others' ? metadata.pageSubTypeId : null,
                        pageSubTypeOthers: metadata.pageSubTypeOthers,
                        serialNo: metadata.serialNumber,
                        page_code: metadata.pageCode || pageCode
                    };

                    const targetForm = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
                    if (targetForm) {
                        targetForm.dataset.saved = '1';
                        targetForm.dataset.existingCoverType = metadata.coverTypeId || coverType;
                        targetForm.dataset.existingPageType = metadata.pageTypeId && metadata.pageTypeId !== 'others' ? metadata.pageTypeId : (metadata.pageTypeOthers ? 'others' : '');
                        targetForm.dataset.existingPageTypeOthers = metadata.pageTypeOthers || '';
                        targetForm.dataset.existingPageTypeName = metadata.pageTypeName || '';
                        targetForm.dataset.existingPageSubtype = metadata.pageSubTypeId && metadata.pageSubTypeId !== 'others' ? metadata.pageSubTypeId : (metadata.pageSubTypeOthers ? 'others' : '');
                        targetForm.dataset.existingPageSubtypeOthers = metadata.pageSubTypeOthers || '';
                        targetForm.dataset.existingPageSubTypeName = metadata.pageSubTypeName || '';
                        targetForm.dataset.existingUpdatedAt = metadata.updatedAt || '';
                        targetForm.dataset.existingSerialNumber = metadata.serialNumber || serialNumber;
                        targetForm.dataset.existingPageCode = metadata.pageCode || pageCode;
                        lockPageForm(targetForm, metadata);
                    }

                    const pageItem = pageData[pageIndex] || {};
                    pageItem.file_path = metadata.filePath || pageItem.file_path;
                    if (metadata.fileUrl) {
                        pageItem.url = metadata.fileUrl;
                    }
                    if (metadata.displayName) {
                        pageItem.display_name = metadata.displayName;
                    }
                    pageData[pageIndex] = pageItem;
                    if (pageItem.scanning_id) {
                        pageDataMap[pageItem.scanning_id] = pageItem;
                    }
                } else {
                    failedCount++;
                }

                // Update progress
                Swal.update({
                    html: `Processing ${processedCount + failedCount} of ${filteredPages.length} pages...`
                });

                await new Promise(resolve => setTimeout(resolve, 100));

            } catch (error) {
                console.error(`Error processing page ${pageIndex}:`, error);
                failedCount++;
            }
        }

        // Show results
        Swal.close();

        if (processedCount > 0) {
            updateProgress();
            if (filteredPages.includes(currentPageIndex)) {
                updateClassificationHeader(currentPageIndex);
            }
            updateSaveButtonState();

            const summaryParts = [];
            summaryParts.push(`Successfully processed ${processedCount} page${processedCount === 1 ? '' : 's'}`);
            if (failedCount > 0) {
                summaryParts.push(`${failedCount} failed`);
            }
            if (skippedPages > 0) {
                summaryParts.push(`${skippedPages} skipped (already classified)`);
            }

            Swal.fire({
                icon: 'success',
                title: 'Processing Complete',
                text: summaryParts.join(' • '),
                confirmButtonColor: '#10b981'
            });

            toggleMultiSelectMode();
        } else {
            const messageParts = [];
            if (failedCount > 0) {
                messageParts.push(`${failedCount} page${failedCount === 1 ? '' : 's'} failed to save`);
            }
            if (skippedPages > 0) {
                messageParts.push(`${skippedPages} skipped (already classified)`);
            }

            Swal.fire({
                icon: failedCount > 0 ? 'error' : 'info',
                title: failedCount > 0 ? 'No Pages Saved' : 'No Changes Applied',
                text: messageParts.join(' • ') || 'No pages were processed.',
                confirmButtonColor: failedCount > 0 ? '#ef4444' : '#6366f1'
            });

            toggleMultiSelectMode();
        }
    }

    function generatePageCode(coverType, pageType, pageSubtype, serialNumber, pageTypeOthers = null, pageSubtypeOthers = null) {
        const coverCode = getCoverTypeById(coverType)?.code || 'XX';
        const pageCode = getPageTypeCode(pageType, pageTypeOthers);
        const subtypeCode = pageSubtype ? getPageSubTypeCode(pageType, pageSubtype, pageSubtypeOthers) : '';
        
        return `${coverCode}-${pageCode}${subtypeCode ? '-' + subtypeCode : ''}-${serialNumber}`;
    }

    // Load data from backend with enhanced error handling
    async function loadPageTypingData() {
        try {
            const response = await fetch(typingDataEndpoint);
            const data = await response.json();
            
            if (data.success) {
                // Normalize cover types
                const rawCover = data.cover_types || [];
                coverTypes = rawCover.map(ct => ({
                    id: (ct.id || ct.Id).toString(),
                    code: ct.code || ct.Code || 'CV',
                    name: ct.name || ct.Name || ct.title || ct.Title || 'Cover'
                }));

                // Normalize page types
                const rawTypes = data.page_types || [];
                pageTypes = rawTypes.map(pt => ({
                    id: (pt.id || pt.Id).toString(),
                    code: pt.code || pt.Code || pt.PageType || 'PT',
                    name: pt.name || pt.Name || (pt.code || pt.Code || 'Page Type')
                }));

                // Add "Others" option to page types
                pageTypes.push({ id: 'others', code: "OTH", name: "Others" });

                // Process page subtypes
                const rawSubs = data.page_sub_types || {};
                pageSubTypes = {};
                
                if (Array.isArray(rawSubs)) {
                    const grouped = {};
                    rawSubs.forEach(st => {
                        const ptId = (st.page_type_id || st.PageTypeId || st.pageTypeId).toString();
                        if (!grouped[ptId]) grouped[ptId] = [];
                        grouped[ptId].push({
                            id: (st.id || st.Id).toString(),
                            code: st.code || st.Code || st.PageSubType || 'ST',
                            name: st.name || st.Name || (st.code || st.Code || 'SubType')
                        });
                    });
                    pageSubTypes = grouped;
                } else {
                    Object.keys(rawSubs || {}).forEach(ptId => {
                        pageSubTypes[ptId.toString()] = (rawSubs[ptId] || []).map(st => ({
                            id: (st.id || st.Id).toString(),
                            code: st.code || st.Code || st.PageSubType || 'ST',
                            name: st.name || st.Name || (st.code || st.Code || 'SubType')
                        }));
                    });
                }

                // Add "Others" option to all page subtypes
                Object.keys(pageSubTypes).forEach(ptId => {
                    pageSubTypes[ptId].push({ id: 'others', code: "OTH", name: "Others" });
                });

                console.log('Loaded page typing data:', { coverTypes, pageTypes, pageSubTypes });
            } else {
                console.error('Error loading page typing data:', data.message);
                setDefaultPageTypingData();
            }
        } catch (error) {
            console.error('Error loading page typing data:', error);
            setDefaultPageTypingData();
        }
        
        // Initialize form options after data is loaded
        initializeFormOptions();
    }

    // Initialize with default data if backend fails
    function setDefaultPageTypingData() {
        coverTypes = [
            { id: '1', code: "FC", name: "Front Cover" },
            { id: '2', code: "BC", name: "Back Cover" }
        ];
        
        pageTypes = [
            { id: '1', code: "FC", name: "File Cover" },
            { id: '2', code: "APP", name: "Application" },
            { id: '3', code: "BN", name: "Bill Notice" },
            { id: '4', code: "COR", name: "Correspondence" },
            { id: 'others', code: "OTH", name: "Others" }
        ];

        pageSubTypes = {
            '1': [
                { id: '1', code: "NFC", name: "New File Cover" }, 
                { id: '2', code: "OFC", name: "Old File Cover" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '2': [
                { id: '3', code: "CO", name: "Certificate of Occupancy" }, 
                { id: '4', code: "REV", name: "Revalidation" },
                { id: 'others', code: "OTH", name: "Others" }
            ]
        };
    }

    // Initialize form options with loaded data
    function initializeFormOptions() {
        // Populate cover type selects
        document.querySelectorAll('.cover-type-select').forEach(select => {
            select.innerHTML = '<option value="">Select cover type</option>';
            coverTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = `${type.name} (${type.code})`;
                select.appendChild(option);
            });
        });

        // Populate page type selects
        document.querySelectorAll('.page-type-select').forEach(select => {
            select.innerHTML = '<option value="">Select page type</option>';
            pageTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = `${type.name} (${type.code})`;
                select.appendChild(option);
            });
        });

        // Initialize page type change handlers
        initializePageTypeHandlers();
    }

    // Initialize page type change handlers
    function initializePageTypeHandlers() {
        // Page type change handlers
        document.querySelectorAll('.page-type-select').forEach(select => {
            select.addEventListener('change', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                updatePageSubtypes(pageIndex);
                updatePageCode(pageIndex);
                updateSerialNumberForSpecialCases(pageIndex);
            });
        });

        // Page subtype change handlers
        document.querySelectorAll('.page-subtype-select').forEach(select => {
            select.addEventListener('change', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                updatePageCode(pageIndex);
                toggleOthersInput(this, '.page-subtype-others-container');
            });
        });

        // Cover type change handlers
        document.querySelectorAll('.cover-type-select').forEach(select => {
            select.addEventListener('change', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                updatePageCode(pageIndex);
                updateSerialNumberForSpecialCases(pageIndex);
            });
        });

        // Serial number change handlers
        document.querySelectorAll('.serial-input').forEach(input => {
            input.addEventListener('input', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                updatePageCode(pageIndex);
            });
        });
    }

    // Helper functions for codes
    function getCoverTypeById(id) {
        return coverTypes.find(ct => ct.id == id);
    }

    function getPageTypeById(id) {
        return pageTypes.find(pt => pt.id == id);
    }

    function getPageTypeCode(typeId, othersValue = null) {
        if (typeId === 'others') {
            const customValue = othersValue || '';
            return customValue.substring(0, 4).toUpperCase() || 'OTH';
        }
        const pageType = getPageTypeById(typeId);
        return pageType?.code || 'XX';
    }

    function getPageSubTypeCode(typeId, subTypeId, othersValue = null) {
        if (subTypeId === 'others') {
            if (typeId === 'others') {
                return '';
            }
            const customValue = othersValue || '';
            return customValue.substring(0, 4).toUpperCase() || 'OTH';
        }
        const subType = getPageSubTypeById(typeId, subTypeId);
        return subType?.code || 'XX';
    }

    // Populate form with existing classification data
    function populateFormWithClassification(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return null;

        const dataset = form.dataset;
        
        // Check if we have existing data
        if (!dataset.existingCoverType && !dataset.existingPageType) {
            return null;
        }

        const classification = {
            coverTypeId: dataset.existingCoverType,
            pageTypeId: dataset.existingPageType,
            pageTypeName: dataset.existingPageTypeName,
            pageTypeOthers: dataset.existingPageTypeOthers,
            pageSubTypeId: dataset.existingPageSubtype,
            pageSubTypeName: dataset.existingPageSubtypeName,
            pageSubTypeOthers: dataset.existingPageSubtypeOthers,
            serialNumber: dataset.existingSerialNumber,
            pageCode: dataset.existingPageCode,
            updatedAt: dataset.existingUpdatedAt
        };

        // Populate form fields
        const coverSelect = form.querySelector('.cover-type-select');
        const pageSelect = form.querySelector('.page-type-select');
        const pageSubSelect = form.querySelector('.page-subtype-select');
        const serialInput = form.querySelector('.serial-input');
        const pageCodeInput = form.querySelector('.page-code-input');
        
        if (coverSelect && classification.coverTypeId) {
            coverSelect.value = classification.coverTypeId;
        }
        
        if (pageSelect && classification.pageTypeId) {
            pageSelect.value = classification.pageTypeId;
            updatePageSubtypes(pageIndex);
        }
        
        if (pageSubSelect && classification.pageSubTypeId) {
            pageSubSelect.value = classification.pageSubTypeId;
        }
        
        if (serialInput && classification.serialNumber) {
            serialInput.value = classification.serialNumber;
        }
        
        if (pageCodeInput && classification.pageCode) {
            pageCodeInput.value = classification.pageCode;
        }

        return classification;
    }

    function lockPageForm(form, metadata = {}, options = {}) {
        if (!form) return;
        form.dataset.locked = 'true';

        const inputs = form.querySelectorAll('input, select, textarea, button');
        inputs.forEach(element => {
            if (element.classList.contains('page-select-checkbox')) {
                element.disabled = true;
                return;
            }
            if (element.type !== 'button') {
                element.disabled = true;
            }
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.setAttribute('readonly', 'readonly');
            }
        });

        const meta = {
            pageTypeName: metadata.pageTypeName || metadata.pageTypeOthers || null,
            pageTypeOthers: metadata.pageTypeOthers || null,
            pageSubTypeName: metadata.pageSubTypeName || metadata.pageSubTypeOthers || null,
            pageSubTypeOthers: metadata.pageSubTypeOthers || null,
            updatedAt: metadata.updatedAt || metadata.lockedAt || null,
            displayName: metadata.displayName || null,
            pageCode: metadata.pageCode || null,
            serialNumber: metadata.serialNumber || null
        };

        if (!options.skipStatusUpdate) {
            const pageIndex = parseInt(form.dataset.pageIndex ?? '-1', 10);
            if (!Number.isNaN(pageIndex) && pageIndex >= 0) {
                markThumbnailCompleted(pageIndex, meta);
                lockedPageMetadata[pageIndex] = Object.assign({}, lockedPageMetadata[pageIndex] || {}, metadata, meta);
            }
        }
    }

    function markThumbnailCompleted(pageIndex, metadata = {}) {
        const thumbnail = document.querySelector(`.document-thumbnail[data-page-index="${pageIndex}"]`);
        if (!thumbnail) return;

        thumbnail.classList.add('classified');
        thumbnail.dataset.classified = '1';

        const typeLabel = metadata.pageTypeName || metadata.pageTypeOthers || '';
        const subtypeLabel = metadata.pageSubTypeName || metadata.pageSubTypeOthers || '';
        if (typeLabel) {
            thumbnail.dataset.pageTypeName = typeLabel;
        }
        if (subtypeLabel) {
            thumbnail.dataset.pageSubtypeName = subtypeLabel;
        }
        if (metadata.updatedAt) {
            thumbnail.dataset.classifiedAt = metadata.updatedAt;
        }
        if (metadata.displayName) {
            thumbnail.dataset.displayName = metadata.displayName;
            const nameElement = thumbnail.querySelector('.folder-item-name');
            if (nameElement) {
                nameElement.textContent = metadata.displayName;
            }
        }

        const tagsContainer = thumbnail.querySelector('.folder-item-tags');
        if (tagsContainer) {
            let classificationTag = tagsContainer.querySelector('.folder-tag-classification');
            if (!classificationTag) {
                classificationTag = document.createElement('span');
                classificationTag.className = 'folder-tag folder-tag-classification';
                tagsContainer.appendChild(classificationTag);
            }

            const labelPieces = [];
            if (typeLabel) {
                labelPieces.push(typeLabel);
            }
            if (subtypeLabel) {
                labelPieces.push(subtypeLabel);
            }
            const labelText = labelPieces.join(' • ') || metadata.pageCode || 'Classified';
            classificationTag.textContent = labelText;
        }

        if (metadata.pageCode) {
            thumbnail.dataset.pageCode = metadata.pageCode;
        }

        const codeBadge = thumbnail.querySelector('.folder-item-code');
        if (codeBadge) {
            const codeValue = metadata.pageCode || '—';
            codeBadge.textContent = codeValue;
            codeBadge.dataset.pageCodeValue = metadata.pageCode || '';
            codeBadge.setAttribute('title', metadata.pageCode || 'Not assigned');
        }

        const statusIndicator = thumbnail.querySelector('.page-status');
        if (statusIndicator) {
            statusIndicator.classList.add('completed');
            statusIndicator.innerHTML = '<i data-lucide="check"></i>';
            lucide.createIcons();
        }
    }

    const folderStatusState = {
        defaultText: null,
        timeoutId: null
    };

    function updateFolderStatus(message, options = {}) {
        const indicator = document.getElementById('folder-status-indicator');
        if (!indicator) {
            return;
        }

        if (!folderStatusState.defaultText) {
            folderStatusState.defaultText = indicator.textContent || '';
        }

        indicator.textContent = message;

        if (folderStatusState.timeoutId) {
            clearTimeout(folderStatusState.timeoutId);
            folderStatusState.timeoutId = null;
        }

        if (options.revert === false) {
            return;
        }

        folderStatusState.timeoutId = setTimeout(() => {
            indicator.textContent = folderStatusState.defaultText || '';
            folderStatusState.timeoutId = null;
        }, options.timeout || 3500);
    }

    function eventHasFiles(event) {
        if (!event || !event.dataTransfer) {
            return false;
        }

        const files = event.dataTransfer.files;
        if (files && files.length) {
            return true;
        }

        const types = event.dataTransfer.types;
        if (!types) {
            return false;
        }

        if (typeof types.includes === 'function') {
            return types.includes('Files');
        }

        if (typeof types.contains === 'function') {
            return types.contains('Files');
        }

        return Array.from(types).includes('Files');
    }

    function registerDropTarget(element, options = {}) {
        if (!element || element.__dropHandlersAttached) {
            return;
        }

        element.__dropOptions = options;

        const dragOver = (event) => handleDocumentDragOver(event, element, options);
        const dragEnter = (event) => handleDocumentDragEnter(event, element, options);
        const dragLeave = (event) => handleDocumentDragLeave(event, element, options);
        const drop = (event) => handleDocumentDrop(event, element, options);

        element.addEventListener('dragover', dragOver);
        element.addEventListener('dragenter', dragEnter);
        element.addEventListener('dragleave', dragLeave);
        element.addEventListener('drop', drop);

        element.__dropHandlersAttached = { dragOver, dragEnter, dragLeave, drop };
    }

    function handleDocumentDragOver(event, element, options = {}) {
        if (!eventHasFiles(event)) {
            return;
        }
        event.preventDefault();
        element.classList.add('dropzone-hover');
        event.dataTransfer.dropEffect = 'copy';
    }

    function handleDocumentDragEnter(event, element, options = {}) {
        if (!eventHasFiles(event)) {
            return;
        }
        event.preventDefault();
        element.classList.add('dropzone-hover');
    }

    function handleDocumentDragLeave(event, element, options = {}) {
        if (!eventHasFiles(event)) {
            return;
        }

        const relatedTarget = event.relatedTarget;
        if (relatedTarget && element.contains(relatedTarget)) {
            return;
        }

        element.classList.remove('dropzone-hover');
    }

    function handleDocumentDrop(event, element, options = {}) {
        if (event.__dropHandled) {
            return;
        }

        event.__dropHandled = true;

        if (!eventHasFiles(event)) {
            return;
        }

        event.preventDefault();
        element.classList.remove('dropzone-hover');

        const files = Array.from(event.dataTransfer.files || []);
        if (!files.length) {
            return;
        }

        const file = files[0];
        const pageIndex = options.isViewer
            ? currentPageIndex
            : parseInt(element.dataset.pageIndex || String(currentPageIndex), 10);

        if (Number.isNaN(pageIndex) || pageIndex < 0 || pageIndex >= totalPages) {
            if (Swal && Swal.fire) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Unsupported Drop',
                    text: 'Select a page before dropping a replacement file.'
                });
            }
            return;
        }

        if (!options.isViewer && pageIndex !== currentPageIndex) {
            navigateToPage(pageIndex);
        }

        uploadReplacementFileForPage(file, pageIndex, element);
        if (event.dataTransfer.clearData) {
            event.dataTransfer.clearData();
        }
    }

    const globalDropState = {
        activeTarget: null,
        options: null,
        hoverTimeout: null
    };

    function findDropTargetInfo(node) {
        if (!node) {
            return null;
        }

        let current = node;
        const elementNodeType = (typeof Node !== 'undefined' && Node.ELEMENT_NODE) ? Node.ELEMENT_NODE : 1;
        const textNodeType = (typeof Node !== 'undefined' && Node.TEXT_NODE) ? Node.TEXT_NODE : 3;

        if (current.nodeType === textNodeType) {
            current = current.parentElement;
        }

        if (current && current.nodeType !== elementNodeType) {
            current = current.parentElement;
        }

        if (!current) {
            return null;
        }

        const thumbnail = current.closest ? current.closest('.document-thumbnail') : null;
        if (thumbnail) {
            return { element: thumbnail, options: thumbnail.__dropOptions || {} };
        }

        const { wrapper } = ensureViewerElements();
        if (wrapper && (current === wrapper || (current.closest && current.closest('#viewer-media-wrapper')))) {
            return { element: wrapper, options: { isViewer: true } };
        }

        return null;
    }

    function setActiveDropTarget(candidate) {
        if (candidate && globalDropState.activeTarget === candidate.element) {
            return;
        }

        if (globalDropState.activeTarget && globalDropState.activeTarget !== candidate?.element) {
            globalDropState.activeTarget.classList.remove('dropzone-hover');
        }

        globalDropState.activeTarget = candidate ? candidate.element : null;
        globalDropState.options = candidate ? candidate.options || {} : null;

        if (globalDropState.activeTarget) {
            globalDropState.activeTarget.classList.add('dropzone-hover');
        }
    }

    function clearActiveDropTarget() {
        if (globalDropState.hoverTimeout) {
            clearTimeout(globalDropState.hoverTimeout);
            globalDropState.hoverTimeout = null;
        }

        if (globalDropState.activeTarget) {
            globalDropState.activeTarget.classList.remove('dropzone-hover');
        }

        globalDropState.activeTarget = null;
        globalDropState.options = null;
    }

    document.addEventListener('dragover', (event) => {
        if (!eventHasFiles(event)) {
            return;
        }

        const candidate = findDropTargetInfo(event.target) || (globalDropState.activeTarget ? { element: globalDropState.activeTarget, options: globalDropState.options } : null);
        if (!candidate) {
            clearActiveDropTarget();
            return;
        }

        event.preventDefault();
        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'copy';
        }

        setActiveDropTarget(candidate);
    });

    document.addEventListener('dragenter', (event) => {
        if (!eventHasFiles(event)) {
            return;
        }

        const candidate = findDropTargetInfo(event.target);
        if (candidate) {
            setActiveDropTarget(candidate);
        }
    });

    document.addEventListener('dragleave', (event) => {
        if (!eventHasFiles(event)) {
            return;
        }

        const related = event.relatedTarget;
        if (related && (related.closest && (related.closest('.document-thumbnail') || related.closest('#viewer-media-wrapper')))) {
            return;
        }

        if (globalDropState.hoverTimeout) {
            clearTimeout(globalDropState.hoverTimeout);
        }

        globalDropState.hoverTimeout = setTimeout(() => {
            clearActiveDropTarget();
        }, 50);
    });

    document.addEventListener('drop', (event) => {
        if (!eventHasFiles(event)) {
            return;
        }

        event.preventDefault();

        const candidate = findDropTargetInfo(event.target) || (globalDropState.activeTarget ? { element: globalDropState.activeTarget, options: globalDropState.options } : null);
        clearActiveDropTarget();

        if (!candidate) {
            return;
        }

        handleDocumentDrop(event, candidate.element, candidate.options || {});
    });

    function showDropUploadingState(element, isUploading) {
        if (!element) {
            return;
        }

        element.classList.toggle('dropzone-uploading', !!isUploading);

        if (element === viewerWrapperElement) {
            element.classList.toggle('dropzone-hover', false);
        }
    }

    async function uploadReplacementFileForPage(file, pageIndex, targetElement) {
        if (!replacePageEndpoint) {
            console.warn('Replace page endpoint not configured');
            return;
        }

        if (fileIndexingId === null || fileIndexingId === undefined) {
            Swal.fire({
                icon: 'error',
                title: 'Missing file reference',
                text: 'Cannot replace the document because the file reference is missing.'
            });
            return;
        }

        const pageInfo = pageData[pageIndex];
        if (!pageInfo || !pageInfo.scanning_id) {
            Swal.fire({
                icon: 'error',
                title: 'Replacement blocked',
                text: 'This page is missing a scanning reference. Upload is not allowed.'
            });
            return;
        }

        const statusMessage = `Replacing "${file.name}"…`;
        updateFolderStatus(statusMessage, { revert: false });
        showDropUploadingState(targetElement, true);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('file_indexing_id', fileIndexingId);
        formData.append('scanning_id', pageInfo.scanning_id);
        formData.append('page_index', pageIndex);
        formData.append('page_number', pageInfo.page_number || pageIndex + 1);

        const csrfToken = getCsrfToken();
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        try {
            const response = await fetch(replacePageEndpoint, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: formData
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result?.success) {
                throw new Error(result?.message || 'Failed to replace the document.');
            }

            applyReplacementResult(pageIndex, result.document || {});
            updateFolderStatus('Replacement uploaded successfully.', { timeout: 2200 });

            if (Swal && Swal.fire) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Page updated',
                    text: `Page ${pageIndex + 1} now uses the new file.`,
                    timer: 2600,
                    showConfirmButton: false
                });
            }

            return true;
        } catch (error) {
            console.error('Replacement upload failed', error);
            updateFolderStatus(error.message || 'Replacement failed.');

            if (Swal && Swal.fire) {
                Swal.fire({
                    icon: 'error',
                    title: 'Replacement failed',
                    text: error.message || 'Unable to replace the selected page.'
                });
            }

            return false;
        } finally {
            showDropUploadingState(targetElement, false);
        }
    }

    function applyReplacementResult(pageIndex, document = {}) {
        const pageInfo = pageData[pageIndex] || {};

        if (document.file_path) {
            pageInfo.file_path = document.file_path;
        }
        const newUrl = document.url || document.file_url;
        if (newUrl) {
            pageInfo.url = newUrl;
        }
        if (document.file_extension) {
            pageInfo.file_extension = document.file_extension;
            pageInfo.type = document.file_extension.toLowerCase() === 'pdf' ? 'pdf' : 'image';
        }
        if (document.display_name) {
            pageInfo.display_name = document.display_name;
        }

        pageData[pageIndex] = pageInfo;
        if (pageInfo.scanning_id) {
            pageDataMap[pageInfo.scanning_id] = pageInfo;
        }

        const thumbnail = document.querySelector(`.document-thumbnail[data-page-index="${pageIndex}"]`);
        if (thumbnail) {
            if (document.file_path) {
                thumbnail.dataset.filePath = document.file_path;
            }
            if (newUrl) {
                thumbnail.dataset.fileUrl = newUrl;
            }
            if (document.file_extension) {
                thumbnail.dataset.fileExtension = document.file_extension.toUpperCase();
                const extBadge = thumbnail.querySelector('.folder-extension-badge');
                if (extBadge) {
                    extBadge.textContent = document.file_extension.toUpperCase();
                }
            }
            if (pageInfo.type) {
                thumbnail.dataset.type = pageInfo.type;
            }
            if (document.display_name) {
                thumbnail.dataset.displayName = document.display_name;
                const nameEl = thumbnail.querySelector('.folder-item-name');
                if (nameEl) {
                    nameEl.textContent = document.display_name;
                }
            }

            const previewImage = thumbnail.querySelector('img');
            const pdfIcon = thumbnail.querySelector('.folder-icon.pdf-icon');
            const imageFallback = thumbnail.querySelector('.folder-icon.image-fallback');
            const isPdf = (pageInfo.file_extension || '').toLowerCase() === 'pdf';

            if (isPdf) {
                if (previewImage) {
                    previewImage.style.display = 'none';
                }
                if (pdfIcon) {
                    pdfIcon.style.display = 'flex';
                }
                if (imageFallback) {
                    imageFallback.style.display = 'none';
                }
            } else {
                const imageSource = pageInfo.url ? `${pageInfo.url}${pageInfo.url.includes('?') ? '&' : '?'}t=${Date.now()}` : null;

                let effectivePreview = previewImage;
                if (!effectivePreview && imageSource) {
                    const previewContainer = thumbnail.querySelector('.folder-item-preview');
                    if (previewContainer) {
                        const createdImage = document.createElement('img');
                        createdImage.src = imageSource;
                        createdImage.alt = pageInfo.display_name || 'Document preview';
                        createdImage.loading = 'lazy';
                        createdImage.className = 'folder-generated-preview';
                        createdImage.onerror = function () {
                            this.style.display = 'none';
                            if (imageFallback) {
                                imageFallback.style.display = 'flex';
                            }
                        };
                        previewContainer.insertBefore(createdImage, previewContainer.firstChild);
                        effectivePreview = createdImage;
                    }
                }

                if (effectivePreview) {
                    if (imageSource) {
                        effectivePreview.src = imageSource;
                        effectivePreview.style.display = '';
                    }
                }

                if (pdfIcon) {
                    pdfIcon.style.display = 'none';
                }
                if (imageFallback) {
                    imageFallback.style.display = imageSource ? 'none' : 'flex';
                }
            }
        }

        if (currentPageIndex === pageIndex) {
            renderPageInViewer(pageIndex, { focusViewer: true });
        }
    }

    function setupDocumentDropHandlers() {
        const grid = document.getElementById('folder-grid');
        if (grid) {
            grid.querySelectorAll('.document-thumbnail').forEach(thumbnail => {
                registerDropTarget(thumbnail);
            });
        }

        const { wrapper } = ensureViewerElements();
        if (wrapper) {
            registerDropTarget(wrapper, { isViewer: true });
        }
    }

    function filterThumbnailsByFolder(folderKey) {
        const thumbnails = document.querySelectorAll('.document-thumbnail');
        let visibleCount = 0;

        thumbnails.forEach(thumbnail => {
            const primaryKey = thumbnail.dataset.folderKey;
            const aliases = (thumbnail.dataset.groups || '').split(',');
            const matches = folderKey === 'all' || primaryKey === folderKey || aliases.includes(folderKey);

            thumbnail.classList.toggle('folder-hidden', !matches);
            if (matches) {
                visibleCount += 1;
            }
        });

        const descriptor = visibleCount === thumbnails.length ? 'All documents visible' : `${visibleCount} document${visibleCount === 1 ? '' : 's'} visible`;
        updateFolderStatus(descriptor, { timeout: 1800 });
    }

    function applyFolderSort(sortType) {
        const grid = document.getElementById('folder-grid');
        if (!grid) {
            return;
        }

        const items = Array.from(grid.querySelectorAll('.document-thumbnail'));
        const sorted = items.slice();

        switch (sortType) {
            case 'alpha':
                sorted.sort((a, b) => (a.dataset.displayName || '').localeCompare(b.dataset.displayName || '', undefined, { sensitivity: 'base' }));
                break;
            case 'recent':
                sorted.sort((a, b) => {
                    const dateA = new Date(a.dataset.createdAt || 0).getTime();
                    const dateB = new Date(b.dataset.createdAt || 0).getTime();
                    return dateB - dateA;
                });
                break;
            case 'type':
                sorted.sort((a, b) => {
                    const typeCompare = (a.dataset.type || '').localeCompare(b.dataset.type || '', undefined, { sensitivity: 'base' });
                    if (typeCompare !== 0) {
                        return typeCompare;
                    }
                    return (a.dataset.fileExtension || '').localeCompare(b.dataset.fileExtension || '', undefined, { sensitivity: 'base' });
                });
                break;
            case 'custom':
            default:
                sorted.sort((a, b) => {
                    const orderA = parseInt(a.dataset.displayOrder || '0', 10);
                    const orderB = parseInt(b.dataset.displayOrder || '0', 10);
                    return orderA - orderB;
                });
                break;
        }

        sorted.forEach(item => grid.appendChild(item));
        updateFolderStatus(`Sorted by ${sortType === 'custom' ? 'custom order' : sortType}.`, { timeout: 1500 });
    }

    function initializeFolderUI() {
        const folderTree = document.querySelector('[data-folder-tree]');
        const folderNodes = folderTree ? Array.from(folderTree.querySelectorAll('.folder-node')) : [];
        const sortButtons = document.querySelectorAll('.folder-sort-btn');
        const grid = document.getElementById('folder-grid');

        const statusIndicator = document.getElementById('folder-status-indicator');
        if (statusIndicator && !folderStatusState.defaultText) {
            folderStatusState.defaultText = statusIndicator.textContent || '';
        }

        if (folderNodes.length) {
            folderNodes.forEach(node => {
                node.addEventListener('click', () => {
                    folderNodes.forEach(btn => btn.classList.remove('active'));
                    node.classList.add('active');
                    filterThumbnailsByFolder(node.dataset.folder || 'all');
                });
            });
        }

        if (sortButtons.length) {
            sortButtons.forEach(button => {
                button.addEventListener('click', () => {
                    sortButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    applyFolderSort(button.dataset.sort || 'custom');
                });
            });
        }

        if (grid) {
            grid.querySelectorAll('.document-thumbnail').forEach(thumbnail => {
                thumbnail.addEventListener('dragstart', () => {
                    isDraggingCard = true;
                    thumbnail.classList.add('dragging');
                });
                thumbnail.addEventListener('dragend', () => {
                    isDraggingCard = false;
                    thumbnail.classList.remove('dragging');
                });
            });
        }

        setupDocumentDropHandlers();
    }

    function updateBookletUI() {
        // Update booklet-related UI components
        console.log('Booklet UI updated');
    }

    function updateThumbnailClickBehavior() {
        // Update thumbnail click behavior
        console.log('Thumbnail click behavior updated');
    }

    function navigateToPage(pageIndex) {
        // Navigate to specific page
        currentPageIndex = pageIndex;
        console.log('Navigated to page:', pageIndex + 1);

        renderPageInViewer(pageIndex, { focusViewer: true });
        
        // Update page counter display
        const pageCounter = document.getElementById('page-counter');
        if (pageCounter) {
            pageCounter.textContent = `Page ${pageIndex + 1} of ${totalPages}`;
        }
        
        // Update navigation buttons
        const prevBtn = document.getElementById('prev-page');
        const nextBtn = document.getElementById('next-page');
        if (prevBtn) prevBtn.disabled = pageIndex === 0;
        if (nextBtn) nextBtn.disabled = pageIndex >= totalPages - 1;
        
        // Show/hide page forms
        document.querySelectorAll('.page-form').forEach((form, index) => {
            if (index === pageIndex) {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        });
        
        // Update thumbnails active state
        document.querySelectorAll('.document-thumbnail').forEach((thumb, index) => {
            if (index === pageIndex) {
                thumb.classList.add('active');
            } else {
                thumb.classList.remove('active');
            }
        });
    }

    // Note: saveCurrentPage function is implemented above at line 1304

    // Get current page index
    function getCurrentPageIndex() {
        return currentPageIndex;
    }

    // Initialize existing classifications from server data
    function initializeExistingClassifications() {
        document.querySelectorAll('.page-form').forEach((form, pageIndex) => {
            if (form.dataset.saved === '1') {
                const classification = populateFormWithClassification(pageIndex);
                if (classification) {
                    updateFormForPage(pageIndex);
                }
            }
        });
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Initializing page typing system...');
        
        // Set default data first
        setDefaultPageTypingData();
        
        // Initialize existing classifications
        initializeExistingClassifications();

        // Load data from backend
        loadPageTypingData();
        
        // Setup dropdown change listeners for "Other" option handling
        setupDropdownListeners();

        // Initialize viewer and control states
        initializeFolderUI();
        updateMultiSelectUI();
        updateBookletUI();
        updateThumbnailClickBehavior();
        renderPageInViewer(currentPageIndex);
        initializeViewerTools();
        
        // Recheck all dropdowns after initialization to show "other" inputs if needed
        setTimeout(() => {
            recheckAllDropdowns();
        }, 500);

        // Set up navigation
        document.getElementById('prev-page')?.addEventListener('click', function() {
            if (currentPageIndex > 0) {
                navigateToPage(currentPageIndex - 1);
            }
        });

        document.getElementById('next-page')?.addEventListener('click', function() {
            if (currentPageIndex < totalPages - 1) {
                navigateToPage(currentPageIndex + 1);
            }
        });

        // Set up thumbnail navigation
        document.querySelectorAll('.document-thumbnail').forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                navigateToPage(pageIndex);
            });
        });

        // Set up save button
        document.getElementById('save-current-btn')?.addEventListener('click', function() {
            const button = this;
            button.disabled = true;
            
            saveCurrentPage().then(success => {
                if (success) {
                    // Auto-navigate to next page if not last page
                    if (currentPageIndex < totalPages - 1) {
                        setTimeout(() => {
                            navigateToPage(currentPageIndex + 1);
                        }, 500);
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'All Pages Completed!',
                            text: 'You have classified all pages in this document.',
                            confirmButtonColor: '#10b981'
                        });
                    }
                }
                button.disabled = false;
            }).catch(() => {
                button.disabled = false;
            });
        });
        
        // Debug button for refreshing reference codes
        document.getElementById('debug-refresh-codes-btn')?.addEventListener('click', function() {
            console.log('Debug: Refreshing all reference codes...');
            forceUpdateAllReferenceCodes();
        });

        // Multi-Select Mode event listeners
        document.querySelector('.toggle-multi-select')?.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMultiSelectMode();
        });

        document.querySelector('.select-all-pages')?.addEventListener('click', function(e) {
            e.preventDefault();
            selectAllPages();
        });

        document.querySelector('.clear-selection')?.addEventListener('click', function(e) {
            e.preventDefault();
            clearSelection();
        });

        document.querySelector('.process-selected')?.addEventListener('click', function(e) {
            e.preventDefault();
            processMultiplePages();
        });

        // Booklet Management event listeners
        document.querySelector('.start-booklet')?.addEventListener('click', function(e) {
            e.preventDefault();
            if (state.bookletMode) {
                endBooklet();
            } else {
                startBooklet();
            }
        });

        document.querySelector('.end-booklet')?.addEventListener('click', function(e) {
            e.preventDefault();
            endBooklet();
        });

        // Initialize first page
        if (totalPages > 0) {
            navigateToPage(0);
            updateProgress();
            
            // Force reference code update for all forms after full initialization
            setTimeout(() => {
                console.log('Final force update of all reference codes...');
                forceUpdateAllReferenceCodes();
            }, 200);
        } else {
            updateProgress();
            showViewerMessage('file-search', 'No document pages available');
        }
    });
    
    // Force update all reference codes - use this when having issues
    function forceUpdateAllReferenceCodes() {
        console.log('Forcing update of all reference codes...');
        
        // Ensure we have data
        if (coverTypes.length === 0 || pageTypes.length === 0) {
            console.log('No data available, setting defaults...');
            setDefaultPageTypingData();
        }
        
        // Re-populate dropdowns
        initializeFormOptions();
        
        // Update all page codes
        document.querySelectorAll('.page-form').forEach(form => {
            const pageIndex = form.dataset.pageIndex;
            if (pageIndex !== undefined) {
                console.log('Force updating page code for pageIndex:', pageIndex);
                
                // Ensure cover type has a value
                const coverSelect = form.querySelector('.cover-type-select');
                if (coverSelect && !coverSelect.value && coverTypes.length > 0) {
                    coverSelect.value = coverTypes[0].id;
                    console.log('Set cover type to:', coverTypes[0].id);
                }
                
                // Ensure page type has a value  
                const pageSelect = form.querySelector('.page-type-select');
                if (pageSelect && !pageSelect.value && pageTypes.length > 0) {
                    pageSelect.value = pageTypes[0].id;
                    console.log('Set page type to:', pageTypes[0].id);
                }
                
                // Ensure serial number has a value
                const serialInput = form.querySelector('.serial-input');
                if (serialInput && !serialInput.value) {
                    serialInput.value = String(parseInt(pageIndex) + 1).padStart(2, '0');
                    console.log('Set serial number to:', serialInput.value);
                }
                
                // Check for special cases that require serial number = "0"
                updateSerialNumberForSpecialCases(pageIndex);
                
                // Update the page code
                updatePageCode(pageIndex);
            }
        });

 
    }

    // Expose optional init helper for debugging
    window.pageTyping = window.pageTyping || {};
    window.pageTyping.forceUpdateAllReferenceCodes = forceUpdateAllReferenceCodes;

})();

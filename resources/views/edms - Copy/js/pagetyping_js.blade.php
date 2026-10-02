<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    lucide.createIcons();
    
    // Configure PDF.js
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    
    // Store page data for easy access  
    const pageData = @json($allPages ?? []);
    const totalPages = pageData.length;
    const fileIndexingId = {{ $fileIndexing->id }};
    let currentPageIndex = 0;
    
    // Page classifications
    const savedPages = new Set();
    
    // State management
    let state = {
        isMultiSelectMode: false,
        selectedPages: new Set(),
        bookletMode: false,
        currentBooklet: null,
        bookletStartPage: null,
        bookletCounter: 'a',
        bookletPages: {},
        processedPages: {},
        typingState: {
            coverType: '1',
            pageType: null,
            pageTypeOthers: '',
            pageSubType: null,
            pageSubTypeOthers: '',
            serialNo: '01'
        }
    };

    // Data from backend
    let coverTypes = [];
    let pageTypes = [];
    let pageSubTypes = {};

    // Initialize with default data
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
            { id: '5', code: "LT", name: "Land Title" },
            { id: '6', code: "LEG", name: "Legal" },
            { id: '7', code: "PE", name: "Payment Evidence" },
            { id: '8', code: "REP", name: "Report" },
            { id: '9', code: "SUR", name: "Survey" },
            { id: '10', code: "MISC", name: "Miscellaneous" },
            { id: '11', code: "IMG", name: "Image" },
            { id: '12', code: "TP", name: "Town Planning" },
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
            ],
            '3': [
                { id: '7', code: "DGR", name: "Demand for Ground Rent" }, 
                { id: '34', code: "DN", name: "Demand Notice" },
                { id: 'others', code: "OTH", name: "Others" }
            ]
        };
    }

    // Load page typing data from backend
    async function loadPageTypingData() {
        try {
            console.log('Attempting to load page typing data from:', '{{ route("pagetyping.get-data") }}');
            
            const response = await fetch('{{ route("pagetyping.get-data") }}', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });
            
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP Error:', response.status, response.statusText);
                console.error('Error response body:', errorText.substring(0, 1000));
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            console.log('Raw response (first 200 chars):', responseText.substring(0, 200));
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response was not JSON. Full response:', responseText);
                throw new Error('Server returned HTML instead of JSON. Check authentication and route.');
            }
            
            if (data.success) {
                // Process cover types
                coverTypes = (data.coverTypes || []).map(ct => {
                    const { id, name, code } = ct;
                    if (!code) {
                        const nm = (name || '').toLowerCase();
                        code = nm.includes('front') ? 'FC' : nm.includes('back') ? 'BC' : 'CV';
                    }
                    return { id: id?.toString(), code, name };
                });

                // Process page types
                pageTypes = (data.pageTypes || []).map(pt => ({
                    id: (pt.id || pt.Id).toString(),
                    code: pt.code || pt.Code || 'PAGE',
                    name: pt.name || pt.Name || (pt.code || pt.Code || 'Page Type')
                }));
                pageTypes.push({ id: 'others', code: "OTH", name: "Others" });

                // Process page subtypes
                Object.keys(data.pageSubTypes || {}).forEach(ptId => {
                    const rawSubs = data.pageSubTypes[ptId];
                    if (Array.isArray(rawSubs)) {
                        pageSubTypes[ptId] = rawSubs.map(st => ({
                            id: (st.id || st.Id).toString(),
                            code: st.code || st.Code || 'SUB',
                            name: st.name || st.Name || (st.code || st.Code || 'SubType')
                        }));
                        pageSubTypes[ptId].push({ id: 'others', code: "OTH", name: "Others" });
                    }
                });

                console.log('Loaded page typing data:', { coverTypes, pageTypes, pageSubTypes });
                populateDropdowns();
            } else {
                console.warn('Backend data load failed, using defaults');
                setDefaultPageTypingData();
                populateDropdowns();
            }
        } catch (error) {
            console.error('Error loading page typing data:', error);
            setDefaultPageTypingData();
            populateDropdowns();
        }
    }

    // Populate all dropdowns
    function populateDropdowns() {
        console.log('populateDropdowns called with data:', { coverTypes, pageTypes });
        
        // Populate cover type dropdowns
        document.querySelectorAll('.cover-type-select').forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '<option value="">Select cover type</option>';
            coverTypes.forEach(ct => {
                const option = document.createElement('option');
                option.value = ct.id;
                option.textContent = `${ct.code} - ${ct.name}`;
                if (ct.id === currentValue) option.selected = true;
                select.appendChild(option);
            });
            
            // If no value is selected, default to the first cover type (Front Cover)
            if (!currentValue && coverTypes.length > 0) {
                select.value = coverTypes[0].id;
                // Trigger a change event to ensure the form processing knows about this default
                select.dispatchEvent(new Event('change', { bubbles: true }));
                console.log('Set default cover type:', coverTypes[0].id, 'for select:', select);
            }
        });

        // Populate page type dropdowns
        document.querySelectorAll('.page-type-select').forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '<option value="">Select page type</option>';
            pageTypes.forEach(pt => {
                const option = document.createElement('option');
                option.value = pt.id;
                option.textContent = `${pt.code} - ${pt.name}`;
                if (pt.id === currentValue) option.selected = true;
                select.appendChild(option);
            });
            
            // If no value is selected, default to the first page type
            if (!currentValue && pageTypes.length > 0) {
                select.value = pageTypes[0].id;
                // Trigger a change event to ensure the form processing knows about this default
                select.dispatchEvent(new Event('change', { bubbles: true }));
                console.log('Set default page type:', pageTypes[0].id, 'for select:', select);
            }
        });

        // Setup change handlers
        setupDropdownHandlers();
        
        // Initialize default values for empty serial inputs
        document.querySelectorAll('.serial-input').forEach((input, index) => {
            if (!input.value || input.value.trim() === '') {
                input.value = String(index + 1).padStart(2, '0');
                console.log('Set default serial number:', input.value, 'for input:', input);
            }
        });
        
        // Update page codes for all forms after population
        document.querySelectorAll('.page-form').forEach(form => {
            const pageIndex = form.dataset.pageIndex;
            if (pageIndex !== undefined) {
                console.log('Updating page code for form with pageIndex:', pageIndex);
                // Check for special cases that require serial number = "0"
                updateSerialNumberForSpecialCases(pageIndex);
                updatePageCode(pageIndex);
                updatePageSubtypes(pageIndex);
            }
        });
        
        // Force update after a brief delay to ensure DOM is settled
        setTimeout(() => {
            console.log('Force updating page codes after delay...');
            document.querySelectorAll('.page-form').forEach(form => {
                const pageIndex = form.dataset.pageIndex;
                if (pageIndex !== undefined) {
                    updateSerialNumberForSpecialCases(pageIndex);
                    updatePageCode(pageIndex);
                }
            });
        }, 100);
    }

    // Setup dropdown change handlers
    function setupDropdownHandlers() {
        // Page type change handler
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('page-type-select')) {
                const pageIndex = e.target.dataset.pageIndex;
                console.log('Page type changed to:', e.target.value, 'for pageIndex:', pageIndex);
                updatePageSubtypes(pageIndex);
                toggleOthersInput(e.target, '.page-type-others-container');
                updateSerialNumberForSpecialCases(pageIndex);
                updatePageCode(pageIndex);
            }
            
            if (e.target.classList.contains('page-subtype-select')) {
                const pageIndex = e.target.dataset.pageIndex;
                toggleOthersInput(e.target, '.page-subtype-others-container');
                updatePageCode(pageIndex);
            }
            
            if (e.target.classList.contains('cover-type-select')) {
                const pageIndex = e.target.dataset.pageIndex || currentPageIndex;
                console.log('Cover type changed to:', e.target.value, 'for pageIndex:', pageIndex);
                updateSerialNumberForSpecialCases(pageIndex);
                updatePageCode(pageIndex);
            }
            
            if (e.target.classList.contains('serial-input')) {
                const pageIndex = e.target.dataset.pageIndex || currentPageIndex;
                updatePageCode(pageIndex);
            }
        });
        
        // Also listen for input events on serial number and others fields
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('serial-input')) {
                const pageIndex = e.target.dataset.pageIndex || currentPageIndex;
                
                // Only check if serial should be locked if the field is currently read-only
                // This prevents interfering with normal user input
                if (e.target.readOnly) {
                    const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
                    if (form && shouldSerialBeZero(form)) {
                        // Reset to "0" if user somehow managed to change a locked field
                        e.target.value = '0';
                        return;
                    }
                }
                
                updatePageCode(pageIndex);
            }
            
            if (e.target.classList.contains('page-type-others-input') || 
                e.target.classList.contains('page-subtype-others-input')) {
                const pageIndex = e.target.dataset.pageIndex || currentPageIndex;
                updatePageCode(pageIndex);
            }
        });
    }

    // Update serial number for special cases
    function updateSerialNumberForSpecialCases(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return;
        
        const coverTypeSelect = form.querySelector('.cover-type-select');
        const pageTypeSelect = form.querySelector('.page-type-select');
        const serialInput = form.querySelector('.serial-input');
        
        if (!coverTypeSelect || !pageTypeSelect || !serialInput) return;
        
        // Make sure we have loaded the dropdown data
        if (coverTypes.length === 0 || pageTypes.length === 0) {
            return;
        }
        
        const coverTypeValue = coverTypeSelect.value;
        const pageTypeValue = pageTypeSelect.value;
        
        // Don't proceed if required values are not selected
        if (!coverTypeValue || !pageTypeValue) return;
        
        // Get cover type and page type info for comparison
        const coverType = getCoverTypeById(coverTypeValue);
        const pageType = pageTypes.find(pt => pt.id === pageTypeValue);
        
        if (!coverType || !pageType) return;
        
        const coverCode = coverType.code;
        const pageTypeCode = pageType.code;
        
        console.log('Checking special cases with coverCode:', coverCode, 'pageTypeCode:', pageTypeCode);
        
        // Check for special cases where serial number should be "0"
        let shouldSetToZero = false;
        
        // SPECIAL CASE 1: Front Cover (FC) + File Cover (FC) = 0
        if (coverCode === 'FC' && pageTypeCode === 'FC') {
            shouldSetToZero = true;
            console.log('Matched special case 1: Front Cover + File Cover');
        }
        
        // SPECIAL CASE 2: Back Cover (BC) + File Back Page (FBP) = 0
        // Only check for direct FBP page type
        if (coverCode === 'BC' && pageTypeCode === 'FBP') {
            shouldSetToZero = true;
            console.log('Matched special case 2: Back Cover + File Back Page');
        }
        
        console.log('shouldSetToZero:', shouldSetToZero);
        
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
            
            console.log(`Auto-set serial number to 0 for Cover Type: ${coverType.name} (${coverCode}) + Page Type: ${pageType.name} (${pageTypeCode})`);
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
                const pageIndex = parseInt(form.dataset.pageIndex);
                
                // Try to use existing data first
                const existingData = state.processedPages && state.processedPages[pageIndex];
                if (existingData && existingData.serialNo && existingData.serialNo !== '0') {
                    serialInput.value = existingData.serialNo;
                    console.log('Restored serial number from existing data:', serialInput.value);
                } else {
                    // Calculate a default serial number
                    const defaultSerial = String(pageIndex + 1).padStart(2, '0');
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
        const pageType = pageTypes.find(pt => pt.id === pageTypeValue);
        
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
            return pageSubTypes[pageTypeId].find(st => st.id === subtypeId);
        }
        return null;
    }

    // Update page subtypes based on selected page type
    function updatePageSubtypes(pageIndex) {
        const subtypeSelect = document.querySelector(`.page-subtype-select[data-page-index="${pageIndex}"]`);
        const pageTypeSelect = document.querySelector(`.page-type-select[data-page-index="${pageIndex}"]`);
        
        if (!subtypeSelect || !pageTypeSelect) return;

        const selectedPageType = pageTypeSelect.value;
        subtypeSelect.innerHTML = '<option value="">Select page subtype</option>';

        if (selectedPageType && pageSubTypes[selectedPageType]) {
            pageSubTypes[selectedPageType].forEach(st => {
                const option = document.createElement('option');
                option.value = st.id;
                option.textContent = `${st.code} - ${st.name}`;
                subtypeSelect.appendChild(option);
            });
        }
    }

    // Toggle others input field
    function toggleOthersInput(selectElement, containerSelector) {
        const form = selectElement.closest('.page-form');
        const container = form.querySelector(containerSelector);
        
        if (container) {
            if (selectElement.value === 'others') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
                const input = container.querySelector('input');
                if (input) input.value = '';
            }
        }
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

    // Helper functions
    function getCoverTypeById(id) {
        return coverTypes.find(ct => ct.id === id);
    }

    function getPageTypeCode(pageTypeId, othersValue) {
        if (pageTypeId === 'others' && othersValue) {
            return othersValue.substring(0, 3).toUpperCase();
        }
        const pageType = pageTypes.find(pt => pt.id === pageTypeId);
        return pageType ? pageType.code : 'XX';
    }

    function getPageSubTypeCode(pageTypeId, subtypeId, othersValue) {
        if (subtypeId === 'others' && othersValue) {
            return othersValue.substring(0, 3).toUpperCase();
        }
        if (pageSubTypes[pageTypeId]) {
            const subtype = pageSubTypes[pageTypeId].find(st => st.id === subtypeId);
            return subtype ? subtype.code : '';
        }
        return '';
    }

    // Navigation
    function navigateToPage(pageIndex) {
        if (pageIndex < 0 || pageIndex >= totalPages) return;
        
        currentPageIndex = pageIndex;
        
        // Hide all page forms
        document.querySelectorAll('.page-form').forEach(form => {
            form.classList.add('hidden');
            form.classList.remove('active');
        });
        
        // Show current page form
        const currentForm = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (currentForm) {
            currentForm.classList.remove('hidden');
            currentForm.classList.add('active');
        }
        
        // Update thumbnails
        document.querySelectorAll('.document-thumbnail').forEach(thumb => {
            thumb.classList.remove('active');
        });
        
        const currentThumb = document.querySelector(`.document-thumbnail[data-page-index="${pageIndex}"]`);
        if (currentThumb) {
            currentThumb.classList.add('active');
        }
        
        // Update counter
        const counter = document.getElementById('page-counter');
        if (counter) {
            counter.textContent = `${pageIndex + 1} of ${totalPages}`;
        }
        
        // Load page in viewer
        loadPageInViewer(pageIndex);
        
        // Update form state
        updateFormForPage(pageIndex);
    }

    // Load page in main viewer
    function loadPageInViewer(pageIndex) {
        const viewer = document.getElementById('document-viewer');
        const pageInfo = pageData[pageIndex];
        
        if (!viewer || !pageInfo) return;
        
        if (pageInfo.type === 'pdf_page') {
            loadPDFPageInMainViewer(pageInfo.file_path, pageInfo.page_number);
        } else {
            loadImageInMainViewer(pageInfo.file_path);
        }
    }

    // Load PDF page in main viewer
    function loadPDFPageInMainViewer(pdfPath, pageNumber) {
        const viewer = document.getElementById('document-viewer');
        const url = '{{ asset("storage/app/public/") }}/' + pdfPath;
        
        viewer.innerHTML = '<div class="viewer-placeholder"><div class="spinner"></div><p>Loading PDF...</p></div>';
        
        pdfjsLib.getDocument(url).promise.then(function(pdf) {
            return pdf.getPage(pageNumber);
        }).then(function(page) {
            const scale = 1.5;
            const viewport = page.getViewport({ scale: scale });
            
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            canvas.style.maxWidth = '100%';
            canvas.style.height = 'auto';
            
            const renderContext = {
                canvasContext: context,
                viewport: viewport
            };
            
            return page.render(renderContext).promise.then(() => {
                viewer.innerHTML = '';
                viewer.appendChild(canvas);
            });
        }).catch(function(error) {
            console.error('Error loading PDF:', error);
            viewer.innerHTML = '<div class="viewer-placeholder"><i data-lucide="file-x"></i><p>Error loading PDF</p></div>';
            lucide.createIcons();
        });
    }

    // Load image in main viewer
    function loadImageInMainViewer(imagePath) {
        const viewer = document.getElementById('document-viewer');
        const url = '{{ asset("storage/app/public/") }}/' + imagePath;
        
        const img = document.createElement('img');
        img.src = url;
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        img.onload = function() {
            viewer.innerHTML = '';
            viewer.appendChild(img);
        };
        img.onerror = function() {
            viewer.innerHTML = '<div class="viewer-placeholder"><i data-lucide="image-off"></i><p>Error loading image</p></div>';
            lucide.createIcons();
        };
    }

    // Update form for current page
    function updateFormForPage(pageIndex) {
        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
        if (!form) return;

        // Set values from existing data
        const existingData = state.processedPages[pageIndex];
        if (existingData) {
            const coverSelect = form.querySelector('.cover-type-select');
            const pageTypeSelect = form.querySelector('.page-type-select');
            const subtypeSelect = form.querySelector('.page-subtype-select');
            const serialInput = form.querySelector('.serial-input');

            if (coverSelect) coverSelect.value = existingData.coverType || '1';
            if (pageTypeSelect) pageTypeSelect.value = existingData.pageType || '';
            if (subtypeSelect) subtypeSelect.value = existingData.pageSubType || '';
            if (serialInput) serialInput.value = existingData.serialNo || '01';

            // Update subtypes for the selected page type
            updatePageSubtypes(pageIndex);
            
            // Check if serial number should be updated for special cases
            updateSerialNumberForSpecialCases(pageIndex);
            
            // Update page code
            updatePageCode(pageIndex);
        } else {
            // Set defaults for new page
            const serialInput = form.querySelector('.serial-input');
            if (serialInput && !serialInput.value) {
                serialInput.value = calculateNextSerialNumber();
            }
            
            // Check if serial number should be updated for special cases even for new pages
            updateSerialNumberForSpecialCases(pageIndex);
        }
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
        const pageTypingData = {
            file_indexing_id: fileIndexingId,
            scanning_id: pageInfo.scanning_id,
            page_number: pageInfo.page_number,
            cover_type_id: parseInt(coverTypeSelect.value),
            page_type: pageTypeSelect.value,
            page_type_others: pageTypeSelect.value === 'others' ? (form.querySelector('.page-type-others-input')?.value || '') : null,
            page_subtype: pageSubtypeSelect.value === 'others' ? null : pageSubtypeSelect.value,
            page_subtype_others: pageSubtypeSelect.value === 'others' ? (form.querySelector('.page-subtype-others-input')?.value || '') : null,
            serial_number: serialInput.value,
            page_code: form.querySelector('.page-code-input').value,
            file_path: pageInfo.file_path
        };

        // Save to backend
        return savePageTypingToBackend(pageTypingData).then(success => {
            if (success) {
                savedPages.add(currentPageIndex);
                form.dataset.saved = '1';
                
                // Store in processed pages
                state.processedPages[currentPageIndex] = {
                    coverType: coverTypeSelect.value,
                    pageType: pageTypeSelect.value,
                    pageTypeOthers: pageTypingData.page_type_others,
                    pageSubType: pageSubtypeSelect.value,
                    pageSubTypeOthers: pageTypingData.page_subtype_others,
                    serialNo: serialInput.value,
                    page_code: pageTypingData.page_code
                };

                updateProgress();
                
                // Update page status indicator
                const statusIndicator = document.querySelector(`.page-status[data-page-index="${currentPageIndex}"]`);
                if (statusIndicator) {
                    statusIndicator.classList.add('completed');
                    statusIndicator.innerHTML = '<i data-lucide="check" style="width: 0.75rem; height: 0.75rem;"></i>';
                    lucide.createIcons();
                }
                
                return true;
            }
            return false;
        });
    }

    // Save to backend
    async function savePageTypingToBackend(pageTypingData) {
        try {
            const response = await fetch('{{ route("pagetyping.save-single") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(pageTypingData)
            });

            const result = await response.json();
            
            if (result.success) {
                return true;
            } else {
                console.error('Backend error:', result.message);
                Swal.fire({
                    icon: 'error',
                    title: 'Save Failed',
                    text: result.message || 'Failed to save page classification.',
                    confirmButtonColor: '#dc3545'
                });
                return false;
            }
        } catch (error) {
            console.error('Network error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Failed to connect to server. Please check your connection.',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
    }

    // Update progress
    function updateProgress() {
        const completed = Object.keys(state.processedPages).length;
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
            if (!thumbnail.querySelector('.page-select-checkbox')) {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'page-select-checkbox';
                checkbox.dataset.pageIndex = thumbnail.dataset.pageIndex;
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
            const clonedThumbnail = thumbnail.cloneNode(true);
            thumbnail.parentNode.replaceChild(clonedThumbnail, thumbnail);
            
            clonedThumbnail.addEventListener('click', function(e) {
                const pageIndex = parseInt(this.dataset.pageIndex);
                
                if (state.isMultiSelectMode) {
                    e.preventDefault();
                    const checkbox = this.querySelector('.page-select-checkbox');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                } else {
                    navigateToPage(pageIndex);
                }
            });
        });
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

        if (!coverType || !pageType) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Required Fields',
                text: 'Please fill in cover type and page type before processing multiple pages.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        // Show progress
        Swal.fire({
            title: 'Processing Selected Pages',
            html: `Processing 0 of ${state.selectedPages.size} pages...`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        let processedCount = 0;
        let failedCount = 0;
        const selectedPagesArray = Array.from(state.selectedPages).sort((a, b) => a - b);

        for (const pageIndex of selectedPagesArray) {
            try {
                const pageInfo = pageData[pageIndex];
                const serialNumber = state.bookletMode 
                    ? state.bookletStartPage + String.fromCharCode('a'.charCodeAt(0) + processedCount)
                    : (parseInt(calculateNextSerialNumber()) + processedCount).toString().padStart(2, '0');

                const pageCode = generatePageCode(coverType, pageType, pageSubtype, serialNumber);

                const pageTypingData = {
                    file_indexing_id: fileIndexingId,
                    scanning_id: pageInfo.scanning_id,
                    page_number: pageInfo.page_number,
                    cover_type_id: parseInt(coverType),
                    page_type: pageType,
                    page_subtype: pageSubtype,
                    serial_number: serialNumber,
                    page_code: pageCode,
                    file_path: pageInfo.file_path
                };

                const success = await savePageTypingToBackend(pageTypingData);
                
                if (success) {
                    processedCount++;
                    savedPages.add(pageIndex);
                    
                    state.processedPages[pageIndex] = {
                        coverType: coverType,
                        pageType: pageType,
                        pageSubType: pageSubtype,
                        serialNo: serialNumber,
                        page_code: pageCode
                    };

                    // Update thumbnail status
                    const thumbnail = document.querySelector(`.document-thumbnail[data-page-index="${pageIndex}"]`);
                    if (thumbnail) {
                        thumbnail.classList.add('completed');
                    }
                } else {
                    failedCount++;
                }

                // Update progress
                Swal.update({
                    html: `Processing ${processedCount + failedCount} of ${state.selectedPages.size} pages...`
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
            
            Swal.fire({
                icon: 'success',
                title: 'Processing Complete',
                text: `Successfully processed ${processedCount} pages${failedCount > 0 ? ` (${failedCount} failed)` : ''}.`,
                confirmButtonColor: '#10b981'
            });
            
            // Exit multi-select mode
            toggleMultiSelectMode();
        }
    }

    function generatePageCode(coverType, pageType, pageSubtype, serialNumber) {
        const coverCode = getCoverTypeById(coverType)?.code || 'XX';
        const pageCode = getPageTypeCode(pageType);
        const subtypeCode = pageSubtype ? getPageSubTypeCode(pageType, pageSubtype) : '';
        
        return `${coverCode}-${pageCode}${subtypeCode ? '-' + subtypeCode : ''}-${serialNumber}`;
    }

    // Helper functions for codes
    function getCoverTypeById(id) {
        return coverTypes.find(ct => ct.id == id);
    }

    function calculateNextSerialNumber() {
        // Get the highest serial number used
        let maxSerial = 0;
        Object.values(state.processedPages).forEach(page => {
            const serial = parseInt(page.serialNo);
            if (!isNaN(serial) && serial > maxSerial) {
                maxSerial = serial;
            }
        });
        
        return (maxSerial + 1).toString().padStart(2, '0');
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Load data first
        loadPageTypingData();
        
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
        populateDropdowns();
        
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
</script>
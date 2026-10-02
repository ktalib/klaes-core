<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<!-- SweetAlert2 for better user feedback -->
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
    
    // Enhanced page classifications with full feature support
    const pageClassifications = {};
    
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

    // Cover types, page types and subtypes - loaded from backend
    let coverTypes = [];
    let pageTypes = [];
    let pageSubTypes = {};

    // Initialize with default data, then load from backend
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
            ],
            '4': [
                { id: '8', code: "AL", name: "Acknowledgment Letter" }, 
                { id: '9', code: "ASR", name: "Application Submission" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '5': [
                { id: '5', code: "CO", name: "Certificate of Occupancy" }, 
                { id: '6', code: "SP", name: "Survey Plan" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '6': [
                { id: '18', code: "AGR", name: "Agreement" }, 
                { id: '44', code: "POA", name: "Power of Attorney" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '7': [
                { id: '19', code: "AOF", name: "Assessment of Fees" }, 
                { id: '20', code: "BT", name: "Bank Teller" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '8': [
                { id: '23', code: "RR", name: "Reinspection Report" }, 
                { id: '65', code: "IPVR", name: "Inspection Report" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '9': [
                { id: '24', code: "TDP", name: "Title Deed Plan" }, 
                { id: '25', code: "SP", name: "Survey Plan" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '10': [
                { id: '27', code: "MISC", name: "Miscellaneous" }, 
                { id: '43', code: "OC", name: "Other Certificates" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '11': [
                { id: '28', code: "PP", name: "Passport" },
                { id: 'others', code: "OTH", name: "Others" }
            ],
            '12': [
                { id: '135', code: "SKT", name: "Sketch" },
                { id: '141', code: "LP", name: "Location Plan" },
                { id: 'others', code: "OTH", name: "Others" }
            ]
        };
    }

    // Load data from backend with enhanced error handling
    async function loadPageTypingData() {
        try {
            const response = await fetch('{{ route("pagetyping.api.typing-data") }}');
            const data = await response.json();
            
            if (data.success) {
                // Normalize data from backend like in working implementation
                const rawCover = data.cover_types || [];
                coverTypes = rawCover.map(ct => {
                    const id = ct.id || ct.Id;
                    const name = ct.name || ct.Name || ct.title || ct.Title || 'Cover';
                    let code = ct.code || ct.Code;
                    if (!code) {
                        const nm = (name || '').toLowerCase();
                        if (nm.includes('front')) code = 'FC';
                        else if (nm.includes('back')) code = 'BC';
                        else code = (name || 'CV').split(/\s+/).map(w => w[0]).join('').substring(0,3).toUpperCase();
                    }
                    return { id: id?.toString(), code, name };
                });

                // Normalize page types and subtypes similar to working implementation
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
                if (Array.isArray(rawSubs)) {
                    const grouped = {};
                    rawSubs.forEach(st => {
                        const id = (st.id || st.Id).toString();
                        const code = st.code || st.Code || st.PageSubType || 'ST';
                        const name = st.name || st.Name || code;
                        let ptId = st.page_type_id || st.PageTypeId || st.pageTypeId;
                        
                        if (!ptId) {
                            const ptCode = st.PageType || st.page_type;
                            if (ptCode) {
                                const found = pageTypes.find(t => (t.code || '').toString().toLowerCase() === ptCode.toString().toLowerCase());
                                if (found) ptId = found.id;
                            }
                        }
                        
                        ptId = ptId?.toString();
                        if (!ptId) return;
                        
                        if (!grouped[ptId]) grouped[ptId] = [];
                        grouped[ptId].push({ id, code, name });
                    });
                    pageSubTypes = grouped;
                } else {
                    const grouped = {};
                    Object.keys(rawSubs || {}).forEach(ptId => {
                        const arr = rawSubs[ptId] || [];
                        grouped[ptId.toString()] = arr.map(st => ({
                            id: (st.id || st.Id).toString(),
                            code: st.code || st.Code || st.PageSubType || 'ST',
                            name: st.name || st.Name || (st.code || st.Code || 'SubType')
                        }));
                    });
                    pageSubTypes = grouped;
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

    // Helper functions from working implementation
    function getCoverTypeById(typeId) {
        return coverTypes.find(type => type.id.toString() === typeId.toString());
    }

    function getPageTypeById(typeId) {
        return pageTypes.find(type => type.id.toString() === typeId.toString());
    }

    function getPageSubTypeById(typeId, subTypeId) {
        return pageSubTypes[parseInt(typeId)]?.find(subType => subType.id.toString() === subTypeId.toString());
    }

    // Get page type code, handling "Others" case
    function getPageTypeCode(typeId, othersValue = null) {
        if (typeId === 'others') {
            if (state.typingState?.pageSubType === 'others') {
                return 'OTHER';
            }
            const customValue = othersValue || state.typingState?.pageTypeOthers || '';
            return customValue.substring(0, 4).toUpperCase() || 'OTH';
        }
        const pageType = getPageTypeById(typeId);
        return pageType?.code || 'XX';
    }

    // Get page subtype code, handling both regular subtypes and "Others" case
    function getPageSubTypeCode(typeId, subTypeId, othersValue = null) {
        if (subTypeId === 'others') {
            if (typeId === 'others') {
                return '';
            }
            const customValue = othersValue || state.typingState?.pageSubTypeOthers || '';
            return customValue.substring(0, 4).toUpperCase() || 'OTH';
        }
        const subType = getPageSubTypeById(typeId, subTypeId);
        return subType?.code || 'XX';
    }

    // Calculate next serial number with enhanced logic
    function calculateNextSerialNumber() {
        if (state.bookletMode) {
            return state.bookletStartPage + state.bookletCounter;
        }

        const existingSerials = Object.values(state.processedPages)
            .map(p => p.serialNo)
            .filter(s => s && !isNaN(parseInt(s.match(/^\d+/)?.[0])))
            .map(s => parseInt(s.match(/^\d+/)?.[0]))
            .sort((a, b) => a - b);

        const nextNum = existingSerials.length > 0 ? Math.max(...existingSerials) + 1 : currentPageIndex + 1;
        return nextNum.toString().padStart(2, '0');
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

        // Set existing values per form and trigger dependent updates
        document.querySelectorAll('.page-form').forEach(form => {
            const pageIndex = parseInt(form.dataset.pageIndex);
            const coverSelect = form.querySelector('.cover-type-select');
            const typeSelect = form.querySelector('.page-type-select');
            const subtypeSelect = form.querySelector('.page-subtype-select');
            const serialInput = form.querySelector('.serial-input');
            const typeOthersInput = form.querySelector('.page-type-others-input');
            const subtypeOthersInput = form.querySelector('.page-subtype-others-input');

            // Existing values from backend
            const existingCover = form.dataset.existingCoverType;
            const existingType = form.dataset.existingPageType;
            const existingSubtype = form.dataset.existingPageSubtype;
            const existingTypeOthers = form.dataset.existingPageTypeOthers;
            const existingSubtypeOthers = form.dataset.existingPageSubtypeOthers;

            if (existingCover) {
                coverSelect.value = existingCover.toString();
            }
            if (existingType) {
                typeSelect.value = existingType.toString();
                updatePageSubtypes(pageIndex, existingType.toString());
            }
            if (existingSubtype) {
                // Wait a tick to ensure subtypes populated
                setTimeout(() => {
                    subtypeSelect.value = existingSubtype.toString();
                    updatePageCodePreview(pageIndex);
                }, 0);
            }

            // Populate Others fields visibility and values
            const typeOthersContainer = form.querySelector('.page-type-others-container');
            const subtypeOthersContainer = form.querySelector('.page-subtype-others-container');
            if (existingType === 'others' && typeOthersContainer && typeOthersInput) {
                typeOthersContainer.style.display = 'block';
                typeOthersInput.value = existingTypeOthers || '';
            }
            if (existingSubtype === 'others' && subtypeOthersContainer && subtypeOthersInput) {
                subtypeOthersContainer.style.display = 'block';
                subtypeOthersInput.value = existingSubtypeOthers || '';
            }

            // Serial number: prefer existing; if missing, only set for current page to avoid duplicate serials
            if (pageIndex === currentPageIndex && !serialInput.value) {
                serialInput.value = calculateNextSerialNumber();
            }
        });
    }

    // Initialize page type change handlers
    function initializePageTypeHandlers() {
        // Cover type change handlers
        document.querySelectorAll('.cover-type-select').forEach(select => {
            select.addEventListener('change', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                if (pageIndex === currentPageIndex) {
                    state.typingState.coverType = this.value;
                    updatePageCodePreview(pageIndex);
                }
            });
        });

        // Page type change handlers
        document.querySelectorAll('.page-type-select').forEach(select => {
            select.addEventListener('change', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                const pageTypeId = this.value;
                
                if (pageIndex === currentPageIndex) {
                    state.typingState.pageType = pageTypeId;
                    state.typingState.pageTypeOthers = '';
                }

                // Handle "Others" option visibility
                const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
                const othersContainer = form?.querySelector(`.page-type-others-container`);
                if (othersContainer) {
                    othersContainer.style.display = pageTypeId === 'others' ? 'block' : 'none';
                }

                // Update subtypes for current page
                updatePageSubtypes(pageIndex, pageTypeId);
                updatePageCodePreview(pageIndex);
            });
        });

        // Page subtype change handlers
        document.querySelectorAll('.page-subtype-select').forEach(select => {
            select.addEventListener('change', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                const subTypeId = this.value;
                
                if (pageIndex === currentPageIndex) {
                    state.typingState.pageSubType = subTypeId;
                    state.typingState.pageSubTypeOthers = '';
                }

                // Handle "Others" option visibility
                const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
                const othersContainer = form?.querySelector(`.page-subtype-others-container`);
                if (othersContainer) {
                    othersContainer.style.display = subTypeId === 'others' ? 'block' : 'none';
                }

                updatePageCodePreview(pageIndex);
            });
        });

        // Serial number change handlers
        document.querySelectorAll('.serial-input').forEach(input => {
            input.addEventListener('input', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                if (pageIndex === currentPageIndex) {
                    state.typingState.serialNo = this.value;
                    updatePageCodePreview(pageIndex);
                }
            });
        });

        // Others input handlers
        document.querySelectorAll('.page-type-others-input').forEach(input => {
            input.addEventListener('input', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                if (pageIndex === currentPageIndex) {
                    state.typingState.pageTypeOthers = this.value;
                    updatePageCodePreview(pageIndex);
                }
            });
        });

        document.querySelectorAll('.page-subtype-others-input').forEach(input => {
            input.addEventListener('input', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                if (pageIndex === currentPageIndex) {
                    state.typingState.pageTypeOthers = this.value;
                    updatePageCodePreview(pageIndex);
                }
            });
                if (pageIndex === currentPageIndex) {
                    state.typingState.pageSubTypeOthers = this.value;
                    updatePageCodePreview(pageIndex);
                }
                const pageIndex = parseInt(this.dataset.pageIndex);
                if (pageIndex === currentPageIndex) {
                    state.typingState.pageSubTypeOthers = this.value;
                    updatePageCodePreview(pageIndex);
                }
            });
        });
    }

    // Update page subtypes based on selected page type
    function updatePageSubtypes(pageIndex, pageTypeId) {
        const subtypeSelect = document.querySelector(`.page-subtype-select[data-page-index="${pageIndex}"]`);
        if (!subtypeSelect) return;

        subtypeSelect.innerHTML = '<option value="">Select page subtype</option>';
        
        if (pageTypeId && pageSubTypes[pageTypeId]) {
            pageSubTypes[pageTypeId].forEach(subtype => {
                const option = document.createElement('option');
                option.value = subtype.id;
                option.textContent = `${subtype.name} (${subtype.code})`;
                subtypeSelect.appendChild(option);
            });
        }
    }

    // Update page code preview
    function updatePageCodePreview(pageIndex) {
        const previewElement = document.getElementById(`page-code-preview-${pageIndex}`);
        const codeInput = document.querySelector(`.page-code-input[data-page-index="${pageIndex}"]`);
        
        if (!previewElement) return;

        const coverType = getCoverTypeById(state.typingState.coverType);
        const pageTypeCode = getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers);
        const subTypeCode = getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers);
        
        const pageCode = `${coverType?.code || 'XX'}-${pageTypeCode}${subTypeCode ? '-' + subTypeCode : ''}-${state.typingState.serialNo || '01'}`;
        
        previewElement.textContent = pageCode;
        if (codeInput) {
            codeInput.value = pageCode;
        }
    }

    // Multi-select and booklet management functions
    function toggleMultiSelectMode() {
        state.isMultiSelectMode = !state.isMultiSelectMode;
        updateMultiSelectUI();
    }

    function updateMultiSelectUI() {
        const toggleBtn = document.querySelector('.toggle-multi-select');
        const multiSelectControls = document.querySelector('.multi-select-controls');
        const multiSelectElements = document.querySelectorAll('.multi-select-only');
        
        if (state.isMultiSelectMode) {
            toggleBtn.textContent = 'Exit Multi-Select';
            toggleBtn.style.backgroundColor = '#ef4444';
            toggleBtn.style.borderColor = '#ef4444';
            toggleBtn.style.color = 'white';
            if (multiSelectControls) multiSelectControls.style.display = 'block';
            multiSelectElements.forEach(el => el.style.display = 'block');
        } else {
            toggleBtn.innerHTML = '<i data-lucide="check-square" style="width: 1rem; height: 1rem; margin-right: 0.5rem;"></i>Multi-Select Mode';
            toggleBtn.style.backgroundColor = '';
            toggleBtn.style.borderColor = '#6366f1';
            toggleBtn.style.color = '#6366f1';
            if (multiSelectControls) multiSelectControls.style.display = 'none';
            multiSelectElements.forEach(el => el.style.display = 'none');
            state.selectedPages.clear();
        }
        
        updateSelectedCount();
        lucide.createIcons();
    }

    function updateSelectedCount() {
        const countElement = document.querySelector('.selected-count');
        if (countElement) {
            const count = state.selectedPages.size;
            countElement.textContent = `${count} page${count !== 1 ? 's' : ''} selected`;
        }
    }

    function startBooklet() {
        state.bookletMode = true;
        state.currentBooklet = `booklet_${Date.now()}`;
        state.bookletStartPage = state.typingState.serialNo;
        state.bookletCounter = 'a';
        
        updateBookletUI();
        
        Swal.fire({
            icon: 'success',
            title: 'Booklet Started!',
            text: `Started booklet with base serial ${state.bookletStartPage}. Pages will be numbered ${state.bookletStartPage}a, ${state.bookletStartPage}b, etc.`,
            confirmButtonColor: '#28a745',
            timer: 3000,
            timerProgressBar: true
        });
    }

    function endBooklet() {
        const bookletPages = state.bookletPages[state.currentBooklet] || [];
        const bookletSummary = bookletPages.length > 0 
            ? `Booklet completed with ${bookletPages.length} pages: ${bookletPages.map(p => p.serialNumber).join(', ')}`
            : 'Booklet ended (no pages were processed)';
        
        state.bookletMode = false;
        state.currentBooklet = null;
        state.bookletStartPage = null;
        state.bookletCounter = 'a';
        
        // Increment serial number for next non-booklet page
        const currentSerial = state.typingState.serialNo?.toString() || '1';
        const numericPart = parseInt(currentSerial.match(/^(\d+)/)?.[1] || '1') || 1;
        const nextSerialNo = numericPart + 1;
        state.typingState.serialNo = nextSerialNo.toString().padStart(2, '0');
        
        updateBookletUI();
        
        Swal.fire({
            icon: 'success',
            title: 'Booklet Completed!',
            text: `${bookletSummary}. Next page will be numbered ${state.typingState.serialNo}.`,
            confirmButtonColor: '#28a745',
            timer: 4000,
            timerProgressBar: true
        });
    }

    function updateBookletUI() {
        const bookletInactive = document.querySelector('.booklet-inactive');
        const bookletActive = document.querySelector('.booklet-active');
        const bookletInfoElements = document.querySelectorAll('.booklet-info');
        
        if (state.bookletMode) {
            if (bookletInactive) bookletInactive.style.display = 'none';
            if (bookletActive) bookletActive.style.display = 'block';
            
            const infoText = document.querySelector('.booklet-info-text');
            const nextNumber = document.querySelector('.next-booklet-number');
            
            if (infoText) {
                infoText.textContent = `Pages ${state.bookletStartPage}a, ${state.bookletStartPage}b, ${state.bookletStartPage}c...`;
            }
            if (nextNumber) {
                nextNumber.textContent = state.bookletStartPage + state.bookletCounter;
            }
            
            // Show booklet info on current form
            bookletInfoElements.forEach(el => el.style.display = 'block');
            
            // Update serial number field
            const serialInput = document.querySelector(`.serial-input[data-page-index="${currentPageIndex}"]`);
            if (serialInput) {
                serialInput.value = state.bookletStartPage + state.bookletCounter;
                serialInput.readOnly = true;
                state.typingState.serialNo = state.bookletStartPage + state.bookletCounter;
            }
            
        } else {
            if (bookletInactive) bookletInactive.style.display = 'block';
            if (bookletActive) bookletActive.style.display = 'none';
            bookletInfoElements.forEach(el => el.style.display = 'none');
            
            // Make serial input editable again
            const serialInput = document.querySelector(`.serial-input[data-page-index="${currentPageIndex}"]`);
            if (serialInput) {
                serialInput.readOnly = false;
            }
        }
        
        updatePageCodePreview(currentPageIndex);
    }

    const savedPages = new Set();
    
    document.addEventListener('DOMContentLoaded', function() {
        const documentViewer = document.getElementById('document-viewer');
        const pageCounter = document.getElementById('page-counter');
        const prevBtn = document.getElementById('prev-page');
        const nextBtn = document.getElementById('next-page');
        const thumbnails = document.querySelectorAll('.document-thumbnail');
        const classificationForms = document.querySelectorAll('.page-form');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        const currentPageTitle = document.getElementById('current-page-title');
        const saveBtn = document.getElementById('save-current-btn');
        
        // Load page typing data from backend
        loadPageTypingData();
        
        // Initialize saved pages
        classificationForms.forEach(form => {
            if (form.dataset.saved === '1') {
                savedPages.add(parseInt(form.dataset.pageIndex));
            }
        });

        function updateSaveButtonState() {
            if (!saveBtn) return;
            
            const saveButtonText = saveBtn.querySelector('.save-button-text');
            
            if (state.isMultiSelectMode && state.selectedPages.size > 0) {
                saveBtn.disabled = false;
                if (saveButtonText) {
                    saveButtonText.textContent = state.bookletMode 
                        ? `Process ${state.selectedPages.size} Pages (Booklet: ${state.typingState.serialNo}a, ${state.typingState.serialNo}b, ...)`
                        : `Process ${state.selectedPages.size} Selected Pages`;
                }
            } else if (savedPages.size >= totalPages) {
                saveBtn.disabled = true;
                if (saveButtonText) saveButtonText.textContent = 'All Pages Saved';
            } else {
                saveBtn.disabled = false;
                if (saveButtonText) saveButtonText.textContent = 'Save & Next Page';
            }
            
            lucide.createIcons();
        }
        
        // Initialize page type change handlers
        initializePageTypeHandlers();
        
        // Initialize
        if (totalPages > 0) {
            showPage(0);
            updateProgress();
            updateSaveButtonState();
        }
        
        // Thumbnail clicks
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', () => {
                showPage(index);
            });
        });
        
        // Navigation buttons
        prevBtn.addEventListener('click', () => {
            if (currentPageIndex > 0) {
                showPage(currentPageIndex - 1);
            }
        });
        
        nextBtn.addEventListener('click', () => {
            if (currentPageIndex < totalPages - 1) {
                showPage(currentPageIndex + 1);
            }
        });
        
        // Enhanced save current page with multi-select and booklet support
        saveBtn.addEventListener('click', function() {
            if (state.isMultiSelectMode && state.selectedPages.size > 0) {
                processMultiplePages();
            } else {
                if (saveCurrentPage()) {
                    if (currentPageIndex < totalPages - 1) {
                        showPage(currentPageIndex + 1);
                    } else {
                        alert('All pages have been classified!');
                    }
                }
            }
        });

        // Multi-select event handlers
        document.querySelector('.toggle-multi-select')?.addEventListener('click', toggleMultiSelectMode);
        document.querySelector('.exit-multi-select')?.addEventListener('click', () => {
            state.isMultiSelectMode = false;
            updateMultiSelectUI();
        });

        document.querySelector('.select-all')?.addEventListener('click', () => {
            for (let i = 0; i < totalPages; i++) {
                state.selectedPages.add(i);
                const checkbox = document.querySelector(`.page-select-checkbox[data-page-index="${i}"]`);
                if (checkbox) checkbox.checked = true;
            }
            updateSelectedCount();
            updateSaveButtonState();
        });

        document.querySelector('.clear-selection')?.addEventListener('click', () => {
            state.selectedPages.clear();
            document.querySelectorAll('.page-select-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
            updateSaveButtonState();
        });

        // Booklet management event handlers
        document.querySelector('.start-booklet')?.addEventListener('click', startBooklet);
        document.querySelector('.end-booklet')?.addEventListener('click', endBooklet);

        // Page selection checkboxes
        document.querySelectorAll('.page-select-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                if (this.checked) {
                    state.selectedPages.add(pageIndex);
                } else {
                    state.selectedPages.delete(pageIndex);
                }
                updateSelectedCount();
                updateSaveButtonState();
            });
        });

        // Functions for page management
        function showPage(pageIndex) {
            currentPageIndex = pageIndex;
            
            // Update counter
            pageCounter.textContent = `${pageIndex + 1} of ${totalPages}`;
            
            // Update navigation buttons
            prevBtn.disabled = pageIndex === 0;
            nextBtn.disabled = pageIndex === totalPages - 1;
            
            // Update thumbnails
            thumbnails.forEach((thumb, index) => {
                thumb.classList.toggle('active', index === pageIndex);
            });
            
            // Update forms
            classificationForms.forEach((form, index) => {
                if (index === pageIndex) {
                    form.classList.remove('hidden');
                    form.classList.add('active');
                } else {
                    form.classList.add('hidden');
                    form.classList.remove('active');
                }
            });
            
            // Update page title
            if (currentPageTitle) {
                currentPageTitle.textContent = `Classify Page ${pageIndex + 1}`;
            }
            
            // Load current page state
            loadCurrentPageState();
            
            // Update document viewer
            updateDocumentViewer(pageIndex);
            
            // Update booklet UI
            updateBookletUI();
            updateSaveButtonState();
        }

        function loadCurrentPageState() {
            const form = document.querySelector(`.page-form[data-page-index="${currentPageIndex}"]`);
            if (!form) return;

            // Load existing values or set defaults
            const coverTypeSelect = form.querySelector('.cover-type-select');
            const pageTypeSelect = form.querySelector('.page-type-select');
            const pageSubtypeSelect = form.querySelector('.page-subtype-select');
            const serialInput = form.querySelector('.serial-input');

            if (coverTypeSelect && coverTypeSelect.value) {
                state.typingState.coverType = coverTypeSelect.value;
            }
            if (pageTypeSelect && pageTypeSelect.value) {
                state.typingState.pageType = pageTypeSelect.value;
                updatePageSubtypes(currentPageIndex, pageTypeSelect.value);
            }
            if (pageSubtypeSelect && pageSubtypeSelect.value) {
                state.typingState.pageSubType = pageSubtypeSelect.value;
            }
            if (serialInput && serialInput.value) {
                state.typingState.serialNo = serialInput.value;
            } else if (serialInput) {
                // Calculate and set next serial number
                state.typingState.serialNo = calculateNextSerialNumber();
                serialInput.value = state.typingState.serialNo;
            }

            updatePageCodePreview(currentPageIndex);
        }

        function updateDocumentViewer(pageIndex) {
            const page = pageData[pageIndex];
            if (!page) return;
            
            const viewer = document.getElementById('document-viewer');
            const filePath = page.file_path;
            const pageNumber = page.page_number;
            const isImage = page.type === 'image';
            const isPdf = page.type === 'pdf_page';
            
            if (isImage) {
                viewer.innerHTML = `<img src="{{ asset('storage/app/public/') }}/${filePath}" alt="Page ${pageIndex + 1}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
            } else if (isPdf) {
                viewer.innerHTML = `
                    <div class="pdf-viewer-container" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                        <canvas id="pdf-viewer-canvas-${pageIndex}" style="max-width: 100%; max-height: 100%; border: 1px solid #ddd;"></canvas>
                    </div>
                `;
                
                // Render PDF page
                setTimeout(() => renderPDFPage(filePath, pageNumber, `pdf-viewer-canvas-${pageIndex}`), 100);
            } else {
                viewer.innerHTML = `
                    <div class="viewer-placeholder">
                        <i data-lucide="file-text" style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Unable to preview this file type</p>
                    </div>
                `;
            }
            
            lucide.createIcons();
        }

        function renderPDFPage(filePath, pageNumber, canvasId) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const url = `{{ asset('storage/app/public/') }}/${filePath}`;
            
            pdfjsLib.getDocument(url).promise.then(function(pdf) {
                return pdf.getPage(pageNumber);
            }).then(function(page) {
                const scale = 1.5;
                const viewport = page.getViewport({ scale: scale });
                
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                const renderContext = {
                    canvasContext: canvas.getContext('2d'),
                    viewport: viewport
                };
                
                return page.render(renderContext).promise;
            }).catch(function(error) {
                console.error('Error rendering PDF:', error);
                canvas.parentElement.innerHTML = `
                    <div class="viewer-placeholder">
                        <i data-lucide="file-text" style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Error loading PDF page</p>
                    </div>
                `;
                lucide.createIcons();
            });
        }

        // updateProgress defined later using form values

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

            // Prepare page data
            const pageInfo = pageData[currentPageIndex];
            const pageTypingData = {
                file_indexing_id: fileIndexingId,
                scanning_id: pageInfo.scanning_id,
                page_number: pageInfo.page_number,
                cover_type_id: parseInt(coverTypeSelect.value),
                page_type: pageTypeSelect.value,
                page_type_others: pageTypeSelect.value === 'others' ? (form.querySelector('.page-type-others-input')?.value || '') : null,
                page_subtype: pageSubtypeSelect.value || null,
                page_subtype_others: pageSubtypeSelect.value === 'others' ? (form.querySelector('.page-subtype-others-input')?.value || '') : null,
                serial_number: serialInput.value,
                page_code: form.querySelector('.page-code-input').value,
                file_path: pageInfo.file_path,
                // Booklet fields
                booklet_id: state.currentBooklet,
                is_booklet_page: state.bookletMode,
                booklet_sequence: state.bookletMode ? state.bookletCounter : null
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

                    // Handle booklet progression
                    if (state.bookletMode) {
                        if (!state.bookletPages[state.currentBooklet]) {
                            state.bookletPages[state.currentBooklet] = [];
                        }
                        state.bookletPages[state.currentBooklet].push({
                            pageIndex: currentPageIndex,
                            serialNumber: serialInput.value,
                            pageCode: pageTypingData.page_code
                        });

                        // Increment booklet counter
                        state.bookletCounter = String.fromCharCode(state.bookletCounter.charCodeAt(0) + 1);
                        state.typingState.serialNo = state.bookletStartPage + state.bookletCounter;
                    } else {
                        // Calculate next serial for non-booklet mode
                        state.typingState.serialNo = calculateNextSerialNumber();
                    }

                    updateProgress();
                    updateSaveButtonState();
                    
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

        // Process multiple pages (for multi-select mode)
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
            const pageTypeOthers = pageType === 'others' ? (currentForm.querySelector('.page-type-others-input')?.value || '') : null;
            const pageSubtypeOthers = pageSubtype === 'others' ? (currentForm.querySelector('.page-subtype-others-input')?.value || '') : null;

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
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            let processedCount = 0;
            let failedCount = 0;
            const selectedPagesArray = Array.from(state.selectedPages).sort((a, b) => a - b);

            for (const pageIndex of selectedPagesArray) {
                try {
                    const pageInfo = pageData[pageIndex];
                    const serialNumber = state.bookletMode 
                        ? state.bookletStartPage + String.fromCharCode('a'.charCodeAt(0) + processedCount)
                        : (parseInt(state.typingState.serialNo) + processedCount).toString().padStart(2, '0');

                    const pageCode = `${getCoverTypeById(coverType)?.code || 'XX'}-${getPageTypeCode(pageType, pageTypeOthers)}${getPageSubTypeCode(pageType, pageSubtype, pageSubtypeOthers) ? '-' + getPageSubTypeCode(pageType, pageSubtype, pageSubtypeOthers) : ''}-${serialNumber}`;

                    const pageTypingData = {
                        file_indexing_id: fileIndexingId,
                        scanning_id: pageInfo.scanning_id,
                        page_number: pageInfo.page_number,
                        cover_type_id: parseInt(coverType),
                        page_type: pageType,
                        page_type_others: pageTypeOthers,
                        page_subtype: pageSubtype,
                        page_subtype_others: pageSubtypeOthers,
                        serial_number: serialNumber,
                        page_code: pageCode,
                        file_path: pageInfo.file_path,
                        booklet_id: state.currentBooklet,
                        is_booklet_page: state.bookletMode,
                        booklet_sequence: state.bookletMode ? String.fromCharCode('a'.charCodeAt(0) + processedCount) : null
                    };

                    const success = await savePageTypingToBackend(pageTypingData);
                    
                    if (success) {
                        processedCount++;
                        savedPages.add(pageIndex);
                        
                        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
                        if (form) form.dataset.saved = '1';
                        
                        state.processedPages[pageIndex] = {
                            coverType: coverType,
                            pageType: pageType,
                            pageTypeOthers: pageTypeOthers,
                            pageSubType: pageSubtype,
                            pageSubTypeOthers: pageSubtypeOthers,
                            serialNo: serialNumber,
                            page_code: pageCode
                        };

                        // Update status indicator
                        const statusIndicator = document.querySelector(`.page-status[data-page-index="${pageIndex}"]`);
                        if (statusIndicator) {
                            statusIndicator.classList.add('completed');
                            statusIndicator.innerHTML = '<i data-lucide="check" style="width: 0.75rem; height: 0.75rem;"></i>';
                        }
                    } else {
                        failedCount++;
                    }

                    // Update progress
                    Swal.update({
                        html: `Processing ${processedCount + failedCount} of ${state.selectedPages.size} pages...`
                    });

                    // Small delay to prevent overwhelming the server
                    await new Promise(resolve => setTimeout(resolve, 100));

                } catch (error) {
                    console.error(`Error processing page ${pageIndex}:`, error);
                    failedCount++;
                }
            }

            Swal.close();

            if (processedCount > 0) {
                Swal.fire({
                    icon: 'success',
                    title: 'Batch Processing Complete',
                    html: `
                        <div class="text-left">
                            <p>✅ Successfully processed: <strong>${processedCount}</strong> pages</p>
                            ${failedCount > 0 ? `<p class="text-red-600">❌ Failed: <strong>${failedCount}</strong> pages</p>` : ''}
                        </div>
                    `,
                    confirmButtonColor: '#28a745'
                });

                // Clear selection and exit multi-select mode
                state.selectedPages.clear();
                state.isMultiSelectMode = false;
                updateMultiSelectUI();
                updateProgress();
                updateSaveButtonState();
                lucide.createIcons();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Processing Failed',
                    text: `Failed to process any pages. ${failedCount} errors occurred.`,
                    confirmButtonColor: '#dc3545'
                });
            }
        }

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
                    return false;
                }
            } catch (error) {
                console.error('Network error:', error);
                return false;
            }
        }

        // Generate PDF thumbnails
        function generatePDFThumbnails() {
            document.querySelectorAll('.pdf-thumbnail-canvas').forEach(canvas => {
                const pdfPath = canvas.dataset.pdfPath;
                const pageNumber = parseInt(canvas.dataset.pageNumber);
                
                pdfjsLib.getDocument(pdfPath).promise.then(function(pdf) {
                    return pdf.getPage(pageNumber);
                }).then(function(page) {
                    const scale = 0.3;
                    const viewport = page.getViewport({ scale: scale });
                    
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    const renderContext = {
                        canvasContext: canvas.getContext('2d'),
                        viewport: viewport
                    };
                    
                    return page.render(renderContext).promise;
                }).then(function() {
                    // Hide loading and show canvas
                    const loadingEl = canvas.parentElement.querySelector('.pdf-thumbnail-loading');
                    if (loadingEl) loadingEl.style.display = 'none';
                    canvas.style.display = 'block';
                }).catch(function(error) {
                    console.error('Error generating PDF thumbnail:', error);
                    // Hide loading and show fallback
                    const loadingEl = canvas.parentElement.querySelector('.pdf-thumbnail-loading');
                    const fallbackEl = canvas.parentElement.querySelector('.pdf-thumbnail-fallback');
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (fallbackEl) fallbackEl.style.display = 'flex';
                });
            });
        }

        // Generate thumbnails for PDF pages
        setTimeout(generatePDFThumbnails, 500);
    });
        
        // Batch save all pages
        const batchBtn = document.getElementById('batch-save-btn');
        if (batchBtn) {
            batchBtn.addEventListener('click', function() {
                batchSaveAllPages();
            });
        }
        
        // Finish classification
        const finishBtn = document.getElementById('finish-btn');
        if (finishBtn) {
            finishBtn.addEventListener('click', function() {
                if (saveCurrentPage()) {
                    finishClassification();
                } else {
                    console.log('Could not save current page before finishing');
                }
            });
        }

        // Navigation controls
        document.getElementById('prev-page')?.addEventListener('click', function() {
            if (currentPageIndex > 0) {
                navigateToPage(currentPageIndex - 1);
            }
        });
                        state.bookletPages[state.currentBooklet].push({
                            pageIndex: currentPageIndex,
                            serialNumber: serialInput.value,
                            pageCode: pageTypingData.page_code
                        });

                        // Increment booklet counter
                        state.bookletCounter = String.fromCharCode(state.bookletCounter.charCodeAt(0) + 1);
                        state.typingState.serialNo = state.bookletStartPage + state.bookletCounter;
                    } else {
                        // Calculate next serial for non-booklet mode
                        state.typingState.serialNo = calculateNextSerialNumber();
                    }

                    updateProgress();
                    updateSaveButtonState();
                    
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

        // Navigation controls
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

        // Thumbnail click navigation
        document.querySelectorAll('.document-thumbnail').forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                const pageIndex = parseInt(this.dataset.pageIndex);
                navigateToPage(pageIndex);
            });
        });
                });
                return;
            }

            // Get current page values for batch processing
            const currentForm = document.querySelector(`.page-form[data-page-index="${currentPageIndex}"]`);
            if (!currentForm) return;

            const coverType = currentForm.querySelector('.cover-type-select').value;
            const pageType = currentForm.querySelector('.page-type-select').value;
            const pageSubtype = currentForm.querySelector('.page-subtype-select').value || null;
            const pageTypeOthers = pageType === 'others' ? (currentForm.querySelector('.page-type-others-input')?.value || '') : null;
            const pageSubtypeOthers = pageSubtype === 'others' ? (currentForm.querySelector('.page-subtype-others-input')?.value || '') : null;

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
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            let processedCount = 0;
            let failedCount = 0;
            const selectedPagesArray = Array.from(state.selectedPages).sort((a, b) => a - b);

            for (const pageIndex of selectedPagesArray) {
                try {
                    const pageInfo = pageData[pageIndex];
                    const serialNumber = state.bookletMode 
                        ? state.bookletStartPage + String.fromCharCode('a'.charCodeAt(0) + processedCount)
                        : (parseInt(state.typingState.serialNo) + processedCount).toString().padStart(2, '0');

                    const pageCode = `${getCoverTypeById(coverType)?.code || 'XX'}-${getPageTypeCode(pageType, pageTypeOthers)}${getPageSubTypeCode(pageType, pageSubtype, pageSubtypeOthers) ? '-' + getPageSubTypeCode(pageType, pageSubtype, pageSubtypeOthers) : ''}-${serialNumber}`;

                    const pageTypingData = {
                        file_indexing_id: fileIndexingId,
                        scanning_id: pageInfo.scanning_id,
                        page_number: pageInfo.page_number,
                        cover_type_id: parseInt(coverType),
                        page_type: pageType,
                        page_type_others: pageTypeOthers,
                        page_subtype: pageSubtype,
                        page_subtype_others: pageSubtypeOthers,
                        serial_number: serialNumber,
                        page_code: pageCode,
                        file_path: pageInfo.file_path,
                        booklet_id: state.currentBooklet,
                        is_booklet_page: state.bookletMode,
                        booklet_sequence: state.bookletMode ? String.fromCharCode('a'.charCodeAt(0) + processedCount) : null
                    };

                    const success = await savePageTypingToBackend(pageTypingData);
                    
                    if (success) {
                        processedCount++;
                        savedPages.add(pageIndex);
                        
                        const form = document.querySelector(`.page-form[data-page-index="${pageIndex}"]`);
                        if (form) form.dataset.saved = '1';
                        
                        state.processedPages[pageIndex] = {
                            coverType: coverType,
                            pageType: pageType,
                            pageTypeOthers: pageTypeOthers,
                            pageSubType: pageSubtype,
                            pageSubTypeOthers: pageSubtypeOthers,
                            serialNo: serialNumber,
                            page_code: pageCode
                        };

                        // Update status indicator
                        const statusIndicator = document.querySelector(`.page-status[data-page-index="${pageIndex}"]`);
                        if (statusIndicator) {
                            statusIndicator.classList.add('completed');
                            statusIndicator.innerHTML = '<i data-lucide="check" style="width: 0.75rem; height: 0.75rem;"></i>';
                        }
                    } else {
                        failedCount++;
                    }

                    // Update progress
                    Swal.update({
                        html: `Processing ${processedCount + failedCount} of ${state.selectedPages.size} pages...`
                    });

                    // Small delay to prevent overwhelming the server
                    await new Promise(resolve => setTimeout(resolve, 100));

                } catch (error) {
                    console.error(`Error processing page ${pageIndex}:`, error);
                    failedCount++;
                }
            }

            Swal.close();

            if (processedCount > 0) {
                Swal.fire({
                    icon: 'success',
                    title: 'Batch Processing Complete',
                    html: `
                        <div class="text-left">
                            <p>✅ Successfully processed: <strong>${processedCount}</strong> pages</p>
                            ${failedCount > 0 ? `<p class="text-red-600">❌ Failed: <strong>${failedCount}</strong> pages</p>` : ''}
                        </div>
                    `,
                    confirmButtonColor: '#28a745'
                });

                // Clear selection and exit multi-select mode
                state.selectedPages.clear();
                state.isMultiSelectMode = false;
                updateMultiSelectUI();
                updateProgress();
                updateSaveButtonState();
                lucide.createIcons();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Processing Failed',
                    text: `Failed to process any pages. ${failedCount} errors occurred.`,
                    confirmButtonColor: '#dc3545'
                });
            }
        }

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
                    return false;
                }
            } catch (error) {
                console.error('Network error:', error);
                return false;
            }
        }

        // Generate PDF thumbnails
        function generatePDFThumbnails() {
            document.querySelectorAll('.pdf-thumbnail-canvas').forEach(canvas => {
                const pdfPath = canvas.dataset.pdfPath;
                const pageNumber = parseInt(canvas.dataset.pageNumber);
                
                pdfjsLib.getDocument(pdfPath).promise.then(function(pdf) {
                    return pdf.getPage(pageNumber);
                }).then(function(page) {
                    const scale = 0.3;
                    const viewport = page.getViewport({ scale: scale });
                    
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    const renderContext = {
                        canvasContext: canvas.getContext('2d'),
                        viewport: viewport
                    };
                    
                    return page.render(renderContext).promise;
                }).then(function() {
                    // Hide loading and show canvas
                    const loadingEl = canvas.parentElement.querySelector('.pdf-thumbnail-loading');
                    if (loadingEl) loadingEl.style.display = 'none';
                    canvas.style.display = 'block';
                }).catch(function(error) {
                    console.error('Error generating PDF thumbnail:', error);
                    // Hide loading and show fallback
                    const loadingEl = canvas.parentElement.querySelector('.pdf-thumbnail-loading');
                    const fallbackEl = canvas.parentElement.querySelector('.pdf-thumbnail-fallback');
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (fallbackEl) fallbackEl.style.display = 'flex';
                });
            });
        }

        // Generate thumbnails for PDF pages
        setTimeout(generatePDFThumbnails, 500);
    });
        
        // Batch save all pages
        const batchBtn = document.getElementById('batch-save-btn');
        if (batchBtn) {
            batchBtn.addEventListener('click', function() {
                batchSaveAllPages();
            });
        }
        
        // Finish classification
        const finishBtn = document.getElementById('finish-btn');
        if (finishBtn) {
            finishBtn.addEventListener('click', function() {
                if (saveCurrentPage()) {
                    finishClassification();
                }
            });
        }
        
        
        // Show page function
        function showPage(index) {
            currentPageIndex = index;
            
            // Update thumbnails
            thumbnails.forEach((thumb, i) => {
                thumb.classList.toggle('active', i === index);
            });
            
            // Update forms
            classificationForms.forEach((form, i) => {
                if (i === index) {
                    form.classList.remove('hidden');
                    form.classList.add('active');
                } else {
                    form.classList.add('hidden');
                    form.classList.remove('active');
                }
            });
            
            // Update counter and title
            pageCounter.textContent = `${index + 1} of ${totalPages}`;
            currentPageTitle.textContent = `Classify ${pageData[index].display_name}`;
            
            // Update navigation buttons
            prevBtn.disabled = index === 0;
            nextBtn.disabled = index === totalPages - 1;
            
            // Load page in viewer
            const currentPage = pageData[index];
          
            if (currentPage.type === 'pdf_page') {
                // For PDF pages, use PDF.js for better rendering
                const pdfPath = `{{ asset('storage/app/public/') }}/${currentPage.file_path}`;
                documentViewer.innerHTML = `
                    <div style="width: 100%; height: 500px; position: relative; background: #f8fafc; display: flex; align-items: center; justify-content: center;">
                        <canvas id="main-pdf-canvas" 
                                style="max-width: 100%; max-height: 100%; border-radius: 0.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);">
                        </canvas>
                        <div id="main-pdf-loading" style="text-align: center; color: #667eea;">
                            <div class="spinner" style="width: 2rem; height: 2rem; margin: 0 auto 1rem;"></div>
                            <div style="font-size: 1rem; font-weight: 500;">Loading ${currentPage.display_name}...</div>
                        </div>
                        <div id="main-pdf-fallback" style="display: none; text-align: center; color: #718096; padding: 2rem;">
                            <i data-lucide="file-text" style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3 style="margin-bottom: 0.5rem;">${currentPage.display_name}</h3>
                            <p>PDF Page ${currentPage.page_number}</p>
                            <p style="font-size: 0.875rem; opacity: 0.7;">Click <a href="${pdfPath}" target="_blank" style="color: #667eea;">here</a> to open PDF in new tab</p>
                        </div>
                        <div style="position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.7); color: white; padding: 0.5rem; border-radius: 0.25rem; font-size: 0.875rem;">
                            ${currentPage.display_name}
                        </div>
                    </div>
                `;
                
                // Render the PDF page
                renderMainPdfPage(pdfPath, currentPage.page_number);
            } else {
                // For images
                const imagePath = `{{ asset('storage/app/public/') }}/${currentPage.file_path}`;
                documentViewer.innerHTML = `
                    <img src="${imagePath}" 
                         alt="${currentPage.display_name}" 
                         style="max-width: 100%; max-height: 500px; object-fit: contain; border-radius: 0.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display: none; text-align: center; color: #718096;">
                        <i data-lucide="image-off" style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Unable to load image</p>
                    </div>
                `;
            }
            
            lucide.createIcons();
        }
        
        // Save current page data
        function saveCurrentPage() {
            const currentForm = document.querySelector(`.page-form[data-page-index="${currentPageIndex}"]`);
            const pageTypeSelect = currentForm.querySelector('.page-type-select');
            const pageSubtypeSelect = currentForm.querySelector('.page-subtype-select');
            const serialInput = currentForm.querySelector('.serial-input');
            const pageCodeInput = currentForm.querySelector('.page-code-input');
            
            // Validate required fields
            if (!pageTypeSelect.value || !serialInput.value) {
                alert('Please fill in all required fields (Page Type and Serial Number).');
                return false;
            }
            
            // Store classification data
            pageClassifications[currentPageIndex] = {
                file_path: currentForm.dataset.filePath,
                page_number: currentForm.dataset.pageNumber,
                scanning_id: currentForm.dataset.scanningId,
                page_type: pageTypeSelect.value,
                page_subtype: pageSubtypeSelect.value,
                serial_number: serialInput.value,
                page_code: pageCodeInput.value
            };
            
            // Update page status
            updatePageStatus(currentPageIndex);
            
            // Auto-save to server (optional)
            saveToServer(currentPageIndex);
            
            return true;
        }
        
        // Save to server
        function saveToServer(pageIndex) {
            const data = pageClassifications[pageIndex];
            if (!data) return;
            
            // Get CSRF token from multiple sources
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            fetch(`{{ route('edms.save-single-page-typing', $fileIndexing->id) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                console.log('Page saved:', result);
                if (result.success) {
                    // Show success indicator
                    showNotification('Page saved successfully!', 'success');
                    // Mark this page as saved and update UI
                    savedPages.add(parseInt(pageIndex));
                    updateSaveButtonState();
                } else {
                    showNotification(result.message || 'Error saving page', 'error');
                }
            })
            .catch(error => {
                console.error('Error saving page:', error);
                showNotification('Error saving page: ' + error.message, 'error');
            });
        }
        
        // Finish classification
        function finishClassification() {
            // Save all classifications
            const allData = Object.values(pageClassifications);
            
            if (allData.length === 0) {
                alert('Please classify at least one page before finishing.');
                return;
            }
            
            // Get CSRF token from multiple sources
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            fetch(`{{ route('edms.finish-page-typing', $fileIndexing->id) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ classifications: allData })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    showNotification('Page classification completed successfully!', 'success');
                    setTimeout(() => {
                        @if($fileIndexing->recertification_application_id)
                            window.location.href = `{{ route('edms.recertification.index', $fileIndexing->recertification_application_id) }}`;
                        @elseif($fileIndexing->subapplication_id)
                            window.location.href = `{{ route('edms.index', [$fileIndexing->main_application_id, 'sub']) }}`;
                        @elseif($fileIndexing->main_application_id)
                            window.location.href = `{{ route('edms.index', $fileIndexing->main_application_id) }}`;
                        @else
                            window.location.href = `{{ url()->previous() }}`;
                        @endif
                    }, 1500);
                } else {
                    showNotification(result.message || 'Error completing classification', 'error');
                }
            })
            .catch(error => {
                console.error('Error finishing classification:', error);
                showNotification('Error completing classification: ' + error.message, 'error');
            });
        }
        
        // Show notification function
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                color: white;
                font-weight: 600;
                z-index: 9999;
                max-width: 400px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            
            // Set background color based on type
            switch (type) {
                case 'success':
                    notification.style.background = 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)';
                    break;
                case 'error':
                    notification.style.background = 'linear-gradient(135deg, #e53e3e 0%, #c53030 100%)';
                    break;
                case 'warning':
                    notification.style.background = 'linear-gradient(135deg, #ed8936 0%, #dd6b20 100%)';
                    break;
                default:
                    notification.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
            
            notification.textContent = message;
            
            // Add to document
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
        
        // Batch save all pages function
        function batchSaveAllPages() {
            const allPages = [];
            let validPages = 0;
            let invalidPages = [];
            
            // Collect all page data
            for (let i = 0; i < totalPages; i++) {
                const form = document.querySelector(`.page-form[data-page-index="${i}"]`);
                const pageTypeSelect = form.querySelector('.page-type-select');
                const pageSubtypeSelect = form.querySelector('.page-subtype-select');
                const serialInput = form.querySelector('.serial-input');
                const pageCodeInput = form.querySelector('.page-code-input');
                
                // Check if required fields are filled
                if (pageTypeSelect.value && serialInput.value) {
                    allPages.push({
                        file_path: form.dataset.filePath,
                        page_number: form.dataset.pageNumber,
                        scanning_id: form.dataset.scanningId,
                        page_type: pageTypeSelect.value,
                        page_subtype: pageSubtypeSelect.value,
                        serial_number: serialInput.value,
                        page_code: pageCodeInput.value
                    });
                    validPages++;
                } else {
                    invalidPages.push(i + 1);
                }
            }
            
            if (validPages === 0) {
                showNotification('No pages have been classified yet. Please classify at least one page.', 'warning');
                return;
            }
            
            if (invalidPages.length > 0) {
                const proceed = confirm(`${invalidPages.length} pages are incomplete (pages: ${invalidPages.join(', ')}). Do you want to save only the ${validPages} completed pages?`);
                if (!proceed) {
                    return;
                }
            }
            
            // Show loading state
            const batchBtn = document.getElementById('batch-save-btn');
            const originalText = batchBtn.innerHTML;
            batchBtn.disabled = true;
            batchBtn.innerHTML = '<div class="spinner"></div> Saving...';
            
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            fetch(`{{ route('edms.batch-save-page-typing', $fileIndexing->id) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ pages: allPages })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    showNotification(`Batch save completed! ${result.saved_count} of ${result.total_count} pages saved successfully.`, 'success');
                    
                    // Update page status indicators for saved pages
                    allPages.forEach((pageData, index) => {
                        const pageIndex = pageData.page_number - 1; // Assuming page numbers start from 1
                        updatePageStatus(pageIndex);
                    });
                    
                    updateProgress();
                } else {
                    showNotification(result.message || 'Error in batch save operation', 'error');
                }
                
                if (result.errors && result.errors.length > 0) {
                    console.error('Batch save errors:', result.errors);
                    showNotification(`Some pages had errors: ${result.errors.length} errors occurred`, 'warning');
                }
            })
            .catch(error => {
                console.error('Error in batch save:', error);
                showNotification('Error in batch save operation: ' + error.message, 'error');
            })
            .finally(() => {
                // Restore button state
                batchBtn.disabled = false;
                batchBtn.innerHTML = originalText;
                lucide.createIcons();
            });
        }
        
        // Update progress function
        function updateProgress() {
            let completed = 0;
            
            for (let i = 0; i < totalPages; i++) {
                const pageTypeSelect = document.querySelector(`select.page-type-select[data-page-index="${i}"]`);
                const serialInput = document.querySelector(`input.serial-input[data-page-index="${i}"]`);
                
                if (pageTypeSelect && pageTypeSelect.value && serialInput && serialInput.value) {
                    completed++;
                }
            }
            
            const percentage = totalPages > 0 ? (completed / totalPages) * 100 : 0;
            progressFill.style.width = percentage + '%';
            progressText.textContent = `${completed} of ${totalPages} pages completed`;
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && currentPageIndex > 0) {
                showPage(currentPageIndex - 1);
            } else if (e.key === 'ArrowRight' && currentPageIndex < totalPages - 1) {
                showPage(currentPageIndex + 1);
            } else if (e.key === 'Enter' && e.ctrlKey) {
                // Ctrl+Enter to save and next
                document.getElementById('save-current-btn').click();
            }
        }); 
        
        // Initialize page status indicators
        for (let i = 0; i < totalPages; i++) {
            updatePageStatus(i);
        }
        
        // Initialize PDF thumbnails
        initializePdfThumbnails();
    });
    
    // PDF Thumbnail Rendering Functions
    async function initializePdfThumbnails() {
        console.log('Initializing PDF thumbnails...');
        const pdfCanvases = document.querySelectorAll('.pdf-thumbnail-canvas');
        console.log(`Found ${pdfCanvases.length} PDF canvases to render`);
        
        // Render thumbnails with a small delay between each to avoid overwhelming the browser
        for (let i = 0; i < pdfCanvases.length; i++) {
            const canvas = pdfCanvases[i];
            const pdfPath = canvas.dataset.pdfPath;
            const pageNumber = parseInt(canvas.dataset.pageNumber);
            
            console.log(`Processing thumbnail ${i + 1}/${pdfCanvases.length}: ${pdfPath}, page ${pageNumber}`);
            
            try {
                // Add a small delay between renders to prevent browser lockup
                if (i > 0) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
                await renderPdfThumbnail(canvas, pdfPath, pageNumber);
            } catch (error) {
                console.error(`Error rendering PDF thumbnail ${i + 1}:`, error);
                showPdfThumbnailFallback(canvas);
            }
        }
        
        console.log('PDF thumbnail initialization complete');
    }
    
    async function renderPdfThumbnail(canvas, pdfPath, pageNumber) {
        const container = canvas.parentElement;
        const loadingElement = container.querySelector('.pdf-thumbnail-loading');
        const fallbackElement = container.querySelector('.pdf-thumbnail-fallback');
        
        try {
            console.log(`Starting PDF thumbnail render for: ${pdfPath}, page: ${pageNumber}`);
            
            // Show loading state
            if (loadingElement) {
                loadingElement.style.display = 'flex';
                loadingElement.style.flexDirection = 'column';
                loadingElement.style.alignItems = 'center';
                loadingElement.style.justifyContent = 'center';
            }
            canvas.style.display = 'none';
            if (fallbackElement) {
                fallbackElement.style.display = 'none';
            }
            
            // Load PDF with simplified options for thumbnails
            const loadingTask = pdfjsLib.getDocument({
                url: pdfPath,
                disableAutoFetch: true,
                disableStream: true,
                disableFontFace: true, // Disable font loading for thumbnails
                verbosity: 0 // Reduce verbosity to minimize warnings
            });
            
            // Set a timeout for PDF loading
            const timeoutPromise = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('PDF loading timeout')), 10000);
            });
            
            const pdf = await Promise.race([loadingTask.promise, timeoutPromise]);
            console.log(`PDF loaded successfully for thumbnail, total pages: ${pdf.numPages}`);
            
            // Validate page number
            if (pageNumber > pdf.numPages || pageNumber < 1) {
                throw new Error(`Invalid page number: ${pageNumber}. PDF has ${pdf.numPages} pages.`);
            }
            
            // Get the specific page
            const page = await pdf.getPage(pageNumber);
            console.log(`Page ${pageNumber} loaded successfully for thumbnail`);
            
            // Wait for container to have dimensions with timeout
            let attempts = 0;
            while ((container.clientWidth === 0 || container.clientHeight === 0) && attempts < 20) {
                await new Promise(resolve => setTimeout(resolve, 50));
                attempts++;
            }
            
            // Use fixed dimensions if container dimensions are still not available
            const containerWidth = container.clientWidth > 0 ? container.clientWidth - 20 : 140;
            const containerHeight = container.clientHeight > 0 ? container.clientHeight - 20 : 180;
            
            const viewport = page.getViewport({ scale: 1 });
            const scaleX = containerWidth / viewport.width;
            const scaleY = containerHeight / viewport.height;
            const scale = Math.min(scaleX, scaleY, 1.5); // Reduced max scale for thumbnails
            
            const scaledViewport = page.getViewport({ scale });
            
            console.log(`Rendering thumbnail at scale: ${scale}, dimensions: ${scaledViewport.width}x${scaledViewport.height}`);
            
            // Set canvas dimensions
            canvas.width = scaledViewport.width;
            canvas.height = scaledViewport.height;
            canvas.style.width = Math.min(scaledViewport.width, containerWidth) + 'px';
            canvas.style.height = Math.min(scaledViewport.height, containerHeight) + 'px';
            canvas.style.maxWidth = '100%';
            canvas.style.maxHeight = '100%';
            canvas.style.objectFit = 'contain';
            
            // Render page to canvas with simplified options
            const context = canvas.getContext('2d');
            
            // Clear canvas with white background
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            
            const renderContext = {
                canvasContext: context,
                viewport: scaledViewport,
                enableWebGL: false,
                renderTextLayer: false, // Disable text layer for thumbnails
                renderAnnotationLayer: false // Disable annotations for thumbnails
            };
            
            // Set timeout for rendering
            const renderTimeoutPromise = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('PDF rendering timeout')), 15000);
            });
            
            const renderTask = page.render(renderContext);
            await Promise.race([renderTask.promise, renderTimeoutPromise]);
            
            console.log(`PDF thumbnail page ${pageNumber} rendered successfully`);
            
            // Hide loading and show canvas
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }
            canvas.style.display = 'block';
            
            // Add subtle animation
            canvas.style.opacity = '0';
            canvas.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                canvas.style.opacity = '1';
            }, 50);
            
            // Clean up
            page.cleanup();
            
        } catch (error) {
            console.error('Error rendering PDF thumbnail:', error);
            showPdfThumbnailFallback(canvas);
        }
    }
    
    function showPdfThumbnailFallback(canvas) {
        const container = canvas.parentElement;
        const loadingElement = container.querySelector('.pdf-thumbnail-loading');
        const fallbackElement = container.querySelector('.pdf-thumbnail-fallback');
        
        console.log('Showing PDF thumbnail fallback');
        
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }
        canvas.style.display = 'none';
        if (fallbackElement) {
            fallbackElement.style.display = 'flex';
            fallbackElement.style.flexDirection = 'column';
            fallbackElement.style.alignItems = 'center';
            fallbackElement.style.justifyContent = 'center';
        }
        
        // Re-initialize lucide icons for fallback
        lucide.createIcons();
    }
    
    // Main PDF Page Rendering Function
    async function renderMainPdfPage(pdfPath, pageNumber) {
        const canvas = document.getElementById('main-pdf-canvas');
        const loadingElement = document.getElementById('main-pdf-loading');
        const fallbackElement = document.getElementById('main-pdf-fallback');
        
        try {
            // Show loading state
            loadingElement.style.display = 'block';
            canvas.style.display = 'none';
            fallbackElement.style.display = 'none';
            
            // Load PDF
            const pdf = await pdfjsLib.getDocument(pdfPath).promise;
            
            // Get the specific page
            const page = await pdf.getPage(pageNumber);
            
            // Calculate scale to fit viewer container
            const viewerContainer = document.getElementById('document-viewer');
            const containerWidth = viewerContainer.clientWidth - 40; // Account for padding
            const containerHeight = 460; // Fixed height minus padding
            
            const viewport = page.getViewport({ scale: 1 });
            const scaleX = containerWidth / viewport.width;
            const scaleY = containerHeight / viewport.height;
            const scale = Math.min(scaleX, scaleY, 2.0); // Max scale of 2.0 for quality
            
            const scaledViewport = page.getViewport({ scale });
            
            // Set canvas dimensions
            canvas.width = scaledViewport.width;
            canvas.height = scaledViewport.height;
            canvas.style.width = scaledViewport.width + 'px';
            canvas.style.height = scaledViewport.height + 'px';
            
            // Render page to canvas
            const context = canvas.getContext('2d');
            const renderContext = {
                canvasContext: context,
                viewport: scaledViewport
            };
            
            await page.render(renderContext).promise;
            
            // Hide loading and show canvas
            loadingElement.style.display = 'none';
            canvas.style.display = 'block';
            
            // Add subtle animation
            canvas.style.opacity = '0';
            canvas.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                canvas.style.opacity = '1';
            }, 50);
            
        } catch (error) {
            console.error('Error rendering main PDF page:', error);
            
            // Show fallback
            loadingElement.style.display = 'none';
            canvas.style.display = 'none';
            fallbackElement.style.display = 'block';
            
            // Re-initialize lucide icons for fallback
            lucide.createIcons();
        }
    }
</script>
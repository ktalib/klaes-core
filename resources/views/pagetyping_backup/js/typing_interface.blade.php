<!-- Page Typing Interface JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
// Configure PDF.js worker immediately
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    console.log('PDF.js worker configured');
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    lucide.createIcons();
    
    console.log('Page Typing Interface: Initializing...');
    
    // State variables
    let selectedFileIndexing = @json($selectedFileIndexing ?? null);
    let currentDocuments = [];
    let currentDocumentIndex = 0;
    let currentPdfPages = [];
    let currentPdfPageIndex = 0;
    let currentPageNumber = 1;
    let savedPages = [];
    let totalPages = 0;
    let zoomLevel = 1;
    
    // DOM Elements - with null checks
    const documentViewer = document.getElementById('document-viewer');
    const documentViewerContainer = document.getElementById('document-viewer-container');
    const documentCounter = document.getElementById('document-counter');
    const currentDocumentInfo = document.getElementById('current-document-info');
    const currentPageInfo = document.getElementById('current-page-info');
    const prevDocumentBtn = document.getElementById('prev-document');
    const nextDocumentBtn = document.getElementById('next-document');
    
    // PDF controls
    const pdfPageControls = document.getElementById('pdf-page-controls');
    const prevPdfPageBtn = document.getElementById('prev-pdf-page');
    const nextPdfPageBtn = document.getElementById('next-pdf-page');
    const pdfPageCounter = document.getElementById('pdf-page-counter');
    
    // Zoom controls
    const zoomOutBtn = document.getElementById('zoom-out');
    const zoomInBtn = document.getElementById('zoom-in');
    const zoomFitBtn = document.getElementById('zoom-fit');
    const zoomLevelSpan = document.getElementById('zoom-level');
    
    // Form elements
    const pageTypingForm = document.getElementById('page-typing-form');
    const pageNumberInput = document.getElementById('page-number');
    const pageTypeSelect = document.getElementById('page-type');
    const pageSubtypeInput = document.getElementById('page-subtype');
    const serialNumberInput = document.getElementById('serial-number');
    const pageCodeInput = document.getElementById('page-code');
    const pageNotesInput = document.getElementById('page-notes');
    const isImportantInput = document.getElementById('is-important');
    const savePageBtn = document.getElementById('save-page');
    const saveAndNextBtn = document.getElementById('save-and-next');
    const completeTypingBtn = document.getElementById('complete-typing');
    const typingProgress = document.getElementById('typing-progress');
    const typingProgressBar = document.getElementById('typing-progress-bar');
    
    // Quick type buttons
    const quickTypeButtons = document.querySelectorAll('.quick-type-btn');
    
    // PDF extraction modal
    const pdfExtractionModal = document.getElementById('pdf-extraction-modal');
    const closePdfModal = document.getElementById('close-pdf-modal');
    const pdfExtractionProgress = document.getElementById('pdf-extraction-progress');
    const pdfExtractionStatus = document.getElementById('pdf-extraction-status');
    
    // Check if required elements exist
    if (!documentViewer) {
        console.error('Document viewer element not found');
        return;
    }
    
    if (!selectedFileIndexing) {
        console.error('No selected file indexing');
        return;
    }
    
    console.log('Selected file ID:', selectedFileIndexing.id);
    
    // Start loading the file automatically
    setTimeout(() => {
        loadFileForTyping(selectedFileIndexing.id);
    }, 500);
    
    // Load file for typing
    function loadFileForTyping(fileIndexingId) {
        console.log('Loading file for typing:', fileIndexingId);
        showLoadingState();
        
        // Fetch file data and documents
        fetch(`{{ route("scanning.list") }}?file_indexing_id=${fileIndexingId}`)
        .then(response => {
            console.log('Scanning list response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Scanning list data:', data);
            if (data.success) {
                currentDocuments = data.scanned_files || [];
                
                if (currentDocuments.length === 0) {
                    showEmptyState();
                    return;
                }
                
                console.log('Loaded documents:', currentDocuments.length);
                
                // Load existing page typings
                loadExistingPageTypings(fileIndexingId);
                
                // Initialize document viewer
                currentDocumentIndex = 0;
                loadDocument(0);
                updateDocumentCounter();
                updateTypingProgress();
            } else {
                throw new Error(data.message || 'Failed to load documents');
            }
        })
        .catch(error => {
            console.error('Error loading file for typing:', error);
            showDocumentError('Error loading file: ' + error.message);
        });
    }
    
    // Show loading state
    function showLoadingState() {
        if (documentViewer) {
            documentViewer.innerHTML = `
                <div class="text-center text-gray-500">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                    <p class="text-lg">Loading documents...</p>
                    <p class="text-sm">Please wait while we prepare your files</p>
                </div>
            `;
        }
        if (currentDocumentInfo) {
            currentDocumentInfo.textContent = 'Loading...';
        }
    }
    
    // Show empty state
    function showEmptyState() {
        if (documentViewer) {
            documentViewer.innerHTML = `
                <div class="text-center text-gray-500">
                    <i data-lucide="inbox" class="h-16 w-16 mx-auto mb-4 text-gray-300"></i>
                    <p class="text-lg">No documents found</p>
                    <p class="text-sm">Upload scanned documents first to begin page typing</p>
                </div>
            `;
            lucide.createIcons();
        }
        if (currentDocumentInfo) {
            currentDocumentInfo.textContent = 'No documents';
        }
    }
    
    // Load existing page typings
    function loadExistingPageTypings(fileIndexingId) {
        fetch(`{{ route("pagetyping.list") }}?file_indexing_id=${fileIndexingId}`)
        .then(response => {
            if (!response.ok) {
                console.warn('Could not load existing page typings:', response.status);
                return { success: false, page_typings: [] };
            }
            return response.json();
        })
        .then(data => {
            console.log('Page typings data:', data);
            if (data.success && data.page_typings) {
                savedPages = data.page_typings || [];
                updateTypingProgress();
                
                // If there are existing typings, load the last one
                if (savedPages.length > 0) {
                    const lastPage = savedPages[savedPages.length - 1];
                    currentPageNumber = lastPage.page_number + 1;
                    if (pageNumberInput) pageNumberInput.value = currentPageNumber;
                    if (serialNumberInput) serialNumberInput.value = savedPages.length + 1;
                }
            } else {
                savedPages = [];
                updateTypingProgress();
            }
        })
        .catch(error => {
            console.error('Error loading existing page typings:', error);
            savedPages = [];
            updateTypingProgress();
        });
    }
    
    // Load document in viewer
    async function loadDocument(index) {
        if (index < 0 || index >= currentDocuments.length) return;
        
        console.log('Loading document at index:', index);
        currentDocumentIndex = index;
        const document = currentDocuments[index];
        
        if (!document || !document.file_url) {
            showDocumentError('Document not available');
            return;
        }
        
        // Update document info
        const filename = document.filename || document.file_name || 'Unknown file';
        const fileExtension = filename.split('.').pop().toLowerCase();
        
        if (currentDocumentInfo) {
            currentDocumentInfo.textContent = `${filename} (${fileExtension.toUpperCase()})`;
        }
        
        // Reset PDF state
        currentPdfPages = [];
        currentPdfPageIndex = 0;
        if (pdfPageControls) {
            pdfPageControls.style.display = 'none';
        }
        
        try {
            console.log('File extension:', fileExtension);
            if (['jpg', 'jpeg', 'png', 'gif', 'tiff', 'bmp', 'webp'].includes(fileExtension)) {
                await loadImageDocument(document);
            } else if (fileExtension === 'pdf') {
                await loadPdfDocument(document);
            } else {
                loadGenericDocument(document);
            }
        } catch (error) {
            console.error('Error loading document:', error);
            showDocumentError('Failed to load document: ' + error.message);
        }
        
        updateDocumentCounter();
        updateCurrentPageInfo();
    }
    
    // Load image document
    async function loadImageDocument(document) {
        console.log('Loading image document:', document.file_url);
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                console.log('Image loaded successfully');
                if (documentViewer) {
                    documentViewer.innerHTML = `
                        <div class="flex items-center justify-center min-h-full p-4">
                            <img src="${document.file_url}" alt="Document" 
                                 class="max-w-full max-h-full object-contain shadow-lg rounded-lg"
                                 style="transform: scale(${zoomLevel})">
                        </div>
                    `;
                }
                resolve();
            };
            img.onerror = (error) => {
                console.error('Image load error:', error);
                reject(new Error('Failed to load image'));
            };
            img.src = document.file_url;
        });
    }
    
    // Load PDF document
    async function loadPdfDocument(document) {
        console.log('Loading PDF document:', document.file_url);
        try {
            showPdfExtractionModal();
            updatePdfExtractionStatus('Loading PDF...', 10);
            
            // Ensure PDF.js is available and worker is configured
            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF.js library not loaded');
            }
            
            if (!pdfjsLib.GlobalWorkerOptions.workerSrc) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            }
            
            console.log('PDF.js worker src:', pdfjsLib.GlobalWorkerOptions.workerSrc);
            
            updatePdfExtractionStatus('Connecting to PDF...', 20);
            
            // First, test if we can fetch the file
            console.log('Testing PDF file access...');
            const testResponse = await fetch(document.file_url, { method: 'HEAD' });
            if (!testResponse.ok) {
                throw new Error(`Cannot access PDF file: HTTP ${testResponse.status}`);
            }
            
            const contentLength = testResponse.headers.get('content-length');
            console.log('PDF file size:', contentLength, 'bytes');
            
            if (contentLength === '0') {
                throw new Error('PDF file is empty (0 bytes)');
            }
            
            updatePdfExtractionStatus('Loading PDF content...', 30);
            
            // Try multiple PDF.js configurations
            let pdf = null;
            const configs = [
                {
                    url: document.file_url,
                    cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
                    cMapPacked: true,
                    disableAutoFetch: false,
                    disableStream: false,
                    disableRange: false,
                    verbosity: pdfjsLib.VerbosityLevel.WARNINGS
                },
                {
                    url: document.file_url,
                    disableStream: true,
                    disableRange: true,
                    disableAutoFetch: true
                },
                {
                    url: document.file_url,
                    verbosity: pdfjsLib.VerbosityLevel.ERRORS
                }
            ];
            
            for (let i = 0; i < configs.length; i++) {
                try {
                    console.log(`Trying PDF.js config ${i + 1}...`);
                    const loadingTask = pdfjsLib.getDocument(configs[i]);
                    pdf = await loadingTask.promise;
                    console.log(`PDF loaded successfully with config ${i + 1}`);
                    break;
                } catch (configError) {
                    console.warn(`Config ${i + 1} failed:`, configError.message);
                    if (i === configs.length - 1) {
                        throw configError;
                    }
                }
            }
            
            if (!pdf) {
                throw new Error('All PDF.js configurations failed');
            }
            
            const numPages = pdf.numPages;
            console.log('PDF loaded, pages:', numPages);
            updatePdfExtractionStatus(`Extracting ${numPages} pages...`, 40);
            
            currentPdfPages = [];
            
            for (let pageNum = 1; pageNum <= numPages; pageNum++) {
                console.log(`Processing PDF page ${pageNum}/${numPages}`);
                updatePdfExtractionStatus(`Processing page ${pageNum} of ${numPages}...`, 40 + (pageNum / numPages) * 50);
                
                try {
                    const page = await pdf.getPage(pageNum);
                    const viewport = page.getViewport({ scale: 1.5 });
                    
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    await page.render({
                        canvasContext: context,
                        viewport: viewport
                    }).promise;
                    
                    currentPdfPages.push({
                        canvas: canvas,
                        pageNumber: pageNum,
                        width: viewport.width,
                        height: viewport.height
                    });
                } catch (pageError) {
                    console.error(`Error processing page ${pageNum}:`, pageError);
                    // Continue with other pages
                }
            }
            
            if (currentPdfPages.length === 0) {
                throw new Error('No pages could be extracted from PDF');
            }
            
            updatePdfExtractionStatus('Rendering pages...', 95);
            
            // Show PDF controls
            if (pdfPageControls) {
                pdfPageControls.style.display = 'flex';
            }
            currentPdfPageIndex = 0;
            renderPdfPage(0);
            updatePdfPageCounter();
            
            updatePdfExtractionStatus('Complete!', 100);
            setTimeout(() => {
                hidePdfExtractionModal();
            }, 1000);
            
        } catch (error) {
            console.error('PDF loading error:', error);
            updatePdfExtractionStatus('Error: ' + error.message, 0);
            setTimeout(() => {
                hidePdfExtractionModal();
                showDocumentError('Failed to load PDF: ' + error.message);
            }, 3000);
            throw error;
        }
    }
    
    // Render specific PDF page
    function renderPdfPage(pageIndex) {
        if (pageIndex < 0 || pageIndex >= currentPdfPages.length) return;
        
        currentPdfPageIndex = pageIndex;
        const pdfPage = currentPdfPages[pageIndex];
        
        if (documentViewer) {
            documentViewer.innerHTML = `
                <div class="flex items-center justify-center min-h-full p-4">
                    <div class="pdf-page" style="transform: scale(${zoomLevel})">
                        <canvas width="${pdfPage.width}" height="${pdfPage.height}"></canvas>
                    </div>
                </div>
            `;
            
            const canvas = documentViewer.querySelector('canvas');
            if (canvas) {
                const context = canvas.getContext('2d');
                context.drawImage(pdfPage.canvas, 0, 0);
            }
        }
        
        updatePdfPageCounter();
        updateCurrentPageInfo();
    }
    
    // Load generic document
    function loadGenericDocument(document) {
        const filename = document.filename || document.file_name || 'Unknown file';
        if (documentViewer) {
            documentViewer.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full text-gray-500 p-8">
                    <i data-lucide="file-text" class="h-24 w-24 mb-6 text-gray-300"></i>
                    <h3 class="text-xl font-semibold mb-2">${filename}</h3>
                    <p class="text-sm text-gray-400 mb-6 text-center">
                        This file type cannot be previewed directly.<br>
                        Click the button below to open it in a new tab.
                    </p>
                    <a href="${document.file_url}" target="_blank" class="btn btn-primary">
                        <i data-lucide="external-link" class="h-4 w-4 mr-2"></i>
                        Open Document
                    </a>
                </div>
            `;
            lucide.createIcons();
        }
    }
    
    // Show document error
    function showDocumentError(message) {
        if (documentViewer) {
            documentViewer.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full text-gray-500 p-8">
                    <i data-lucide="alert-circle" class="h-16 w-16 mb-4 text-red-400"></i>
                    <p class="text-lg font-medium text-red-600">${message}</p>
                </div>
            `;
            lucide.createIcons();
        }
    }
    
    // Update document counter
    function updateDocumentCounter() {
        if (documentCounter) {
            documentCounter.textContent = `${currentDocumentIndex + 1} / ${currentDocuments.length}`;
        }
        
        // Update navigation buttons
        if (prevDocumentBtn) {
            prevDocumentBtn.disabled = currentDocumentIndex === 0;
        }
        if (nextDocumentBtn) {
            nextDocumentBtn.disabled = currentDocumentIndex === currentDocuments.length - 1;
        }
    }
    
    // Update PDF page counter
    function updatePdfPageCounter() {
        if (pdfPageCounter && currentPdfPages.length > 0) {
            pdfPageCounter.textContent = `${currentPdfPageIndex + 1} / ${currentPdfPages.length}`;
        }
        
        // Update PDF navigation buttons
        if (prevPdfPageBtn) {
            prevPdfPageBtn.disabled = currentPdfPageIndex === 0;
        }
        if (nextPdfPageBtn) {
            nextPdfPageBtn.disabled = currentPdfPageIndex === currentPdfPages.length - 1;
        }
    }
    
    // Update current page info
    function updateCurrentPageInfo() {
        if (currentPageInfo) {
            let pageInfo = `Page ${currentPageNumber}`;
            if (currentPdfPages.length > 0) {
                pageInfo += ` (PDF Page ${currentPdfPageIndex + 1})`;
            }
            pageInfo += ` of Document ${currentDocumentIndex + 1}`;
            currentPageInfo.textContent = pageInfo;
        }
    }
    
    // Update typing progress
    function updateTypingProgress() {
        // Calculate total pages from all documents and PDF pages
        totalPages = currentDocuments.reduce((total, doc, index) => {
            if (index === currentDocumentIndex && currentPdfPages.length > 0) {
                return total + currentPdfPages.length;
            }
            return total + 1; // Default 1 page per document
        }, 0);
        
        const completedPages = savedPages.length;
        const progressPercentage = totalPages > 0 ? (completedPages / totalPages) * 100 : 0;
        
        if (typingProgress) {
            typingProgress.textContent = `${completedPages} / ${totalPages} pages`;
        }
        if (typingProgressBar) {
            typingProgressBar.style.width = progressPercentage + '%';
        }
        if (completeTypingBtn) {
            completeTypingBtn.disabled = completedPages < totalPages;
        }
    }
    
    // PDF extraction modal functions
    function showPdfExtractionModal() {
        console.log('Showing PDF extraction modal');
        if (pdfExtractionModal) {
            pdfExtractionModal.classList.remove('hidden');
            pdfExtractionModal.setAttribute('aria-hidden', 'false');
        }
    }
    
    function hidePdfExtractionModal() {
        console.log('Hiding PDF extraction modal');
        if (pdfExtractionModal) {
            pdfExtractionModal.classList.add('hidden');
            pdfExtractionModal.setAttribute('aria-hidden', 'true');
        }
    }
    
    function updatePdfExtractionStatus(status, progress) {
        console.log('PDF extraction status:', status, progress + '%');
        if (pdfExtractionStatus) {
            pdfExtractionStatus.textContent = status;
        }
        if (pdfExtractionProgress) {
            pdfExtractionProgress.style.width = progress + '%';
        }
    }
    
    // Event listeners
    if (closePdfModal) {
        closePdfModal.addEventListener('click', hidePdfExtractionModal);
    }
    
    if (prevDocumentBtn) {
        prevDocumentBtn.addEventListener('click', () => {
            if (currentDocumentIndex > 0) {
                loadDocument(currentDocumentIndex - 1);
            }
        });
    }
    
    if (nextDocumentBtn) {
        nextDocumentBtn.addEventListener('click', () => {
            if (currentDocumentIndex < currentDocuments.length - 1) {
                loadDocument(currentDocumentIndex + 1);
            }
        });
    }
    
    if (prevPdfPageBtn) {
        prevPdfPageBtn.addEventListener('click', () => {
            if (currentPdfPageIndex > 0) {
                renderPdfPage(currentPdfPageIndex - 1);
            }
        });
    }
    
    if (nextPdfPageBtn) {
        nextPdfPageBtn.addEventListener('click', () => {
            if (currentPdfPageIndex < currentPdfPages.length - 1) {
                renderPdfPage(currentPdfPageIndex + 1);
            }
        });
    }
    
    // Zoom controls
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', () => {
            zoomLevel = Math.max(0.5, zoomLevel - 0.25);
            updateZoom();
        });
    }
    
    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', () => {
            zoomLevel = Math.min(3, zoomLevel + 0.25);
            updateZoom();
        });
    }
    
    if (zoomFitBtn) {
        zoomFitBtn.addEventListener('click', () => {
            zoomLevel = 1;
            updateZoom();
        });
    }
    
    function updateZoom() {
        if (zoomLevelSpan) {
            zoomLevelSpan.textContent = Math.round(zoomLevel * 100) + '%';
        }
        
        // Re-render current document with new zoom
        if (currentPdfPages.length > 0) {
            renderPdfPage(currentPdfPageIndex);
        } else if (currentDocuments.length > 0) {
            loadDocument(currentDocumentIndex);
        }
    }
    
    // Quick type buttons
    quickTypeButtons.forEach(button => {
        button.addEventListener('click', () => {
            const pageType = button.getAttribute('data-type');
            if (pageTypeSelect) {
                pageTypeSelect.value = pageType;
            }
        });
    });
    
    // Save page functionality
    if (savePageBtn) {
        savePageBtn.addEventListener('click', savePage);
    }
    
    if (saveAndNextBtn) {
        saveAndNextBtn.addEventListener('click', () => {
            savePage().then(() => {
                // Move to next page/document
                if (currentPdfPages.length > 0 && currentPdfPageIndex < currentPdfPages.length - 1) {
                    renderPdfPage(currentPdfPageIndex + 1);
                } else if (currentDocumentIndex < currentDocuments.length - 1) {
                    loadDocument(currentDocumentIndex + 1);
                }
                
                // Update page number
                currentPageNumber++;
                if (pageNumberInput) pageNumberInput.value = currentPageNumber;
                if (serialNumberInput) serialNumberInput.value = savedPages.length + 1;
            });
        });
    }
    
    async function savePage() {
        if (!pageTypeSelect || !pageTypeSelect.value) {
            alert('Please select a page type');
            return;
        }
        
        const pageData = {
            file_indexing_id: selectedFileIndexing.id,
            scanning_id: currentDocuments[currentDocumentIndex]?.id || 1,
            page_number: pageNumberInput ? parseInt(pageNumberInput.value) : currentPageNumber,
            page_type: pageTypeSelect.value,
            page_subtype: pageSubtypeInput ? pageSubtypeInput.value : '',
            serial_number: serialNumberInput ? parseInt(serialNumberInput.value) : savedPages.length + 1,
            page_code: pageCodeInput ? pageCodeInput.value : '',
            file_path: currentDocuments[currentDocumentIndex]?.file_url || '',
        };
        
        try {
            const response = await fetch('{{ route("pagetyping.save-single") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(pageData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('Page saved successfully');
                savedPages.push(pageData);
                updateTypingProgress();
                
                // Clear form for next page
                if (pageSubtypeInput) pageSubtypeInput.value = '';
                if (pageCodeInput) pageCodeInput.value = '';
                if (pageNotesInput) pageNotesInput.value = '';
                if (isImportantInput) isImportantInput.checked = false;
            } else {
                throw new Error(result.message || 'Failed to save page');
            }
        } catch (error) {
            console.error('Error saving page:', error);
            alert('Error saving page: ' + error.message);
        }
    }
    
    // Add the missing startPageTyping function for compatibility
    window.startPageTyping = function(fileIndexingId) {
        console.log('Starting page typing for file ID:', fileIndexingId);
        if (selectedFileIndexing && selectedFileIndexing.id === fileIndexingId) {
            loadFileForTyping(fileIndexingId);
        } else {
            console.error('File indexing ID mismatch or not set');
        }
    };
    
    console.log('Page Typing Interface: Initialization complete');
});
</script>
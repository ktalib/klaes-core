<!-- Complete Page Typing Interface JavaScript with PDF Support -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
// Configure PDF.js worker immediately
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    console.log('PDF.js worker configured');
} else {
    console.error('PDF.js library not loaded!');
}

// Global variables for typing interface
let selectedFileIndexing = null;
let currentDocuments = [];
let currentDocumentIndex = 0;
let currentPdfPages = [];
let currentPdfPageIndex = 0;
let currentPageNumber = 1;
let savedPages = [];
let totalPages = 0;
let zoomLevel = 1;

// Initialize typing interface when needed
function initializeTypingInterface() {
    @if(isset($selectedFileIndexing))
        console.log('Initializing typing interface for file:', {{ $selectedFileIndexing->id }});
        
        // Set the selected file
        selectedFileIndexing = @json($selectedFileIndexing);
        
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
            const filename = document.filename || document.file_name || document.original_filename || 'Unknown file';
            
            // Get file extension from multiple sources
            let fileExtension = '';
            if (document.document_path) {
                fileExtension = document.document_path.split('.').pop().toLowerCase();
            } else if (filename) {
                fileExtension = filename.split('.').pop().toLowerCase();
            }
            
            console.log('Document info:', {
                filename: filename,
                document_path: document.document_path,
                file_url: document.file_url,
                fileExtension: fileExtension
            });
            
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
                console.log('File extension detected:', fileExtension);
                console.log('File URL:', document.file_url);
                
                if (['jpg', 'jpeg', 'png', 'gif', 'tiff', 'bmp', 'webp'].includes(fileExtension)) {
                    console.log('Loading as image document');
                    await loadImageDocument(document);
                } else if (fileExtension === 'pdf') {
                    console.log('Loading as PDF document');
                    await loadPdfDocument(document);
                } else {
                    console.log('Loading as generic document');
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

        // Load PDF document - Complete implementation
        async function loadPdfDocument(document) {
            console.log('Loading PDF document:', document.file_url);
            try {
                showPdfExtractionModal();
                updatePdfExtractionStatus('Initializing PDF.js...', 5);
                
                // Wait a bit to ensure the modal is visible
                await new Promise(resolve => setTimeout(resolve, 200));
                
                // Check if PDF.js is available
                console.log('Checking PDF.js availability...');
                updatePdfExtractionStatus('Checking PDF.js library...', 10);
                
                if (typeof pdfjsLib === 'undefined') {
                    console.error('PDF.js library not found');
                    throw new Error('PDF.js library not loaded. Please refresh the page and try again.');
                }
                
                console.log('PDF.js version:', pdfjsLib.version);
                
                // Configure worker
                updatePdfExtractionStatus('Configuring PDF worker...', 15);
                if (!pdfjsLib.GlobalWorkerOptions.workerSrc) {
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                }
                
                console.log('PDF.js worker src:', pdfjsLib.GlobalWorkerOptions.workerSrc);
                
                // Test file accessibility
                updatePdfExtractionStatus('Testing file access...', 20);
                console.log('Testing PDF file access:', document.file_url);
                
                try {
                    const testResponse = await fetch(document.file_url, { 
                        method: 'HEAD',
                        mode: 'cors',
                        credentials: 'same-origin'
                    });
                    
                    if (!testResponse.ok) {
                        throw new Error(`Cannot access PDF file: HTTP ${testResponse.status} ${testResponse.statusText}`);
                    }
                } catch (fetchError) {
                    console.error('File access test failed:', fetchError);
                    throw new Error(`Cannot access PDF file: ${fetchError.message}`);
                }
                
                // Load PDF
                updatePdfExtractionStatus('Loading PDF document...', 30);
                console.log('Loading PDF with PDF.js...');
                
                let pdf = null;
                
                try {
                    const loadingTask = pdfjsLib.getDocument({
                        url: document.file_url,
                        verbosity: pdfjsLib.VerbosityLevel.INFOS
                    });
                    
                    // Add progress listener
                    loadingTask.onProgress = function(progress) {
                        if (progress.total > 0) {
                            const percent = Math.round((progress.loaded / progress.total) * 100);
                            console.log(`PDF loading progress: ${percent}%`);
                            updatePdfExtractionStatus(`Loading PDF... ${percent}%`, 30 + (percent * 0.15));
                        }
                    };
                    
                    pdf = await loadingTask.promise;
                    console.log('PDF loaded successfully');
                    
                } catch (simpleError) {
                    console.warn('Simple PDF load failed:', simpleError.message);
                    
                    // Try with CORS disabled
                    console.log('Trying with CORS disabled...');
                    try {
                        const loadingTask = pdfjsLib.getDocument({
                            url: document.file_url,
                            disableStream: true,
                            disableRange: true,
                            disableAutoFetch: true,
                            verbosity: pdfjsLib.VerbosityLevel.ERRORS
                        });
                        
                        pdf = await loadingTask.promise;
                        console.log('PDF loaded successfully with CORS disabled');
                        
                    } catch (corsError) {
                        console.error('CORS disabled load failed:', corsError.message);
                        throw new Error(`Failed to load PDF: ${corsError.message}`);
                    }
                }
                
                if (!pdf) {
                    throw new Error('PDF loading failed - no PDF object returned');
                }
                
                const numPages = pdf.numPages;
                console.log('PDF loaded successfully! Pages:', numPages);
                updatePdfExtractionStatus(`PDF loaded! Extracting ${numPages} pages...`, 50);
                
                if (numPages === 0) {
                    throw new Error('PDF has no pages');
                }
                
                // Extract pages
                currentPdfPages = [];
                let successfulPages = 0;
                let failedPages = 0;
                
                console.log(`Starting page extraction for ${numPages} pages...`);
                
                for (let pageNum = 1; pageNum <= numPages; pageNum++) {
                    console.log(`Processing PDF page ${pageNum}/${numPages}`);
                    const progressPercent = 50 + ((pageNum - 1) / numPages) * 40;
                    updatePdfExtractionStatus(`Extracting page ${pageNum} of ${numPages}...`, progressPercent);
                    
                    try {
                        const page = await pdf.getPage(pageNum);
                        const viewport = page.getViewport({ scale: 1.5 });
                        
                        if (viewport.width === 0 || viewport.height === 0) {
                            throw new Error(`Page ${pageNum} has invalid dimensions: ${viewport.width}x${viewport.height}`);
                        }
                        
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        
                        if (!context) {
                            throw new Error(`Failed to get 2D context for page ${pageNum}`);
                        }
                        
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        const renderTask = page.render({
                            canvasContext: context,
                            viewport: viewport
                        });
                        
                        await renderTask.promise;
                        console.log(`Page ${pageNum} rendered successfully`);
                        
                        currentPdfPages.push({
                            canvas: canvas,
                            pageNumber: pageNum,
                            width: viewport.width,
                            height: viewport.height
                        });
                        
                        successfulPages++;
                        
                    } catch (pageError) {
                        failedPages++;
                        console.error(`Error processing page ${pageNum}:`, pageError.message);
                    }
                }
                
                console.log(`Page extraction summary:`, {
                    totalPages: numPages,
                    successfulPages: successfulPages,
                    failedPages: failedPages,
                    extractedPages: currentPdfPages.length
                });
                
                if (currentPdfPages.length === 0) {
                    throw new Error(`No pages could be extracted from PDF. Total pages: ${numPages}, Successful: ${successfulPages}, Failed: ${failedPages}.`);
                }
                
                console.log(`Successfully extracted ${currentPdfPages.length} pages`);
                updatePdfExtractionStatus('Finalizing...', 95);
                
                // Show PDF controls
                if (pdfPageControls) {
                    pdfPageControls.style.display = 'flex';
                }
                currentPdfPageIndex = 0;
                renderPdfPage(0);
                updatePdfPageCounter();
                
                updatePdfExtractionStatus('Complete!', 100);
                console.log('PDF extraction completed successfully');
                
                // Hide modal after a short delay
                setTimeout(() => {
                    hidePdfExtractionModal();
                }, 1500);
                
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
                        <p class="text-lg font-medium text-red-600 text-center">${message}</p>
                        <button onclick="location.reload()" class="btn btn-outline mt-4">
                            <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                            Refresh Page
                        </button>
                    </div>
                `;
                lucide.createIcons();
            }
        }

        // PDF extraction modal functions
        function showPdfExtractionModal() {
            console.log('Showing PDF extraction modal');
            if (pdfExtractionModal) {
                pdfExtractionModal.classList.remove('hidden');
                pdfExtractionModal.setAttribute('aria-hidden', 'false');
                pdfExtractionModal.style.display = 'block';
            }
        }
        
        function hidePdfExtractionModal() {
            console.log('Hiding PDF extraction modal');
            if (pdfExtractionModal) {
                pdfExtractionModal.classList.add('hidden');
                pdfExtractionModal.setAttribute('aria-hidden', 'true');
                pdfExtractionModal.style.display = 'none';
                console.log('PDF extraction modal hidden successfully');
            } else {
                console.warn('PDF extraction modal element not found');
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

        // Event listeners for navigation
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

        // Complete typing button
        if (completeTypingBtn) {
            completeTypingBtn.addEventListener('click', async () => {
                if (confirm('Are you sure you want to complete page typing for this file? This action cannot be undone.')) {
                    try {
                        const response = await fetch(`{{ route("pagetyping.store") }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            },
                            body: JSON.stringify({
                                file_indexing_id: selectedFileIndexing.id,
                                action: 'complete'
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('Page typing completed successfully!');
                            // Redirect back to dashboard
                            window.location.href = '{{ route("pagetyping.index") }}';
                        } else {
                            throw new Error(result.message || 'Failed to complete page typing');
                        }
                    } catch (error) {
                        console.error('Error completing page typing:', error);
                        alert('Error completing page typing: ' + error.message);
                    }
                }
            });
        }

        // PDF modal close button
        if (closePdfModal) {
            closePdfModal.addEventListener('click', hidePdfExtractionModal);
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
    @endif
}
</script>
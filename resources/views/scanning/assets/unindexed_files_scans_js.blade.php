<script>
    // Global variables
    let uploadStatus = 'idle';
    let uploadProgress = 0;
    let selectedFiles = [];
    let uploadedFiles = [];
    let aiProcessingStage = 'idle';
    let aiProgress = 0;
    let extractedMetadata = {}; // Now keyed by uploadedFile.id
    let currentEditingFile = null;
    let ocrProgress = 0;
    let filteredFiles = []; // For search functionality
    let currentPDFDocument = null; // Stores the PDFDocumentProxy object for the currently opened PDF in the modal
    let currentPageNumber = 1;    // Stores the current page number being viewed in the PDF preview

    // Initialize PDF.js worker
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    // Initialize the page when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializePage();
    });

    function initializePage() {
        // Set up event listeners
        setupEventListeners();
        
        // Initialize displays
        updateUploadedFilesDisplay();
        updateStats();
        
        console.log('File Upload System initialized');
    }

    function setupEventListeners() {
        // Tab functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                switchTab(tabName);
            });
        });

        // File upload
        const fileInput = document.getElementById('file-upload');
        if (fileInput) {
            fileInput.addEventListener('change', handleFileSelect);
        }

        // Search functionality
        const searchInput = document.getElementById('file-search');
        if (searchInput) {
            searchInput.addEventListener('input', handleFileSearch);
        }

        // Drag and drop functionality
        const uploadArea = document.getElementById('upload-area');
        if (uploadArea) {
            uploadArea.addEventListener('click', () => {
                document.getElementById('file-upload').click();
            });

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('border-blue-500', 'bg-blue-50');
            });

            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('border-blue-500', 'bg-blue-50');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('border-blue-500', 'bg-blue-50');
                const files = Array.from(e.dataTransfer.files);
                handleFiles(files);
            });
        }
    }

    // Search functionality
    function handleFileSearch(e) {
        const searchTerm = e.target.value.toLowerCase();
        if (searchTerm === '') {
            filteredFiles = uploadedFiles;
        } else {
            filteredFiles = uploadedFiles.filter(file => 
                file.name.toLowerCase().includes(searchTerm) ||
                file.type.toLowerCase().includes(searchTerm) ||
                file.status.toLowerCase().includes(searchTerm)
            );
        }
        updateUploadedFilesDisplay();
    }

    // Tab functionality
    function switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });
        const activeTab = document.querySelector(`[data-tab="${tabName}"]`);
        if (activeTab) {
            activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
        }

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        const activeContent = document.getElementById(`${tabName}-tab`);
        if (activeContent) {
            activeContent.classList.remove('hidden');
        }
    }

    function switchToUpload() {
        switchTab('upload');
    }

    function switchToUploadedFiles() {
        switchTab('uploaded-files');
    }

    // File handling
    function handleFileSelect(e) {
        const files = Array.from(e.target.files);
        handleFiles(files);
    }

    async function handleFiles(files) {
        selectedFiles = files;
        
        // filePreviewData is no longer needed as original file reference is stored in uploadedFiles
        
        updateSelectedFilesDisplay();
        updateUploadButtons();
    }

    function updateSelectedFilesDisplay() {
        const container = document.getElementById('selected-files');
        const list = document.getElementById('selected-files-list');
        const count = document.getElementById('selected-count');

        if (!container || !list || !count) return;

        if (selectedFiles.length === 0) {
            container.classList.add('hidden');
            return;
        }

        container.classList.remove('hidden');
        count.textContent = `${selectedFiles.length} files selected`;

        list.innerHTML = selectedFiles.map((file, index) => `
            <div class="flex items-center justify-between p-3">
                <div class="flex items-center gap-3">
                    ${getFileIcon(file.type)}
                    <div>
                        <p class="font-medium">${file.name}</p>
                        <p class="text-xs text-gray-500">${formatFileSize(file.size)}</p>
                    </div>
                </div>
                <button class="text-gray-400 hover:text-gray-600" onclick="removeSelectedFile(${index})">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `).join('');
    }

    function removeSelectedFile(index) {
        // No need to delete from filePreviewData anymore
        selectedFiles.splice(index, 1);
        updateSelectedFilesDisplay();
        updateUploadButtons();
    }

    function clearAllFiles() {
        selectedFiles = [];
        // filePreviewData = {}; // No longer needed
        updateSelectedFilesDisplay();
        updateUploadButtons();
    }

    function updateUploadButtons() {
        const startBtn = document.getElementById('start-upload-btn');
        const cancelBtn = document.getElementById('cancel-upload-btn');
        const uploadMoreBtn = document.getElementById('upload-more-btn');
        const viewFilesBtn = document.getElementById('view-files-btn');

        // Hide all buttons first
        [startBtn, cancelBtn, uploadMoreBtn, viewFilesBtn].forEach(btn => {
            if (btn) btn.classList.add('hidden');
        });

        if (uploadStatus === 'idle' && selectedFiles.length > 0) {
            if (startBtn) startBtn.classList.remove('hidden');
        } else if (uploadStatus === 'uploading') {
            if (cancelBtn) cancelBtn.classList.remove('hidden');
        } else if (uploadStatus === 'complete') {
            if (uploadMoreBtn) uploadMoreBtn.classList.remove('hidden');
            if (viewFilesBtn) viewFilesBtn.classList.remove('hidden');
        }
    }

    // Real backend upload for unindexed files
    async function startUpload() {
        if (selectedFiles.length === 0) {
            alert('Please select files to upload');
            return;
        }

        uploadStatus = 'uploading';
        uploadProgress = 0;
        updateUploadStatus();
        updateUploadButtons();

        // Show progress bar
        const progressDiv = document.getElementById('upload-progress');
        if (progressDiv) progressDiv.classList.remove('hidden');

        // Create FormData for real backend upload
        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));
        
        // Add files to FormData
        selectedFiles.forEach((file, index) => {
            formData.append(`documents[${index}]`, file);
        });
        
        // Add extracted metadata if available
        if (Object.keys(extractedMetadata).length > 0) {
            formData.append('extracted_metadata', JSON.stringify(extractedMetadata));
        }

        // Add files to uploaded list with original file references immediately
        const newFiles = selectedFiles.map((file, index) => ({
            id: `UPLOAD-${Date.now()}-${index}`, // Unique ID for each uploaded file
            name: file.name,
            size: formatFileSize(file.size),
            type: file.type || getFileTypeFromName(file.name),
            status: 'Uploading...', // Initial status
            date: new Date().toLocaleDateString(),
            file: file // Store original file reference
        }));

        uploadedFiles = [...newFiles, ...uploadedFiles];
        filteredFiles = uploadedFiles; // Initialize filtered files
        updateUploadedFilesDisplay(); // Update display to show new files as 'Uploading...'

        try {
            // Send to real backend
            const response = await fetch('{{ route("scanning.upload-unindexed") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': formData.get('_token')
                }
            });

            const result = await response.json();

            if (result.success) {
                // Update progress to 100%
                uploadProgress = 100;
                updateUploadProgress();
                
                uploadStatus = 'complete';
                updateUploadStatus();
                updateUploadButtons();

                // Update status of newly uploaded files
                newFiles.forEach((file, index) => {
                    const uploadedDoc = result.uploaded_documents[index];
                    if (uploadedDoc) {
                        const fileIndex = uploadedFiles.findIndex(f => f.id === file.id);
                        if (fileIndex !== -1) {
                            uploadedFiles[fileIndex].status = 'Indexed & Scanned';
                            uploadedFiles[fileIndex].backendId = uploadedDoc.id;
                            uploadedFiles[fileIndex].file_indexing_id = uploadedDoc.file_indexing_id;
                            uploadedFiles[fileIndex].file_number = uploadedDoc.file_number;
                        }
                    }
                });

                updateUploadedFilesDisplay();
                updateStats();

                // Show success message with redirect option
                if (result.created_indexings && result.created_indexings.length > 0) {
                    const indexing = result.created_indexings[0];
                    if (confirm(`Files successfully uploaded and indexed!\n\nFile Number: ${indexing.file_number}\nTitle: ${indexing.file_title}\n\nWould you like to proceed to Page Typing?`)) {
                        window.location.href = `{{ route('pagetyping.index') }}?file_indexing_id=${indexing.id}`;
                    }
                }

                // Clear selected files for next upload
                selectedFiles = [];
                updateSelectedFilesDisplay();

            } else {
                throw new Error(result.message || 'Upload failed');
            }

        } catch (error) {
            console.error('Upload error:', error);
            
            // Update file status to show error
            newFiles.forEach(file => {
                const fileIndex = uploadedFiles.findIndex(f => f.id === file.id);
                if (fileIndex !== -1) {
                    uploadedFiles[fileIndex].status = 'Upload Failed';
                }
            });
            
            updateUploadedFilesDisplay();
            
            uploadStatus = 'error';
            updateUploadStatus();
            updateUploadButtons();
            
            alert('Upload failed: ' + error.message);
        }
    }

    function cancelUpload() {
        uploadStatus = 'idle';
        uploadProgress = 0;
        updateUploadStatus();
        updateUploadButtons();
        const progressDiv = document.getElementById('upload-progress');
        if (progressDiv) progressDiv.classList.add('hidden');
    }

    function resetUpload() {
        uploadStatus = 'idle';
        uploadProgress = 0;
        selectedFiles = [];
        // filePreviewData = {}; // No longer needed
        aiProcessingStage = 'idle';
        aiProgress = 0;
        extractedMetadata = {}; // Clear extracted metadata
        
        updateUploadStatus();
        updateSelectedFilesDisplay();
        updateUploadButtons();
        
        const progressDiv = document.getElementById('upload-progress');
        const aiDiv = document.getElementById('ai-processing');
        if (progressDiv) aiDiv.classList.add('hidden');
        if (progressDiv) progressDiv.classList.add('hidden');
    }

    function updateUploadStatus() {
        const statusText = document.getElementById('uploadStatusText');
        const statusBadge = document.getElementById('uploadStatusBadge');

        if (!statusText || !statusBadge) return;

        let text, badgeText, badgeClass;

        switch (uploadStatus) {
            case 'idle':
                text = 'Ready';
                badgeText = 'Ready';
                badgeClass = 'bg-green-100 text-green-800';
                break;
            case 'uploading':
                text = 'Uploading...';
                badgeText = 'Active';
                badgeClass = 'bg-blue-100 text-blue-800';
                break;
            case 'complete':
                text = 'Complete';
                badgeText = 'Complete';
                badgeClass = 'bg-green-100 text-green-800';
                break;
            case 'error':
                text = 'Error';
                badgeText = 'Error';
                badgeClass = 'bg-red-100 text-red-800';
                break;
        }

        statusText.textContent = text;
        statusBadge.textContent = badgeText;
        statusBadge.className = `ml-2 px-2 py-1 text-xs font-medium rounded-full ${badgeClass}`;
    }

    function updateUploadProgress() {
        const percentEl = document.getElementById('upload-progress-percent');
        const barEl = document.getElementById('upload-progress-bar');
        
        if (percentEl) percentEl.textContent = `${uploadProgress}%`;
        if (barEl) barEl.style.width = `${uploadProgress}%`;
    }

    function updateStats() {
        const todaysEl = document.getElementById('todaysUploads');
        const pendingEl = document.getElementById('pendingIndexing');
        
        if (todaysEl) todaysEl.textContent = uploadedFiles.length;
        if (pendingEl) pendingEl.textContent = uploadedFiles.filter(f => f.status !== 'Indexed').length; // Count files not yet indexed
    }

    // AI Processing functionality with real OCR
    async function startAiProcessing(fileIdsToProcess) {
        aiProcessingStage = 'analyzing';
        aiProgress = 0;
        
        const aiDiv = document.getElementById('ai-processing');
        if (aiDiv) aiDiv.classList.remove('hidden');
        
        updateAiProgress();

        // Show OCR modal
        const ocrModal = document.getElementById('ocr-modal');
        if (ocrModal) ocrModal.classList.remove('hidden');

        try {
            const newExtractedMetadata = {};

            for (let i = 0; i < fileIdsToProcess.length; i++) {
                const fileId = fileIdsToProcess[i];
                const fileEntry = uploadedFiles.find(f => f.id === fileId);
                if (!fileEntry || !fileEntry.file) {
                    console.warn(`File entry not found for ID: ${fileId}`);
                    continue;
                }
                const file = fileEntry.file;
                
                // Update current file being processed
                const currentFileEl = document.getElementById('ocr-current-file');
                if (currentFileEl) {
                    currentFileEl.textContent = `Processing: ${file.name}`;
                }
                
                updateOcrProgress((i / fileIdsToProcess.length) * 25);

                let extractedText = '';

                if (file.type === 'application/pdf') {
                    extractedText = await extractTextFromPDF(file);
                } else if (file.type.startsWith('image/')) {
                    extractedText = await extractTextFromImage(file);
                } else {
                    extractedText = `Unsupported file type: ${file.type}`;
                }

                updateOcrProgress(50 + (i / fileIdsToProcess.length) * 50);

                const fileMetadata = extractMetadataFromText(extractedText, file.name);
                newExtractedMetadata[fileId] = { // Key by the uploadedFile's ID
                    ...fileMetadata,
                    originalFileName: file.name,
                    extractedText: extractedText,
                    fileSize: formatFileSize(file.size),
                    fileType: file.type,
                    file: file // Store original file reference
                };

                // Update the status of the file in the main uploadedFiles array
                const uploadedFileIndex = uploadedFiles.findIndex(f => f.id === fileId);
                if (uploadedFileIndex !== -1) {
                    uploadedFiles[uploadedFileIndex].status = 'Analysis Complete';
                }
            }

            updateOcrProgress(100);
            // Merge new extracted metadata with existing
            extractedMetadata = { ...extractedMetadata, ...newExtractedMetadata };

            setTimeout(() => {
                if (ocrModal) ocrModal.classList.add('hidden');
                
                aiProcessingStage = 'extracting';
                aiProgress = 60;
                updateAiProgress();

                setTimeout(() => {
                    aiProcessingStage = 'creating';
                    aiProgress = 90;
                    updateAiProgress();

                    setTimeout(() => {
                        aiProcessingStage = 'complete';
                        aiProgress = 100;
                        updateAiProgress();
                        showAnalysisResults();
                        updateUploadedFilesDisplay(); // Refresh the table after analysis
                        updateStats();
                    }, 2000);
                }, 2000);
            }, 1000);

        } catch (error) {
            console.error('Error processing documents:', error);
            if (ocrModal) ocrModal.classList.add('hidden');
            aiProcessingStage = 'idle';
            alert('Error processing documents. Please try again.');
        }
    }

    function updateAiProgress() {
        const percentEl = document.getElementById('ai-progress-percent');
        const barEl = document.getElementById('ai-progress-bar');
        
        if (percentEl) percentEl.textContent = `${Math.round(aiProgress)}%`;
        if (barEl) barEl.style.width = `${aiProgress}%`;

        // Update stage indicators
        const stages = ['analyzing', 'extracting', 'creating', 'complete'];
        const currentIndex = stages.indexOf(aiProcessingStage);

        document.querySelectorAll('[data-stage]').forEach((element, index) => {
            const stage = element.dataset.stage;
            element.className = 'text-center p-2 rounded ';

            if (stage === aiProcessingStage) {
                element.className += 'bg-blue-100 text-blue-700';
            } else if (index < currentIndex) {
                element.className += 'bg-green-100 text-green-700';
            } else {
                element.className += 'bg-gray-100 text-gray-500';
            }
        });
    }

    function updateOcrProgress(progress) {
        ocrProgress = progress;
        const percentEl = document.getElementById('ocr-progress-percent');
        const barEl = document.getElementById('ocr-progress-bar');
        
        if (percentEl) percentEl.textContent = `${Math.round(progress)}%`;
        if (barEl) barEl.style.width = `${progress}%`;
    }

    function showAnalysisResults() {
        const resultsContainer = document.getElementById('analysis-results');
        const metadataResults = document.getElementById('metadata-results');
        const filesProcessed = document.getElementById('files-processed');

        if (!resultsContainer || !metadataResults || !filesProcessed) return;

        resultsContainer.classList.remove('hidden');
        filesProcessed.textContent = `${Object.keys(extractedMetadata).length} files processed`;

        // Generate results HTML
        const resultsHTML = Object.entries(extractedMetadata).map(([fileId, data]) => 
            generateMetadataResultHTML(fileId, data)
        ).join('');

        metadataResults.innerHTML = resultsHTML;
    }

    function generateMetadataResultHTML(fileId, data) {
        return `
            <div class="border rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 border-b">
                    <div class="flex justify-between items-center">
                        <div>
                            <h5 class="font-semibold text-gray-900">${data.originalFileName}</h5>
                            <p class="text-sm text-gray-600 mt-1">Document successfully analyzed and processed</p>
                        </div>
                        <span class="px-3 py-1 text-sm font-medium rounded-full ${data.confidence > 70 ? 'bg-green-100 text-green-800 border-green-200' : 'bg-amber-100 text-amber-800 border-amber-200'}">
                            ${data.confidence}% confidence
                        </span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h6 class="font-medium text-gray-900 border-b pb-2">File Numbers</h6>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">New File Number (KANGIS)</span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${data.fileNumberFound ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}">
                                        ${data.fileNumberFound ? '✓ Detected' : '⚠ Not Found'}
                                    </span>
                                </div>
                                <div class="text-lg font-mono bg-white p-2 rounded border">
                                    ${data.extractedFileNumber || 'No file number detected'}
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Property Owner</span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${data.ownerFound ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}">
                                        ${data.ownerFound ? '✓ Detected' : '⚠ Not Found'}
                                    </span>
                                </div>
                                <div class="text-lg font-semibold bg-white p-2 rounded border">
                                    ${data.detectedOwner || 'No owner detected'}
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h6 class="font-medium text-gray-900 border-b pb-2">Property Information</h6>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Plot No:</span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${data.plotNumberFound ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}">
                                        ${data.plotNumberFound ? '✓ Detected' : '⚠ Not Found'}
                                    </span>
                                </div>
                                <div class="text-lg bg-white p-2 rounded border">
                                    ${data.plotNumber || 'No plot number detected'}
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Land Use Type</span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${data.landUseFound ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}">
                                        ${data.landUseFound ? '✓ Detected' : '⚠ Not Found'}
                                    </span>
                                </div>
                                <div class="text-lg bg-white p-2 rounded border">
                                    ${data.landUseType ? `<span class="inline-flex items-center px-2 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">${data.landUseType}</span>` : 'No land use detected'}
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">District/Location</span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${data.districtFound ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}">
                                        ${data.districtFound ? '✓ Detected' : '⚠ Not Found'}
                                    </span>
                                </div>
                                <div class="text-lg bg-white p-2 rounded border">
                                    ${data.district || 'No district detected'}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end mt-6 gap-3">
                        <button class="inline-flex items-center gap-2 px-3 py-1 text-sm border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="openMetadataEditModal('${fileId}')">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Preview Document
                        </button>
                        <button class="inline-flex items-center gap-2 px-3 py-1 text-sm border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="openMetadataEditModal('${fileId}')">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Metadata
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    // Real PDF text extraction using PDF.js
    async function extractTextFromPDF(file) {
        try {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            let fullText = '';
            let hasExtractableText = false;

            // First try to extract text directly from PDF
            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(' ');
                
                if (pageText.trim().length > 0) {
                    fullText += `--- Page ${i} ---\n${pageText}\n\n`;
                    hasExtractableText = true;
                }
            }

            // If we got good text extraction, return it
            if (hasExtractableText && fullText.trim().length > 50) {
                return fullText;
            }

            // Otherwise, fall back to OCR
            console.log('PDF has no extractable text, using OCR...');
            return await extractTextFromPDFWithOCR(file, pdf);
        } catch (error) {
            console.error('Error processing PDF:', error);
            return `Error processing PDF: ${error.message}`;
        }
    }

    // OCR for scanned PDFs
    async function extractTextFromPDFWithOCR(file, pdf) {
        let ocrText = '';

        for (let i = 1; i <= pdf.numPages; i++) {
            updateOcrProgress(25 + ((i - 1) / pdf.numPages) * 50);

            try {
                const page = await pdf.getPage(i);
                const viewport = page.getViewport({ scale: 2.0 });
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };

                await page.render(renderContext).promise;

                // Convert canvas to blob for Tesseract
                const blob = await new Promise(resolve => {
                    canvas.toBlob(resolve, 'image/png');
                });

                // Use Tesseract for OCR
                const { data: { text } } = await Tesseract.recognize(blob, 'eng', {
                    logger: m => {
                        if (m.status === 'recognizing text') {
                            const progress = 25 + ((i - 1) / pdf.numPages) * 50 + (m.progress * 25 / pdf.numPages);
                            updateOcrProgress(progress);
                        }
                    }
                });

                if (text && text.trim().length > 0) {
                    ocrText += `--- Page ${i} (OCR) ---\n${text.trim()}\n\n`;
                }
            } catch (pageError) {
                console.error(`Error processing page ${i}:`, pageError);
                ocrText += `--- Page ${i} (OCR) ---\nError processing this page\n\n`;
            }
        }

        return ocrText || `PDF Document: ${file.name}\nNo readable text could be extracted.`;
    }

    // Real image OCR using Tesseract.js
    async function extractTextFromImage(file) {
        try {
            const { data: { text } } = await Tesseract.recognize(file, 'eng', {
                logger: m => {
                    if (m.status === 'recognizing text') {
                        updateOcrProgress(50 + (m.progress * 50));
                    }
                }
            });
            return text || 'No text could be extracted from this image.';
        } catch (error) {
            console.error('Error during OCR:', error);
            return `Error during OCR: ${error.message}`;
        }
    }

    // Metadata extraction
    function extractMetadataFromText(text, fileName) {
        const defaultMetadata = {
            extractedFileNumber: '',
            fileNumberFound: false,
            oldFileNumber: '',
            oldFileNumberFound: false,
            plotNumber: '', 
            plotNumberFound: false, 
            detectedOwner: '',
            ownerFound: false,
            landUseType: '',
            landUseFound: false,
            district: '',
            districtFound: false,
            documentType: determineDocumentType(fileName, ''),
            documentTypeFound: false,
            confidence: 0,
            pageCount: 1,
            hasSignature: false,
            hasStamp: false,
            quality: 'Poor - No text extracted',
            readyForPageTyping: false,
            extractionStatus: 'No readable text found'
        };

        if (!text || text.trim() === '') {
            return defaultMetadata;
        }

        const cleanText = text.replace(/\s+/g, ' ').trim();
        
        // Extract file numbers
        let newFileNumber = '';
        let newFileNumberFound = false;
        const newFileNumberPatterns = [
            /NEW FILE NUMBER\s+MLKN\s+(\d+)/gi,
            /KANGIS FILE NO\s+MLKN\s+(\d+)/gi,
            /MLKN\s+(\d+)/gi
        ];

        for (const pattern of newFileNumberPatterns) {
            const matches = [...cleanText.matchAll(pattern)];
            if (matches.length > 0 && !newFileNumberFound) {
                const match = matches[0];
                if (match[1]) {
                    const number = match[1].padStart(6, '0');
                    newFileNumber = `MLKN ${number}`;
                    newFileNumberFound = true;
                    break;
                }
            }
        }

        // Extract owner (now "File Name" in the UI)
        let ownerName = '';
        let ownerFound = false;
        const ownerPatterns = [
            /TITLE\s+ALH\.\s+([A-Z\s.]+?)(?:\s+OLD|\n|$)/gi,
            /NAME OF ALLOTTEE\s+ALH\.\s+([A-Z\s.]+?)(?:\n|ADDRESS|$)/gi,
            /ALH\.\s+([A-Z\s.]+?)(?:\s+ADDRESS|\s+PLOT|\n|$)/gi
        ];

        for (const pattern of ownerPatterns) {
            const matches = [...cleanText.matchAll(pattern)];
            if (matches.length > 0 && !ownerFound) {
                const match = matches[0];
                ownerName = `ALH. ${match[1].trim()}`;
                if (ownerName.length > 5) {
                    ownerFound = true;
                    break;
                }
            }
        }

        // Extract plot number
        let plotNumber = '';
        let plotNumberFound = false;
        const plotNumberPatterns = [
            /PLOT\s+NO\s*[:\s]*([A-Z0-9\/]+)/gi,
            /PLOT NUMBER\s*[:\s]*([A-Z0-9\/]+)/gi,
            /PLOT\s*([A-Z0-9\/]+)/gi
        ];

        for (const pattern of plotNumberPatterns) {
            const matches = [...cleanText.matchAll(pattern)];
            if (matches.length > 0 && !plotNumberFound) {
                plotNumber = matches[0][1].trim();
                if (plotNumber.length > 0) {
                    plotNumberFound = true;
                    break;
                }
            }
        }

        // Extract land use
        let landUse = '';
        let landUseFound = false;
        if (/COMMERCIAL/gi.test(cleanText)) {
            landUse = 'Commercial';
            landUseFound = true;
        } else if (/RESIDENTIAL/gi.test(cleanText)) {
            landUse = 'Residential';
            landUseFound = true;
        } else if (/INDUSTRIAL/gi.test(cleanText)) {
            landUse = 'Industrial';
            landUseFound = true;
        }

        // Extract district
        let district = '';
        let districtFound = false;
        const districtPatterns = [
            /LGA\s+([A-Z]+)/gi,
            /(FAGGE|NASARAWA|BOMPAI|KANO MUNICIPAL|DALA|GWALE|TARAUNI)/gi
        ];

        for (const pattern of districtPatterns) {
            const matches = [...cleanText.matchAll(pattern)];
            if (matches.length > 0 && !districtFound) {
                district = matches[0][1] || matches[0][0];
                districtFound = true;
                break;
            }
        }

        // Determine document type
        let documentType = '';
        let documentTypeFound = false;
        if (/RECERTIFICATION/gi.test(cleanText)) {
            documentType = 'Recertification Document';
            documentTypeFound = true;
        } else if (/CERTIFICATE OF OCCUPANCY/gi.test(cleanText)) {
            documentType = 'Certificate of Occupancy';
            documentTypeFound = true;
        }

        // Calculate confidence
        const confidence = calculateConfidenceScore(
            newFileNumberFound,
            ownerFound,
            landUseFound,
            districtFound,
            documentTypeFound,
            plotNumberFound
        );

        return {
            extractedFileNumber: newFileNumber,
            fileNumberFound: newFileNumberFound,
            oldFileNumber: '',
            oldFileNumberFound: false,
            plotNumber: plotNumber, 
            plotNumberFound: plotNumberFound, 
            detectedOwner: ownerName,
            ownerFound,
            landUseType: landUse,
            landUseFound,
            district,
            districtFound,
            documentType: documentType || 'Land Document',
            documentTypeFound,
            confidence,
            pageCount: Math.max(1, Math.floor(cleanText.length / 1000)),
            hasSignature: /(?:SIGNATURE|SIGNED|SEAL)/gi.test(cleanText),
            hasStamp: /(?:STAMP|SEAL|OFFICIAL|KANGIS)/gi.test(cleanText),
            quality: cleanText.length > 500 ? 'Good' : 'Poor',
            readyForPageTyping: confidence > 30,
            extractionStatus: 'Successfully extracted'
        };
    }

    function calculateConfidenceScore(newFileNumberFound, ownerFound, landUseFound, districtFound, documentTypeFound, plotNumberFound) {
        let score = 0;
        if (newFileNumberFound) score += 25;
        if (ownerFound) score += 20;
        if (plotNumberFound) score += 15; // Added for Plot No:
        if (landUseFound) score += 10;
        if (districtFound) score += 5;
        if (documentTypeFound) score += 5;
        return score;
    }

    function determineDocumentType(fileName, content) {
        const lowerFileName = fileName.toLowerCase();
        if (lowerFileName.includes('certificate')) return 'Certificate of Occupancy';
        if (lowerFileName.includes('deed')) return 'Deed of Assignment';
        if (lowerFileName.includes('site')) return 'Site Plan';
        return 'Land Document';
    }

    // Utility functions
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function getFileTypeFromName(filename) {
        const extension = filename.split('.').pop()?.toLowerCase() || '';
        const fileTypes = {
            pdf: 'application/pdf',
            jpg: 'image/jpeg',
            jpeg: 'image/jpeg',
            png: 'image/png',
            gif: 'image/gif'
        };
        return fileTypes[extension] || 'application/octet-stream';
    }

    function getFileIcon(fileType) {
        if (fileType.includes('pdf')) {
            return '<svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
        }
        if (fileType.includes('image')) {
            return '<svg class="h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
        }
        return '<svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
    }

    // Unified function to open metadata edit modal with preview
    function openMetadataEditModal(fileId) {
        currentEditingFile = fileId;
        
        const fileEntry = uploadedFiles.find(f => f.id === fileId);
        if (!fileEntry || !fileEntry.file) {
            alert('File not found or not available for editing/preview.');
            return;
        }

        const metadataEntry = extractedMetadata[fileId] || {
            extractedFileNumber: '',
            plotNumber: '', 
            detectedOwner: '',
            landUseType: '',
            extractedText: 'Text extraction not yet performed or available.'
        };
        
        const form = document.getElementById('metadata-form');
        const extractedTextPreview = document.getElementById('metadata-extracted-text-preview');
        const modalTitle = document.getElementById('metadata-modal-title');
        
        // Preview elements
        const pdfPreviewWrapper = document.getElementById('pdf-preview-wrapper');
        const imagePreviewWrapper = document.getElementById('image-preview-wrapper');
        const unsupportedPreviewMessage = document.getElementById('unsupported-preview-message');
        const pdfPreviewCanvas = document.getElementById('pdf-preview-canvas');
        const pdfLoadingPlaceholder = document.getElementById('pdf-loading-placeholder');
        const pdfNavigationControls = document.getElementById('pdf-navigation-controls');
        const imagePreviewImg = document.getElementById('image-preview-img');
        const imageLoadingPlaceholder = document.getElementById('image-loading-placeholder');


        if (!form || !extractedTextPreview || !modalTitle || !pdfPreviewWrapper || !imagePreviewWrapper || !unsupportedPreviewMessage || !pdfPreviewCanvas || !pdfLoadingPlaceholder || !pdfNavigationControls || !imagePreviewImg || !imageLoadingPlaceholder) {
            console.error("One or more modal elements not found.");
            return;
        }

        modalTitle.textContent = `Edit Metadata - ${fileEntry.name}`;
        
        // Populate the metadata form
        form.innerHTML = `
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">File Number</label>
                <input type="text" id="edit-fileNumber" value="${metadataEntry.extractedFileNumber}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Enter file number">
            </div>
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Plot No:</label>
                <input type="text" id="edit-plotNumber" value="${metadataEntry.plotNumber}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Enter plot number">
            </div>
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">File Name</label>
                <input type="text" id="edit-owner" value="${metadataEntry.detectedOwner}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Enter file name">
            </div>
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Land Use Type</label>
                <select id="edit-landUse" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select land use</option>
                    <option value="Commercial" ${metadataEntry.landUseType === 'Commercial' ? 'selected' : ''}>Commercial</option>
                    <option value="Residential" ${metadataEntry.landUseType === 'Residential' ? 'selected' : ''}>Residential</option>
                    <option value="Industrial" ${metadataEntry.landUseType === 'Industrial' ? 'selected' : ''}>Industrial</option>
                    <option value="Agricultural" ${metadataEntry.landUseType === 'Agricultural' ? 'selected' : ''}>Agricultural</option>
                </select>
            </div>
        `;
        
        // Populate extracted text preview
        extractedTextPreview.textContent = metadataEntry.extractedText;
        
        // Reset and hide all preview elements first
        pdfPreviewWrapper.classList.add('hidden');
        imagePreviewWrapper.classList.add('hidden');
        unsupportedPreviewMessage.classList.add('hidden');

        // Render document preview based on file type
        if (fileEntry.file.type === 'application/pdf') {
            pdfPreviewWrapper.classList.remove('hidden');
            pdfPreviewCanvas.classList.add('hidden'); // Hide canvas until rendered
            pdfLoadingPlaceholder.classList.remove('hidden'); // Show loading message
            pdfNavigationControls.classList.add('hidden'); // Hide controls until PDF is loaded

            currentPDFDocument = null; // Reset document for new file
            currentPageNumber = 1; // Reset to first page
            loadAndRenderPDFPreview(fileEntry.file);
        } else if (fileEntry.file.type.startsWith('image/')) {
            imagePreviewWrapper.classList.remove('hidden');
            imagePreviewImg.classList.add('hidden'); // Hide img until loaded
            imageLoadingPlaceholder.classList.remove('hidden'); // Show loading message
            renderImagePreview(fileEntry.file);
        } else {
            unsupportedPreviewMessage.classList.remove('hidden');
        }

        const modal = document.getElementById('metadata-modal');
        if (modal) modal.classList.remove('hidden');
    }

    async function loadAndRenderPDFPreview(file) {
        const pdfLoadingPlaceholder = document.getElementById('pdf-loading-placeholder');
        const pdfPreviewCanvas = document.getElementById('pdf-preview-canvas');
        const pdfNavigationControls = document.getElementById('pdf-navigation-controls');

        pdfLoadingPlaceholder.classList.remove('hidden'); // Show loading message
        pdfPreviewCanvas.classList.add('hidden');     // Hide canvas
        pdfNavigationControls.classList.add('hidden'); // Hide controls

        try {
            if (typeof pdfjsLib === 'undefined') {
                throw new Error("PDF.js library is not loaded.");
            }
            const arrayBuffer = await file.arrayBuffer();
            currentPDFDocument = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            currentPageNumber = 1; // Ensure it starts on page 1
            renderSinglePDFPage(); // Render the first page
        } catch (error) {
            console.error('Error loading PDF document:', error);
            pdfLoadingPlaceholder.textContent = 'Error loading PDF preview.';
            pdfLoadingPlaceholder.classList.remove('hidden');
        }
    }

    async function renderSinglePDFPage() {
        const pdfPreviewCanvas = document.getElementById('pdf-preview-canvas');
        const pdfNavigationControls = document.getElementById('pdf-navigation-controls');
        const pageInfoSpan = document.getElementById('page-info');
        const prevPageBtn = document.getElementById('prev-page-btn');
        const nextPageBtn = document.getElementById('next-page-btn');
        const pdfLoadingPlaceholder = document.getElementById('pdf-loading-placeholder');

        if (!currentPDFDocument || !pdfPreviewCanvas || !pageInfoSpan || !prevPageBtn || !nextPageBtn || !pdfNavigationControls || !pdfLoadingPlaceholder) {
            console.error("PDF preview elements not found or PDF not loaded.");
            return;
        }

        // Hide placeholder and show canvas
        pdfLoadingPlaceholder.classList.add('hidden');
        pdfPreviewCanvas.classList.remove('hidden');
        pdfNavigationControls.classList.remove('hidden');

        try {
            const page = await currentPDFDocument.getPage(currentPageNumber);
            const viewport = page.getViewport({ scale: 1.5 }); // Slightly larger scale for better preview
            const context = pdfPreviewCanvas.getContext('2d');

            // Set canvas dimensions to fit container, maintaining aspect ratio
            const containerWidth = pdfPreviewCanvas.parentElement.offsetWidth - (2 * 16); // Account for padding
            const scale = containerWidth / viewport.width;
            const scaledViewport = page.getViewport({ scale: scale });

            pdfPreviewCanvas.height = scaledViewport.height;
            pdfPreviewCanvas.width = scaledViewport.width;

            await page.render({
                canvasContext: context,
                viewport: scaledViewport
            }).promise;

            pageInfoSpan.textContent = `Page ${currentPageNumber} of ${currentPDFDocument.numPages}`;
            prevPageBtn.disabled = currentPageNumber <= 1;
            nextPageBtn.disabled = currentPageNumber >= currentPDFDocument.numPages;

        } catch (error) {
            console.error('Error rendering PDF page:', error);
            pdfPreviewCanvas.classList.add('hidden');
            pdfLoadingPlaceholder.textContent = 'Error rendering page. Please try again.';
            pdfLoadingPlaceholder.classList.remove('hidden');
            pdfNavigationControls.classList.add('hidden'); // Hide controls on error
        }
    }

    function goToNextPage() {
        if (currentPDFDocument && currentPageNumber < currentPDFDocument.numPages) {
            currentPageNumber++;
            renderSinglePDFPage();
        }
    }

    function goToPreviousPage() {
        if (currentPDFDocument && currentPageNumber > 1) {
            currentPageNumber--;
            renderSinglePDFPage();
        }
    }

    function renderImagePreview(file) {
        const imagePreviewImg = document.getElementById('image-preview-img');
        const imageLoadingPlaceholder = document.getElementById('image-loading-placeholder');

        if (!imagePreviewImg || !imageLoadingPlaceholder) {
            console.error("Image preview elements not found.");
            return;
        }

        imagePreviewImg.classList.add('hidden'); // Hide img until loaded
        imageLoadingPlaceholder.classList.remove('hidden'); // Show loading message

        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreviewImg.src = e.target.result;
            imagePreviewImg.onload = () => {
                imageLoadingPlaceholder.classList.add('hidden');
                imagePreviewImg.classList.remove('hidden');
            };
            imagePreviewImg.onerror = () => {
                imageLoadingPlaceholder.textContent = 'Error loading image preview.';
                imageLoadingPlaceholder.classList.remove('hidden');
                imagePreviewImg.classList.add('hidden');
            };
        };
        reader.readAsDataURL(file);
    }

    // These functions now simply call the unified openMetadataEditModal
    function showDocumentPreviewFromTable(fileId) {
        openMetadataEditModal(fileId);
    }

    function editMetadataFromTable(fileId) {
        openMetadataEditModal(fileId);
    }

    function editMetadata(fileId) { // For calls from analysis results section
        openMetadataEditModal(fileId);
    }

    function closeMetadataModal() {
        const modal = document.getElementById('metadata-modal');
        if (modal) modal.classList.add('hidden');
        // Reset PDF state when closing modal
        currentPDFDocument = null;
        currentPageNumber = 1;
    }

    function saveMetadata() {
        if (!currentEditingFile) return;
        
        const fileNumber = document.getElementById('edit-fileNumber')?.value || '';
        const plotNumber = document.getElementById('edit-plotNumber')?.value || '';
        const owner = document.getElementById('edit-owner')?.value || '';
        const landUse = document.getElementById('edit-landUse')?.value || '';
        
        // Update extractedMetadata using currentEditingFile (which is the UPLOAD- ID)
        if (extractedMetadata[currentEditingFile]) {
            extractedMetadata[currentEditingFile] = {
                ...extractedMetadata[currentEditingFile],
                extractedFileNumber: fileNumber,
                plotNumber: plotNumber, 
                detectedOwner: owner,
                landUseType: landUse
            };
        } 
        
        // Always update the status in uploadedFiles if the file exists there
        const fileIndex = uploadedFiles.findIndex(f => f.id === currentEditingFile);
        if (fileIndex !== -1) {
            uploadedFiles[fileIndex].status = 'Metadata updated';
            updateUploadedFilesDisplay(); // Re-render uploaded files table
        }
        
        // Re-render analysis results if the modal was opened from there
        showAnalysisResults(); 

        closeMetadataModal();
    }

    function createIndexingEntries() {
        const entries = Object.values(extractedMetadata).map(data => ({
            id: `FILE-${new Date().getFullYear()}-${Math.floor(Math.random() * 1000).toString().padStart(3, '0')}`,
            fileNumber: data.extractedFileNumber || 'UNIDENTIFIED',
            name: data.detectedOwner || 'Unknown Owner',
            type: data.documentType || 'Unclassified Document'
        }));
        
        alert(`Successfully created ${entries.length} entries in File Indexing Assistant!\n\nFiles are now ready for Page Typing workflow.`);
        resetUpload();
    }

    function sendToIndexing() {
        if (uploadedFiles.length === 0) {
            alert('No files to send to indexing');
            return;
        }
        window.location.href = '/file-digital-registry/indexing-assistant';
    }

    function updateUploadedFilesDisplay() {
        const container = document.getElementById('uploaded-files-list');
        const footer = document.getElementById('uploaded-files-footer');
        
        if (!container) return;
        
        const filesToDisplay = filteredFiles.length > 0 ? filteredFiles : uploadedFiles;
        
        if (filesToDisplay.length === 0) {
            container.innerHTML = `
                <div class="p-8 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                        <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-medium">No uploaded files yet</h3>
                    <p class="mb-4 text-sm text-gray-500">Upload files to see them listed here</p>
                    <button class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors" onclick="switchToUpload()">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Go to Upload
                    </button>
                </div>
            `;
            if (footer) footer.classList.add('hidden');
        } else {
            container.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${filesToDisplay.map(file => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            ${getFileIcon(file.type)}
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">${file.name}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${file.type.includes('pdf') ? 'bg-red-100 text-red-800' : file.type.includes('image') ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'}">
                                        ${file.type.includes('pdf') ? 'PDF' : file.type.includes('image') ? 'Image' : 'Other'}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${file.size}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${file.status === 'Ready for analysis' ? 'bg-yellow-100 text-yellow-800' : file.status === 'Metadata updated' || file.status === 'Analysis Complete' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                        ${file.status}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${file.date}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="showDocumentPreviewFromTable('${file.id}')">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            Preview
                                        </button>
                                        <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="editMetadataFromTable('${file.id}')">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Edit
                                        </button>
                                        <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="indexFile('${file.id}')">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                            </svg>
                                            Index
                                        </button>
                                        <button class="inline-flex items-center px-2 py-1 text-xs text-red-600 hover:text-red-800 transition-colors" onclick="deleteFile('${file.id}')">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            if (footer) footer.classList.remove('hidden');
        }
    }

    function deleteFile(fileId) {
        if (confirm('Are you sure you want to delete this file?')) {
            uploadedFiles = uploadedFiles.filter(file => file.id !== fileId);
            filteredFiles = filteredFiles.filter(file => file.id !== fileId);
            // Also remove from extractedMetadata
            delete extractedMetadata[fileId];
            updateUploadedFilesDisplay();
            updateStats();
        }
    }

    function indexFile(fileId) {
        alert(`Sending file to indexing...`);
        window.location.href = '/file-digital-registry/indexing-assistant';
    }
</script>
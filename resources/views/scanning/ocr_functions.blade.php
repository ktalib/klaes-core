<script>
// These variables should be defined in the main file
/* Global variables are now moved to main file */

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

    // Start Upload button
    const startUploadBtn = document.getElementById('start-upload-btn');
    if (startUploadBtn) {
        startUploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            startUpload();
        });
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
    updateSelectedFilesDisplay();
    updateUploadButtons();
    
    // Generate client-side preview for the first selected file
    if (files.length > 0) {
        await generateClientSidePreview(files[0]);
    }
}

// Generate client-side preview for selected files before upload
async function generateClientSidePreview(file) {
    const previewContainer = document.getElementById('client-preview-container');
    if (!previewContainer) return;
    
    // Show preview container
    previewContainer.classList.remove('hidden');
    
    const previewContent = document.getElementById('client-preview-content');
    const previewTitle = document.getElementById('client-preview-title');
    
    if (previewTitle) {
        previewTitle.textContent = `Preview: ${file.name}`;
    }
    
    if (!previewContent) return;
    
    try {
        if (file.type === 'application/pdf') {
            await loadClientPDFPreview(file, previewContent);
        } else if (file.type.startsWith('image/')) {
            await loadClientImagePreview(file, previewContent);
        } else {
            previewContent.innerHTML = `
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">${file.name}</h3>
                    <p class="mt-1 text-sm text-gray-500">File Type: ${file.type}</p>
                    <p class="mt-1 text-sm text-gray-500">Size: ${formatFileSize(file.size)}</p>
                    <p class="mt-1 text-xs text-gray-400">Preview not available for this file type</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error generating client preview:', error);
        previewContent.innerHTML = `
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Preview Error</h3>
                <p class="mt-1 text-sm text-gray-500">Could not generate preview for ${file.name}</p>
                <p class="mt-1 text-xs text-gray-400">${error.message}</p>
            </div>
        `;
    }
}

// Load PDF preview from client-side file
async function loadClientPDFPreview(file, container) {
    container.innerHTML = `
        <div class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            <p class="mt-2 text-sm text-gray-600">Loading PDF preview...</p>
        </div>
    `;
    
    try {
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        
        // Get first page
        const page = await pdf.getPage(1);
        
        // Calculate appropriate scale for preview
        const viewport = page.getViewport({ scale: 1.0 });
        const maxWidth = 400; // Maximum width for preview
        const scale = Math.min(maxWidth / viewport.width, 1.5);
        const scaledViewport = page.getViewport({ scale });
        
        // Create canvas for PDF rendering
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.height = scaledViewport.height;
        canvas.width = scaledViewport.width;
        
        // Render page
        await page.render({
            canvasContext: context,
            viewport: scaledViewport
        }).promise;
        
        // Create preview HTML with the rendered canvas
        container.innerHTML = `
            <div class="text-center">
                <div class="border rounded-lg p-4 bg-white inline-block shadow-sm">
                    <div class="mb-3">
                        <canvas class="max-w-full h-auto border rounded" style="max-height: 400px;"></canvas>
                    </div>
                    <div class="text-sm text-gray-600">
                        <p class="font-medium text-gray-900">${file.name}</p>
                        <p class="text-xs">Page 1 of ${pdf.numPages} • ${formatFileSize(file.size)}</p>
                        <p class="text-xs text-green-600 mt-1">✓ PDF Preview Ready</p>
                    </div>
                </div>
            </div>
        `;
        
        // Copy the rendered canvas to the DOM canvas
        const displayCanvas = container.querySelector('canvas');
        displayCanvas.width = canvas.width;
        displayCanvas.height = canvas.height;
        const displayContext = displayCanvas.getContext('2d');
        displayContext.drawImage(canvas, 0, 0);
        
    } catch (error) {
        console.error('PDF preview error:', error);
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">PDF Preview Failed</h3>
                <p class="mt-1 text-sm text-gray-500">${file.name}</p>
                <p class="mt-1 text-xs text-red-600">${error.message}</p>
            </div>
        `;
    }
}

// Load image preview from client-side file
async function loadClientImagePreview(file, container) {
    container.innerHTML = `
        <div class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            <p class="mt-2 text-sm text-gray-600">Loading image preview...</p>
        </div>
    `;
    
    try {
        const imageUrl = URL.createObjectURL(file);
        
        // Create image element to get dimensions
        const img = new Image();
        
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = imageUrl;
        });
        
        // Create preview HTML with the loaded image
        container.innerHTML = `
            <div class="text-center">
                <div class="border rounded-lg p-4 bg-white inline-block shadow-sm">
                    <div class="mb-3">
                        <img src="${imageUrl}" alt="${file.name}" class="max-w-full h-auto border rounded" style="max-height: 400px; max-width: 400px;">
                    </div>
                    <div class="text-sm text-gray-600">
                        <p class="font-medium text-gray-900">${file.name}</p>
                        <p class="text-xs">${img.width} × ${img.height} pixels • ${formatFileSize(file.size)}</p>
                        <p class="text-xs text-green-600 mt-1">✓ Image Preview Ready</p>
                    </div>
                </div>
            </div>
        `;
        
        // Clean up object URL after a delay to ensure image is loaded
        setTimeout(() => URL.revokeObjectURL(imageUrl), 5000);
        
    } catch (error) {
        console.error('Image preview error:', error);
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Image Preview Failed</h3>
                <p class="mt-1 text-sm text-gray-500">${file.name}</p>
                <p class="mt-1 text-xs text-red-600">${error.message}</p>
            </div>
        `;
    }
}

// Hide client preview
function hideClientPreview() {
    const previewContainer = document.getElementById('client-preview-container');
    if (previewContainer) {
        previewContainer.classList.add('hidden');
    }
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
    selectedFiles.splice(index, 1);
    updateSelectedFilesDisplay();
    updateUploadButtons();
}

function clearAllFiles() {
    selectedFiles = [];
    updateSelectedFilesDisplay();
    updateUploadButtons();
    hideClientPreview();
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

// Upload functionality with backend integration
async function startUpload(event) {
    console.log('Starting upload process...');
    
    // Prevent any default form submission behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    if (!window.selectedFiles || window.selectedFiles.length === 0) {
        alert('Please select files to upload first.');
        return false;
    }

    window.uploadStatus = 'uploading';
    window.uploadProgress = 0;
    updateUploadStatus();
    updateUploadButtons();

    // Show progress bar
    const progressDiv = document.getElementById('upload-progress');
    if (progressDiv) progressDiv.classList.remove('hidden');

    try {
        // First, upload files to the server
        const formData = new FormData();
        window.selectedFiles.forEach((file, index) => {
            formData.append(`documents[]`, file);
        });

        // Add CSRF token
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        // Show uploading progress
        updateUploadProgress();
        const uploadInterval = setInterval(() => {
            if (uploadProgress < 30) {
                uploadProgress += 2;
                updateUploadProgress();
            }
        }, 100);

        const uploadResponse = await fetch('/scanning/upload-unindexed', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        clearInterval(uploadInterval);
        uploadProgress = 40;
        updateUploadProgress();

        if (!uploadResponse.ok) {
            const errorData = await uploadResponse.json();
            throw new Error(errorData.message || 'Upload failed: ' + uploadResponse.statusText);
        }

        const uploadResult = await uploadResponse.json();
        
        if (!uploadResult.success) {
            throw new Error(uploadResult.message || 'Upload failed');
        }

        // Add uploaded files to the list with backend data
        const newFiles = uploadResult.files.map(file => ({
            id: file.id, // Use the actual scanning ID from backend
            scanning_id: file.id,
            file_indexing_id: file.file_indexing_id,
            name: file.name,
            size: file.size,
            type: file.type,
            status: 'Uploaded - Processing...',
            date: file.date,
            originalFile: selectedFiles.find(f => f.name === file.name) // Keep reference for OCR
        }));

        uploadedFiles = [...newFiles, ...uploadedFiles];
        filteredFiles = uploadedFiles;
        updateUploadedFilesDisplay();

        uploadProgress = 50;
        updateUploadProgress();

        // Start AI processing for the newly uploaded files
        await startAiProcessing(newFiles);

    } catch (error) {
        console.error('Upload error:', error);
        uploadStatus = 'error';
        updateUploadStatus();
        updateUploadButtons();
        
        const progressDiv = document.getElementById('upload-progress');
        if (progressDiv) progressDiv.classList.add('hidden');

        Swal.fire({
            icon: 'error',
            title: 'Upload Failed',
            text: error.message || 'An error occurred while uploading files.',
            confirmButtonColor: '#3b82f6'
        });
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
    aiProcessingStage = 'idle';
    aiProgress = 0;
    extractedMetadata = {}; // Clear extracted metadata
    
    updateUploadStatus();
    updateSelectedFilesDisplay();
    updateUploadButtons();
    
    const progressDiv = document.getElementById('upload-progress');
    const aiDiv = document.getElementById('ai-processing');
    if (aiDiv) aiDiv.classList.add('hidden');
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
    if (pendingEl) pendingEl.textContent = uploadedFiles.filter(f => f.status !== 'Indexed').length;
}

// AI Processing functionality with real OCR
async function startAiProcessing(newFiles) {
    aiProcessingStage = 'analyzing';
    aiProgress = 50; // Start from 50% since upload is complete
    
    const aiDiv = document.getElementById('ai-processing');
    if (aiDiv) aiDiv.classList.remove('hidden');
    
    updateAiProgress();

    // Show OCR modal
    const ocrModal = document.getElementById('ocr-modal');
    if (ocrModal) ocrModal.classList.remove('hidden');

    try {
        const newExtractedMetadata = {};

        for (let i = 0; i < newFiles.length; i++) {
            const fileEntry = newFiles[i];
            const file = fileEntry.originalFile;
            
            if (!file) {
                console.warn(`Original file not found for: ${fileEntry.name}`);
                continue;
            }
            
            // Update current file being processed
            const currentFileEl = document.getElementById('ocr-current-file');
            if (currentFileEl) {
                currentFileEl.textContent = `Processing: ${file.name}`;
            }
            
            updateOcrProgress(25 + (i / newFiles.length) * 25);

            let extractedText = '';

            if (file.type === 'application/pdf') {
                extractedText = await extractTextFromPDF(file);
            } else if (file.type.startsWith('image/')) {
                extractedText = await extractTextFromImage(file);
            } else {
                extractedText = `Unsupported file type: ${file.type}`;
            }

            updateOcrProgress(50 + (i / newFiles.length) * 50);

            const fileMetadata = extractMetadataFromText(extractedText, file.name);
            newExtractedMetadata[fileEntry.id] = {
                ...fileMetadata,
                originalFileName: file.name,
                extractedText: extractedText,
                fileSize: formatFileSize(file.size),
                fileType: file.type,
                file_indexing_id: fileEntry.file_indexing_id
            };

            // Update the status of the file in the main uploadedFiles array
            const uploadedFileIndex = uploadedFiles.findIndex(f => f.id === fileEntry.id);
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
            aiProgress = 70;
            updateAiProgress();

            setTimeout(() => {
                aiProcessingStage = 'creating';
                aiProgress = 90;
                updateAiProgress();

                setTimeout(() => {
                    aiProcessingStage = 'complete';
                    aiProgress = 100;
                    uploadStatus = 'complete';
                    updateUploadStatus();
                    updateUploadButtons();
                    updateAiProgress();
                    showAnalysisResults();
                    updateUploadedFilesDisplay();
                    updateStats();
                }, 1000);
            }, 1000);
        }, 1000);

    } catch (error) {
        console.error('Error processing documents:', error);
        if (ocrModal) ocrModal.classList.add('hidden');
        aiProcessingStage = 'idle';
        
        Swal.fire({
            icon: 'error',
            title: 'Processing Failed',
            text: 'Error processing documents. Please try again.',
            confirmButtonColor: '#3b82f6'
        });
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

// Create indexing entries with backend integration
async function createIndexingEntries() {
    try {
        // Prepare entries data for backend
        const entries = Object.entries(extractedMetadata).map(([fileId, data]) => ({
            file_indexing_id: data.file_indexing_id,
            file_number: data.extractedFileNumber || `AUTO-${Date.now()}`,
            file_title: data.detectedOwner || data.originalFileName,
            plot_number: data.plotNumber || null,
            land_use_type: data.landUseType || 'Unknown',
            district: data.district || 'Unknown',
            lga: data.district || 'Unknown'
        }));

        if (entries.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Data to Save',
                text: 'No extracted metadata found to create indexing entries.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Show loading
        Swal.fire({
            title: 'Creating Indexing Entries...',
            text: 'Please wait while we save the extracted data.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('/unindexed-scanning/create-indexing-entry', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ entries })
        });

        const result = await response.json();

        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message,
                confirmButtonColor: '#3b82f6'
            }).then(() => {
                // Reset the upload interface
                resetUpload();
                // Optionally redirect to page typing or file indexing
                // window.location.href = '/pagetyping';
            });
        } else {
            throw new Error(result.message || 'Failed to create indexing entries');
        }

    } catch (error) {
        console.error('Error creating indexing entries:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'An error occurred while creating indexing entries.',
            confirmButtonColor: '#3b82f6'
        });
    }
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
    if (plotNumberFound) score += 15;
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
        return '<svg class="h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
    }
    return '<svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
}

// Modal and metadata editing functions
async function openMetadataEditModal(fileId) {
    try {
        const modal = document.getElementById('metadata-modal');
        const modalTitle = document.getElementById('metadata-modal-title');
        const metadataForm = document.getElementById('metadata-form');
        const previewContent = document.getElementById('metadata-preview-content');
        const extractedTextPreview = document.getElementById('metadata-extracted-text-preview');
        
        if (!modal || !modalTitle || !metadataForm || !previewContent) {
            console.error('Modal elements not found');
            return;
        }

        // Get uploaded file data from local array (optional)
        const uploadedFile = uploadedFiles.find(f => f.id === fileId);
        
        // Get or create file metadata from local storage
        let fileData = extractedMetadata[fileId];
        
        // If no metadata exists yet, create default structure
        if (!fileData) {
            const fileName = uploadedFile ? uploadedFile.name : `Document ${fileId}`;
            fileData = {
                extractedFileNumber: '',
                fileNumberFound: false,
                detectedOwner: fileName.replace(/\.[^/.]+$/, ""), // Use filename without extension as default
                ownerFound: false,
                plotNumber: '',
                plotNumberFound: false,
                landUseType: '',
                landUseFound: false,
                district: '',
                districtFound: false,
                documentType: 'Land Document',
                documentTypeFound: false,
                confidence: 0,
                originalFileName: fileName,
                extractedText: 'You can edit the metadata manually. OCR data will be merged when processing completes.',
                fileSize: uploadedFile ? uploadedFile.size : 'Unknown',
                fileType: uploadedFile ? uploadedFile.type : 'Unknown',
                file_indexing_id: uploadedFile ? uploadedFile.file_indexing_id : null
            };
            
            // Store the default metadata
            extractedMetadata[fileId] = fileData;
        }

        // Set current editing file
        currentEditingFile = fileId;
        
        // Update modal title
        modalTitle.textContent = `Edit Metadata - ${fileData.originalFileName}`;
        
        // Generate metadata form
        generateMetadataForm(fileData, metadataForm);
        
        // Load document preview using the original file data
        await loadModalDocumentPreview(fileId, uploadedFile, previewContent);
        
        // Show extracted text
        if (extractedTextPreview) {
            if (fileData.extractedText) {
                extractedTextPreview.textContent = fileData.extractedText.substring(0, 2000) + 
                    (fileData.extractedText.length > 2000 ? '...\n\n[Text truncated for display]' : '');
            } else {
                extractedTextPreview.textContent = 'You can edit the metadata manually. OCR data will be merged when available.';
            }
        }
        
        // Show modal
        modal.classList.remove('hidden');
        
    } catch (error) {
        console.error('Error opening metadata modal:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to open metadata editor.',
            confirmButtonColor: '#3b82f6'
        });
    }
}

function generateMetadataForm(fileData, container) {
    const formFields = [
        {
            key: 'extractedFileNumber',
            label: 'File Number (KANGIS)',
            type: 'text',
            placeholder: 'e.g., MLKN 123456',
            required: true
        },
        {
            key: 'detectedOwner',
            label: 'Property Owner/File Title',
            type: 'text',
            placeholder: 'e.g., ALH. JOHN DOE',
            required: true
        },
        {
            key: 'plotNumber',
            label: 'Plot Number',
            type: 'text',
            placeholder: 'e.g., A/123/45'
        },
        {
            key: 'landUseType',
            label: 'Land Use Type',
            type: 'select',
            options: ['', 'RESIDENTIAL', 'AGRICULTURAL', 'COMMERCIAL', 'COMMERCIAL (WARE HOUSE)', 
                 'COMMERCIAL (OFFICES)', 'COMMERCIAL (PETROL FILLING STATION)', 'COMMERCIAL (RICE PROCESSING)',
                 'COMMERCIAL (SCHOOL)', 'COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)', 'COMMERCIAL (SHOPS AND OFFICES)',
                 'COMMERCIAL (SHOPS)', 'COMMERCIAL (WAREHOUSE)', 'COMMERCIAL (WORKSHOP AND OFFICES)',
                 'COMMERCIAL AND RESIDENTIAL', 'INDUSTRIAL', 'INDUSTRIAL (SMALL SCALE)', 
                 'RESIDENTIAL AND COMMERCIAL', 'RESIDENTIAL/COMMERCIAL', 'RESIDENTIAL/COMMERCIAL LAYOUT']
        },
        {
            key: 'district',
            label: 'LGA',
            type: 'select',
            options: ['', 'Fagge', 'Nasarawa', 'Bompai', 'Kano Municipal', 'Dala', 'Gwale', 'Tarauni', 'Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Bichi', 'Bunkure', 'Dambatta', 'Dawakin Kudu', 'Dawakin Tofa', 'Doguwa', 'Gabasawa', 'Garko', 'Garun Mallam', 'Gaya', 'Gezawa', 'Gwarzo', 'Kabo', 'Karaye', 'Kibiya', 'Kiru', 'Kumbotso', 'Kunchi', 'Kura', 'Madobi', 'Makoda', 'Minjibir', 'Rano', 'Rimin Gado', 'Rogo', 'Shanono', 'Sumaila', 'Takai', 'Tofa', 'Tsanyawa', 'Tudun Wada', 'Ungogo', 'Warawa', 'Wudil']
        },
        {
            key: 'documentType',
            label: 'Document Type',
            type: 'select',
            options: ['', 'Certificate of Occupancy', 'Deed of Assignment', 'Survey Plan', 'Recertification Document', 'Other']
        }
    ];

    const formHTML = formFields.map(field => {
        const value = fileData[field.key] || '';
        
        if (field.type === 'select') {
            const optionsHTML = field.options.map(option => 
                `<option value="${option}" ${option === value ? 'selected' : ''}>${option}</option>`
            ).join('');
            
            return `
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700" for="${field.key}">
                        ${field.label} ${field.required ? '<span class="text-red-500">*</span>' : ''}
                    </label>
                    <select id="${field.key}" name="${field.key}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" ${field.required ? 'required' : ''}>
                        ${optionsHTML}
                    </select>
                </div>
            `;
        } else {
            return `
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700" for="${field.key}">
                        ${field.label} ${field.required ? '<span class="text-red-500">*</span>' : ''}
                    </label>
                    <input type="${field.type}" id="${field.key}" name="${field.key}" value="${value}" placeholder="${field.placeholder || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" ${field.required ? 'required' : ''}>
                </div>
            `;
        }
    }).join('');

    container.innerHTML = formHTML;
}

// New function for modal document preview using original file data
async function loadModalDocumentPreview(fileId, uploadedFile, container) {
    try {
        // Hide all preview wrappers first
        const pdfWrapper = document.getElementById('pdf-preview-wrapper');
        const imageWrapper = document.getElementById('image-preview-wrapper');
        const unsupportedMessage = document.getElementById('unsupported-preview-message');
        
        [pdfWrapper, imageWrapper, unsupportedMessage].forEach(el => {
            if (el) el.classList.add('hidden');
        });

        // Get the original file from the uploaded file data
        const originalFile = uploadedFile ? uploadedFile.originalFile : null;
        
        if (originalFile) {
            // Use client-side preview functions with the original file
            if (originalFile.type === 'application/pdf') {
                await loadModalPDFPreview(originalFile, pdfWrapper);
            } else if (originalFile.type.startsWith('image/')) {
                await loadModalImagePreview(originalFile, imageWrapper);
            } else {
                if (unsupportedMessage) {
                    unsupportedMessage.classList.remove('hidden');
                    unsupportedMessage.textContent = `Preview not available for ${originalFile.type}`;
                }
            }
        } else {
            // Fallback: try backend preview if original file not available
            if (uploadedFile && uploadedFile.type === 'application/pdf') {
                await loadPDFPreview(fileId, uploadedFile, pdfWrapper);
            } else if (uploadedFile && uploadedFile.type.startsWith('image/')) {
                await loadImagePreview(fileId, uploadedFile, imageWrapper);
            } else {
                if (unsupportedMessage) {
                    unsupportedMessage.classList.remove('hidden');
                    unsupportedMessage.textContent = 'Document preview not available';
                }
            }
        }
    } catch (error) {
        console.error('Error loading modal document preview:', error);
        const unsupportedMessage = document.getElementById('unsupported-preview-message');
        if (unsupportedMessage) {
            unsupportedMessage.classList.remove('hidden');
            unsupportedMessage.textContent = 'Error loading document preview';
        }
    }
}

async function loadDocumentPreview(fileId, uploadedFile, container) {
    try {
        // Hide all preview wrappers first
        const pdfWrapper = document.getElementById('pdf-preview-wrapper');
        const imageWrapper = document.getElementById('image-preview-wrapper');
        const unsupportedMessage = document.getElementById('unsupported-preview-message');
        
        [pdfWrapper, imageWrapper, unsupportedMessage].forEach(el => {
            if (el) el.classList.add('hidden');
        });

        if (uploadedFile.type === 'application/pdf') {
            await loadPDFPreview(fileId, uploadedFile, pdfWrapper);
        } else if (uploadedFile.type.startsWith('image/')) {
            await loadImagePreview(fileId, uploadedFile, imageWrapper);
        } else {
            if (unsupportedMessage) {
                unsupportedMessage.classList.remove('hidden');
                unsupportedMessage.textContent = `Preview not available for ${uploadedFile.type}`;
            }
        }
    } catch (error) {
        console.error('Error loading document preview:', error);
        const unsupportedMessage = document.getElementById('unsupported-preview-message');
        if (unsupportedMessage) {
            unsupportedMessage.classList.remove('hidden');
            unsupportedMessage.textContent = 'Error loading document preview';
        }
    }
}

async function loadPDFPreview(fileId, uploadedFile, wrapper) {
    if (!wrapper) return;
    
    wrapper.classList.remove('hidden');
    const canvas = document.getElementById('pdf-preview-canvas');
    const loadingPlaceholder = document.getElementById('pdf-loading-placeholder');
    const navigationControls = document.getElementById('pdf-navigation-controls');
    const pageInfo = document.getElementById('page-info');
    
    if (loadingPlaceholder) {
        loadingPlaceholder.classList.remove('hidden');
        loadingPlaceholder.textContent = 'Loading PDF preview...';
    }

    try {
        // Use the file ID directly as it's now the scanning ID
        const previewUrl = `/unindexed-scanning/preview/${fileId}`;
        
        // Try to load the PDF using PDF.js
        const loadingTask = pdfjsLib.getDocument(previewUrl);
        const pdf = await loadingTask.promise;
        
        currentPDFDocument = pdf;
        currentPageNumber = 1;
        
        // Render first page
        await renderPDFPage(1);
        
        // Show navigation controls if more than one page
        if (pdf.numPages > 1) {
            if (navigationControls) navigationControls.classList.remove('hidden');
            if (pageInfo) pageInfo.textContent = `Page 1 of ${pdf.numPages}`;
            
            // Update navigation buttons
            const prevBtn = document.getElementById('prev-page-btn');
            const nextBtn = document.getElementById('next-page-btn');
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = pdf.numPages <= 1;
        }
        
        if (loadingPlaceholder) loadingPlaceholder.classList.add('hidden');
        if (canvas) canvas.classList.remove('hidden');
        
    } catch (error) {
        console.error('Error loading PDF preview:', error);
        
        // Fallback to placeholder
        if (loadingPlaceholder) {
            loadingPlaceholder.innerHTML = `
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">PDF Document</h3>
                    <p class="mt-1 text-sm text-gray-500">${uploadedFile.name}</p>
                    <p class="mt-1 text-xs text-gray-400">Preview not available - ${error.message}</p>
                </div>
            `;
        }
        
        if (navigationControls) navigationControls.classList.add('hidden');
        if (canvas) canvas.classList.add('hidden');
    }
}

async function loadImagePreview(fileId, uploadedFile, wrapper) {
    if (!wrapper) return;
    
    wrapper.classList.remove('hidden');
    const img = document.getElementById('image-preview-img');
    const loadingPlaceholder = document.getElementById('image-loading-placeholder');
    
    if (loadingPlaceholder) {
        loadingPlaceholder.classList.remove('hidden');
        loadingPlaceholder.textContent = 'Loading image preview...';
    }

    try {
        // Use the file ID directly as it's now the scanning ID
        const previewUrl = `/unindexed-scanning/preview/${fileId}`;
        
        if (img) {
            img.onload = function() {
                if (loadingPlaceholder) loadingPlaceholder.classList.add('hidden');
                img.classList.remove('hidden');
            };
            
            img.onerror = function() {
                // Fallback to placeholder on error
                if (loadingPlaceholder) {
                    loadingPlaceholder.innerHTML = `
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Image Document</h3>
                            <p class="mt-1 text-sm text-gray-500">${uploadedFile.name}</p>
                            <p class="mt-1 text-xs text-gray-400">Preview not available</p>
                        </div>
                    `;
                }
                img.classList.add('hidden');
            };
            
            img.src = previewUrl;
        }
        
    } catch (error) {
        console.error('Error loading image preview:', error);
        if (loadingPlaceholder) {
            loadingPlaceholder.innerHTML = `
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Image Document</h3>
                    <p class="mt-1 text-sm text-gray-500">${uploadedFile.name}</p>
                    <p class="mt-1 text-xs text-gray-400">Error loading preview</p>
                </div>
            `;
        }
        if (img) img.classList.add('hidden');
    }
}

function closeMetadataModal() {
    const modal = document.getElementById('metadata-modal');
    if (modal) {
        modal.classList.add('hidden');
        currentEditingFile = null;
        currentPDFDocument = null;
        currentPageNumber = 1;
    }
}

function applyMetadata() {
    if (!currentEditingFile) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No file selected for editing.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    try {
        // Get form container (not form element)
        const formContainer = document.getElementById('metadata-form');
        if (!formContainer) {
            throw new Error('Metadata form not found');
        }

        // Collect values from input fields manually
        const updatedData = {};
        const inputs = formContainer.querySelectorAll('input, select');
        
        inputs.forEach(input => {
            if (input.name) {
                updatedData[input.name] = input.value.trim();
            }
        });

        // Validate required fields
        if (!updatedData.extractedFileNumber || !updatedData.detectedOwner) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Required Fields',
                text: 'Please fill in the File Number and Property Owner fields.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Update local metadata
        const currentData = extractedMetadata[currentEditingFile] || {};
        extractedMetadata[currentEditingFile] = {
            ...currentData,
            ...updatedData,
            fileNumberFound: !!updatedData.extractedFileNumber,
            ownerFound: !!updatedData.detectedOwner,
            plotNumberFound: !!updatedData.plotNumber,
            landUseFound: !!updatedData.landUseType,
            districtFound: !!updatedData.district,
            documentTypeFound: !!updatedData.documentType,
            confidence: calculateConfidenceScore(
                !!updatedData.extractedFileNumber,
                !!updatedData.detectedOwner,
                !!updatedData.landUseType,
                !!updatedData.district,
                !!updatedData.documentType,
                !!updatedData.plotNumber
            )
        };

        // Update uploaded file status
        const uploadedFileIndex = uploadedFiles.findIndex(f => f.id === currentEditingFile);
        if (uploadedFileIndex !== -1) {
            uploadedFiles[uploadedFileIndex].status = 'Metadata Updated';
        }

        // Update the Document Analysis Results section
        updateAnalysisResultsDisplay();
        
        // Update the uploaded files display
        updateUploadedFilesDisplay();

        // Show success message
        Swal.fire({
            icon: 'success',
            title: 'Applied!',
            text: 'Metadata changes have been applied successfully.',
            confirmButtonColor: '#3b82f6',
            timer: 1500,
            showConfirmButton: false
        });

        // Close modal
        closeMetadataModal();

    } catch (error) {
        console.error('Error applying metadata:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to apply metadata changes.',
            confirmButtonColor: '#3b82f6'
        });
    }
}

// Update the analysis results display to reflect changes
function updateAnalysisResultsDisplay() {
    showAnalysisResults();
}

// PDF navigation functions (for when PDF preview is fully implemented)
function goToPreviousPage() {
    if (currentPageNumber > 1) {
        currentPageNumber--;
        renderPDFPage(currentPageNumber);
    }
}

function goToNextPage() {
    if (currentPDFDocument && currentPageNumber < currentPDFDocument.numPages) {
        currentPageNumber++;
        renderPDFPage(currentPageNumber);
    }
}

async function renderPDFPage(pageNumber) {
    if (!currentPDFDocument) return;
    
    try {
        const page = await currentPDFDocument.getPage(pageNumber);
        const canvas = document.getElementById('pdf-preview-canvas');
        const context = canvas.getContext('2d');
        
        // Calculate scale to fit the canvas container
        const containerWidth = canvas.parentElement.clientWidth - 40; // Account for padding
        const viewport = page.getViewport({ scale: 1 });
        const scale = Math.min(containerWidth / viewport.width, 1.5); // Max scale of 1.5
        const scaledViewport = page.getViewport({ scale });
        
        canvas.height = scaledViewport.height;
        canvas.width = scaledViewport.width;
        
        const renderContext = {
            canvasContext: context,
            viewport: scaledViewport
        };
        
        await page.render(renderContext).promise;
        
        // Update page info and navigation buttons
        const pageInfo = document.getElementById('page-info');
        const prevBtn = document.getElementById('prev-page-btn');
        const nextBtn = document.getElementById('next-page-btn');
        
        if (pageInfo) {
            pageInfo.textContent = `Page ${pageNumber} of ${currentPDFDocument.numPages}`;
        }
        
        if (prevBtn) {
            prevBtn.disabled = pageNumber <= 1;
        }
        
        if (nextBtn) {
            nextBtn.disabled = pageNumber >= currentPDFDocument.numPages;
        }
        
    } catch (error) {
        console.error('Error rendering PDF page:', error);
    }
}

function sendToIndexing() {
    if (uploadedFiles.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Files',
            text: 'No files to send to indexing',
            confirmButtonColor: '#3b82f6'
        });
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
                                    <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="openMetadataEditModal('${file.id}')">
                                        <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Preview
                                    </button>
                                    <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="openMetadataEditModal('${file.id}')">
                                        <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit
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
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            uploadedFiles = uploadedFiles.filter(file => file.id !== fileId);
            filteredFiles = filteredFiles.filter(file => file.id !== fileId);
            delete extractedMetadata[fileId];
            updateUploadedFilesDisplay();
            updateStats();
            
            Swal.fire(
                'Deleted!',
                'Your file has been deleted.',
                'success'
            );
        }
    });
}

// Modal-specific PDF preview function using original file
async function loadModalPDFPreview(originalFile, wrapper) {
    if (!wrapper) return;
    
    wrapper.classList.remove('hidden');
    const canvas = document.getElementById('pdf-preview-canvas');
    const loadingPlaceholder = document.getElementById('pdf-loading-placeholder');
    const navigationControls = document.getElementById('pdf-navigation-controls');
    const pageInfo = document.getElementById('page-info');
    
    if (loadingPlaceholder) {
        loadingPlaceholder.classList.remove('hidden');
        loadingPlaceholder.innerHTML = `
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-sm text-gray-600">Loading PDF preview...</p>
            </div>
        `;
    }

    try {
        const arrayBuffer = await originalFile.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        
        currentPDFDocument = pdf;
        currentPageNumber = 1;
        
        // Get first page
        const page = await pdf.getPage(1);
        
        // Calculate appropriate scale for modal preview
        const viewport = page.getViewport({ scale: 1.0 });
        const maxWidth = 500; // Larger for modal
        const scale = Math.min(maxWidth / viewport.width, 1.5);
        const scaledViewport = page.getViewport({ scale });
        
        // Create canvas for PDF rendering
        const tempCanvas = document.createElement('canvas');
        const context = tempCanvas.getContext('2d');
        tempCanvas.height = scaledViewport.height;
        tempCanvas.width = scaledViewport.width;
        
        // Render page
        await page.render({
            canvasContext: context,
            viewport: scaledViewport
        }).promise;
        
        // Update the modal canvas
        if (canvas) {
            canvas.width = tempCanvas.width;
            canvas.height = tempCanvas.height;
            const displayContext = canvas.getContext('2d');
            displayContext.drawImage(tempCanvas, 0, 0);
            canvas.classList.remove('hidden');
        }
        
        // Show navigation controls if more than one page
        if (pdf.numPages > 1) {
            if (navigationControls) navigationControls.classList.remove('hidden');
            if (pageInfo) pageInfo.textContent = `Page 1 of ${pdf.numPages}`;
            
            // Update navigation buttons
            const prevBtn = document.getElementById('prev-page-btn');
            const nextBtn = document.getElementById('next-page-btn');
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = pdf.numPages <= 1;
        }
        
        if (loadingPlaceholder) loadingPlaceholder.classList.add('hidden');
        
    } catch (error) {
        console.error('Error loading modal PDF preview:', error);
        
        // Show error placeholder
        if (loadingPlaceholder) {
            loadingPlaceholder.innerHTML = `
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">PDF Preview Failed</h3>
                    <p class="mt-1 text-sm text-gray-500">${originalFile.name}</p>
                    <p class="mt-1 text-xs text-red-600">${error.message}</p>
                </div>
            `;
        }
        
        if (navigationControls) navigationControls.classList.add('hidden');
        if (canvas) canvas.classList.add('hidden');
    }
}

// Modal-specific image preview function using original file
async function loadModalImagePreview(originalFile, wrapper) {
    if (!wrapper) return;
    
    wrapper.classList.remove('hidden');
    const img = document.getElementById('image-preview-img');
    const loadingPlaceholder = document.getElementById('image-loading-placeholder');
    
    if (loadingPlaceholder) {
        loadingPlaceholder.classList.remove('hidden');
        loadingPlaceholder.innerHTML = `
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-sm text-gray-600">Loading image preview...</p>
            </div>
        `;
    }

    try {
        const imageUrl = URL.createObjectURL(originalFile);
        
        // Create image element to get dimensions
        const tempImg = new Image();
        
        await new Promise((resolve, reject) => {
            tempImg.onload = resolve;
            tempImg.onerror = reject;
            tempImg.src = imageUrl;
        });
        
        // Update the modal image
        if (img) {
            img.onload = function() {
                if (loadingPlaceholder) loadingPlaceholder.classList.add('hidden');
                img.classList.remove('hidden');
            };
            
            img.onerror = function() {
                // Show error placeholder
                if (loadingPlaceholder) {
                    loadingPlaceholder.innerHTML = `
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Image Preview Failed</h3>
                            <p class="mt-1 text-sm text-gray-500">${originalFile.name}</p>
                            <p class="mt-1 text-xs text-red-600">Failed to load image</p>
                        </div>
                    `;
                }
                img.classList.add('hidden');
            };
            
            img.src = imageUrl;
        }
        
        // Clean up object URL after a delay
        setTimeout(() => URL.revokeObjectURL(imageUrl), 5000);
        
    } catch (error) {
        console.error('Error loading modal image preview:', error);
        
        // Show error placeholder
        if (loadingPlaceholder) {
            loadingPlaceholder.innerHTML = `
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Image Preview Failed</h3>
                    <p class="mt-1 text-sm text-gray-500">${originalFile.name}</p>
                    <p class="mt-1 text-xs text-red-600">${error.message}</p>
                </div>
            `;
        }
        
        if (img) img.classList.add('hidden');
    }
}

function indexFile(fileId) {
    Swal.fire({
        title: 'Send to Indexing?',
        text: 'This will send the file to the indexing workflow.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, send it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/file-digital-registry/indexing-assistant';
        }
    });
}
</script>
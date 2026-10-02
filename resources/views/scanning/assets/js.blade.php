<!-- Dynamic Scanning JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    lucide.createIcons();
    
    // State variables
    let selectedFileIndexing = @json($selectedFileIndexing ?? null);
    let selectedFiles = [];
    let uploadedFiles = [];
    let isUploading = false;
    
    // DOM Elements
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    const selectFileBtn = document.getElementById('select-file-btn');
    const fileSelectorDialog = document.getElementById('file-selector-dialog');
    const indexedFilesList = document.getElementById('indexed-files-list');
    const searchIndexedFiles = document.getElementById('search-indexed-files');
    const cancelFileSelectBtn = document.getElementById('cancel-file-select-btn');
    const confirmFileSelectBtn = document.getElementById('confirm-file-select-btn');
    const selectedFileNumber = document.getElementById('selected-file-number');
    const selectedFileBadge = document.querySelector('.selected-file-badge');
    const changeFileText = document.getElementById('change-file-text');
    const selectFileWarning = document.getElementById('select-file-warning');
    
    // Upload elements
    const fileUpload = document.getElementById('file-upload');
    const browseFilesBtn = document.getElementById('browse-files-btn');
    const uploadIdle = document.getElementById('upload-idle');
    const selectedFilesContainer = document.getElementById('selected-files-container');
    const selectedFilesList = document.getElementById('selected-files-list');
    const selectedFilesCount = document.getElementById('selected-files-count');
    const clearAllBtn = document.getElementById('clear-all-btn');
    const startUploadBtn = document.getElementById('start-upload-btn');
    const uploadProgress = document.getElementById('upload-progress');
    const uploadComplete = document.getElementById('upload-complete');
    const uploadPercentage = document.getElementById('upload-percentage');
    const progressBar = document.getElementById('progress-bar');
    const uploadingCount = document.getElementById('uploading-count');
    const cancelUploadBtn = document.getElementById('cancel-upload-btn');
    const uploadMoreBtn = document.getElementById('upload-more-btn');
    const viewUploadedBtn = document.getElementById('view-uploaded-btn');
    const proceedPageTypingBtn = document.getElementById('proceed-page-typing-btn');
    
    // Tab switching functionality
    function switchTab(tabName) {
        tabs.forEach(tab => {
            if (tab.getAttribute('data-tab') === tabName) {
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
            } else {
                tab.classList.remove('active');
                tab.setAttribute('aria-selected', 'false');
            }
        });

        tabContents.forEach(content => {
            if (content.getAttribute('data-tab-content') === tabName) {
                content.classList.remove('hidden');
                content.classList.add('active');
                content.setAttribute('aria-hidden', 'false');
            } else {
                content.classList.add('hidden');
                content.classList.remove('active');
                content.setAttribute('aria-hidden', 'true');
            }
        });
    }
    
    // Load indexed files for selection
    function loadIndexedFiles(search = '') {
        const url = `{{ route("fileindexing.list") }}?status=indexed&search=${encodeURIComponent(search)}`;
        
        fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateIndexedFilesList(data.file_indexings);
            } else {
                console.error('Error loading indexed files:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading indexed files:', error);
        });
    }
    
    // Populate indexed files list
    function populateIndexedFilesList(files) {
        if (!indexedFilesList) return;
        
        indexedFilesList.innerHTML = '';
        
        if (files.length === 0) {
            indexedFilesList.innerHTML = `
                <div class="p-8 text-center text-gray-500">
                    <i data-lucide="inbox" class="h-12 w-12 mx-auto mb-4 text-gray-300"></i>
                    <p class="text-lg font-medium mb-2">No indexed files found</p>
                    <p class="text-sm">Please create file indexes first before uploading documents.</p>
                </div>
            `;
            lucide.createIcons();
            return;
        }
        
        files.forEach(file => {
            const fileItem = document.createElement('div');
            fileItem.className = 'p-4 hover:bg-gray-50 cursor-pointer border-b last:border-b-0';
            fileItem.dataset.fileId = file.id;
            fileItem.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium">${file.file_number}</h3>
                        <p class="text-sm text-gray-600">${file.file_title}</p>
                        <p class="text-xs text-gray-500">${file.district} • ${file.lga}</p>
                    </div>
                    <div class="text-right">
                        <span class="badge bg-blue-500 text-white">${file.status}</span>
                        <p class="text-xs text-gray-500 mt-1">${file.created_at}</p>
                    </div>
                </div>
            `;
            
            fileItem.addEventListener('click', function() {
                // Remove previous selection
                indexedFilesList.querySelectorAll('.bg-blue-50').forEach(item => {
                    item.classList.remove('bg-blue-50', 'border-blue-200');
                });
                
                // Add selection to current item
                this.classList.add('bg-blue-50', 'border-blue-200');
                
                // Enable confirm button
                if (confirmFileSelectBtn) {
                    confirmFileSelectBtn.disabled = false;
                    confirmFileSelectBtn.dataset.selectedFile = JSON.stringify(file);
                }
            });
            
            indexedFilesList.appendChild(fileItem);
        });
        
        lucide.createIcons();
    }
    
    // Show file selector dialog
    function showFileSelector() {
        if (fileSelectorDialog) {
            fileSelectorDialog.classList.remove('hidden');
            loadIndexedFiles();
        }
    }
    
    // Hide file selector dialog
    function hideFileSelector() {
        if (fileSelectorDialog) {
            fileSelectorDialog.classList.add('hidden');
        }
        if (confirmFileSelectBtn) {
            confirmFileSelectBtn.disabled = true;
            delete confirmFileSelectBtn.dataset.selectedFile;
        }
    }
    
    // Select file for upload
    function selectFileForUpload() {
        if (confirmFileSelectBtn && confirmFileSelectBtn.dataset.selectedFile) {
            const file = JSON.parse(confirmFileSelectBtn.dataset.selectedFile);
            selectedFileIndexing = file;
            
            // Update UI
            if (selectedFileNumber) {
                selectedFileNumber.textContent = file.file_number;
            }
            if (selectedFileBadge) {
                selectedFileBadge.classList.remove('hidden');
            }
            if (changeFileText) {
                changeFileText.textContent = 'Change File';
            }
            if (selectFileWarning) {
                selectFileWarning.style.display = 'none';
            }
            if (browseFilesBtn) {
                browseFilesBtn.disabled = false;
            }
            
            // Update proceed button URL
            if (proceedPageTypingBtn) {
                const currentHref = proceedPageTypingBtn.href;
                const url = new URL(currentHref);
                url.searchParams.set('file_indexing_id', file.id);
                proceedPageTypingBtn.href = url.toString();
            }
            
            hideFileSelector();
        }
    }
    
    // Handle file selection for upload
    function handleFileSelection(files) {
        selectedFiles = Array.from(files);
        updateSelectedFilesDisplay();
    }
    
    // Update selected files display
    function updateSelectedFilesDisplay() {
        if (!selectedFilesContainer || !selectedFilesList || !selectedFilesCount) return;
        
        if (selectedFiles.length === 0) {
            selectedFilesContainer.classList.add('hidden');
            if (startUploadBtn) startUploadBtn.classList.add('hidden');
            return;
        }
        
        selectedFilesContainer.classList.remove('hidden');
        if (startUploadBtn) startUploadBtn.classList.remove('hidden');
        
        selectedFilesCount.textContent = selectedFiles.length;
        
        selectedFilesList.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'p-3 flex items-center justify-between';
            fileItem.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i data-lucide="file-text" class="h-5 w-5 text-gray-400"></i>
                    <div>
                        <p class="text-sm font-medium">${file.name}</p>
                        <p class="text-xs text-gray-500">${formatFileSize(file.size)}</p>
                    </div>
                </div>
                <button class="btn btn-ghost btn-sm" onclick="removeFile(${index})">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            `;
            selectedFilesList.appendChild(fileItem);
        });
        
        lucide.createIcons();
    }
    
    // Remove file from selection
    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updateSelectedFilesDisplay();
    }
    
    // Clear all selected files
    function clearAllFiles() {
        selectedFiles = [];
        updateSelectedFilesDisplay();
        if (fileUpload) fileUpload.value = '';
    }
    
    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Start upload process
    function startUpload() {
        if (!selectedFileIndexing || selectedFiles.length === 0) {
            alert('Please select a file index and documents to upload');
            return;
        }
        
        isUploading = true;
        
        // Show upload progress
        if (uploadIdle) uploadIdle.classList.add('hidden');
        if (selectedFilesContainer) selectedFilesContainer.classList.add('hidden');
        if (uploadProgress) uploadProgress.classList.remove('hidden');
        if (startUploadBtn) startUploadBtn.classList.add('hidden');
        if (cancelUploadBtn) cancelUploadBtn.classList.remove('hidden');
        
        // Prepare form data
        const formData = new FormData();
        formData.append('file_indexing_id', selectedFileIndexing.id);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));
        
        selectedFiles.forEach((file, index) => {
            formData.append(`documents[${index}]`, file);
        });
        
        // Update uploading count
        if (uploadingCount) uploadingCount.textContent = selectedFiles.length;
        
        // Simulate upload progress
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90;
            
            if (progressBar) progressBar.style.width = progress + '%';
            if (uploadPercentage) uploadPercentage.textContent = Math.round(progress) + '%';
        }, 200);
        
        // Send upload request
        fetch('{{ route("scanning.upload") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': formData.get('_token')
            }
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(progressInterval);
            
            if (data.success) {
                // Complete upload
                if (progressBar) progressBar.style.width = '100%';
                if (uploadPercentage) uploadPercentage.textContent = '100%';
                
                setTimeout(() => {
                    completeUpload(data);
                }, 500);
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        })
        .catch(error => {
            clearInterval(progressInterval);
            console.error('Upload error:', error);
            alert('Upload failed: ' + error.message);
            resetUploadState();
        });
    }
    
    // Complete upload process
    function completeUpload(data) {
        isUploading = false;
        
        // Hide progress, show complete
        if (uploadProgress) uploadProgress.classList.add('hidden');
        if (uploadComplete) uploadComplete.classList.remove('hidden');
        if (cancelUploadBtn) cancelUploadBtn.classList.add('hidden');
        if (uploadMoreBtn) uploadMoreBtn.classList.remove('hidden');
        if (viewUploadedBtn) viewUploadedBtn.classList.remove('hidden');
        if (proceedPageTypingBtn) proceedPageTypingBtn.classList.remove('hidden');
        
        uploadedFiles = data.uploaded_documents || [];
        
        // Show success message
        if (data.message) {
            const completeDiv = uploadComplete.querySelector('.text-green-700');
            if (completeDiv) {
                completeDiv.textContent = data.message;
            }
        }
        
        // Update stats
        const uploadsCountEl = document.getElementById('uploads-count');
        if (uploadsCountEl) {
            const currentCount = parseInt(uploadsCountEl.textContent) || 0;
            uploadsCountEl.textContent = currentCount + 1;
        }
    }
    
    // Reset upload state
    function resetUploadState() {
        isUploading = false;
        selectedFiles = [];
        
        if (uploadProgress) uploadProgress.classList.add('hidden');
        if (uploadComplete) uploadComplete.classList.add('hidden');
        if (uploadIdle) uploadIdle.classList.remove('hidden');
        if (selectedFilesContainer) selectedFilesContainer.classList.add('hidden');
        if (startUploadBtn) startUploadBtn.classList.add('hidden');
        if (cancelUploadBtn) cancelUploadBtn.classList.add('hidden');
        if (uploadMoreBtn) uploadMoreBtn.classList.add('hidden');
        if (viewUploadedBtn) viewUploadedBtn.classList.add('hidden');
        if (proceedPageTypingBtn) proceedPageTypingBtn.classList.add('hidden');
        
        if (fileUpload) fileUpload.value = '';
        if (progressBar) progressBar.style.width = '0%';
        if (uploadPercentage) uploadPercentage.textContent = '0%';
    }
    
    // Event listeners
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.getAttribute('data-tab');
            switchTab(tabName);
        });
    });
    
    if (selectFileBtn) {
        selectFileBtn.addEventListener('click', showFileSelector);
    }
    
    if (cancelFileSelectBtn) {
        cancelFileSelectBtn.addEventListener('click', hideFileSelector);
    }
    
    if (confirmFileSelectBtn) {
        confirmFileSelectBtn.addEventListener('click', selectFileForUpload);
    }
    
    if (searchIndexedFiles) {
        let searchTimeout;
        searchIndexedFiles.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadIndexedFiles(this.value);
            }, 300);
        });
    }
    
    if (browseFilesBtn) {
        browseFilesBtn.addEventListener('click', () => {
            if (fileUpload) fileUpload.click();
        });
    }
    
    if (fileUpload) {
        fileUpload.addEventListener('change', (e) => {
            handleFileSelection(e.target.files);
        });
    }
    
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', clearAllFiles);
    }
    
    if (startUploadBtn) {
        startUploadBtn.addEventListener('click', startUpload);
    }
    
    if (cancelUploadBtn) {
        cancelUploadBtn.addEventListener('click', () => {
            if (confirm('Are you sure you want to cancel the upload?')) {
                resetUploadState();
            }
        });
    }
    
    if (uploadMoreBtn) {
        uploadMoreBtn.addEventListener('click', resetUploadState);
    }
    
    if (viewUploadedBtn) {
        viewUploadedBtn.addEventListener('click', () => {
            switchTab('scanned-files');
        });
    }
    
    // Drag and drop functionality
    if (uploadIdle) {
        uploadIdle.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadIdle.classList.add('border-blue-500', 'bg-blue-50');
        });
        
        uploadIdle.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadIdle.classList.remove('border-blue-500', 'bg-blue-50');
        });
        
        uploadIdle.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadIdle.classList.remove('border-blue-500', 'bg-blue-50');
            
            if (selectedFileIndexing) {
                handleFileSelection(e.dataTransfer.files);
            } else {
                alert('Please select an indexed file first');
            }
        });
    }
    
    // Make functions globally available
    window.removeFile = removeFile;
    window.viewDocument = function(scanId) {
        window.open(`{{ route('scanning.view', '') }}/${scanId}`, '_blank');
    };
    
    console.log('Dynamic Scanning module initialized');
});
</script>
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
    let currentEditingDocument = null;
    let currentEditingFileIndex = null;
    let isEditingFileName = false;
    let isUnindexedMode = false; // Track upload mode
    
    // DOM Elements
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    const uploadTypeSwitch = document.getElementById('upload-type-switch');
    const switchLabel = document.getElementById('switch-label');
    const pageTitle = document.getElementById('page-title');
    const pageDescription = document.getElementById('page-description');
    const uploadTabBtn = document.getElementById('upload-tab-btn');
    const selectedFileInfo = document.getElementById('selected-file-info');
    const indexedUploadContent = document.getElementById('indexed-upload-content');
    const unindexedUploadContent = document.getElementById('unindexed-upload-content');
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
    
    // Document details dialog elements
    const documentDetailsDialog = document.getElementById('document-details-dialog');
    const documentNameDisplay = document.getElementById('document-name');
    const cancelDetailsBtn = document.getElementById('cancel-details-btn');
    const saveDetailsBtn = document.getElementById('save-details-btn');
    
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
        
        // Load scanned files when switching to that tab
        if (tabName === 'scanned-files') {
            loadScannedFiles();
        }
    }
    
    // Toggle upload mode function
    function toggleUploadMode() {
        isUnindexedMode = uploadTypeSwitch ? uploadTypeSwitch.checked : false;
        
        // Update UI elements
        if (switchLabel) {
            switchLabel.textContent = isUnindexedMode ? 'Unindexed Files' : 'Indexed Files';
        }
        
        if (pageTitle) {
            pageTitle.textContent = isUnindexedMode ? 'Upload Unindexed Files' : 'Upload Indexed Scanned File';
        }
        
        if (pageDescription) {
            pageDescription.textContent = isUnindexedMode ? 
                'Upload files for analysis and automatic indexing' : 
                'Upload scanned documents to their digital folders';
        }
        
        if (uploadTabBtn) {
            uploadTabBtn.textContent = isUnindexedMode ? 'Upload Unindexed Files' : 'Upload Indexed Scanned File';
        }
        
        // Show/hide appropriate content
        if (indexedUploadContent && unindexedUploadContent) {
            if (isUnindexedMode) {
                indexedUploadContent.style.display = 'none';
                unindexedUploadContent.style.display = 'block';
                unindexedUploadContent.classList.remove('hidden');
                unindexedUploadContent.setAttribute('data-tab-content', 'upload');
            } else {
                indexedUploadContent.style.display = 'block';
                unindexedUploadContent.style.display = 'none';
                unindexedUploadContent.classList.add('hidden');
                indexedUploadContent.setAttribute('data-tab-content', 'upload');
            }
        }
        
        // Hide/show selected file info
        if (selectedFileInfo) {
            selectedFileInfo.style.display = isUnindexedMode ? 'none' : 'block';
        }
        
        // Reset any ongoing uploads
        resetUploadState();
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
                        <p class="text-xs text-gray-500">${file.district || 'No District'} • ${file.lga || 'No LGA'}</p>
                        ${file.applicant_name ? `<p class="text-xs text-blue-600">Applicant: ${file.applicant_name}</p>` : ''}
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
    
    // Get applicant name from selected file indexing
    function getApplicantName() {
        if (!selectedFileIndexing) return 'Document';
        
        // Try to get applicant name from various possible fields
        return selectedFileIndexing.applicant_name || 
               selectedFileIndexing.file_title || 
               selectedFileIndexing.file_number || 
               'Document';
    }
    
    // Handle file selection for upload
    function handleFileSelection(files) {
        // Use applicant name from selected file indexing as the file name
        const applicantName = getApplicantName();
            
        selectedFiles = Array.from(files).map((file, index) => ({
            file: file,
            customName: files.length === 1 ? applicantName : `${applicantName}_${index + 1}`, // Use applicant name, add index if multiple files
            paperSize: 'A4', // Default paper size
            documentType: 'Certificate', // Default document type
            notes: '' // Default notes
        }));
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
        selectedFiles.forEach((fileObj, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'p-3 flex items-center justify-between';
            fileItem.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i data-lucide="file-text" class="h-5 w-5 text-gray-400"></i>
                    <div>
                        <p class="text-sm font-medium">${fileObj.customName}</p>
                        <p class="text-xs text-gray-500">${formatFileSize(fileObj.file.size)} • ${fileObj.paperSize} • ${fileObj.documentType}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button class="btn btn-ghost btn-sm" onclick="editFileName(${index})">
                        <i data-lucide="edit" class="h-4 w-4"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="removeFile(${index})">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
            `;
            selectedFilesList.appendChild(fileItem);
        });
        
        lucide.createIcons();
    }
    
    // Edit file name function using modal with complete form
    function editFileName(index) {
        currentEditingFileIndex = index;
        isEditingFileName = true;
        
        const fileObj = selectedFiles[index];
        
        // Show the document details modal for file editing
        if (documentNameDisplay) {
            documentNameDisplay.textContent = fileObj.customName;
        }
        
        // Set paper size
        const paperSizeRadio = document.querySelector(`input[name="paper-size"][value="${fileObj.paperSize}"]`);
        if (paperSizeRadio) {
            paperSizeRadio.checked = true;
        }
        
        // Set document type
        const documentTypeSelect = document.getElementById('document-type');
        if (documentTypeSelect) {
            documentTypeSelect.value = fileObj.documentType;
        }
        
        // Set notes
        const documentNotesTextarea = document.getElementById('document-notes');
        if (documentNotesTextarea) {
            documentNotesTextarea.value = fileObj.notes;
        }
        
        // Update modal title
        const modalTitle = document.querySelector('#document-details-dialog h2');
        if (modalTitle) {
            modalTitle.textContent = 'Edit File Details';
        }
        
        // Show the modal
        if (documentDetailsDialog) {
            documentDetailsDialog.classList.remove('hidden');
        }
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
        
        selectedFiles.forEach((fileObj, index) => {
            formData.append(`documents[${index}]`, fileObj.file);
            formData.append(`custom_names[${index}]`, fileObj.customName);
            formData.append(`paper_sizes[${index}]`, fileObj.paperSize);
            formData.append(`document_types[${index}]`, fileObj.documentType);
            formData.append(`notes[${index}]`, fileObj.notes);
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
    
    // Load scanned files dynamically
    function loadScannedFiles(search = '') {
        const scannedFilesList = document.getElementById('scanned-files-list');
        if (!scannedFilesList) return;
        
        // Show loading state in the table body
        scannedFilesList.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8">
                    <i data-lucide="loader" class="h-8 w-8 mx-auto text-gray-400 animate-spin mb-4"></i>
                    <p class="text-gray-500">Loading scanned files...</p>
                </td>
            </tr>
        `;
        lucide.createIcons();
        
        const url = `{{ route("scanning.list") }}?${selectedFileIndexing ? 'file_indexing_id=' + selectedFileIndexing.id + '&' : ''}search=${encodeURIComponent(search)}`;
        
        fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderScannedFiles(data.scanned_files);
            } else {
                scannedFilesList.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-8">
                            <i data-lucide="alert-circle" class="h-12 w-12 mx-auto text-red-300 mb-4"></i>
                            <p class="text-red-500">Error loading scanned files</p>
                        </td>
                    </tr>
                `;
                lucide.createIcons();
            }
        })
        .catch(error => {
            console.error('Error loading scanned files:', error);
            scannedFilesList.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-8">
                        <i data-lucide="wifi-off" class="h-12 w-12 mx-auto text-red-300 mb-4"></i>
                        <p class="text-red-500">Network error loading files</p>
                    </td>
                </tr>
            `;
            lucide.createIcons();
        });
    }
    
    // Render scanned files list as table rows
    function renderScannedFiles(files) {
        const scannedFilesList = document.getElementById('scanned-files-list');
        if (!scannedFilesList) return;
        
        if (files.length === 0) {
            scannedFilesList.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-8">
                        <i data-lucide="inbox" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No scanned files found</p>
                        <p class="text-sm text-gray-400">Upload some documents to see them here</p>
                    </td>
                </tr>
            `;
            lucide.createIcons();
            return;
        }
        
        // Clear the tbody and add table rows
        scannedFilesList.innerHTML = '';
        
        files.forEach(file => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    ${file.file_indexing ? file.file_indexing.file_number : 'Unknown'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${file.filename || file.original_filename || 'Document'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${file.uploaded_at || 'Unknown'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(file.status)}">
                        ${file.status ? file.status.charAt(0).toUpperCase() + file.status.slice(1) : 'Unknown'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    1 page
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${file.uploader_name || 'Unknown'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center space-x-2">
                        <button class="text-indigo-600 hover:text-indigo-900" onclick="viewDocument(${file.id})">
                            <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                            View
                        </button>
                        
                    </div>
                </td>
            `;
            scannedFilesList.appendChild(row);
        });
        
        lucide.createIcons();
    }
    
    // Get status badge class for table format
    function getStatusBadgeClass(status) {
        switch (status) {
            case 'typed': return 'bg-green-100 text-green-800';
            case 'scanned': return 'bg-blue-100 text-blue-800';
            default: return 'bg-yellow-100 text-yellow-800';
        }
    }
    
    // Edit document function
    function editDocument(scanId) {
        // Fetch document details
        fetch(`{{ route('scanning.details', '') }}/${scanId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentEditingDocument = data.document;
                showDocumentDetailsDialog(data.document);
            } else {
                alert(data.message || 'Error loading document details');
            }
        })
        .catch(error => {
            console.error('Error loading document details:', error);
            alert('Error loading document details');
        });
    }
    
    // Show document details dialog
    function showDocumentDetailsDialog(document) {
        if (!documentDetailsDialog) return;
        
        isEditingFileName = false;
        currentEditingFileIndex = null;
        
        // Reset modal title
        const modalTitle = document.querySelector('#document-details-dialog h2');
        if (modalTitle) {
            modalTitle.textContent = 'Document Details';
        }
        
        // Populate form with document data
        if (documentNameDisplay) {
            documentNameDisplay.textContent = document.original_filename || '';
        }
        
        // Set paper size
        const paperSizeRadio = document.querySelector(`input[name="paper-size"][value="${document.paper_size || 'A4'}"]`);
        if (paperSizeRadio) {
            paperSizeRadio.checked = true;
        }
        
        // Set document type
        const documentTypeSelect = document.getElementById('document-type');
        if (documentTypeSelect) {
            documentTypeSelect.value = document.document_type || 'Certificate';
        }
        
        // Set notes
        const documentNotesTextarea = document.getElementById('document-notes');
        if (documentNotesTextarea) {
            documentNotesTextarea.value = document.notes || '';
        }
        
        // Show dialog
        documentDetailsDialog.classList.remove('hidden');
    }
    
    // Hide document details dialog
    function hideDocumentDetailsDialog() {
        if (documentDetailsDialog) {
            documentDetailsDialog.classList.add('hidden');
        }
        currentEditingDocument = null;
        currentEditingFileIndex = null;
        isEditingFileName = false;
    }
    
    // Save document details
    function saveDocumentDetails() {
        if (isEditingFileName && currentEditingFileIndex !== null) {
            // Save file details for pre-upload file
            const fileObj = selectedFiles[currentEditingFileIndex];
            
            // Update file object with form values
            fileObj.paperSize = document.querySelector('input[name="paper-size"]:checked')?.value || 'A4';
            fileObj.documentType = document.getElementById('document-type')?.value || 'Certificate';
            fileObj.notes = document.getElementById('document-notes')?.value || '';
            
            updateSelectedFilesDisplay();
            hideDocumentDetailsDialog();
            return;
        }
        
        if (!currentEditingDocument) return;
        
        // Save document details for uploaded document
        const formData = {
            paper_size: document.querySelector('input[name="paper-size"]:checked')?.value || 'A4',
            document_type: document.getElementById('document-type')?.value || 'Certificate',
            notes: document.getElementById('document-notes')?.value || '',
            _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        };
        
        fetch(`{{ route('scanning.update', '') }}/${currentEditingDocument.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': formData._token
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                hideDocumentDetailsDialog();
                loadScannedFiles(); // Reload the list
            } else {
                alert(data.message || 'Error updating document');
            }
        })
        .catch(error => {
            console.error('Error updating document:', error);
            alert('Error updating document');
        });
    }
    
    // Delete document function
    function deleteDocument(scanId) {
        if (!confirm('Are you sure you want to delete this document?')) {
            return;
        }
        
        fetch(`{{ route('scanning.delete', '') }}/${scanId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadScannedFiles(); // Reload the list
            } else {
                alert(data.message || 'Error deleting document');
            }
        })
        .catch(error => {
            console.error('Error deleting document:', error);
            alert('Error deleting document');
        });
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
            loadScannedFiles();
        });
    }
    
    if (uploadTypeSwitch) {
        uploadTypeSwitch.addEventListener('change', toggleUploadMode);
    }
    
    // Document details dialog event listeners
    if (cancelDetailsBtn) {
        cancelDetailsBtn.addEventListener('click', hideDocumentDetailsDialog);
    }
    
    if (saveDetailsBtn) {
        saveDetailsBtn.addEventListener('click', saveDocumentDetails);
    }
    
    // Search scanned files
    const searchScannedFiles = document.getElementById('search-scanned-files');
    if (searchScannedFiles) {
        let searchTimeout;
        searchScannedFiles.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadScannedFiles(this.value);
            }, 300);
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
    window.editFileName = editFileName;
    window.viewDocument = function(scanId) {
        window.open(`{{ route('scanning.view', '') }}/${scanId}`, '_blank');
    };
    window.editDocument = editDocument;
    window.deleteDocument = deleteDocument;
    
    console.log('Dynamic Scanning module initialized');
});
</script>

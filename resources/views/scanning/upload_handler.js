// Initialize event listeners when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const startUploadBtn = document.getElementById('start-upload-btn');
    if (startUploadBtn) {
        startUploadBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            await handleUploadAndAnalysis();
        });
    }
});

// Handle the upload and analysis process
async function handleUploadAndAnalysis() {
    if (!window.selectedFiles || window.selectedFiles.length === 0) {
        alert('Please select files to upload');
        return;
    }

    try {
        // Show OCR modal first
        const ocrModal = document.getElementById('ocr-modal');
        if (ocrModal) {
            ocrModal.classList.remove('hidden');
        }

        // Process each file
        for (const file of window.selectedFiles) {
            let extractedText = '';
            
            // Update OCR progress display
            const currentFileEl = document.getElementById('ocr-current-file');
            if (currentFileEl) {
                currentFileEl.textContent = `Processing: ${file.name}`;
            }

            // Extract text based on file type
            if (file.type === 'application/pdf') {
                extractedText = await extractTextFromPDF(file);
            } else if (file.type.startsWith('image/')) {
                extractedText = await extractTextFromImage(file);
            }

            // Add to metadata
            window.extractedMetadata[file.name] = {
                extractedText,
                originalFileName: file.name,
                fileSize: formatFileSize(file.size),
                fileType: file.type
            };

            // Update OCR progress
            updateOcrProgress((window.selectedFiles.indexOf(file) + 1) / window.selectedFiles.length * 100);
        }

        // Prepare form data for upload
        const formData = new FormData();
        window.selectedFiles.forEach((file, index) => {
            formData.append(`documents[]`, file);
        });
        
        // Add metadata
        formData.append('metadata', JSON.stringify(window.extractedMetadata));
        
        // Add CSRF token
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) {
            formData.append('_token', token);
        }

        // Update progress
        window.uploadProgress = 50;
        updateUploadProgress();

        // Send to server
        const response = await fetch('/scanning/upload-unindexed', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Upload failed: ${response.statusText}`);
        }

        const result = await response.json();
        
        // Hide OCR modal
        if (ocrModal) {
            ocrModal.classList.add('hidden');
        }

        // Update progress
        window.uploadProgress = 100;
        updateUploadProgress();

        // Show success message
        alert('Files uploaded and processed successfully');
        
        // Update UI
        window.uploadStatus = 'complete';
        updateUploadStatus();
        updateUploadButtons();
        
        // Add files to uploaded list
        const newFiles = window.selectedFiles.map((file, index) => ({
            id: `UPLOAD-${Date.now()}-${index}`,
            name: file.name,
            size: formatFileSize(file.size),
            type: file.type || getFileTypeFromName(file.name),
            status: 'Analysis Complete',
            date: new Date().toLocaleDateString(),
            file: file
        }));

        window.uploadedFiles = [...newFiles, ...window.uploadedFiles];
        updateUploadedFilesDisplay();
        updateStats();
        
    } catch (error) {
        console.error('Upload error:', error);
        alert('Error uploading files: ' + error.message);
        
        // Hide OCR modal
        const ocrModal = document.getElementById('ocr-modal');
        if (ocrModal) {
            ocrModal.classList.add('hidden');
        }

        // Reset status
        window.uploadStatus = 'idle';
        window.uploadProgress = 0;
        updateUploadStatus();
        updateUploadButtons();
    }
}

// Helper function to update OCR progress
function updateOcrProgress(percent) {
    const progressBar = document.getElementById('ocr-progress-bar');
    const progressText = document.getElementById('ocr-progress-percent');
    
    if (progressBar) {
        progressBar.style.width = `${percent}%`;
    }
    if (progressText) {
        progressText.textContent = `${Math.round(percent)}%`;
    }
}

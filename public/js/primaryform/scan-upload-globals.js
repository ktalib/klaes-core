// Global Variables for Scan Upload Functionality
// Primary Form EDMS Integration
// File: resources/views/primaryform/assets/js/scan-upload-globals.js

// Global scan upload configuration
window.SCAN_UPLOAD_CONFIG = {
    // File upload settings
    maxFileSize: 5 * 1024 * 1024, // 5MB
    allowedTypes: ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'],
    maxFiles: 5,
    
    // Storage paths
    basePath: 'EDMS/SCAN_UPLOAD',
    
    // Document types for accompanying submission documents
    documentTypes: {
        'application_letter': {
            name: 'Application Letter',
            required: true,
            maxSize: 5 * 1024 * 1024
        },
        'building_plan': {
            name: 'Building Plan',
            required: true,
            maxSize: 5 * 1024 * 1024
        },
        'architectural_design': {
            name: 'Architectural Design',
            required: false,
            maxSize: 5 * 1024 * 1024
        },
        'ownership_document': {
            name: 'Ownership Document',
            required: true,
            maxSize: 5 * 1024 * 1024
        },
        'survey_plan': {
            name: 'Survey Plan',
            required: true,
            maxSize: 5 * 1024 * 1024
        }
    },
    
    // API endpoints
    endpoints: {
        upload: null, // Will be set by the form
        trackingGenerate: '/api/generate-tracking-id',
        scanningStore: '/api/scanning/store'
    },
    
    // Error messages
    messages: {
        fileTooLarge: 'File size must be less than 5MB',
        invalidFileType: 'Only PDF, JPG, JPEG, and PNG files are allowed',
        tooManyFiles: 'Maximum 5 files allowed per document type',
        uploadFailed: 'Upload failed. Please try again.',
        success: 'Files uploaded successfully'
    },
    
    // File naming convention
    fileNaming: {
        prefix: '', // Will be set from file number
        pattern: '{prefix}_{sequence}.{extension}',
        sequenceStart: 1,
        sequencePad: 4 // Zero pad to 4 digits (0001, 0002, etc.)
    }
};

// Global functions for scan upload
window.ScanUploadHelper = {
    
    /**
     * Generate tracking ID for file indexing
     */
    generateTrackingId: function() {
        const segment1 = this.generateRandomString(8);
        const segment2 = this.generateRandomString(5);
        return `TRK-${segment1}-${segment2}`;
    },
    
    /**
     * Generate random string for tracking ID
     */
    generateRandomString: function(length) {
        const characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += characters[Math.floor(Math.random() * characters.length)];
        }
        return result;
    },
    
    /**
     * Validate file for upload
     */
    validateFile: function(file) {
        // Check file size
        if (file.size > window.SCAN_UPLOAD_CONFIG.maxFileSize) {
            return {
                valid: false,
                message: window.SCAN_UPLOAD_CONFIG.messages.fileTooLarge
            };
        }
        
        // Check file type
        if (!window.SCAN_UPLOAD_CONFIG.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                message: window.SCAN_UPLOAD_CONFIG.messages.invalidFileType
            };
        }
        
        return { valid: true };
    },
    
    /**
     * Generate filename based on file number and sequence
     */
    generateFilename: function(fileNumber, sequence, extension) {
        const config = window.SCAN_UPLOAD_CONFIG.fileNaming;
        const paddedSequence = sequence.toString().padStart(config.sequencePad, '0');
        
        return config.pattern
            .replace('{prefix}', fileNumber)
            .replace('{sequence}', paddedSequence)
            .replace('{extension}', extension);
    },
    
    /**
     * Get file extension from filename
     */
    getFileExtension: function(filename) {
        return filename.split('.').pop().toLowerCase();
    },
    
    /**
     * Format file size for display
     */
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },
    
    /**
     * Show loading state
     */
    showLoading: function(message = 'Processing...') {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
            const loadingText = overlay.querySelector('.loading-text');
            if (loadingText) {
                loadingText.textContent = message;
            }
        }
    },
    
    /**
     * Hide loading state
     */
    hideLoading: function() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    },
    
    /**
     * Show success message
     */
    showSuccess: function(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            alert(message);
        }
    },
    
    /**
     * Show error message
     */
    showError: function(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message
            });
        } else {
            alert('Error: ' + message);
        }
    },
    
    /**
     * Log debug information
     */
    log: function(message, data = null) {
        if (window.console && window.console.log) {
            console.log('🔧 [ScanUpload]', message, data || '');
        }
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.ScanUploadHelper.log('Scan Upload globals initialized');
    
    // Set the upload endpoint if form submit URL is available
    if (window.FORM_SUBMIT_URL) {
        window.SCAN_UPLOAD_CONFIG.endpoints.upload = window.FORM_SUBMIT_URL;
        window.ScanUploadHelper.log('Upload endpoint set to:', window.FORM_SUBMIT_URL);
    }
});

console.log('✅ Scan Upload globals loaded');
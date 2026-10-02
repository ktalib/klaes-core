<style>
    /* Modern Card System */
    .main-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 1.5rem;
    }
    
    .workflow-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 1.5rem;
        padding: 2.5rem;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        position: relative;
        overflow: hidden;
    }
    
    .workflow-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        opacity: 0.3;
    }
    
    .workflow-title {
        font-size: 2.25rem;
        font-weight: 800;
        margin-bottom: 0.75rem;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .workflow-subtitle {
        font-size: 1.2rem;
        opacity: 0.95;
        position: relative;
        z-index: 1;
        font-weight: 400;
    }
    
    .progress-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .progress-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
    }
    
    .progress-counter {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .progress-bar-container {
        background: #e2e8f0;
        height: 8px;
        border-radius: 1rem;
        overflow: hidden;
    }
    
    .progress-bar-fill {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        height: 100%;
        border-radius: 1rem;
        transition: width 0.5s ease;
        position: relative;
    }
    
    .progress-bar-fill::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    /* Main Content Layout */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 1200px) {
        .content-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
    }
    
    /* Document Viewer Card */
    .viewer-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .viewer-header {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .viewer-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .viewer-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #2d3748;
    }
    
    .nav-controls {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .nav-btn {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .nav-btn:hover:not(:disabled) {
        border-color: #667eea;
        background: #f7fafc;
        transform: translateY(-1px);
    }
    
    .nav-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .doc-counter {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .document-viewer {
        padding: 2rem;
        min-height: 500px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
    }
    
    .document-viewer img {
        max-width: 100%;
        max-height: 500px;
        object-fit: contain;
        border-radius: 0.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .document-viewer iframe {
        width: 100%;
        height: 500px;
        border: none;
        border-radius: 0.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .viewer-placeholder {
        text-align: center;
        color: #718096;
    }
    
    .viewer-placeholder i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Sidebar */
    .sidebar {
        gap: 1.5rem;
    }
    
    /* Document Thumbnails Card */
    .thumbnails-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        min-height: 400px;
        max-height: 600px;
        overflow-y: auto;
    }
    
    .thumbnails-header {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .thumbnails-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .thumbnails-grid {
        padding: 1.5rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
    }
    
    @media (max-width: 480px) {
        .thumbnails-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    .document-thumbnail {
        aspect-ratio: 3/4;
        border: 3px solid #e2e8f0;
        border-radius: 0.75rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }
    
    .document-thumbnail:hover {
        border-color: #667eea;
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 30px rgba(102, 126, 234, 0.25);
    }
    
    .document-thumbnail.active {
        border-color: #4f46e5;
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        box-shadow: 0 12px 30px rgba(79, 70, 229, 0.3);
        transform: translateY(-2px);
    }
    
    .document-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 0.5rem;
    }
    
    .thumbnail-label {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.9), rgba(0,0,0,0.6), transparent);
        color: white;
        padding: 1rem 0.75rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }
    
    .thumbnail-icon {
        font-size: 2.5rem;
        color: #667eea;
        margin-bottom: 0.75rem;
        opacity: 0.8;
    }

    /* Multi-select mode styles */
    .page-select-checkbox {
        position: absolute;
        top: 8px;
        left: 8px;
        width: 16px;
        height: 16px;
        accent-color: #3b82f6;
        z-index: 10;
        cursor: pointer;
    }

    .document-thumbnail.selected {
        border-color: #3b82f6 !important;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
    }

    .multi-select-mode .document-thumbnail {
        cursor: pointer;
    }

    .multi-select-mode .document-thumbnail:hover {
        border-color: #93c5fd;
    }

    /* Booklet mode styles */
    .document-thumbnail.booklet-page {
        border-color: #f59e0b !important;
        background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%) !important;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2) !important;
    }

    .document-thumbnail.booklet-page::before {
        content: 'B';
        position: absolute;
        top: 4px;
        left: 4px;
        width: 16px;
        height: 16px;
        background: #f59e0b;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 10px;
        font-weight: bold;
        z-index: 10;
    }

    /* Completed state enhancement */
    .document-thumbnail.completed::after {
        content: '✓';
        position: absolute;
        top: 4px;
        right: 4px;
        width: 16px;
        height: 16px;
        background: #10b981;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 10px;
        font-weight: bold;
        z-index: 10;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    /* PDF Thumbnail Specific Styles */
    .pdf-thumbnail-container {
        width: 100%;
        height: 100%;
        position: relative;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
    }
    
    .pdf-thumbnail-canvas {
        max-width: 100%;
        max-height: 100%;
        border-radius: 0.25rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: opacity 0.3s ease;
    }
    
    .pdf-thumbnail-fallback {
        text-align: center;
        color: #64748b;
        padding: 1rem;
    }
    
    .pdf-thumbnail-fallback i {
        width: 2.5rem;
        height: 2.5rem;
        margin-bottom: 0.75rem;
        opacity: 0.7;
        color: #667eea;
    }
    
    .pdf-thumbnail-loading {
        text-align: center;
        color: #667eea;
        padding: 1rem;
    }
    
    .pdf-thumbnail-loading .spinner {
        width: 1.5rem;
        height: 1.5rem;
        margin: 0 auto 0.75rem;
        border: 2px solid #e2e8f0;
        border-top: 2px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    /* Thumbnail Quality Indicator */
    .thumbnail-quality {
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.625rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .thumbnail-quality.pdf {
        background: rgba(239, 68, 68, 0.9);
    }
    
    .thumbnail-quality.image {
        background: rgba(34, 197, 94, 0.9);
    }
    
    /* Classification Form Card */
    .classification-card {
        background: white;
        border-radius: 1.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        height: calc(100vh - 200px);
        max-height: 850px;
        min-height: 700px;
        position: sticky;
        top: 1rem;
        margin-bottom: 1rem;
        overflow: hidden;
    }
    
    .classification-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .classification-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="8" height="8" patternUnits="userSpaceOnUse"><path d="M 8 0 L 0 0 0 8" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        opacity: 0.3;
    }
    
    .classification-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: white;
        margin: 0;
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
    }
    
    .classification-subtitle {
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.9);
        margin-top: 0.5rem;
        position: relative;
        z-index: 1;
        font-weight: 500;
    }

    .form-container {
        flex: 1 1 auto;
        overflow-y: auto;
        min-height: 200px;
        max-height: 100%;
        padding: 2rem 1.5rem;
        background: #fafbfc;
    }
    
    .form-footer {
        position: sticky;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        padding: 1.5rem;
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -8px 16px rgba(0, 0, 0, 0.1);
        z-index: 10;
        margin-top: auto;
    }
    
    /* Enhanced Save Button */
    .btn-save-enhanced {
        width: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 1rem;
        padding: 0;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        min-height: 3.5rem;
    }
    
    .btn-save-enhanced:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    }
    
    .btn-save-enhanced:active {
        transform: translateY(0);
    }
    
    .btn-save-content {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 1rem 1.5rem;
        position: relative;
        z-index: 2;
    }
    
    .btn-save-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: rgba(255, 255, 255, 0.2);
        overflow: hidden;
    }
    
    .btn-save-progress-bar {
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        width: 0%;
        transition: width 0.3s ease;
    }
    
    /* Form Groups */
    .form-group {
        margin-bottom: 1.5rem;
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    
    .form-group:hover {
        border-color: #cbd5e0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    .form-label {
        display: flex;
        align-items: center;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
    }
    
    .form-help {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: #718096;
        display: flex;
        align-items: center;
        line-height: 1.4;
    }
    
    .form-select, .form-input {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
    }
    
    .form-select:focus, .form-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .required {
        color: #e53e3e;
        margin-left: 0.25rem;
    }
    
    /* Page Code Preview */
    .page-code-preview {
        margin-bottom: 0.75rem;
        display: flex;
        justify-content: center;
    }
    
    .page-code-preview .badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 0.875rem;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }
    
    .page-form {
        transition: all 0.3s ease;
    }
    
    .page-form.active {
        opacity: 1;
        transform: translateY(0);
    }
    
    .page-form.hidden {
        display: none;
    }
    
    /* Override existing form-group styles with enhanced version */
    .form-group {
        margin-bottom: 1.5rem;
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    
    .form-group:hover {
        border-color: #cbd5e0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    .form-label {
        display: flex;
        align-items: center;
        font-size: 0.875rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.75rem;
    }
    
    .required {
        color: #e53e3e;
        margin-left: 0.25rem;
    }
    
    .form-input, .form-select {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
    }
    
    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-help {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: #718096;
        display: flex;
        align-items: center;
        line-height: 1.4;
    }
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-input.error, .form-select.error {
        border-color: #e53e3e;
        box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1);
    }
    
    .form-help {
        font-size: 0.75rem;
        color: #718096;
        margin-top: 0.25rem;
    }
    
    .form-footer {
        padding: 1.5rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }
    
    .btn-save {
        width: 100%;
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 1rem 1.5rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .btn-save:hover:not(:disabled) {
        background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(72, 187, 120, 0.3);
    }
    
    .btn-save:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .btn-back {
        background: white;
        border: 2px solid #e2e8f0;
        color: #4a5568;
        border-radius: 0.75rem;
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .btn-back:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
        transform: translateY(-1px);
    }
    
    .status-text {
        color: #718096;
        font-size: 0.875rem;
    }
    
    /* Help Section */
    .help-card {
        background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
        border: 1px solid #90cdf4;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .help-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .help-icon {
        color: #3182ce;
        font-size: 1.25rem;
        margin-top: 0.125rem;
    }
    
    .help-content h4 {
        font-size: 1rem;
        font-weight: 600;
        color: #2c5282;
        margin: 0 0 0.75rem 0;
    }
    
    .help-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .help-list li {
        color: #2c5282;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
        padding-left: 1rem;
        position: relative;
    }
    
    .help-list li::before {
        content: '•';
        color: #3182ce;
        font-weight: bold;
        position: absolute;
        left: 0;
    }
    
    /* No Documents State */
    .no-documents {
        background: white;
        border-radius: 1rem;
        padding: 3rem;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    
    .no-documents-icon {
        font-size: 4rem;
        color: #cbd5e0;
        margin-bottom: 1.5rem;
    }
    
    .no-documents h3 {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 1rem;
    }
    
    .no-documents p {
        color: #718096;
        margin-bottom: 2rem;
        font-size: 1.1rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 1rem 2rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    /* Breadcrumb */
    .breadcrumb {
        background: white;
        border-radius: 1rem;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }
    
    .breadcrumb-list {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    .breadcrumb-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .breadcrumb-link {
        color: #4a5568;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease;
    }
    
    .breadcrumb-link:hover {
        color: #667eea;
    }
    
    .breadcrumb-separator {
        color: #cbd5e0;
    }
    
    .breadcrumb-current {
        color: #718096;
        font-weight: 500;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .main-container {
            padding: 1rem;
        }
        
        .workflow-header {
            padding: 1.5rem;
        }
        
        .workflow-title {
            font-size: 1.5rem;
        }
        
        .thumbnails-grid {
            grid-template-columns: 1fr;
        }
        
        .nav-controls {
            gap: 0.5rem;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
    }
    
    /* Loading States */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    .spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Page Status Indicators */
    .page-status {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        width: 1rem;
        height: 1rem;
        border-radius: 50%;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .page-status.completed {
        background: #48bb78;
        color: white;
    }
    
    .page-status.in-progress {
        background: #ed8936;
        color: white;
    }

    /* Multi-Select and Booklet Management Styles */
    .multi-select-controls {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1rem;
    }

    .booklet-controls {
        background: #fdf4ff;
        border: 1px solid #d946ef;
        border-radius: 0.5rem;
        padding: 1rem;
    }

    .btn-outline {
        background: white;
        border: 1px solid;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-outline:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    .btn-xs {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .page-code-preview-container {
        margin-top: 0.5rem;
    }

    .page-code-preview {
        margin-bottom: 0.5rem;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .bg-blue-500 {
        background-color: #3b82f6;
    }

    .text-white {
        color: white;
    }

    /* Form enhancements for new features */
    .page-type-others-container,
    .page-subtype-others-container {
        margin-top: 0.5rem;
        padding: 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.375rem;
    }

    .multi-select-only {
        padding: 0.75rem;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.375rem;
        margin-bottom: 1rem;
    }

    .booklet-info {
        background: #fdf4ff;
        border: 1px solid #d946ef;
        border-radius: 0.375rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    /* Enhanced thumbnails for multi-select */
    .document-thumbnail.selected {
        border-color: #8b5cf6 !important;
        background-color: #f3f4f6;
        transform: scale(1.02);
    }

    .document-thumbnail.booklet-page {
        border-color: #d946ef !important;
        background-color: #fdf4ff;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .multi-select-controls,
        .booklet-controls {
            padding: 0.75rem;
        }
        
        .btn-outline {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
        
        .page-code-preview .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    }

    /* Advanced Controls Section */
    .advanced-controls-section {
        margin-bottom: 2rem;
    }

    .control-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .control-card:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);
    }

    .control-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.75rem;
    }

    .control-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }

    .control-description {
        color: #64748b;
        font-size: 0.875rem;
        line-height: 1.5;
        margin: 0;
    }

    .btn-control {
        background: white;
        border: 2px solid #e2e8f0;
        color: #4a5568;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 100px;
        justify-content: center;
    }

    .btn-control:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
    }

    .btn-control[data-active="true"] {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-control[data-active="true"]:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    }

    .btn-xs {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        border: 1px solid #e2e8f0;
        background: white;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-xs:hover {
        background: #f7fafc;
        border-color: #cbd5e0;
    }

    .multi-select-active,
    .booklet-active {
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Selected page thumbnail styling */
    .document-thumbnail.selected {
        border: 3px solid #6366f1;
        background: #eff6ff;
        transform: scale(1.05);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
    }

    .document-thumbnail.selected::after {
        content: '✓';
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: #6366f1;
        color: white;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
        z-index: 10;
    }

    /* Booklet page styling */
    .document-thumbnail.booklet-page {
        border: 3px solid #9333ea;
        background: #fdf4ff;
    }

    .document-thumbnail.booklet-page::after {
        content: '📖';
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: #9333ea;
        color: white;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        z-index: 10;
    }

    /* Multi-select checkboxes for pages */
    .page-select-checkbox {
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
        width: 1.25rem;
        height: 1.25rem;
        cursor: pointer;
        z-index: 10;
    }

    .multi-select-only {
        display: none;
    }

    .multi-select-mode .multi-select-only {
        display: block;
        margin-bottom: 1rem;
        padding: 1rem;
        background: #eff6ff;
        border-radius: 0.5rem;
        border: 1px solid #3b82f6;
    }
</style>
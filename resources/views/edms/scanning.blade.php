@extends('layouts.app')
@section('page-title')
    {{ __('Document Scanning') }}
@endsection

@include('sectionaltitling.partials.assets.css')

@section('content')
<style>
    /* Modern Card System */
    .main-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1.5rem;
    }
    
    .workflow-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 1rem;
        padding: 2rem;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }
    
    .workflow-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .workflow-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    
    .info-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .info-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .info-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #2d3748;
    }
    
    .info-subtitle {
        font-size: 0.875rem;
        color: #718096;
        margin-top: 0.25rem;
    }
    
    .badge {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
        border: 1px solid #93c5fd;
        padding: 0.375rem 0.875rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    /* Upload Section */
    .upload-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    
    .upload-header {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .upload-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }
    
    .upload-area {
        padding: 3rem;
        border: 3px dashed #cbd5e0;
        border-radius: 1rem;
        margin: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        cursor: pointer;
    }
    
    .upload-area:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, #ebf4ff 0%, #dbeafe 100%);
        transform: translateY(-2px);
    }
    
    .upload-area.dragover {
        border-color: #667eea;
        background: linear-gradient(135deg, #ebf4ff 0%, #dbeafe 100%);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
    }
    
    .upload-icon {
        width: 4rem;
        height: 4rem;
        color: #667eea;
        margin: 0 auto 1.5rem;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
    }
    
    .upload-text {
        font-size: 1.25rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.5rem;
    }
    
    .upload-subtext {
        color: #718096;
        margin-bottom: 1.5rem;
    }
    
    .btn-browse {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 1rem 2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-browse:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    .upload-help {
        font-size: 0.875rem;
        color: #718096;
        margin-top: 1rem;
    }
    
    /* File List */
    .file-list-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    
    .file-list-header {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .file-list-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }
    
    .file-list {
        padding: 1.5rem;
    }
    
    .file-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.75rem;
        margin-bottom: 1rem;
        background: #f8fafc;
        transition: all 0.2s ease;
    }
    
    .file-item:hover {
        border-color: #cbd5e0;
        background: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .file-item.uploading {
        border-color: #667eea;
        background: linear-gradient(135deg, #ebf4ff 0%, #dbeafe 100%);
    }
    
    .file-item.uploaded {
        border-color: #48bb78;
        background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
    }
    
    .file-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .file-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    
    .file-icon.pdf {
        background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
    }
    
    .file-icon.image {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .file-details h4 {
        font-weight: 600;
        color: #2d3748;
        margin: 0 0 0.25rem 0;
    }
    
    .file-details p {
        font-size: 0.875rem;
        color: #718096;
        margin: 0;
    }
    
    .file-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-remove {
        background: #fed7d7;
        color: #c53030;
        border: 1px solid #feb2b2;
        border-radius: 0.5rem;
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-remove:hover {
        background: #feb2b2;
        transform: translateY(-1px);
    }
    
    .upload-controls {
        padding: 1.5rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .btn-clear {
        background: white;
        border: 2px solid #e2e8f0;
        color: #4a5568;
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-clear:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
    }
    
    .btn-upload {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-upload:hover {
        background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(72, 187, 120, 0.3);
    }
    
    /* Progress */
    .progress-container {
        padding: 1.5rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }
    
    .progress-title {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 1rem;
    }
    
    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e2e8f0;
        border-radius: 1rem;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 1rem;
        transition: width 0.3s ease;
        position: relative;
    }
    
    .progress-fill::after {
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
    
    .progress-text {
        font-size: 0.875rem;
        color: #718096;
        margin-top: 0.5rem;
    }
    
    /* Existing Documents */
    .documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem;
    }
    
    .document-card {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 1.5rem;
        transition: all 0.2s ease;
    }
    
    .document-card:hover {
        border-color: #cbd5e0;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }
    
    .document-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    
    .document-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .document-title {
        font-weight: 600;
        color: #2d3748;
    }
    
    .document-meta {
        font-size: 0.75rem;
        color: #718096;
        margin-bottom: 1rem;
    }
    
    .document-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-view, .btn-download {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .btn-view {
        background: #ebf4ff;
        color: #3182ce;
        border: 1px solid #bee3f8;
    }
    
    .btn-view:hover {
        background: #bee3f8;
        transform: translateY(-1px);
    }
    
    .btn-download {
        background: #f0fff4;
        color: #38a169;
        border: 1px solid #c6f6d5;
    }
    
    .btn-download:hover {
        background: #c6f6d5;
        transform: translateY(-1px);
    }
    
    .btn-edit {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        cursor: pointer;
    }
    
    .btn-edit:hover {
        background: #fde68a;
        transform: translateY(-1px);
    }
    
    /* Document Details Dialog */
    .dialog-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .dialog-overlay.active {
        display: flex;
        opacity: 1;
    }
    
    .dialog-content {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        transform: scale(0.9) translateY(20px);
        transition: all 0.3s ease;
    }
    
    .dialog-overlay.active .dialog-content {
        transform: scale(1) translateY(0);
    }
    
    .animate-fade-in {
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .radio-group {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    
    .radio-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .radio-item:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
    }
    
    .radio-item input[type="radio"] {
        margin: 0;
    }
    
    .radio-item input[type="radio"]:checked + label {
        font-weight: 600;
        color: #667eea;
    }
    
    .radio-item:has(input[type="radio"]:checked) {
        border-color: #667eea;
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    }
    
    .input {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }
    
    .input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-outline {
        background: white;
        border: 2px solid #e2e8f0;
        color: #4a5568;
    }
    
    .btn-outline:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }
    
    .badge-success {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border: 1px solid #6ee7b7;
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
    
    .btn-continue {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .btn-continue:hover {
        background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(72, 187, 120, 0.3);
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
        
        .documents-grid {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
        
        .upload-area {
            padding: 2rem 1rem;
        }
    }
    
    /* Utility Classes */
    .hidden {
        display: none;
    }
    
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
</style>

<!-- Main Content -->
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Dashboard Content -->
    <div class="main-container">
        <!-- Workflow Header -->
        <div class="workflow-header">
            <h1 class="workflow-title">Document Scanning</h1>
            <p class="workflow-subtitle">Upload and manage your scanned documents for digital processing</p>
        </div>

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <ol class="breadcrumb-list">
                <li class="breadcrumb-item">
                    @if($fileIndexing->recertification_application_id)
                        <a href="{{ route('edms.recertification.index', $fileIndexing->recertification_application_id) }}" class="breadcrumb-link">
                            EDMS Workflow
                        </a>
                    @elseif($fileIndexing->subapplication_id)
                        @if($fileIndexing->main_application_id)
                            <a href="{{ route('edms.sub.index', $fileIndexing->main_application_id) }}" class="breadcrumb-link">
                                EDMS Workflow
                            </a>
                        @else
                            <span class="breadcrumb-link text-gray-500">
                                EDMS Workflow
                            </span>
                        @endif
                    @else
                        <a href="{{ route('edms.index', $fileIndexing->main_application_id) }}" class="breadcrumb-link">
                            EDMS Workflow
                        </a>
                    @endif
                </li>
                <li class="breadcrumb-separator">
                    <i data-lucide="chevron-right" style="width: 1rem; height: 1rem;"></i>
                </li>
                <li class="breadcrumb-item">
                    <span class="breadcrumb-current">Document Scanning</span>
                </li>
            </ol>
        </nav>

        <!-- File Info -->
        <div class="info-card">
            <div class="info-header">
                <div>
                    <h3 class="info-title">{{ $fileIndexing->file_title }}</h3>
                    <p class="info-subtitle">{{ $fileIndexing->file_number }}</p>
                </div>
                <span class="badge">Step 2 of 3</span>
            </div>
        </div>

        <!-- Upload Section -->
        <div class="upload-card">
            <div class="upload-header">
                <h2 class="upload-title">Upload Documents</h2>
            </div>
            
            <form id="upload-form" action="{{ route('edms.upload-documents', $fileIndexing->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <!-- Upload Area -->
                <div id="upload-area" class="upload-area">
                    <div class="upload-icon">
                        <i data-lucide="upload" style="width: 2rem; height: 2rem;"></i>
                    </div>
                    <h3 class="upload-text">Drag and drop your documents here</h3>
                    <p class="upload-subtext">or click the button below to browse your files</p>
                    <input type="file" id="file-input" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                    <button type="button" id="browse-btn" class="btn-browse">
                        <i data-lucide="folder" style="width: 1.25rem; height: 1.25rem;"></i>
                        Browse Files
                    </button>
                    <p class="upload-help">Supported formats: PDF, JPG, PNG • Maximum 10MB per file</p>
                </div>

                <!-- Selected Files -->
                <div id="selected-files" class="hidden">
                    <div class="file-list-header">
                        <h3 class="file-list-title">Selected Files</h3>
                    </div>
                    <div id="file-list" class="file-list">
                        <!-- Files will be added here dynamically -->
                    </div>
                    
                    <div class="upload-controls">
                        <button type="button" id="clear-all" class="btn-clear">Clear All</button>
                        <button type="submit" id="upload-btn" class="btn-upload">
                            <i data-lucide="upload" style="width: 1.25rem; height: 1.25rem;"></i>
                            Upload Documents
                        </button>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div id="upload-progress" class="hidden">
                    <div class="progress-container">
                        <h3 class="progress-title">Uploading Documents...</h3>
                        <div class="progress-bar">
                            <div id="progress-fill" class="progress-fill" style="width: 0%"></div>
                        </div>
                        <p id="progress-text" class="progress-text">0% complete</p>
                    </div>
                </div>
            </form>
        </div>

        <!-- Existing Documents -->
        @if($fileIndexing->scannings->count() > 0)
        <div class="file-list-card">
            <div class="file-list-header">
                <h2 class="file-list-title">Uploaded Documents ({{ $fileIndexing->scannings->count() }})</h2>
            </div>
            
            <div class="documents-grid">
                @foreach($fileIndexing->scannings as $scanning)
                <div class="document-card">
                    <div class="document-header">
                        <div class="document-info">
                            @if(str_ends_with($scanning->document_path, '.pdf'))
                                <div class="file-icon pdf">
                                    <i data-lucide="file-text" style="width: 1.25rem; height: 1.25rem;"></i>
                                </div>
                            @else
                                <div class="file-icon image">
                                    <i data-lucide="image" style="width: 1.25rem; height: 1.25rem;"></i>
                                </div>
                            @endif
                            <div>
                                <h4 class="document-title">Document {{ $loop->iteration }}</h4>
                                <span class="badge badge-success">{{ ucfirst($scanning->status) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <p class="document-meta">
                        Uploaded: {{ $scanning->created_at->format('M d, Y H:i') }}
                    </p>
                    
                    <div class="document-actions">
                        <a href="{{ asset('storage/' . $scanning->document_path) }}" target="_blank" class="btn-view">
                            <i data-lucide="eye" style="width: 1rem; height: 1rem;"></i>
                            View
                        </a>
                        <!-- <button type="button" class="btn-edit" 
                                data-document-id="{{ $scanning->id }}"
                                data-file-name="{{ $scanning->original_filename ?? $fileIndexing->file_title ?? basename($scanning->document_path) }}"
                                data-paper-size="{{ $scanning->paper_size ?? 'A4' }}"
                                data-document-type="{{ $scanning->document_type ?? 'Certificate' }}"
                                data-notes="{{ $scanning->notes ?? '' }}">
                            <i data-lucide="edit" style="width: 1rem; height: 1rem;"></i>
                            Edit
                        </button> -->
                        {{-- <a href="{{ asset('storage/' . $scanning->document_path) }}" download class="btn-download">
                            <i data-lucide="download" style="width: 1rem; height: 1rem;"></i>
                            Download
                        </a> --}}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="{{ route('edms.fileindexing', $fileIndexing->id) }}" class="btn-back">
                <i data-lucide="arrow-left" style="width: 1rem; height: 1rem;"></i>
                Back to File Indexing
            </a>
            
            @if($fileIndexing->scannings->count() > 0)
                <a href="{{ route('edms.pagetyping', $fileIndexing->id) }}" class="btn-continue">
                    <i data-lucide="arrow-right" style="width: 1rem; height: 1rem;"></i>
                    Continue to Page Typing
                </a>
            @else
                <div style="color: #718096; font-size: 0.875rem;">
                    Upload documents to continue to the next step
                </div>
            @endif
        </div>

        <!-- Test Modal Button (for debugging) -->
        <div class="info-card hidden">
            <div class="info-header">
                <div>
                    <h3 class="info-title">Debug Modal Test</h3>
                    <p class="info-subtitle">Click the button below to test if the modal works</p>
                </div>
                <button type="button" class="btn-edit" onclick="testModal()">
                    <i data-lucide="test-tube" style="width: 1rem; height: 1rem;"></i>
                    Test Modal
                </button>
            </div>
        </div>

        <!-- Help Section -->
        <div class="help-card">
            <div class="help-header">
                <i data-lucide="help-circle" class="help-icon"></i>
                <div class="help-content">
                    <h4>Document Upload Guidelines</h4>
                    <ul class="help-list">
                        <li>Upload clear, high-quality scans of all relevant documents</li>
                        <li>Ensure documents are properly oriented and readable</li>
                        <li>Use PDF format for multi-page documents when possible</li>
                        <li>Maximum file size is 10MB per document</li>
                        <li>Supported formats: PDF, JPG, JPEG, PNG</li>
                        <li>You can drag and drop multiple files at once</li>
                        <li>Review uploaded documents before proceeding to classification</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Document Details Dialog -->
<div id="document-details-dialog" class="dialog-overlay">
    <div class="dialog-content animate-fade-in">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0; color: #2d3748;">Document Details</h2>
        </div>
        
        <div style="padding: 1.5rem;">
            <div style="margin-bottom: 1.5rem;">
                <label for="document-name" style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #374151;">File Name</label>
                <p style="font-size: 0.875rem; font-weight: 500; color: #2d3748; margin: 0;" id="document-name"></p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label for="paper-size" style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #374151;">Paper Size</label>
                <div class="radio-group">
                    <div class="radio-item">
                        <input type="radio" name="paper-size" id="A4" value="A4">
                        <label for="A4" style="font-size: 0.875rem;">A4</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="paper-size" id="A5" value="A5">
                        <label for="A5" style="font-size: 0.875rem;">A5</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="paper-size" id="A3" value="A3">
                        <label for="A3" style="font-size: 0.875rem;">A3</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="paper-size" id="Letter" value="Letter">
                        <label for="Letter" style="font-size: 0.875rem;">Letter</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="paper-size" id="Legal" value="Legal">
                        <label for="Legal" style="font-size: 0.875rem;">Legal</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="paper-size" id="Custom" value="Custom">
                        <label for="Custom" style="font-size: 0.875rem;">Custom</label>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label for="document-type" style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #374151;">Document Type</label>
                <select id="document-type" class="input">
                    <option value="Certificate">Certificate</option>
                    <option value="Deed">Deed</option>
                    <option value="Letter">Letter</option>
                    <option value="Application Form">Application Form</option>
                    <option value="Map">Map</option>
                    <option value="Survey Plan">Survey Plan</option>
                    <option value="Receipt">Receipt</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label for="document-notes" style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #374151;">Notes (Optional)</label>
                <textarea id="document-notes" class="input" rows="3" placeholder="Add any additional notes about this document..."></textarea>
            </div>
        </div>
        
        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1.5rem; border-top: 1px solid #e2e8f0;">
            <button class="btn btn-outline" id="cancel-details-btn">Cancel</button>
            <button class="btn btn-primary" id="save-details-btn">Save Details</button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    lucide.createIcons();
    
    document.addEventListener('DOMContentLoaded', function() {
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        const browseBtn = document.getElementById('browse-btn');
        const selectedFiles = document.getElementById('selected-files');
        const fileList = document.getElementById('file-list');
        const clearAllBtn = document.getElementById('clear-all');
        const uploadBtn = document.getElementById('upload-btn');
        const uploadProgress = document.getElementById('upload-progress');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        const uploadForm = document.getElementById('upload-form');
        
        let files = [];
        
        // Add event listeners for edit buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-edit') && e.target.closest('.btn-edit').hasAttribute('data-document-id')) {
                const button = e.target.closest('.btn-edit');
                const documentId = button.getAttribute('data-document-id');
                const fileName = button.getAttribute('data-file-name');
                const paperSize = button.getAttribute('data-paper-size');
                const documentType = button.getAttribute('data-document-type');
                const notes = button.getAttribute('data-notes');
                
                console.log('Edit button clicked with data:', {
                    documentId, fileName, paperSize, documentType, notes
                });
                
                openDocumentDetails(documentId, fileName, paperSize, documentType, notes);
            }
        });
        
        // Browse button click
        browseBtn.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Upload area click
        uploadArea.addEventListener('click', (e) => {
            if (e.target === uploadArea || e.target.closest('.upload-icon, .upload-text, .upload-subtext')) {
                fileInput.click();
            }
        });
        
        // File input change
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        // Handle files
        function handleFiles(fileList) {
            for (let file of fileList) {
                if (validateFile(file)) {
                    files.push(file);
                }
            }
            updateFileList();
        }
        
        // Validate file
        function validateFile(file) {
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload PDF, JPG, or PNG files.');
                return false;
            }
            
            if (file.size > maxSize) {
                alert('File size too large. Maximum size is 10MB.');
                return false;
            }
            
            return true;
        }
        
        // Update file list
        function updateFileList() {
            if (files.length === 0) {
                selectedFiles.classList.add('hidden');
                return;
            }
            
            selectedFiles.classList.remove('hidden');
            fileList.innerHTML = '';
            
            files.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const isPdf = file.type === 'application/pdf';
                const iconClass = isPdf ? 'pdf' : 'image';
                const iconName = isPdf ? 'file-text' : 'image';
                
                fileItem.innerHTML = `
                    <div class="file-info">
                        <div class="file-icon ${iconClass}">
                            <i data-lucide="${iconName}" style="width: 1.25rem; height: 1.25rem;"></i>
                        </div>
                        <div class="file-details">
                            <h4>${file.name}</h4>
                            <p>${formatFileSize(file.size)}</p>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button type="button" class="btn-remove" onclick="removeFile(${index})">
                            <i data-lucide="x" style="width: 1rem; height: 1rem;"></i>
                        </button>
                    </div>
                `;
                fileList.appendChild(fileItem);
            });
            
            lucide.createIcons();
        }
        
        // Remove file
        window.removeFile = function(index) {
            files.splice(index, 1);
            updateFileList();
        };
        
        // Clear all files
        clearAllBtn.addEventListener('click', () => {
            files = [];
            fileInput.value = '';
            updateFileList();
        });
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Form submission
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (files.length === 0) {
                alert('Please select files to upload.');
                return;
            }
            
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            
            files.forEach(file => {
                formData.append('documents[]', file);
            });
            
            // Show progress
            uploadProgress.classList.remove('hidden');
            selectedFiles.classList.add('hidden');
            
            // Simulate upload progress
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                progressFill.style.width = progress + '%';
                progressText.textContent = progress + '% complete';
                
                if (progress >= 100) {
                    clearInterval(interval);
                    
                    // Submit form
                    fetch(uploadForm.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            alert(data.message);
                            // Reload page to show uploaded documents
                            window.location.reload();
                        } else {
                            alert('Upload failed: ' + (data.message || 'Unknown error'));
                            uploadProgress.classList.add('hidden');
                            selectedFiles.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Upload failed. Please try again.');
                        uploadProgress.classList.add('hidden');
                        selectedFiles.classList.remove('hidden');
                    });
                }
            }, 200);
        });
    });
    
    // Document Details Dialog Functions
    let currentDocumentId = null;
    
    // Open document details dialog
    window.openDocumentDetails = function(documentId, fileName, paperSize, documentType, notes) {
        console.log('Opening document details modal for document ID:', documentId);
        currentDocumentId = documentId;
        
        const dialog = document.getElementById('document-details-dialog');
        if (!dialog) {
            console.error('Document details dialog not found!');
            return;
        }
        
        // Populate dialog fields
        const documentNameEl = document.getElementById('document-name');
        const documentTypeEl = document.getElementById('document-type');
        const documentNotesEl = document.getElementById('document-notes');
        
        if (documentNameEl) documentNameEl.textContent = fileName;
        if (documentTypeEl) documentTypeEl.value = documentType;
        if (documentNotesEl) documentNotesEl.value = notes;
        
        // Set paper size radio button
        const paperSizeRadio = document.querySelector(`input[name="paper-size"][value="${paperSize}"]`);
        if (paperSizeRadio) {
            paperSizeRadio.checked = true;
        }
        
        // Show dialog
        dialog.style.display = 'flex';
        dialog.classList.add('active');
        
        // Add event listeners
        const cancelBtn = document.getElementById('cancel-details-btn');
        const saveBtn = document.getElementById('save-details-btn');
        
        if (cancelBtn) cancelBtn.onclick = closeDocumentDetails;
        if (saveBtn) saveBtn.onclick = saveDocumentDetails;
        
        // Close on overlay click
        dialog.onclick = function(e) {
            if (e.target === dialog) {
                closeDocumentDetails();
            }
        };
    };
    
    // Close document details dialog
    function closeDocumentDetails() {
        const dialog = document.getElementById('document-details-dialog');
        if (dialog) {
            dialog.classList.remove('active');
        }
        currentDocumentId = null;
    }
    
    // Save document details
    function saveDocumentDetails() {
        if (!currentDocumentId) return;
        
        const paperSize = document.querySelector('input[name="paper-size"]:checked')?.value || 'A4';
        const documentType = document.getElementById('document-type').value;
        const notes = document.getElementById('document-notes').value;
        
        // Prepare form data
        const formData = new FormData();
        formData.append('_token', document.querySelector('input[name="_token"]').value);
        formData.append('_method', 'PUT');
        formData.append('paper_size', paperSize);
        formData.append('document_type', documentType);
        formData.append('notes', notes);
        
        // Show loading state
        const saveBtn = document.getElementById('save-details-btn');
        const originalText = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';
        saveBtn.disabled = true;
        
        // Send update request
        fetch(`/edms/scanning/${currentDocumentId}/update-details`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Document details updated successfully!',
                timer: 2000,
                showConfirmButton: false
            });
            closeDocumentDetails();
            // Optionally reload the page to show updated data
            // window.location.reload();
            } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to update document details: ' + (data.message || 'Unknown error')
            });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to update document details. Please try again.'
            });
        })
        .finally(() => {
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
        });
    }
    
    // Close dialog on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDocumentDetails();
        }
    });
    
    // Test modal function
    window.testModal = function() {
        console.log('Test modal function called');
        openDocumentDetails(999, 'Test Document.pdf', 'A4', 'Certificate', 'This is a test note');
    };
</script>
@endsection
@extends('layouts.app')
@section('page-title')
    {{ __('Page Typing') }}
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
        min-height: 300px;
        max-height: 500px;
        overflow-y: auto;
    }
    
    .thumbnails-header {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .thumbnails-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }
    
    .thumbnails-grid {
        padding: 1.5rem;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .document-thumbnail {
        aspect-ratio: 3/4;
        border: 2px solid #e2e8f0;
        border-radius: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    
    .document-thumbnail:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
    }
    
    .document-thumbnail.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #ebf4ff 0%, #dbeafe 100%);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
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
        background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
        color: white;
        padding: 0.75rem 0.5rem 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
    }
    
    .thumbnail-icon {
        font-size: 2rem;
        color: #667eea;
        margin-bottom: 0.5rem;
    }
    
    /* Classification Form Card */
    .classification-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        height: calc(100vh - 200px);
        max-height: 800px;
        min-height: 700px;
        position: sticky;
        top: 1rem;
        margin-bottom: 1rem;
    }
    
    .form-container {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        height: 100%;
        min-height: 400px;
    }
    
    .form-footer {
        position: sticky;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        padding: 1rem;
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 10;
        margin-top: auto;
    }
    
    .classification-header {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .classification-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }
    
    .classification-subtitle {
        font-size: 0.875rem;
        color: #718096;
        margin-top: 0.25rem;
    }

    .form-container {
        flex: 1 1 auto;
        overflow-y: auto;
        min-height: 200px;
        max-height: 100%;
        padding: 1.5rem;
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
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.5rem;
    }
    
    .required {
        color: #e53e3e;
    }
    
    .form-input, .form-select {
        width: 100%;
        padding: 0.875rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
    }
    
    .form-input:focus, .form-select:focus {
        outline: none;
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
</style>

<!-- Main Content -->
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Dashboard Content -->
    <div class="main-container">
        <!-- Workflow Header -->
        <div class="workflow-header">
            <h1 class="workflow-title">Page Typing & Classification</h1>
            <p class="workflow-subtitle">Classify and organize your scanned documents for efficient retrieval</p>
        </div>

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <ol class="breadcrumb-list">
                <li class="breadcrumb-item">
                    <a href="{{ route('edms.index', $fileIndexing->main_application_id) }}" class="breadcrumb-link">
                        EDMS Workflow
                    </a>
                </li>
                <li class="breadcrumb-separator">
                    <i data-lucide="chevron-right" style="width: 1rem; height: 1rem;"></i>
                </li>
                <li class="breadcrumb-item">
                    <span class="breadcrumb-current">Page Typing</span>
                </li>
            </ol>
        </nav>

        @if($fileIndexing->scannings->count() > 0)
        <!-- Progress Card -->
        <div class="progress-card">
            <div class="progress-header">
                <div class="progress-title">Classification Progress</div>
                <div class="progress-counter" id="progress-text">0 of {{ $fileIndexing->scannings->count() }} completed</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progress-fill" style="width: 0%"></div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Document Viewer -->
            <div class="viewer-card">
                <div class="viewer-header">
                    <div class="viewer-controls">
                        <h3 class="viewer-title">Document Viewer</h3>
                        <div class="nav-controls">
                            <button id="prev-doc" class="nav-btn">
                                <i data-lucide="chevron-left" style="width: 1.25rem; height: 1.25rem;"></i>
                            </button>
                            <div class="doc-counter" id="doc-counter">1 of {{ $fileIndexing->scannings->count() }}</div>
                            <button id="next-doc" class="nav-btn">
                                <i data-lucide="chevron-right" style="width: 1.25rem; height: 1.25rem;"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="document-viewer" class="document-viewer">
                    <div class="viewer-placeholder">
                        <i data-lucide="file-text" style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Select a document to view</p>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Document Thumbnails -->
                <div class="thumbnails-card">
                    <div class="thumbnails-header">
                        <h3 class="thumbnails-title">Documents ({{ $fileIndexing->scannings->count() }})</h3>
                    </div>
                    <div class="thumbnails-grid">
                        @foreach($fileIndexing->scannings as $index => $scanning)
                        <div class="document-thumbnail {{ $index === 0 ? 'active' : '' }}" 
                             data-index="{{ $index }}" 
                             data-path="{{ asset('storage/app/public/' . $scanning->document_path) }}"
                             data-id="{{ $scanning->id }}">
                            @if(str_ends_with($scanning->document_path, '.pdf'))
                                <img src="{{ asset('storage/upload/images/PDF_file_icon.svg') }}" 
                                     alt="PDF Document"
                                     class="thumbnail-icon">
                                
                            @else
                                <img src="{{ asset('storage/app/public/' . $scanning->document_path) }}" 
                                     alt="Document {{ $index + 1 }}"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <i data-lucide="image" class="thumbnail-icon" style="display: none;"></i>
                            @endif
                            <div class="thumbnail-label">Document {{ $index + 1 }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Classification Form -->
                <div class="classification-card">
                    <div class="classification-header">
                        <h3 class="classification-title">Page Classification</h3>
                        <p class="classification-subtitle">Classify the selected document</p>
                    </div>
                    
                    <form id="page-typing-form" action="{{ route('edms.save-page-typing', $fileIndexing->id) }}" method="POST" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                        @csrf
                        
                        <div class="form-container">
                            @foreach($fileIndexing->scannings as $index => $scanning)
                            @php
                                // Find existing page typing data for this document
                                $existingPageTyping = $fileIndexing->pagetypings->where('file_path', $scanning->document_path)->first();
                            @endphp
                            <div class="page-form {{ $index === 0 ? 'active' : 'hidden' }}" data-index="{{ $index }}" style="margin-bottom: 1rem;">
                                <input type="hidden" name="page_types[{{ $index }}][file_path]" value="{{ $scanning->document_path }}">
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        Page Type <span class="required">*</span>
                                    </label>
                                    <select name="page_types[{{ $index }}][page_type]" class="form-select page-type-select" required data-index="{{ $index }}">
                                        <option value="">Select page type</option>
                                        <option value="1" {{ $existingPageTyping && $existingPageTyping->page_type == '1' ? 'selected' : '' }}>File Cover (FC)</option>
                                        <option value="2" {{ $existingPageTyping && $existingPageTyping->page_type == '2' ? 'selected' : '' }}>Application (APP)</option>
                                        <option value="3" {{ $existingPageTyping && $existingPageTyping->page_type == '3' ? 'selected' : '' }}>Bill Notice (BN)</option>
                                        <option value="4" {{ $existingPageTyping && $existingPageTyping->page_type == '4' ? 'selected' : '' }}>Correspondence (COR)</option>
                                        <option value="5" {{ $existingPageTyping && $existingPageTyping->page_type == '5' ? 'selected' : '' }}>Land Title (LT)</option>
                                        <option value="6" {{ $existingPageTyping && $existingPageTyping->page_type == '6' ? 'selected' : '' }}>Legal (LEG)</option>
                                        <option value="7" {{ $existingPageTyping && $existingPageTyping->page_type == '7' ? 'selected' : '' }}>Payment Evidence (PE)</option>
                                        <option value="8" {{ $existingPageTyping && $existingPageTyping->page_type == '8' ? 'selected' : '' }}>Report (REP)</option>
                                        <option value="9" {{ $existingPageTyping && $existingPageTyping->page_type == '9' ? 'selected' : '' }}>Survey (SUR)</option>
                                        <option value="10" {{ $existingPageTyping && $existingPageTyping->page_type == '10' ? 'selected' : '' }}>Miscellaneous (MISC)</option>
                                        <option value="11" {{ $existingPageTyping && $existingPageTyping->page_type == '11' ? 'selected' : '' }}>Image (IMG)</option>
                                        <option value="12" {{ $existingPageTyping && $existingPageTyping->page_type == '12' ? 'selected' : '' }}>Town Planning (TP)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Page Subtype</label>
                                    <select name="page_types[{{ $index }}][page_subtype]" class="form-select page-subtype-select" data-index="{{ $index }}">
                                        <option value="">Select page subtype</option>
                                        @if($existingPageTyping && $existingPageTyping->page_subtype)
                                            <option value="{{ $existingPageTyping->page_subtype }}" selected>{{ $existingPageTyping->page_subtype }}</option>
                                        @endif
                                    </select>
                                    <div class="form-help">Specific classification based on page type</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        Serial Number <span class="required">*</span>
                                    </label>
                                    <input type="number" name="page_types[{{ $index }}][serial_number]" 
                                           class="form-input serial-input" 
                                           value="{{ $existingPageTyping ? $existingPageTyping->serial_number : $index + 1 }}" 
                                           required min="1">
                                    <div class="form-help">Sequential page number for ordering</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Reference Code</label>
                                    <input type="text" name="page_types[{{ $index }}][page_code]" 
                                           class="form-input page-code-input" 
                                           value="{{ $existingPageTyping ? $existingPageTyping->page_code : '' }}"
                                           placeholder="e.g., FC-001, APP-002" readonly>
                                    <div class="form-help">Auto-generated reference code for quick identification</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        <div class="form-footer">
                            <button type="submit" class="btn-save" id="save-btn">
                                <i data-lucide="save" style="width: 1.25rem; height: 1.25rem;"></i>
                                Complete Classification & Finish EDMS
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="{{ route('edms.scanning', $fileIndexing->id) }}" class="btn-back">
                <i data-lucide="arrow-left" style="width: 1rem; height: 1rem;"></i>
                Back to Document Scanning
            </a>
            
            <div class="status-text">
                Complete all document classifications to finish the EDMS workflow
            </div>
        </div>

        @else
        <!-- No Documents State -->
        <div class="no-documents">
            <i data-lucide="file-x" class="no-documents-icon"></i>
            <h3>No Documents Available</h3>
            <p>You need to upload documents before you can classify them. Please go back to the scanning step to upload your documents.</p>
            <a href="{{ route('edms.scanning', $fileIndexing->id) }}" class="btn-primary">
                <i data-lucide="upload" style="width: 1.25rem; height: 1.25rem;"></i>
                Go to Document Scanning
            </a>
        </div>
        @endif

        <!-- Help Section -->
        <div class="help-card">
            <div class="help-header">
                <i data-lucide="help-circle" class="help-icon"></i>
                <div class="help-content">
                    <h4>Document Classification Guidelines</h4>
                    <ul class="help-list">
                        <li>Carefully examine each document to determine its type and purpose</li>
                        <li>Select the appropriate page type first, then choose a specific subtype</li>
                        <li>Assign sequential serial numbers for proper document ordering</li>
                        <li>Reference codes are auto-generated based on page type and serial number</li>
                        <li>Ensure all required fields are completed before saving</li>
                        <li>Use the document viewer to examine details before classification</li>
                        <li>Navigate between documents using the arrow buttons or thumbnails</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    @include('admin.footer')
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    lucide.createIcons();
    
    // Page types and subtypes data structure
    const pageTypes = [
        { id: 1, code: "FC", name: "File Cover" },
        { id: 2, code: "APP", name: "Application" },
        { id: 3, code: "BN", name: "Bill Notice" },
        { id: 4, code: "COR", name: "Correspondence" },
        { id: 5, code: "LT", name: "Land Title" },
        { id: 6, code: "LEG", name: "Legal" },
        { id: 7, code: "PE", name: "Payment Evidence" },
        { id: 8, code: "REP", name: "Report" },
        { id: 9, code: "SUR", name: "Survey" },
        { id: 10, code: "MISC", name: "Miscellaneous" },
        { id: 11, code: "IMG", name: "Image" },
        { id: 12, code: "TP", name: "Town Planning" }
    ];

    // Page subtypes organized by page type ID
    const pageSubTypes = {
        1: [ // File Cover
            { id: 1, code: "NFC", name: "New File Cover" },
            { id: 2, code: "OFC", name: "Old File Cover" }
        ],
        2: [ // Application
            { id: 3, code: "CO", name: "Certificate of Occupancy" },
            { id: 4, code: "REV", name: "Revalidation" },
            { id: 42, code: "OTH", name: "Others" },
            { id: 96, code: "ASI", name: "Application for Surrender/Issuance of CofO" },
            { id: 97, code: "ATF", name: "Application for Temporary Files" },
            { id: 128, code: "REC", name: "Recertification" },
            { id: 136, code: "INS", name: "Inspection" },
            { id: 137, code: "CF", name: "Computer Form" }
        ],
        3: [ // Bill Notice
            { id: 7, code: "DGR", name: "Demand for Ground Rent" },
            { id: 34, code: "DN", name: "Demand Notice" },
            { id: 35, code: "MISC", name: "Miscellaneous" },
            { id: 77, code: "NOA", name: "Notice of Assessment" },
            { id: 92, code: "AUC", name: "Auction Notice" },
            { id: 133, code: "FRP", name: "First Registration of Plot Bill" }
        ],
        4: [ // Correspondence
            { id: 8, code: "AL", name: "Acknowledgment Letter" },
            { id: 9, code: "ASR", name: "Application Submission for Recommendation" },
            { id: 10, code: "ACO", name: "Approval of Certificate of Occupancy" },
            { id: 11, code: "AUL", name: "Authority Letter" },
            { id: 12, code: "BIR", name: "Board of Internal Revenue" },
            { id: 13, code: "CL", name: "Conveyance Letter" },
            { id: 14, code: "DTP", name: "Director Town Planning" },
            { id: 15, code: "SD", name: "Survey Description" },
            { id: 16, code: "SP", name: "Survey Plan" },
            { id: 17, code: "SG", name: "Surveyor General" },
            { id: 29, code: "MISC", name: "Miscellaneous" },
            { id: 30, code: "IM", name: "Internal Memo" },
            { id: 31, code: "EM", name: "External Memo" }
        ],
        5: [ // Land Title
            { id: 5, code: "CO", name: "Certificate of Occupancy" },
            { id: 6, code: "SP", name: "Survey Plan" },
            { id: 32, code: "MISC", name: "Miscellaneous" },
            { id: 130, code: "LFR", name: "Letter of First Registration" },
            { id: 131, code: "CR", name: "Confirmation of Registration" },
            { id: 140, code: "PRO", name: "Provisional Right of Occupancy" }
        ],
        6: [ // Legal
            { id: 18, code: "AGR", name: "Agreement" },
            { id: 39, code: "REP", name: "Report" },
            { id: 44, code: "POA", name: "Power of Attorney" },
            { id: 45, code: "DOS", name: "Deed of Surrender" },
            { id: 46, code: "WCC", name: "Withdrawal of Clients CofO" },
            { id: 48, code: "CACC", name: "CAC Certificate" },
            { id: 49, code: "CI", name: "Certificate of Incorporation" },
            { id: 50, code: "LA", name: "Letter of Administration" },
            { id: 51, code: "MISC", name: "Miscellaneous" },
            { id: 53, code: "DOA", name: "Deed of Assignment" }
        ],
        7: [ // Payment Evidence
            { id: 19, code: "AOF", name: "Assessment of Fees" },
            { id: 20, code: "BT", name: "Bank Teller" },
            { id: 21, code: "ITCC", name: "Income Tax Clearance Certificate" },
            { id: 22, code: "RCR", name: "Revenue Collector's Receipt" },
            { id: 36, code: "MISC", name: "Miscellaneous" },
            { id: 41, code: "REP", name: "Report" },
            { id: 70, code: "ITPR", name: "Income TAX P.A.Y.E Receipt" },
            { id: 78, code: "REC", name: "Receipts" }
        ],
        8: [ // Report
            { id: 23, code: "RR", name: "Reinspection Report" },
            { id: 37, code: "MISC", name: "Miscellaneous" },
            { id: 65, code: "IPVR", name: "Inspection and Property Valuation Report" },
            { id: 101, code: "PSR", name: "Property Search Report" },
            { id: 110, code: "LITR", name: "Low Income TAX Report" },
            { id: 111, code: "RVR", name: "Reconciliation of Valuation Report" },
            { id: 116, code: "PVR", name: "Property Valuation Report" }
        ],
        9: [ // Survey
            { id: 24, code: "TDP", name: "Title Deed Plan" },
            { id: 25, code: "SP", name: "Survey Plan" },
            { id: 26, code: "SD", name: "Survey Description" },
            { id: 33, code: "MISC", name: "Miscellaneous" },
            { id: 38, code: "REP", name: "Report" }
        ],
        10: [ // Miscellaneous
            { id: 27, code: "MISC", name: "Miscellaneous" },
            { id: 43, code: "OC", name: "Other Certificates" },
            { id: 59, code: "CP", name: "Company Profile" },
            { id: 132, code: "LRAT", name: "Land Registration Acknowledgment Ticket" }
        ],
        11: [ // Image
            { id: 28, code: "PP", name: "Passport" }
        ],
        12: [ // Town Planning
            { id: 135, code: "SKT", name: "Sketch" },
            { id: 141, code: "LP", name: "Location Plan" }
        ]
    };
    
    document.addEventListener('DOMContentLoaded', function() {
        const documentViewer = document.getElementById('document-viewer');
        const docCounter = document.getElementById('doc-counter');
        const prevBtn = document.getElementById('prev-doc');
        const nextBtn = document.getElementById('next-doc');
        const thumbnails = document.querySelectorAll('.document-thumbnail');
        const classificationForms = document.querySelectorAll('.page-form');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        
        let currentIndex = 0;
        const totalDocs = {{ $fileIndexing->scannings->count() }};
        
        // Initialize page type change handlers
        initializePageTypeHandlers();
        
        // Initialize
        if (totalDocs > 0) {
            showDocument(0);
            updateProgress();
        }
        
        // Thumbnail clicks
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', () => {
                showDocument(index);
            });
        });
        
        // Navigation buttons
        prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) {
                showDocument(currentIndex - 1);
            }
        });
        
        nextBtn.addEventListener('click', () => {
            if (currentIndex < totalDocs - 1) {
                showDocument(currentIndex + 1);
            }
        });
        
        // Initialize page type change handlers
        function initializePageTypeHandlers() {
            document.querySelectorAll('.page-type-select').forEach(select => {
                select.addEventListener('change', function() {
                    const index = this.dataset.index;
                    const pageTypeId = parseInt(this.value);
                    const subtypeSelect = document.querySelector(`select[name="page_types[${index}][page_subtype]"]`);
                    
                    // Clear existing subtypes
                    subtypeSelect.innerHTML = '<option value="">Select page subtype</option>';
                    
                    // Populate subtypes if page type is selected
                    if (pageTypeId && pageSubTypes[pageTypeId]) {
                        pageSubTypes[pageTypeId].forEach(subtype => {
                            const option = document.createElement('option');
                            option.value = subtype.id;
                            option.textContent = `${subtype.name} (${subtype.code})`;
                            subtypeSelect.appendChild(option);
                        });
                    }
                    
                    // Update reference code
                    updateReferenceCode(index);
                    updateProgress();
                });
                
                // Trigger change event for existing data
                if (select.value) {
                    select.dispatchEvent(new Event('change'));
                }
            });
            
            // Serial number change handlers
            document.querySelectorAll('.serial-input').forEach(input => {
                input.addEventListener('input', function() {
                    const index = this.name.match(/\[(\d+)\]/)[1];
                    updateReferenceCode(index);
                });
            });
        }
        
        // Update reference code
        function updateReferenceCode(index) {
            const pageTypeSelect = document.querySelector(`select[name="page_types[${index}][page_type]"]`);
            const serialInput = document.querySelector(`input[name="page_types[${index}][serial_number]"]`);
            const codeInput = document.querySelector(`input[name="page_types[${index}][page_code]"]`);
            
            if (pageTypeSelect.value && serialInput.value) {
                const pageType = pageTypes.find(pt => pt.id == pageTypeSelect.value);
                if (pageType) {
                    const serial = serialInput.value.padStart(3, '0');
                    codeInput.value = `${pageType.code}-${serial}`;
                }
            } else {
                codeInput.value = '';
            }
        }
        
        // Show document function
        function showDocument(index) {
            currentIndex = index;
            
            // Update thumbnails
            thumbnails.forEach((thumb, i) => {
                thumb.classList.toggle('active', i === index);
            });
            
            // Update forms
            classificationForms.forEach((form, i) => {
                if (i === index) {
                    form.classList.remove('hidden');
                    form.classList.add('active');
                } else {
                    form.classList.add('hidden');
                    form.classList.remove('active');
                }
            });
            
            // Update counter
            docCounter.textContent = `${index + 1} of ${totalDocs}`;
            
            // Update navigation buttons
            prevBtn.disabled = index === 0;
            nextBtn.disabled = index === totalDocs - 1;
            
            // Load document in viewer
            const thumbnail = thumbnails[index];
            const documentPath = thumbnail.dataset.path;
            
            console.log('Loading document:', documentPath);
            
            if (documentPath.toLowerCase().endsWith('.pdf')) {
                documentViewer.innerHTML = `
                    <iframe src="${documentPath}" style="width: 100%; height: 500px; border: none; border-radius: 0.5rem;"></iframe>
                `;
            } else {
                documentViewer.innerHTML = `
                    <img src="${documentPath}" 
                         alt="Document ${index + 1}" 
                         style="max-width: 100%; max-height: 500px; object-fit: contain; border-radius: 0.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display: none; text-align: center; color: #718096;">
                        <i data-lucide="image-off" style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Unable to load image</p>
                    </div>
                `;
            }
            
            lucide.createIcons();
        }
        
        // Update progress function
        function updateProgress() {
            let completed = 0;
            const pageTypeSelects = document.querySelectorAll('.page-type-select');
            
            pageTypeSelects.forEach(select => {
                if (select.value) {
                    completed++;
                }
            });
            
            const percentage = totalDocs > 0 ? (completed / totalDocs) * 100 : 0;
            progressFill.style.width = percentage + '%';
            progressText.textContent = `${completed} of ${totalDocs} completed`;
        }
        
        // Form submission
        document.getElementById('page-typing-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all forms
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                alert('Please fill in all required fields for all documents.');
                return;
            }
            
            // Show loading state
            const saveBtn = document.getElementById('save-btn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<div class="spinner"></div> Saving Classifications...';
            
            // Submit form
            this.submit();
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && currentIndex > 0) {
                showDocument(currentIndex - 1);
            } else if (e.key === 'ArrowRight' && currentIndex < totalDocs - 1) {
                showDocument(currentIndex + 1);
            }
        });
        
        // Auto-save progress (optional)
        let autoSaveTimeout;
        const formInputs = document.querySelectorAll('#page-typing-form input, #page-typing-form select');
        
        formInputs.forEach(input => {
            input.addEventListener('change', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    console.log('Auto-saving form data...');
                }, 2000);
            });
        });
    });
</script>
@endsection
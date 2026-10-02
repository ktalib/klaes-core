@extends('layouts.app')
@section('page-title')
    {{ __('Page Typing') }}
@endsection
  
 

@section('content')
@include('edms.css.pagetyping_css')

<!-- Main Content -->
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Dashboard Content -->
    <div class="main-container">
        <!-- Workflow Header -->
        <div class="workflow-header">
            <h1 class="workflow-title">Page Typing & Classification</h1>
            <p class="workflow-subtitle">Classify each page of your scanned documents for efficient retrieval</p>
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
                        <a href="{{ route('edms.index', [$fileIndexing->main_application_id, 'sub']) }}" class="breadcrumb-link">
                            EDMS Workflow
                        </a>
                    @elseif($fileIndexing->main_application_id)
                        <a href="{{ route('edms.index', $fileIndexing->main_application_id) }}" class="breadcrumb-link">
                            EDMS Workflow
                        </a>
                    @else
                        <a href="#" class="breadcrumb-link">
                            EDMS Workflow
                        </a>
                    @endif
                </li>
                <li class="breadcrumb-separator">
                    <i data-lucide="chevron-right" style="width: 1rem; height: 1rem;"></i>
                </li>
                <li class="breadcrumb-item">
                    <span class="breadcrumb-current">Page Typing</span>
                </li>
               
            </ol>
            
        </nav>

        @php
            // Get all pages from all documents
            $allPages = [];
            $pageIndex = 0;
            
            foreach($fileIndexing->scannings as $docIndex => $scanning) {
                // Use the actual document path from scanning table
                $documentPath = $scanning->document_path;
                
                if(str_ends_with($documentPath, '.pdf')) {
                    // For PDFs, try to get actual page count
                    $pdfInfo = app('App\Http\Controllers\EdmsController')->getPdfPageInfo($documentPath);
                    $pageCount = $pdfInfo['page_count'] ?? 1;
                    
                    for($page = 1; $page <= $pageCount; $page++) {
                        $allPages[] = [
                            'type' => 'pdf_page',
                            'document_index' => $docIndex,
                            'page_number' => $page,
                            'file_path' => $documentPath, // Use actual path from scanning table
                            'display_name' => "Document " . ($docIndex + 1) . " - Page " . $page,
                            'page_index' => $pageIndex++,
                            'scanning_id' => $scanning->id
                        ];
                    }
                } else {
                    // For images, treat as single page
                    $allPages[] = [
                        'type' => 'image',
                        'document_index' => $docIndex,
                        'page_number' => 1,
                        'file_path' => $documentPath, // Use actual path from scanning table
                        'display_name' => "Document " . ($docIndex + 1),
                        'page_index' => $pageIndex++,
                        'scanning_id' => $scanning->id
                    ];
                }
            }
            
            $totalPages = count($allPages);
        @endphp
        
        @if($totalPages > 0)
        <!-- Advanced Controls Section -->
        <div class="advanced-controls-section" style="margin-bottom: 2rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <!-- Multi-Select Mode Control -->
                <div class="control-card">
                    <div class="control-header">
                        <div class="flex items-center gap-2">
                            <i data-lucide="check-square" style="width: 1.25rem; height: 1.25rem; color: #6366f1;"></i>
                            <h3 class="control-title">Multi-Select Mode</h3>
                        </div>
                        <button type="button" class="toggle-multi-select btn-control" data-active="false">
                            <span class="control-text">Enable</span>
                        </button>
                    </div>
                    <p class="control-description">Select multiple pages to apply the same classification settings to all at once</p>
                    
                    <!-- Multi-Select Active State -->
                    <div class="multi-select-active" style="display: none; margin-top: 1rem; padding: 1rem; background: #f0f9ff; border-radius: 0.5rem; border: 1px solid #0ea5e9;">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <span class="selected-count text-sm font-medium text-blue-700">0 pages selected</span>
                                <div class="flex gap-1">
                                    <button type="button" class="btn-xs btn-outline select-all">Select All</button>
                                    <button type="button" class="btn-xs btn-outline clear-selection">Clear</button>
                                </div>
                            </div>
                            <button type="button" class="btn-xs btn-outline exit-multi-select text-red-600 border-red-200">Exit Multi-Select</button>
                        </div>
                        <p class="text-xs text-blue-600">Click on page thumbnails to select/deselect them, then use the classification form to apply settings to all selected pages.</p>
                    </div>
                </div>

                <!-- Booklet Management Control -->
                <div class="control-card">
                    <div class="control-header">
                        <div class="flex items-center gap-2">
                            <i data-lucide="book-open" style="width: 1.25rem; height: 1.25rem; color: #9333ea;"></i>
                            <h3 class="control-title">Booklet Management</h3>
                        </div>
                        <button type="button" class="start-booklet btn-control" data-active="false">
                            <span class="control-text">Start Booklet</span>
                        </button>
                    </div>
                    <p class="control-description">Group consecutive pages as a single document (e.g., Power of Attorney, Survey Plan)</p>
                    
                    <!-- Booklet Active State -->
                    <div class="booklet-active" style="display: none; margin-top: 1rem; padding: 1rem; background: #fdf4ff; border-radius: 0.5rem; border: 1px solid #d946ef;">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-purple-700">
                                <strong>Active Booklet:</strong> <span class="booklet-info-text">Pages 1a, 1b, 1c...</span>
                            </span>
                            <button type="button" class="btn-xs btn-outline end-booklet text-red-600 border-red-200">End Booklet</button>
                        </div>
                        <div class="text-xs text-purple-600">
                            Next page will be numbered: <strong class="next-booklet-number">1a</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Card -->
        <div class="progress-card">
            <div class="progress-header">
                <div class="progress-title">Page Classification Progress</div>
                <div class="progress-counter" id="progress-text">0 of {{ $totalPages }} pages completed</div>
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
                        <h3 class="viewer-title">Page Viewer</h3>
                        <div class="nav-controls">
                            <button id="prev-page" class="nav-btn">
                                <i data-lucide="chevron-left" style="width: 1.25rem; height: 1.25rem;"></i>
                            </button>
                            <div class="doc-counter" id="page-counter">1 of {{ $totalPages }}</div>
                            <button id="next-page" class="nav-btn">
                                <i data-lucide="chevron-right" style="width: 1.25rem; height: 1.25rem;"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="document-viewer" class="document-viewer">
                    <div class="viewer-placeholder">
                        <i data-lucide="file-text" style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Loading page...</p>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Page Thumbnails -->
                <div class="thumbnails-card">
                    <div class="thumbnails-header">
                        <h3 class="thumbnails-title">Pages ({{ $totalPages }})</h3>
                    </div>
                    <div class="thumbnails-grid">
                        @foreach($allPages as $pageData)
                        <div class="document-thumbnail {{ $pageData['page_index'] === 0 ? 'active' : '' }}" 
                             data-page-index="{{ $pageData['page_index'] }}" 
                             data-file-path="{{ $pageData['file_path'] }}"
                             data-page-number="{{ $pageData['page_number'] }}"
                             data-type="{{ $pageData['type'] }}"
                             data-scanning-id="{{ $pageData['scanning_id'] }}">
                            
                            <!-- Quality Indicator -->
                            <div class="thumbnail-quality {{ $pageData['type'] === 'pdf_page' ? 'pdf' : 'image' }}">
                                {{ $pageData['type'] === 'pdf_page' ? 'PDF' : 'IMG' }}
                            </div>
                            
                            @if($pageData['type'] === 'pdf_page')
                                <div class="pdf-thumbnail-container">
                                    <canvas class="pdf-thumbnail-canvas" 
                                            data-pdf-path="{{ asset('storage/app/public/' . $pageData['file_path']) }}"
                                            data-page-number="{{ $pageData['page_number'] }}">
                                    </canvas>
                                    <div class="pdf-thumbnail-fallback" style="display: none;">
                                        <i data-lucide="file-text"></i>
                                        <div style="font-size: 0.75rem; font-weight: 500; margin-top: 0.5rem;">
                                            PDF Page {{ $pageData['page_number'] }}
                                        </div>
                                    </div>
                                    <div class="pdf-thumbnail-loading">
                                        <div class="spinner"></div>
                                        <div style="font-size: 0.75rem; margin-top: 0.5rem;">Loading...</div>
                                    </div>
                                </div>
                            @else
                                <img src="{{ asset('storage/app/public/' . $pageData['file_path']) }}" 
                                     alt="{{ $pageData['display_name'] }}"
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.5rem;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="thumbnail-icon" style="display: none; width: 100%; height: 100%; flex-direction: column; align-items: center; justify-content: center;">
                                    <i data-lucide="image" style="width: 2.5rem; height: 2.5rem; color: #667eea; margin-bottom: 0.5rem;"></i>
                                    <div style="font-size: 0.75rem; color: #64748b; text-align: center;">Image Error</div>
                                </div>
                            @endif
                            
                            <div class="thumbnail-label">{{ $pageData['display_name'] }}</div>
                            <div class="page-status" data-page-index="{{ $pageData['page_index'] }}">
                                <i data-lucide="circle" style="width: 0.75rem; height: 0.75rem;"></i>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Classification Form -->
                <div class="classification-card">
                    <div class="classification-header">
                        <h3 class="classification-title">
                            <i data-lucide="tag" style="width: 1.25rem; height: 1.25rem; display: inline; margin-right: 0.5rem;"></i>
                            Page Classification
                        </h3>
                        <p class="classification-subtitle" id="current-page-title">Classify Page 1</p>
                    </div>
                    
                    <form id="page-typing-form" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                        @csrf
                        
                        <div class="form-container">
                            @foreach($allPages as $pageData)
                            @php
                                // Find existing page typing data for this specific page
                                $existingPageTyping = $fileIndexing->pagetypings
                                    ->where('file_path', $pageData['file_path'])
                                    ->where('page_number', $pageData['page_number'])
                                    ->first();
                            @endphp
                       <div class="page-form {{ $pageData['page_index'] === 0 ? 'active' : 'hidden' }}" 
                           data-page-index="{{ $pageData['page_index'] }}" 
                           data-file-path="{{ $pageData['file_path'] }}"
                           data-page-number="{{ $pageData['page_number'] }}"
                           data-scanning-id="{{ $pageData['scanning_id'] }}"
                           data-saved="{{ $existingPageTyping ? '1' : '0' }}"
                           data-existing-cover-type="{{ $existingPageTyping?->cover_type_id }}"
                           data-existing-page-type="{{ $existingPageTyping?->page_type }}"
                           data-existing-page-subtype="{{ $existingPageTyping?->page_subtype }}"
                           data-existing-page-type-others="{{ $existingPageTyping?->page_type_others }}"
                           data-existing-page-subtype-others="{{ $existingPageTyping?->page_subtype_others }}">
                                
                                <!-- Multi-Select Checkbox (for multi-select mode) -->
                                <div class="form-group multi-select-only" style="display: none;">
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="page-select-checkbox" data-page-index="{{ $pageData['page_index'] }}">
                                        <span class="text-sm">Select this page for batch processing</span>
                                    </label>
                                </div>

                                <!-- Booklet Management Section (for booklet mode) -->
                                <div class="form-group booklet-info" style="display: none; background: #fdf4ff; border: 1px solid #d946ef; border-radius: 0.5rem; padding: 1rem;">
                                    <h4 class="text-sm font-semibold text-purple-900 mb-2">Booklet Mode Active</h4>
                                    <p class="text-sm text-purple-700 mb-2">
                                        This page will be numbered as part of a booklet sequence.
                                    </p>
                                    <div class="booklet-details text-xs text-purple-600">
                                        <!-- Booklet details will be populated by JavaScript -->
                                    </div>
                                </div>

                                <!-- Cover Type Field -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i data-lucide="layers" style="width: 1rem; height: 1rem; display: inline; margin-right: 0.5rem;"></i>
                                        Cover Type <span class="required">*</span>
                                    </label>
                                    <select class="form-select cover-type-select" required data-page-index="{{ $pageData['page_index'] }}">
                                        <option value="">Select cover type</option>
                                        <!-- Cover types will be populated by JavaScript -->
                                    </select>
                                    <div class="form-help">
                                        <i data-lucide="info" style="width: 0.875rem; height: 0.875rem; display: inline; margin-right: 0.25rem;"></i>
                                        Front Cover: Main documents with pagination | Back Cover: Supporting documents without pagination
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i data-lucide="file-text" style="width: 1rem; height: 1rem; display: inline; margin-right: 0.5rem;"></i>
                                        Page Type <span class="required">*</span>
                                    </label>
                                    <select class="form-select page-type-select" required data-page-index="{{ $pageData['page_index'] }}">
                                        <option value="">Select page type</option>
                                        <!-- Page types will be populated by JavaScript -->
                                    </select>
                                    
                                    <!-- Others input field for Page Type - only show when "Others" is selected -->
                                    <div class="page-type-others-container" style="display: none; margin-top: 0.75rem;">
                                        <label class="form-label text-sm">Specify Other Page Type</label>
                                        <input type="text" class="form-input page-type-others-input" 
                                               placeholder="Enter custom page type" maxlength="50" 
                                               data-page-index="{{ $pageData['page_index'] }}">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i data-lucide="folder" style="width: 1rem; height: 1rem; display: inline; margin-right: 0.5rem;"></i>
                                        Page Subtype
                                    </label>
                                    <select class="form-select page-subtype-select" data-page-index="{{ $pageData['page_index'] }}">
                                        <option value="">Select page subtype</option>
                                        <!-- Page subtypes will be populated by JavaScript based on page type -->
                                    </select>
                                    
                                    <!-- Others input field for Page Subtype - only show when "Others" is selected -->
                                    <div class="page-subtype-others-container" style="display: none; margin-top: 0.75rem;">
                                        <label class="form-label text-sm">Specify Other Subtype</label>
                                        <input type="text" class="form-input page-subtype-others-input" 
                                               placeholder="Enter custom subtype" maxlength="50" 
                                               data-page-index="{{ $pageData['page_index'] }}">
                                    </div>
                                    <div class="form-help">
                                        <i data-lucide="info" style="width: 0.875rem; height: 0.875rem; display: inline; margin-right: 0.25rem;"></i>
                                        Specific classification based on page type
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i data-lucide="hash" style="width: 1rem; height: 1rem; display: inline; margin-right: 0.5rem;"></i>
                                        Serial Number <span class="required">*</span>
                                    </label>
                                    <div class="serial-input-container" style="position: relative;">
                                        <input type="text" class="form-input serial-input" 
                                               value="{{ $existingPageTyping ? $existingPageTyping->serial_number : '' }}" 
                                               required maxlength="5" data-page-index="{{ $pageData['page_index'] }}">
                                        <div class="serial-lock-indicator" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6b7280;">
                                            <i data-lucide="lock" style="width: 1rem; height: 1rem;"></i>
                                        </div>
                                    </div>
                                    <div class="form-help">
                                        <i data-lucide="info" style="width: 0.875rem; height: 0.875rem; display: inline; margin-right: 0.25rem;"></i>
                                        Sequential page number for ordering
                                        <span class="serial-locked-help" style="display: none; color: #6b7280; margin-left: 0.5rem;">
                                            (Auto-set to 0 for cover pages)
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i data-lucide="code" style="width: 1rem; height: 1rem; display: inline; margin-right: 0.5rem;"></i>
                                        Reference Code
                                    </label>
                                    <div class="page-code-preview-container">
                                        <div class="page-code-preview">
                                            <span class="badge bg-blue-500 text-white text-base py-2 px-4 rounded-lg font-medium" id="page-code-preview-{{ $pageData['page_index'] }}">
                                                <!-- Page code will be generated by JavaScript -->
                                            </span>
                                        </div>
                                        <input type="text" class="form-input page-code-input" 
                                               value="{{ $existingPageTyping ? $existingPageTyping->page_code : '' }}"
                                               placeholder="e.g., FC-001, APP-002" readonly data-page-index="{{ $pageData['page_index'] }}">
                                    </div>
                                    <div class="form-help">
                                        <i data-lucide="info" style="width: 0.875rem; height: 0.875rem; display: inline; margin-right: 0.25rem;"></i>
                                        Auto-generated reference code for quick identification
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        <!-- Main Save Button - Moved to bottom -->
                        <div class="form-footer">
                            <button type="button" class="btn-save-enhanced" id="save-current-btn">
                                <div class="btn-save-content">
                                    <i data-lucide="save" style="width: 1.25rem; height: 1.25rem;"></i>
                                    <span class="save-button-text">Save & Next Page</span>
                                </div>
                                <div class="btn-save-progress">
                                    <div class="btn-save-progress-bar"></div>
                                </div>
                            </button>
                            
                            <!-- Debug button for testing Reference Code generation -->
                            {{-- <button type="button" class="btn-save-enhanced" id="debug-refresh-codes-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); margin-top: 0.5rem; font-size: 0.875rem; padding: 0.5rem 1rem;">
                                <div class="btn-save-content">
                                    <i data-lucide="refresh-cw" style="width: 1rem; height: 1rem;"></i>
                                    <span class="save-button-text">Debug: Refresh Reference Codes</span>
                                </div>
                            </button> --}}
                        </div>
                            <div style="display: flex; gap: 1rem; display: none;">
                                @if($fileIndexing->recertification_application_id)
                                    <a href="{{ route('recertification.index') }}" class="btn-save" style="flex: 1; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                        <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                                        Finish EDMS - Return to Recertification
                                    </a>
                                @elseif($fileIndexing->subapplication_id)
                                    <a href="{{ route('sectionaltitling.units') }}" class="btn-save" style="flex: 1; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                        <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                                        Finish EDMS - Return to Unit Applications
                                    </a>
                                @elseif($fileIndexing->main_application_id)
                                    <a href="{{ route('sectionaltitling.primary') }}?url=infopro" class="btn-save" style="flex: 1; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                        <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                                        Finish EDMS - Return to Primary Applications
                                    </a>
                                @else
                                    <a href="{{ url('/dashboard') }}" class="btn-save" style="flex: 1; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                        <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                                        Finish EDMS - Return to Dashboard
                                    </a>
                                @endif
                            </div>
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
            
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- <div class="status-text">
                if you have completed classifying all pages, you may click the button below to go back.
                </div> -->
                
                @if($fileIndexing->recertification_application_id)
                    <a href="{{ route('recertification.index') }}" class="btn-primary">
                        <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                        Finish EDMS
                    </a>
                @elseif($fileIndexing->subapplication_id)
                    <a href="{{ route('sectionaltitling.units') }}" class="btn-primary">
                        <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                        Finish EDMS
                    </a>
                @elseif($fileIndexing->main_application_id)
                    <a href="{{ route('sectionaltitling.primary') }}?url=infopro" class="btn-primary">
                        <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                        Finish EDMS
                    </a>
                @else
                    <a href="{{ url('/dashboard') }}" class="btn-primary">
                        <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                        Finish EDMS
                    </a>
                @endif
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
                    <h4>Page Classification Guidelines</h4>
                    <ul class="help-list">
                        <li>Each page of your PDF documents needs to be classified individually</li>
                        <li>For example: Page 1 might be "File Cover", Page 2 might be "Land Title", etc.</li>
                        <li>Select the appropriate page type first, then choose a specific subtype</li>
                        <li>Assign sequential serial numbers for proper document ordering</li>
                        <li>Reference codes are auto-generated based on page type and serial number</li>
                        <li>Use "Save & Next Page" to continue to the next page, or "Finish" when done</li>
                        <li>You can return anytime to continue where you left off</li>
                        <li>Navigate between pages using the arrow buttons or thumbnails</li>
                    </ul>
                </div>
            </div>
     

    <!-- Footer -->
    @include('admin.footer')
</div>

@include('edms.js.pagetyping_js')
@endsection
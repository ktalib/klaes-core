@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('File Viewer') }}
@endsection

@section('content')
<style>
    .pdf-preview-container {
        width: 100%;
        height: 100%;
        background: #ffffff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
    }
    
    .pdf-header {
        background: #dc2626;
        height: 32px;
        border-radius: 8px 8px 0 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 12px;
    }
    
    .window-controls {
        display: flex;
        gap: 6px;
    }
    
    .control-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    
    .control-dot.red { background: #ff5f56; }
    .control-dot.yellow { background: #ffbd2e; }
    .control-dot.green { background: #27ca3f; }
    
    .pdf-badge {
        background: rgba(255,255,255,0.9);
        color: #dc2626;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .pdf-body {
        flex: 1;
        padding: 16px;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .skeleton-title {
        height: 16px;
        background: #374151;
        border-radius: 4px;
        width: 80%;
    }
    
    .skeleton-subtitle {
        height: 12px;
        background: #6b7280;
        border-radius: 3px;
        width: 60%;
    }
    
    .skeleton-line {
        height: 8px;
        background: #d1d5db;
        border-radius: 2px;
        margin: 2px 0;
    }
    
    .skeleton-line.short { width: 75%; }
    .skeleton-line.medium { width: 90%; }
    .skeleton-line.long { width: 85%; }
    
    .skeleton-image {
        height: 40px;
        background: #e5e7eb;
        border-radius: 6px;
        margin: 8px 0;
        width: 100%;
    }
    
    .file-viewer-container {
        background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%);
        min-height: 100vh;
    }
    
    .content-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .header-section {
        background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        color: white;
        padding: 2rem;
    }
    
    .file-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem;
    }
    
    .file-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }
    
    .file-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }
    
    .file-header {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        padding: 1rem;
        font-weight: 600;
    }
    
    .file-content {
        padding: 1.5rem;
    }
    
    .file-preview {
        width: 100%;
        height: 200px;
        background: #f8fafc;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        border: 2px dashed #cbd5e1;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .file-preview:hover {
        border-color: #8b5cf6;
        background: #faf5ff;
    }
    
    .file-icon {
        font-size: 3rem;
        color: #64748b;
    }
    
    .page-type-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        margin: 0.25rem;
    }
    
    .badge-certificate { background: #dcfce7; color: #166534; }
    .badge-survey { background: #fef3c7; color: #92400e; }
    .badge-plan { background: #dbeafe; color: #1e40af; }
    .badge-deed { background: #fce7f3; color: #be185d; }
    .badge-other { background: #f3f4f6; color: #374151; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #8b5cf6;
    }
    
    .stat-label {
        color: #64748b;
        font-size: 0.875rem;
        margin-top: 0.5rem;
    }
    
    .application-summary {
        background: #f8fafc;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .summary-item {
        display: flex;
        flex-direction: column;
    }
    
    .summary-label {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    
    .summary-value {
        font-weight: 600;
        color: #1f2937;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .btn-preview {
        background: #8b5cf6;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .btn-preview:hover {
        background: #7c3aed;
        transform: translateY(-1px);
    }
    
    .btn-download {
        background: #10b981;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .btn-download:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.8);
    }
    
    .modal-content {
        background-color: white;
        margin: 2% auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 1200px;
        height: 90%;
        overflow: hidden;
    }
    
    .modal-header {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-body {
        padding: 2rem;
        height: calc(100% - 80px);
        overflow-y: auto;
    }
    
    .close {
        color: white;
        font-size: 2rem;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }
    
    .close:hover {
        opacity: 0.7;
    }
    
    .file-viewer-frame {
        width: 100%;
        height: 600px;
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .no-files-message {
        text-align: center;
        padding: 3rem;
        color: #64748b;
    }
    
    .no-files-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .parent-info {
        background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid #d8b4fe;
    }
    
    .parent-info h4 {
        color: #7c3aed;
        font-weight: 600;
        margin-bottom: 1rem;
    }
</style>
 


<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <div class="p-6">
        <div class="content-card">
            <!-- Header Section -->
            <div class="header-section">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">File Viewer - Unit Application</h1>
                        <p class="text-purple-100">{{ $ownerName }}</p>
                        <p class="text-purple-200 text-sm">Unit File No: {{ $subApplication->fileno ?? 'N/A' }}</p>
                        {{-- @if($subApplication->unit_number)
                            <p class="text-purple-200 text-sm">Unit {{ $subApplication->unit_number }}
                                @if($subApplication->block_number), Block {{ $subApplication->block_number }}@endif
                                @if($subApplication->floor_number), Floor {{ $subApplication->floor_number }}@endif
                            </p>
                        @endif --}}
                    </div>
                    <div class="text-right">
                        <a href="{{ route('programmes.eRegistry') }}" class="bg-white text-purple-600 px-4 py-2 rounded-lg font-medium hover:bg-purple-50 transition-colors">
                            <i data-lucide="arrow-left" class="w-4 h-4 mr-2 inline"></i>
                            Back to eRegistry
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="p-6">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">{{ $scannings->count() }}</div>
                        <div class="stat-label">Total Files</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">{{ $pageTypings->count() }}</div>
                        <div class="stat-label">Typed Pages</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">{{ $pageTypings->groupBy('page_type')->count() }}</div>
                        <div class="stat-label">Document Types</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">{{ $fileIndexing ? 'Indexed' : 'Pending' }}</div>
                        <div class="stat-label">Index Status</div>
                    </div>
                </div>
                
                <!-- Parent Application Info -->
                @if($parentApplication)
                <div class="parent-info">
                    <h4><i data-lucide="link" class="w-4 h-4 mr-2 inline"></i>Parent Application Information</h4>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span class="summary-label">Parent File Number</span>
                            <span class="summary-value">{{ $parentApplication->fileno ?? 'N/A' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Land Use</span>
                            <span class="summary-value">{{ $parentApplication->land_use ?? 'N/A' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Property Location</span>
                            <span class="summary-value">{{ $parentApplication->property_street_name ?? 'N/A' }}, {{ $parentApplication->property_lga ?? 'N/A' }}</span>
                        </div>
                        {{-- <div class="summary-item">
                            <span class="summary-label">Total Units</span>
                            <span class="summary-value">{{ $parentApplication->NoOfUnits ?? 'N/A' }}</span>
                        </div> --}}
                    </div>
                </div>
                @endif
                
                <!-- Unit Application Summary -->
                <div class="application-summary">
                    <h3 class="text-xl font-bold mb-4 text-gray-800">Unit Application Summary</h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span class="summary-label">Sub Application ID</span>
                            <span class="summary-value">{{ $subApplication->id }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Unit File Number</span>
                            <span class="summary-value">{{ $subApplication->fileno ?? 'N/A' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Unit Number</span>
                            <span class="summary-value">{{ $subApplication->unit_number ?? 'N/A' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Block Number</span>
                            <span class="summary-value">{{ $subApplication->block_number ?? 'N/A' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Floor Number</span>
                            <span class="summary-value">{{ $subApplication->floor_number ?? 'N/A' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Application Status</span>
                            <span class="summary-value">{{ $subApplication->application_status ?? 'N/A' }}</span>
                        </div>
                        @if($fileIndexing)
                        <div class="summary-item">
                            <span class="summary-label">File Title</span>
                            <span class="summary-value">{{ $fileIndexing->file_title ?? 'N/A' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Plot Number</span>
                            <span class="summary-value">{{ $fileIndexing->plot_number ?? 'N/A' }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Files Section -->
                @if($scannings->count() > 0)
                    <h3 class="text-xl font-bold mb-4 text-gray-800">Document Files</h3>
                    <div class="file-grid">
                        @foreach($scannings as $scanning)
                            @php
                                $pageTyping = $pageTypings->where('scanning_id', $scanning->id)->first();
                                $fileExtension = strtolower(pathinfo($scanning->document_path, PATHINFO_EXTENSION));
                            @endphp
                            <div class="file-card">
                                <div class="file-header">
                                    <div class="flex justify-between items-center">
                                        <span>{{ $scanning->original_filename }}</span>
                                        <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded">{{ strtoupper($fileExtension) }}</span>
                                    </div>
                                </div>
                                <div class="file-content">
                                    <div class="file-preview" onclick="previewFile({{ $scanning->id }})">
                                        @if(in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif']))
                                            <img src="{{ asset('storage/app/public/' . $scanning->document_path) }}" alt="Preview" class="w-full h-full object-cover rounded">
                                        @elseif($fileExtension === 'pdf')
                                            <div class="pdf-preview-container">
                                                <div class="pdf-header">
                                                    <div class="window-controls">
                                                        <div class="control-dot red"></div>
                                                        <div class="control-dot yellow"></div>
                                                        <div class="control-dot green"></div>
                                                    </div>
                                                    <div class="pdf-badge">PDF</div>
                                                </div>
                                                <div class="pdf-body">
                                                    <div class="skeleton-title"></div>
                                                    <div class="skeleton-subtitle"></div>
                                                    <div class="skeleton-line short"></div>
                                                    <div class="skeleton-line medium"></div>
                                                    <div class="skeleton-line long"></div>
                                                    <div class="skeleton-image"></div>
                                                    <div class="skeleton-line medium"></div>
                                                    <div class="skeleton-line short"></div>
                                                    <div class="skeleton-line long"></div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="text-center">
                                                <i data-lucide="file" class="file-icon"></i>
                                                <p class="text-sm text-gray-600 mt-2">{{ strtoupper($fileExtension) }} File</p>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    @if($pageTyping)
                                        <div class="mb-3">
                                            <span class="page-type-badge badge-{{ strtolower($pageTyping->page_type) }}">
                                                {{ $pageTyping->page_type }}
                                            </span>
                                            @if($pageTyping->page_subtype)
                                                <span class="page-type-badge badge-other">
                                                    {{ $pageTyping->page_subtype }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    <div class="text-sm text-gray-600 mb-3">
                                        <p><strong>Document Type:</strong> {{ $scanning->document_type ?? 'N/A' }}</p>
                                        <p><strong>Paper Size:</strong> {{ $scanning->paper_size ?? 'N/A' }}</p>
                                        @if($pageTyping)
                                            <p><strong>Page Number:</strong> {{ $pageTyping->page_number ?? 'N/A' }}</p>
                                            <p><strong>Serial Number:</strong> {{ $pageTyping->serial_number ?? 'N/A' }}</p>
                                        @endif
                                        <p><strong>Uploaded:</strong> {{ \Carbon\Carbon::parse($scanning->created_at)->format('M d, Y H:i') }}</p>
                                    </div>
                                    
                                    @if($scanning->notes)
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                                            <p class="text-sm text-yellow-800"><strong>Notes:</strong> {{ $scanning->notes }}</p>
                                        </div>
                                    @endif
                                    
                                    <div class="action-buttons">
                                        <button onclick="previewFile({{ $scanning->id }})" class="btn-preview">
                                            <i data-lucide="eye" class="w-4 h-4 mr-1 inline"></i>
                                            Preview
                                        </button>
                                        <a href="{{ route('file-viewer.download', $scanning->id) }}" class="btn-download">
                                            <i data-lucide="download" class="w-4 h-4 mr-1 inline"></i>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="no-files-message">
                        <div class="no-files-icon">
                            <i data-lucide="folder-x"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Files Found</h3>
                        <p class="text-gray-500">This unit application has not been processed through the EDMS system yet, or no files have been uploaded.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- File Preview Modal -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">File Preview</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="filePreviewContent">
                    <!-- File content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>


<script>
    function previewFile(scanningId) {
        fetch(`/file-viewer/preview/${scanningId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('filePreviewModal');
                    const modalTitle = document.getElementById('modalTitle');
                    const previewContent = document.getElementById('filePreviewContent');
                    
                    modalTitle.textContent = data.file.name;
                    
                    const fileExtension = data.file.extension.toLowerCase();
                    
                    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                        previewContent.innerHTML = `
                            <div class="text-center">
                                <img src="${data.file.path}" alt="Preview" style="max-width: 100%; max-height: 600px; border-radius: 10px;">
                                <div class="mt-4 text-sm text-gray-600">
                                    <p><strong>File Size:</strong> ${data.file.size}</p>
                                    <p><strong>Document Type:</strong> ${data.file.document_type}</p>
                                    <p><strong>Paper Size:</strong> ${data.file.paper_size}</p>
                                </div>
                            </div>
                        `;
                    } else if (fileExtension === 'pdf') {
                        previewContent.innerHTML = `
                            <iframe src="${data.file.path}" class="file-viewer-frame"></iframe>
                            <div class="mt-4 text-sm text-gray-600">
                                <p><strong>File Size:</strong> ${data.file.size}</p>
                                <p><strong>Document Type:</strong> ${data.file.document_type}</p>
                                <p><strong>Paper Size:</strong> ${data.file.paper_size}</p>
                            </div>
                        `;
                    } else {
                        previewContent.innerHTML = `
                            <div class="text-center py-12">
                                <i data-lucide="file" style="font-size: 4rem; color: #64748b; margin-bottom: 1rem;"></i>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">Preview Not Available</h3>
                                <p class="text-gray-500 mb-4">This file type cannot be previewed in the browser.</p>
                                <a href="${data.file.path}" download="${data.file.name}" class="btn-download">
                                    <i data-lucide="download" class="w-4 h-4 mr-1 inline"></i>
                                    Download File
                                </a>
                                <div class="mt-4 text-sm text-gray-600">
                                    <p><strong>File Size:</strong> ${data.file.size}</p>
                                    <p><strong>Document Type:</strong> ${data.file.document_type}</p>
                                    <p><strong>Paper Size:</strong> ${data.file.paper_size}</p>
                                </div>
                            </div>
                        `;
                    }
                    
                    modal.style.display = 'block';
                    
                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                } else {
                    alert('Error loading file preview: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading file preview');
            });
    }
    
    function closeModal() {
        document.getElementById('filePreviewModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('filePreviewModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
</script>
@endsection
@extends('layouts.app')
@section('page-title')
    {{ __('File Upload - EDMS') }}
@endsection
@section('content')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://unpkg.com/tesseract.js@4/dist/tesseract.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Global File Number Modal Assets -->
    <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    
<meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
            <div class="container mx-auto py-6 space-y-6">
                <style>
                    .lucide {
                        width: 1em;
                        height: 1em;
                        display: inline-block;
                        vertical-align: middle;
                    }
                    .document-preview {
                        max-height: 400px;
                        overflow-y: auto;
                    }
                    .pdf-page {
                        margin-bottom: 10px;
                        border: 1px solid #e5e7eb;
                    }
                </style>

                <div class="container mx-auto py-6 space-y-6 px-4">
                    <!-- Page Header -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-900">File Upload</h1>
                        <p class="text-gray-600 mt-2">Upload digital files to the registry</p>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-lg border shadow-sm">
                            <div class="p-4 pb-2">
                                <h3 class="text-sm font-medium text-gray-600">Today's Uploads</h3>
                            </div>
                            <div class="p-4 pt-0">
                                <div class="text-2xl font-bold" id="todaysUploads">{{ $stats['uploads_today'] ?? 0 }}</div>
                                <p class="text-xs text-gray-500 mt-1">Files uploaded today</p>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg border shadow-sm">
                            <div class="p-4 pb-2">
                                <h3 class="text-sm font-medium text-gray-600">Pending Indexing</h3>
                            </div>
                            <div class="p-4 pt-0">
                                <div class="text-2xl font-bold" id="pendingIndexing">{{ $stats['pending_indexing'] ?? 0 }}</div>
                                <p class="text-xs text-gray-500 mt-1">Files waiting to be indexed</p>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg border shadow-sm">
                            <div class="p-4 pb-2">
                                <h3 class="text-sm font-medium text-gray-600">Upload Status</h3>
                            </div>
                            <div class="p-4 pt-0">
                                <div class="text-2xl font-bold flex items-center">
                                    <span id="uploadStatusText">Ready</span>
                                    <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800" id="uploadStatusBadge">Ready</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Current upload status</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="w-full">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button class="tab-button active py-2 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600" data-tab="upload">
                                    Upload Files
                                </button>
                                <button class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="uploaded-files">
                                    Uploaded Files
                                </button>
                            </nav>
                        </div>

                        <!-- Upload Tab Content -->
                        <div id="upload-tab" class="tab-content mt-6">
                            <div class="bg-white rounded-lg border shadow-sm">
                                <div class="p-6 border-b">
                                    <h3 class="text-lg font-semibold">Upload Files</h3>
                                    <p class="text-gray-600 text-sm mt-1">Upload digital files to the registry</p>
                                </div>
                                <div class="p-6">
                                    <div class="space-y-6">
                                        <!-- Upload Area -->
                                        <div id="upload-area" class="rounded-md border-2 border-dashed border-gray-300 p-8 text-center hover:border-blue-400 transition-colors cursor-pointer">
                                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                                                <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                                </svg>
                                            </div>
                                            <h3 class="mb-2 text-lg font-medium">Drag and drop files here</h3>
                                            <p class="mb-4 text-sm text-gray-500">or click to browse files on your computer</p>
                                            <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.tiff,.webp" class="hidden" id="file-upload">
                                            <button class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors" onclick="event.stopPropagation(); document.getElementById('file-upload').click();">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                                </svg>
                                                Browse Files
                                            </button>
                                            <p class="text-xs text-gray-500 mt-2">
                                                Supported formats: PDF, JPG, PNG, GIF, BMP, TIFF, WebP (OCR enabled for scanned documents)
                                            </p>
                                        </div>

                                        <!-- Selected Files -->
                                        <div id="selected-files" class="hidden rounded-md border divide-y">
                                            <div class="p-3 bg-gray-50 flex justify-between items-center">
                                                <span class="font-medium" id="selected-count">0 files selected</span>
                                                <button class="text-sm text-gray-600 hover:text-gray-800" onclick="clearAllFiles()">Clear All</button>
                                            </div>
                                            <div id="selected-files-list"></div>
                                        </div>

                                        <!-- Upload Progress -->
                                        <div id="upload-progress" class="hidden space-y-2">
                                            <div class="flex justify-between text-sm">
                                                <span id="upload-progress-text">Uploading files...</span>
                                                <span id="upload-progress-percent">0%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" id="upload-progress-bar" style="width: 0%"></div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex flex-col md:flex-row gap-4 justify-center">
                                            <button id="start-upload-btn" class="hidden inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors" onclick="startUpload()">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                                </svg>
                                                Start Upload & Analysis
                                            </button>
                                            <button id="cancel-upload-btn" class="hidden inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors" onclick="cancelUpload()">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Cancel
                                            </button>
                                            <button id="upload-more-btn" class="hidden inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors" onclick="resetUpload()">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                                Upload More
                                            </button>
                                            <button id="view-files-btn" class="hidden inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors" onclick="switchToUploadedFiles()">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                View Uploaded Files
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Uploaded Files Tab Content -->
                        <div id="uploaded-files-tab" class="tab-content mt-6 hidden">
                            <div class="bg-white rounded-lg border shadow-sm">
                                <div class="p-6 border-b">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div>
                                            <h3 class="text-lg font-semibold">Uploaded Files</h3>
                                            <p class="text-gray-600 text-sm mt-1">Recently uploaded files ready for processing</p>
                                        </div>
                                        <div class="relative w-full md:w-64">
                                            <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                            <input type="search" placeholder="Search files..." class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="file-search">
                                        </div>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <div id="uploaded-files-list">
                                        <!-- Content will be populated by JavaScript -->
                                    </div>
                                </div>
                                <div id="uploaded-files-footer" class="hidden border-t p-6 flex justify-between">
                                    <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors" onclick="switchToUpload()">Upload More</button>
                                    <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors" onclick="sendToIndexing()">Send All to Indexing</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Processing Section -->
                    <div id="ai-processing" class="hidden mt-6 p-4 bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-lg">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="p-2 bg-blue-100 rounded-full">
                                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-blue-900">AI Document Analysis</h3>
                                <p class="text-sm text-blue-700">Extracting metadata for File Indexing Assistant</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Processing Progress</span>
                                <span class="text-sm font-medium" id="ai-progress-percent">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" id="ai-progress-bar" style="width: 0%"></div>
                            </div>
                            <div class="grid grid-cols-4 gap-2 mt-4" id="ai-stages">
                                <div class="text-center p-2 rounded bg-gray-100 text-gray-500" data-stage="analyzing">
                                    <svg class="h-4 w-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    <div class="text-xs font-medium">Analyzing</div>
                                </div>
                                <div class="text-center p-2 rounded bg-gray-100 text-gray-500" data-stage="extracting">
                                    <svg class="h-4 w-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0112.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    <div class="text-xs font-medium">Extracting</div>
                                </div>
                                <div class="text-center p-2 rounded bg-gray-100 text-gray-500" data-stage="creating">
                                    <svg class="h-4 w-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                    </svg>
                                    <div class="text-xs font-medium">Creating</div>
                                </div>
                                <div class="text-center p-2 rounded bg-gray-100 text-gray-500" data-stage="complete">
                                    <svg class="h-4 w-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div class="text-xs font-medium">Complete</div>
                                </div>
                            </div>

                            <!-- Analysis Results -->
                            <div id="analysis-results" class="hidden mt-4 p-4 bg-white rounded-lg border shadow-sm">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-lg font-semibold text-gray-900">Document Analysis Results</h4>
                                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800 border border-green-200" id="files-processed">0 files processed</span>
                                </div>
                                <div id="metadata-results" class="space-y-6"></div>

                                <!-- Summary and Actions -->
                                <div class="mt-6 p-4 bg-gradient-to-r from-green-50 to-blue-50 rounded-lg border border-green-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h5 class="font-semibold text-gray-900 mb-1">Analysis Complete</h5>
                                            <p class="text-sm text-gray-600">
                                                All documents have been processed and are ready to be uploaded. They will all be grouped under a single file number and stored in the same folder.
                                            </p>
                                        </div>
                                        <div class="flex gap-3">
                                            <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors" onclick="resetUpload()">
                                                Cancel
                                            </button>
                                            <button class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors" onclick="createIndexingEntries()">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                                </svg>
                                                Create File Number & Upload Files
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- OCR Processing Modal -->
                <div id="ocr-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <h3 class="text-lg font-semibold mb-4">Document Text Extraction</h3>
                        <div class="space-y-4">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="p-2 bg-blue-100 rounded-full">
                                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium">Extracting Text from Documents</h4>
                                    <p class="text-sm text-gray-600" id="ocr-current-file">Processing documents...</p>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Extraction Progress</span>
                                    <span id="ocr-progress-percent">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" id="ocr-progress-bar" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="p-3 bg-gray-100 rounded text-sm">
                                <p class="font-medium mb-1">Processing:</p>
                                <ul class="space-y-1 text-gray-600">
                                    <li>• Reading PDF pages</li>
                                    <li>• Converting pages to images</li>
                                    <li>• Running OCR on each page</li>
                                    <li>• Analyzing document structure</li>
                                    <li>• Searching for metadata patterns</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Metadata Modal -->
                <div id="metadata-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="closeMetadataModal()" tabindex="-1">
                    <div class="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold" id="metadata-modal-title">Edit Document Metadata</h3>
                            <button class="text-gray-400 hover:text-gray-600" onclick="closeMetadataModal()">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-medium mb-2">Metadata Fields</h4>
                                <div id="metadata-form" class="space-y-4"></div>
                            </div>
                            <div>
                                <h4 class="font-medium mb-2">Document Preview</h4>
                                <div id="metadata-preview-content" class="document-preview border rounded-lg p-4 bg-gray-50">
                                    <div class="flex justify-end mb-2">
                                        <button id="fullscreen-preview-btn" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center gap-1" onclick="openFullScreenFromPreview()">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 1v4m0 0h-4m4 0l-5-5" />
                                            </svg>
                                            Fullscreen
                                        </button>
                                    </div>
                                    <div id="pdf-preview-wrapper" class="relative hidden">
                                        <canvas id="pdf-preview-canvas" class="w-full h-auto border rounded-lg"></canvas>
                                        <p id="pdf-loading-placeholder" class="text-gray-500 text-center py-8 hidden">Loading PDF preview...</p>
                                        <div id="pdf-navigation-controls" class="flex justify-between items-center mt-4 hidden">
                                            <button id="prev-page-btn" class="px-3 py-1 bg-gray-200 rounded text-gray-700 hover:bg-gray-300 disabled:opacity-50" onclick="goToPreviousPage()" disabled>Previous</button>
                                            <span id="page-info" class="text-sm font-medium text-gray-700"></span>
                                            <button id="next-page-btn" class="px-3 py-1 bg-gray-200 rounded text-gray-700 hover:bg-gray-300 disabled:opacity-50" onclick="goToNextPage()" disabled>Next</button>
                                        </div>
                                    </div>
                                    <div id="image-preview-wrapper" class="hidden">
                                        <img id="image-preview-img" src="/placeholder.svg" alt="Document preview" class="max-w-full h-auto border rounded">
                                        <p id="image-loading-placeholder" class="text-gray-500 text-center py-8 hidden">Loading image preview...</p>
                                    </div>
                                    <p id="unsupported-preview-message" class="text-gray-500 hidden">Preview not available for this file type</p>
                                </div>
                                <h4 class="font-medium mb-2 mt-4">Extracted Text</h4>
                                <div class="document-preview border rounded-lg p-4 bg-white">
                                    <pre id="metadata-extracted-text-preview" class="text-xs whitespace-pre-wrap text-gray-700"></pre>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors" onclick="closeMetadataModal()">
                                Cancel
                            </button>
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors" onclick="applyMetadataChanges()">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Fullscreen Viewer Modal -->
                <div id="fullscreen-viewer" class="hidden fixed inset-0 bg-black bg-opacity-95 flex items-center justify-center z-[999]" onclick="closeFullScreenViewer()" tabindex="-1">
                    <div class="relative w-full h-full mx-2 my-4 max-w-6xl max-h-[96vh]" onclick="event.stopPropagation()">
                        <div class="absolute right-3 top-3 z-70 flex items-center space-x-2">
                            <button id="fullscreen-download" class="px-3 py-2 bg-gray-800 text-white rounded" onclick="downloadFullScreenFile(event)">Download</button>
                            <button id="fullscreen-close" class="px-3 py-2 bg-red-600 text-white rounded" onclick="closeFullScreenViewer()">Close</button>
                        </div>

                        <div id="fullscreen-controls" class="absolute left-3 top-3 z-70 flex items-center space-x-2 text-white">
                            <button id="fullscreen-prev" class="px-3 py-2 bg-gray-800 bg-opacity-60 rounded" onclick="fullScreenPrevPage(event)">Prev</button>
                            <span id="fullscreen-page-info" class="px-3 py-2 bg-gray-800 bg-opacity-30 rounded">Page 0 / 0</span>
                            <button id="fullscreen-next" class="px-3 py-2 bg-gray-800 bg-opacity-60 rounded" onclick="fullScreenNextPage(event)">Next</button>
                        </div>

                        <div id="fullscreen-content" class="w-full h-full flex items-center justify-center">
                            <canvas id="fullscreen-pdf-canvas" class="mx-auto" style="max-width:100%; height:auto; display:none;"></canvas>
                            <img id="fullscreen-image" src="" alt="" class="max-w-full max-h-full object-contain hidden" />
                            <div id="fullscreen-unsupported" class="text-white hidden">Preview not available</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>

    <!-- Include Global File Number Modal -->
    @include('components.global-fileno-modal')

    <script>
        // Global variables
        let uploadStatus = 'idle';
        let uploadProgress = 0;
        let selectedFiles = [];
        let uploadedFiles = [];
        let aiProcessingStage = 'idle';
        let aiProgress = 0;
        let extractedMetadata = {}; // Now keyed by uploadedFile.id
        let currentEditingFile = null;
        let ocrProgress = 0;
        let filteredFiles = []; // For search functionality
        let currentPDFDocument = null; // Stores the PDFDocumentProxy object for the currently opened PDF in the modal
        let currentPageNumber = 1;    // Stores the current page number being viewed in the PDF preview

        // Tracking ID generation function
        function generateTrackingId() {
            const segment1 = generateRandomAlphanumeric(8); // 8 characters
            const segment2 = generateRandomAlphanumeric(5); // 5 characters
            return `TRK-${segment1}-${segment2}`;
        }

        function generateRandomAlphanumeric(length) {
            const characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ123456789'; // Exclude O, 0 for clarity
            let result = '';
            for (let i = 0; i < length; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            return result;
        }

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

            // Load uploaded files from backend
            loadUploadedFiles();

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

            // Load data when switching to uploaded files tab
            if (tabName === 'uploaded-files') {
                loadUploadedFiles();
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
            // Clear the input value to allow selecting the same files again
            e.target.value = '';
        }

        async function handleFiles(files) {
            console.log('handleFiles called with', files.length, 'files'); // Debug log
            selectedFiles = files;

            console.log('Total selected files now:', selectedFiles.length); // Debug log
            console.log('Current uploadStatus:', uploadStatus); // Debug log

            // filePreviewData is no longer needed as original file reference is stored in uploadedFiles

            updateSelectedFilesDisplay();
            updateUploadButtons();
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
            // No need to delete from filePreviewData anymore
            selectedFiles.splice(index, 1);
            updateSelectedFilesDisplay();
            updateUploadButtons();
        }

        function clearAllFiles() {
            selectedFiles = [];
            // filePreviewData = {}; // No longer needed
            updateSelectedFilesDisplay();
            updateUploadButtons();
        }

        function updateUploadButtons() {
            const startBtn = document.getElementById('start-upload-btn');
            const cancelBtn = document.getElementById('cancel-upload-btn');
            const uploadMoreBtn = document.getElementById('upload-more-btn');
            const viewFilesBtn = document.getElementById('view-files-btn');

            // Hide all buttons first EXCEPT start button (we'll handle it separately)
            [cancelBtn, uploadMoreBtn, viewFilesBtn].forEach(btn => {
                if (btn) btn.classList.add('hidden');
            });

            if (uploadStatus === 'idle' && selectedFiles.length > 0) {
                console.log('Showing start upload button'); // Debug log
                if (startBtn) startBtn.classList.remove('hidden');
            } else if (uploadStatus === 'idle' && selectedFiles.length === 0) {
                console.log('Hiding start upload button - no files selected'); // Debug log
                if (startBtn) startBtn.classList.add('hidden');
            } else if (uploadStatus === 'uploading') {
                console.log('Showing cancel button'); // Debug log
                if (startBtn) startBtn.classList.add('hidden'); // Hide start button during upload
                if (cancelBtn) cancelBtn.classList.remove('hidden');
            } else if (uploadStatus === 'complete') {
                console.log('Showing upload more and view files buttons'); // Debug log
                if (startBtn) startBtn.classList.add('hidden'); // Hide start button when complete
                if (uploadMoreBtn) uploadMoreBtn.classList.remove('hidden');
                if (viewFilesBtn) viewFilesBtn.classList.remove('hidden');
            }
        }

        // Upload functionality
        function startUpload() {
            if (selectedFiles.length === 0) {
                alert('Please select files to upload');
                return;
            }

            uploadStatus = 'uploading';
            uploadProgress = 0;
            updateUploadStatus();
            updateUploadButtons();

            // Show progress bar
            const progressDiv = document.getElementById('upload-progress');
            if (progressDiv) progressDiv.classList.remove('hidden');

            // Add files to uploaded list with original file references immediately
            const newFiles = selectedFiles.map((file, index) => ({
                id: `UPLOAD-${Date.now()}-${index}`, // Unique ID for each uploaded file
                name: file.name,
                size: formatFileSize(file.size),
                type: file.type || getFileTypeFromName(file.name),
                status: 'Uploading...', // Initial status
                date: new Date().toLocaleDateString(),
                file: file // Store original file reference
            }));

            uploadedFiles = [...newFiles, ...uploadedFiles];
            filteredFiles = uploadedFiles; // Initialize filtered files
            updateUploadedFilesDisplay(); // Update display to show new files as 'Uploading...'

            // Simulate upload progress
            const interval = setInterval(() => {
                uploadProgress += 5;
                updateUploadProgress();

                    if (uploadProgress >= 100) {
                    clearInterval(interval);
                    uploadStatus = 'complete';
                    updateUploadStatus();
                    updateUploadButtons();
                    // Update status of newly uploaded files: only first file will be queued for analysis
                    newFiles.forEach((file, idx) => {
                        const index = uploadedFiles.findIndex(f => f.id === file.id);
                        if (index !== -1) {
                            if (idx === 0) {
                                uploadedFiles[index].status = 'Ready for analysis';
                            } else {
                                uploadedFiles[index].status = 'Uploaded';
                            }
                        }
                    });
                    updateUploadedFilesDisplay(); // Refresh table with new status
                    updateStats();

                    // Start AI processing for only the first of the newly uploaded files
                    setTimeout(() => {
                        if (newFiles.length > 0) {
                            startAiProcessing([newFiles[0].id]); // Only process first file
                        }
                    }, 500);
                }
            }, 200);
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
            // filePreviewData = {}; // No longer needed
            aiProcessingStage = 'idle';
            aiProgress = 0;
            extractedMetadata = {}; // Clear extracted metadata

            updateUploadStatus();
            updateSelectedFilesDisplay();
            updateUploadButtons();

            const progressDiv = document.getElementById('upload-progress');
            const aiDiv = document.getElementById('ai-processing');
            if (progressDiv) aiDiv.classList.add('hidden');
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
            // Fetch real statistics from backend instead of using local array
            fetch('/scanning/unindexed-files')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const todaysEl = document.getElementById('todaysUploads');
                        const pendingEl = document.getElementById('pendingIndexing');

                        if (todaysEl) todaysEl.textContent = data.count;
                        if (pendingEl) pendingEl.textContent = 0; // Always 0 since we upload and index simultaneously
                    }
                })
                .catch(error => {
                    console.error('Error fetching statistics:', error);
                });
        }

        // Load uploaded files from backend
        function loadUploadedFiles() {
            fetch('/scanning/unindexed-files')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        uploadedFiles = data.files;
                        filteredFiles = uploadedFiles;
                        updateUploadedFilesDisplay();
                        updateStats();
                    }
                })
                .catch(error => {
                    console.error('Error loading uploaded files:', error);
                });
        }

        // AI Processing functionality with real OCR
        async function startAiProcessing(fileIdsToProcess) {
            aiProcessingStage = 'analyzing';
            aiProgress = 0;

            const aiDiv = document.getElementById('ai-processing');
            if (aiDiv) aiDiv.classList.remove('hidden');

            updateAiProgress();

            // Show OCR modal
            const ocrModal = document.getElementById('ocr-modal');
            if (ocrModal) ocrModal.classList.remove('hidden');

            try {
                const newExtractedMetadata = {};

                for (let i = 0; i < fileIdsToProcess.length; i++) {
                    const fileId = fileIdsToProcess[i];
                    const fileEntry = uploadedFiles.find(f => f.id === fileId);
                    if (!fileEntry || !fileEntry.file) {
                        console.warn(`File entry not found for ID: ${fileId}`);
                        continue;
                    }
                    const file = fileEntry.file;

                    // Update current file being processed
                    const currentFileEl = document.getElementById('ocr-current-file');
                    if (currentFileEl) {
                        currentFileEl.textContent = `Processing: ${file.name}`;
                    }

                    updateOcrProgress((i / fileIdsToProcess.length) * 25);

                    let extractedText = '';

                    if (file.type === 'application/pdf') {
                        extractedText = await extractTextFromPDF(file);
                    } else if (file.type.startsWith('image/')) {
                        extractedText = await extractTextFromImage(file);
                    } else {
                        extractedText = `Unsupported file type: ${file.type}`;
                    }

                    updateOcrProgress(50 + (i / fileIdsToProcess.length) * 50);

                    const fileMetadata = extractMetadataFromText(extractedText, file.name);
                    newExtractedMetadata[fileId] = { // Key by the uploadedFile's ID
                        ...fileMetadata,
                        originalFileName: file.name,
                        extractedText: extractedText,
                        fileSize: formatFileSize(file.size),
                        fileType: file.type,
                        file: file // Store original file reference
                    };
                    // Ensure optional fields exist to avoid undefined later
                    newExtractedMetadata[fileId].serialNo = newExtractedMetadata[fileId].serialNo || '';
                    newExtractedMetadata[fileId].registry = newExtractedMetadata[fileId].registry || '';

                    // Update the status of the file in the main uploadedFiles array
                    const uploadedFileIndex = uploadedFiles.findIndex(f => f.id === fileId);
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
                    aiProgress = 60;
                    updateAiProgress();

                    setTimeout(() => {
                        aiProcessingStage = 'creating';
                        aiProgress = 90;
                        updateAiProgress();

                        setTimeout(() => {
                            aiProcessingStage = 'complete';
                            aiProgress = 100;
                            updateAiProgress();
                            showAnalysisResults();
                            updateUploadedFilesDisplay(); // Refresh the table after analysis
                            updateStats();
                        }, 2000);
                    }, 2000);
                }, 1000);

            } catch (error) {
                console.error('Error processing documents:', error);
                if (ocrModal) ocrModal.classList.add('hidden');
                aiProcessingStage = 'idle';
                alert('Error processing documents. Please try again.');
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
            const analyzedCount = Object.keys(extractedMetadata).length;
            filesProcessed.textContent = `${analyzedCount} files processed`;

            // Grouping summary - show total files that will be grouped under one file number
            const groupingHTML = `
                <div class="border rounded-lg overflow-hidden mb-4">
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 border-b">
                        <div class="flex justify-between items-center">
                            <div>
                                <h5 class="font-semibold text-gray-900">File Grouping Summary</h5>
                                <p class="text-sm text-gray-600 mt-1">All ${uploadedFiles.length} files will be grouped under one file number</p>
                            </div>
                            <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
                                Group Upload
                            </span>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-yellow-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <div>
                                    <h6 class="font-medium text-yellow-800">Important Notice</h6>
                                    <p class="text-sm text-yellow-700 mt-1">
                                        All selected files will be stored in the same folder and associated with a single file number.
                                        This ensures all related documents are grouped together for easy management.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Build details: show the analyzed file(s) from extractedMetadata first, then list uploaded-only files
            const analyzedHTML = Object.entries(extractedMetadata).map(([fileId, data]) =>
                generateMetadataResultHTML(fileId, data)
            ).join('');

            // Uploaded-only files (no extracted metadata) - show minimal info
            const uploadedOnlyHTML = uploadedFiles.filter(f => !extractedMetadata[f.id]).map(f => `
                <div class="border rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 py-3 border-b">
                        <div class="flex justify-between items-center">
                            <div>
                                <h5 class="font-semibold text-gray-900">${f.name}</h5>
                                <p class="text-sm text-gray-600 mt-1">Uploaded - no analysis performed (grouped under batch)</p>
                            </div>
                            <span class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">Uploaded</span>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-lg bg-white p-2 rounded border">Filename: ${f.name}</div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-lg bg-white p-2 rounded border">Status: ${f.status}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            metadataResults.innerHTML = groupingHTML + analyzedHTML + uploadedOnlyHTML;
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
            if (plotNumberFound) score += 15; // Added for Plot No:
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
                gif: 'image/gif',
                bmp: 'image/bmp',
                tiff: 'image/tiff',
                webp: 'image/webp'
            };
            return fileTypes[extension] || 'application/octet-stream';
        }

        function getFileIcon(fileType) {
            if (fileType.includes('pdf')) {
                return '<svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
            }
            if (fileType.includes('image')) {
                return '<svg class="h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
            }
            return '<svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
        }

        // Unified function to open metadata edit modal with preview
        function openMetadataEditModal(fileId) {
            currentEditingFile = fileId;

            const fileEntry = uploadedFiles.find(f => f.id === fileId);
            if (!fileEntry || !fileEntry.file) {
                alert('File not found or not available for editing/preview.');
                return;
            }

            const metadataEntry = extractedMetadata[fileId] || {
                extractedFileNumber: '',
                plotNumber: '',
                detectedOwner: '',
                landUseType: '',
                extractedText: 'Text extraction not yet performed or available.'
            };

            const form = document.getElementById('metadata-form');
            const extractedTextPreview = document.getElementById('metadata-extracted-text-preview');
            const modalTitle = document.getElementById('metadata-modal-title');

            // Preview elements
            const pdfPreviewWrapper = document.getElementById('pdf-preview-wrapper');
            const imagePreviewWrapper = document.getElementById('image-preview-wrapper');
            const unsupportedPreviewMessage = document.getElementById('unsupported-preview-message');
            const pdfPreviewCanvas = document.getElementById('pdf-preview-canvas');
            const pdfLoadingPlaceholder = document.getElementById('pdf-loading-placeholder');
            const pdfNavigationControls = document.getElementById('pdf-navigation-controls');
            const imagePreviewImg = document.getElementById('image-preview-img');
            const imageLoadingPlaceholder = document.getElementById('image-loading-placeholder');


            if (!form || !extractedTextPreview || !modalTitle || !pdfPreviewWrapper || !imagePreviewWrapper || !unsupportedPreviewMessage || !pdfPreviewCanvas || !pdfLoadingPlaceholder || !pdfNavigationControls || !imagePreviewImg || !imageLoadingPlaceholder) {
                console.error("One or more modal elements not found.");
                return;
            }

            modalTitle.textContent = `Edit Metadata - ${fileEntry.name}`;

            // Populate the metadata form
            form.innerHTML = `
                <div class="mb-4 p-2 bg-gray-50 border border-gray-200 rounded-md">
                    <p class="text-sm text-gray-700">Fields marked with <span class="text-red-500">*</span> are required.</p>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Tracking ID</label>
                    <input type="text" id="edit-trackingId" value="${metadataEntry.trackingId || generateTrackingId()}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 font-mono text-sm font-bold text-red-600"
                           readonly placeholder="Auto-generated tracking ID">
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">File Number <span class="text-red-500">*</span></label>
                    <div class="flex space-x-2">
                        <!-- Disabled input for display -->
                        <input type="text" id="edit-fileNumber-display" value="${metadataEntry.extractedFileNumber}" 
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700"
                               placeholder="No file number selected" readonly>
                        
                        <!-- Hidden input for actual value submission -->
                        <input type="hidden" id="edit-fileNumber" name="fileno" value="${metadataEntry.extractedFileNumber}">
                        
                        <button type="button" id="open-fileno-modal-btn" 
                                class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center space-x-1"
                                onclick="openFileNumberModal()"
                                title="Open File Number Selector">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                            <span class="hidden sm:inline">Select</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500">Click "Select" to choose from MLPP, KANGIS, or New KANGIS formats</p>
                </div>
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">File Name <span class="text-red-500">*</span></label>
                    <input type="text" id="edit-owner" value="${metadataEntry.detectedOwner}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Enter file name">
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Plot No:</label>
                    <input type="text" id="edit-plotNumber" value="${metadataEntry.plotNumber}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Enter plot number">
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">TP Number</label>
                    <input type="text" id="edit-tpNumber" value="${metadataEntry.tpNumber || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Enter TP number">
                </div>
                 <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">LPKN Number</label>
                    <input type="text" id="edit-lpknNo" value="${metadataEntry.lpknNo || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Enter LPKN number">
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Land Use Type</label>
                    <select id="edit-landUse" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select land use</option>
                        <option value="‌RESIDENTIAL" ${metadataEntry.landUseType === '‌RESIDENTIAL' ? 'selected' : ''}>‌RESIDENTIAL</option>
                        <option value="AGRICULTURAL" ${metadataEntry.landUseType === 'AGRICULTURAL' ? 'selected' : ''}>AGRICULTURAL</option>
                        <option value="COMMERCIAL" ${metadataEntry.landUseType === 'COMMERCIAL' ? 'selected' : ''}>COMMERCIAL</option>
                        <option value="COMMERCIAL ( WARE HOUSE)" ${metadataEntry.landUseType === 'COMMERCIAL ( WARE HOUSE)' ? 'selected' : ''}>COMMERCIAL ( WARE HOUSE)</option>
                        <option value="COMMERCIAL (OFFICES)" ${metadataEntry.landUseType === 'COMMERCIAL (OFFICES)' ? 'selected' : ''}>COMMERCIAL (OFFICES)</option>
                        <option value="COMMERCIAL (PETROL FILLING STATION)" ${metadataEntry.landUseType === 'COMMERCIAL (PETROL FILLING STATION)' ? 'selected' : ''}>COMMERCIAL (PETROL FILLING STATION)</option>
                        <option value="COMMERCIAL (RICE PROCESSING)" ${metadataEntry.landUseType === 'COMMERCIAL (RICE PROCESSING)' ? 'selected' : ''}>COMMERCIAL (RICE PROCESSING)</option>
                        <option value="COMMERCIAL (SCHOOL)" ${metadataEntry.landUseType === 'COMMERCIAL (SCHOOL)' ? 'selected' : ''}>COMMERCIAL (SCHOOL)</option>
                        <option value="COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)" ${metadataEntry.landUseType === 'COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)' ? 'selected' : ''}>COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)</option>
                        <option value="COMMERCIAL (SHOPS AND OFFICES)" ${metadataEntry.landUseType === 'COMMERCIAL (SHOPS AND OFFICES)' ? 'selected' : ''}>COMMERCIAL (SHOPS AND OFFICES)</option>
                        <option value="COMMERCIAL (SHOPS)" ${metadataEntry.landUseType === 'COMMERCIAL (SHOPS)' ? 'selected' : ''}>COMMERCIAL (SHOPS)</option>
                        <option value="COMMERCIAL (WAREHOUSE)" ${metadataEntry.landUseType === 'COMMERCIAL (WAREHOUSE)' ? 'selected' : ''}>COMMERCIAL (WAREHOUSE)</option>
                        <option value="COMMERCIAL (WORKSHOP AND OFFICES)" ${metadataEntry.landUseType === 'COMMERCIAL (WORKSHOP AND OFFICES)' ? 'selected' : ''}>COMMERCIAL (WORKSHOP AND OFFICES)</option>
                        <option value="COMMERCIAL AND RESIDENTIAL" ${metadataEntry.landUseType === 'COMMERCIAL AND RESIDENTIAL' ? 'selected' : ''}>COMMERCIAL AND RESIDENTIAL</option>
                        <option value="INDUSTRIAL" ${metadataEntry.landUseType === 'INDUSTRIAL' ? 'selected' : ''}>INDUSTRIAL</option>
                        <option value="INDUSTRIAL (SMALL SCALE)" ${metadataEntry.landUseType === 'INDUSTRIAL (SMALL SCALE)' ? 'selected' : ''}>INDUSTRIAL (SMALL SCALE)</option>
                        <option value="RESIDENTIAL" ${metadataEntry.landUseType === 'RESIDENTIAL' ? 'selected' : ''}>RESIDENTIAL</option>
                        <option value="RESIDENTIAL AND COMMERCIAL" ${metadataEntry.landUseType === 'RESIDENTIAL AND COMMERCIAL' ? 'selected' : ''}>RESIDENTIAL AND COMMERCIAL</option>
                        <option value="RESIDENTIAL/COMMERCIAL" ${metadataEntry.landUseType === 'RESIDENTIAL/COMMERCIAL' ? 'selected' : ''}>RESIDENTIAL/COMMERCIAL</option>
                        <option value="RESIDENTIAL/COMMERCIAL LAYOUT" ${metadataEntry.landUseType === 'RESIDENTIAL/COMMERCIAL LAYOUT' ? 'selected' : ''}>RESIDENTIAL/COMMERCIAL LAYOUT</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">District</label>
                    <select id="edit-district" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="" selected>Select District Name</option>
                        <option value="DALA">DALA</option>
                        <option value="DAWAKIN KUDU">DAWAKIN KUDU</option>
                        <option value="FAGGE">FAGGE</option>
                        <option value="GWALE">GWALE</option>
                        <option value="KUMBOTSO">KUMBOTSO</option>
                        <option value="AJINGI">AJINGI</option>
                        <option value="ALBASU">ALBASU</option>
                        <option value="BAGWAI">BAGWAI</option>
                        <option value="BEBEJI">BEBEJI</option>
                        <option value="BICHI">BICHI</option>
                        <option value="BUNKURE">BUNKURE</option>
                        <option value="CITY">CITY</option>
                        <option value="CITY DISTRICT">CITY DISTRICT</option>
                        <option value="D/KUDU">D/KUDU</option>
                        <option value="DAMBATTA">DAMBATTA</option>
                        <option value="DAN DINSHE KOFAR DAWANAU">DAN DINSHE KOFAR DAWANAU</option>
                        <option value="DANBATTA">DANBATTA</option>
                        <option value="DAWAKIL KUDU">DAWAKIL KUDU</option>
                        <option value="DAWAKIN KUDU DISTRICT">DAWAKIN KUDU DISTRICT</option>
                        <option value="DAWAKIN TOFA">DAWAKIN TOFA</option>
                        <option value="DAWAKIN-KUDU">DAWAKIN-KUDU</option>
                        <option value="DAWAKIN-TOFA">DAWAKIN-TOFA</option>
                        <option value="DAWANAU TOFA">DAWANAU TOFA</option>
                        <option value="DOGUWA">DOGUWA</option>
                        <option value="DORAYI KARAMA">DORAYI KARAMA</option>
                        <option value="GABASAWA">GABASAWA</option>
                        <option value="GARKO">GARKO</option>
                        <option value="GARUN MALAM">GARUN MALAM</option>
                        <option value="GARUN MALLAM">GARUN MALLAM</option>
                        <option value="GAYA">GAYA</option>
                        <option value="GEZAWA">GEZAWA</option>
                        <option value="GWALA">GWALA</option>
                        <option value="GWALE DISTRICT">GWALE DISTRICT</option>
                        <option value="GWAMMAJA">GWAMMAJA</option>
                        <option value="GWARZO">GWARZO</option>
                        <option value="HAUSAWA">HAUSAWA</option>
                        <option value="INUBAWA">INUBAWA</option>
                        <option value="KABO">KABO</option>
                        <option value="KANO CITY">KANO CITY</option>
                        <option value="KANO MUNICIPAL">KANO MUNICIPAL</option>
                        <option value="KANO MUNICIPAL CITY">KANO MUNICIPAL CITY</option>
                        <option value="KANO STATE">KANO STATE</option>
                        <option value="KANO-CITY">KANO-CITY</option>
                        <option value="KARAYE">KARAYE</option>
                        <option value="KIBIYA">KIBIYA</option>
                        <option value="KIMBOTSO">KIMBOTSO</option>
                        <option value="KIRU">KIRU</option>
                        <option value="KOFAR DAWANAU">KOFAR DAWANAU</option>
                        <option value="KUMBOSTO">KUMBOSTO</option>
                        <option value="KUMBOTSO VILLAGE">KUMBOTSO VILLAGE</option>
                        <option value="KUMBOTSOI">KUMBOTSOI</option>
                        <option value="KUNCHI">KUNCHI</option>
                        <option value="KURA">KURA</option>
                        <option value="MADOBI">MADOBI</option>
                        <option value="MAKODA">MAKODA</option>
                        <option value="MINJIBIR">MINJIBIR</option>
                        <option value="MUNICIPAL">MUNICIPAL</option>
                        <option value="MUNICIPAL LOCAL GOVERNMENT">MUNICIPAL LOCAL GOVERNMENT</option>
                        <option value="MUNNICIPAL">MUNNICIPAL</option>
                        <option value="NASARAWA">NASARAWA</option>
                        <option value="NASSARAWA">NASSARAWA</option>
                        <option value="RANO">RANO</option>
                        <option value="RIMIN GADO">RIMIN GADO</option>
                        <option value="RIMIN ZAKARA">RIMIN ZAKARA</option>
                        <option value="ROGO">ROGO</option>
                        <option value="SUMAILA">SUMAILA</option>
                        <option value="TAKAI">TAKAI</option>
                        <option value="TARAUNI">TARAUNI</option>
                        <option value="TARAUNI DISTRICT">TARAUNI DISTRICT</option>
                        <option value="TOFA">TOFA</option>
                        <option value="TSANTAWA">TSANTAWA</option>
                        <option value="TSANYAWA">TSANYAWA</option>
                        <option value="TUDUN WADA">TUDUN WADA</option>
                        <option value="UNGOGGO">UNGOGGO</option>
                        <option value="UNGOGO">UNGOGO</option>
                        <option value="WAJE">WAJE</option>
                        <option value="WARAWA">WARAWA</option>
                        <option value="WUDIL">WUDIL</option>
                        <option value="ZAWACHIKI">ZAWACHIKI</option>
                        <option value="other">Other</option>
                    </select>
                    <input 
                        type="text" 
                        id="edit-custom-district" 
                        class="form-input text-sm property-input mt-2 hidden w-full px-3 py-2 border border-gray-300 rounded-md" 
                        placeholder="Please specify other district name"
                        value="${metadataEntry.districtOther || ''}"
                    />
                </div>
                  <!-- Added Serial No and Registry fields -->
               

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Registry <span class="text-red-500">*</span></label>
                    <select id="edit-registry" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Registry</option>
                        <option value="Registry 1 - Lands">Registry 1 - Lands</option>
                        <option value="Registry 2 - Lands">Registry 2 - Lands</option>
                        <option value="Registry 3 - Lands">Registry 3 - Lands</option>
                        <option value="Registry 1 - Deeds">Registry 1 - Deeds</option>
                        <option value="Registry 2 - Deeds">Registry 2 - Deeds</option>
                        <option value="Registry 1 - Cadastral">Registry 1 - Cadastral</option>
                        <option value="Registry 2 - Cadastral">Registry 2 - Cadastral</option>
                        <option value="KANGIS Registry">KANGIS Registry</option>
                        <option value="SLTR Registry">SLTR Registry</option>
                        <option value="ST Registry">ST Registry</option>
                        <option value="DCIV Registry">DCIV Registry</option>
                        <option value="New Archive">New Archive</option>
                        <option value="Other">Other</option>
                    </select>
                    <div id="edit-custom-registry-container" class="hidden mt-2">
                        <input type="text" id="edit-custom-registry-input" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Enter registry name" value="${metadataEntry.registryOther || ''}">
                    </div>
                </div>
                 <div class="space-y-2">
                    <label for="serial-no" class="block text-sm font-medium text-gray-700">Serial No <span class="text-red-500">*</span></label>
                    <input type="text" id="edit-serialNo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="${metadataEntry.serialNo || ''}" placeholder="Enter serial number">
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Batch No <span class="text-red-500">*</span></label>
                    <select id="edit-batchNo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select batch number</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Shelf/Rack Location <span class="text-red-500">*</span></label>
                    <input type="text" id="edit-shelfLocation" value="${metadataEntry.shelfLocation || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50"
                           readonly placeholder="Select batch first">
                </div>
             
              
            `;

            // Populate extracted text preview
            extractedTextPreview.textContent = metadataEntry.extractedText;

            // Load available batches and set up event handlers
            loadAvailableBatches();
            setupBatchChangeHandler();
            // Setup registry change handler inside modal
            const registrySelect = document.getElementById('edit-registry');
            const customRegistryContainer = document.getElementById('edit-custom-registry-container');
            const customRegistryInput = document.getElementById('edit-custom-registry-input');
            if (registrySelect) {
                if (registrySelect.value === 'Other') {
                    customRegistryContainer.classList.remove('hidden');
                } else {
                    customRegistryContainer.classList.add('hidden');
                }

                registrySelect.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        customRegistryContainer.classList.remove('hidden');
                        if (customRegistryInput) customRegistryInput.focus();
                    } else {
                        customRegistryContainer.classList.add('hidden');
                    }
                });
            }

            // Setup district change handler to show "Other" input
            const districtSelect = document.getElementById('edit-district');
            const customDistrictInput = document.getElementById('edit-custom-district');
            if (districtSelect) {
                // initialize visibility
                if (districtSelect.value === 'other' || (metadataEntry.districtOther && metadataEntry.districtOther.length > 0)) {
                    if (customDistrictInput) customDistrictInput.classList.remove('hidden');
                } else {
                    if (customDistrictInput) customDistrictInput.classList.add('hidden');
                }

                districtSelect.addEventListener('change', function() {
                    if (this.value === 'other') {
                        if (customDistrictInput) {
                            customDistrictInput.classList.remove('hidden');
                            customDistrictInput.focus();
                        }
                    } else {
                        if (customDistrictInput) customDistrictInput.classList.add('hidden');
                    }
                });
            }

            // Reset and hide all preview elements first
            pdfPreviewWrapper.classList.add('hidden');
            imagePreviewWrapper.classList.add('hidden');
            unsupportedPreviewMessage.classList.add('hidden');

            // Render document preview based on file type
            if (fileEntry.file.type === 'application/pdf') {
                pdfPreviewWrapper.classList.remove('hidden');
                pdfPreviewCanvas.classList.add('hidden'); // Hide canvas until rendered
                pdfLoadingPlaceholder.classList.remove('hidden'); // Show loading message
                pdfNavigationControls.classList.add('hidden'); // Hide controls until PDF is loaded

                currentPDFDocument = null; // Reset document for new file
                currentPageNumber = 1; // Reset to first page
                loadAndRenderPDFPreview(fileEntry.file);
            } else if (fileEntry.file.type.startsWith('image/')) {
                imagePreviewWrapper.classList.remove('hidden');
                imagePreviewImg.classList.add('hidden'); // Hide img until loaded
                imageLoadingPlaceholder.classList.remove('hidden'); // Show loading message
                renderImagePreview(fileEntry.file);
            } else {
                unsupportedPreviewMessage.classList.remove('hidden');
            }

            const modal = document.getElementById('metadata-modal');
            if (modal) modal.classList.remove('hidden');

            // Focus the modal for keyboard navigation
            setTimeout(() => {
                modal.focus();
            }, 100);
        }

        // Load available batches for the metadata form
        function loadAvailableBatches() {
            const batchSelect = document.getElementById('edit-batchNo');
            if (!batchSelect) return;

            // Show loading state
            batchSelect.innerHTML = '<option value="">Loading batches...</option>';

            fetch('/fileindexing/get-available-batches')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.batches) {
                        // Clear existing options
                        batchSelect.innerHTML = '<option value="">Select batch number</option>';
                        
                        // Add new options
                        data.batches.forEach(function(batch) {
                            const option = document.createElement('option');
                            option.value = batch.id;
                            option.textContent = `Batch ${batch.id}`;
                            batchSelect.appendChild(option);
                        });
                        
                        console.log('Batch options loaded:', data.batches.length);
                    } else {
                        batchSelect.innerHTML = '<option value="">No batches available</option>';
                        console.warn('No batches available');
                    }
                })
                .catch(error => {
                    console.error('Error loading batches:', error);
                    batchSelect.innerHTML = '<option value="">Error loading batches</option>';
                });
        }

        // Set up batch change handler for the metadata form
        function setupBatchChangeHandler() {
            const batchSelect = document.getElementById('edit-batchNo');
            const shelfInput = document.getElementById('edit-shelfLocation');
            
            if (!batchSelect || !shelfInput) return;

            batchSelect.addEventListener('change', function() {
                const selectedBatch = this.value;
                console.log('Batch selected:', selectedBatch);
                
                if (selectedBatch) {
                    // Show loading state
                    shelfInput.value = 'Loading...';
                    shelfInput.style.backgroundColor = '#f9fafb';
                    shelfInput.classList.add('loading');
                    
                    // Fetch shelf location from API
                    fetch(`/fileindexing/get-shelf-for-batch/${selectedBatch}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.label) {
                                shelfInput.value = data.label;
                                shelfInput.style.backgroundColor = '#f0f9ff';
                                shelfInput.style.borderColor = '#10b981';
                            } else {
                                shelfInput.value = '';
                                shelfInput.style.backgroundColor = '#fef2f2';
                                shelfInput.style.borderColor = '#ef4444';
                                console.warn('Batch not available:', data.message);
                                
                                // Reset selection if batch is not available
                                batchSelect.value = '';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching shelf:', error);
                            shelfInput.value = '';
                            shelfInput.style.backgroundColor = '#fef2f2';
                        })
                        .finally(() => {
                            shelfInput.classList.remove('loading');
                            setTimeout(() => {
                                shelfInput.style.borderColor = '';
                            }, 2000);
                        });
                } else {
                    // Clear shelf location if no batch selected
                    shelfInput.value = '';
                    shelfInput.style.backgroundColor = '#f9fafb';
                    shelfInput.style.borderColor = '';
                    shelfInput.classList.remove('loading');
                }
            });
        }

        async function loadAndRenderPDFPreview(file) {
            const pdfLoadingPlaceholder = document.getElementById('pdf-loading-placeholder');
            const pdfPreviewCanvas = document.getElementById('pdf-preview-canvas');
            const pdfNavigationControls = document.getElementById('pdf-navigation-controls');

            pdfLoadingPlaceholder.classList.remove('hidden'); // Show loading message
            pdfPreviewCanvas.classList.add('hidden');     // Hide canvas
            pdfNavigationControls.classList.add('hidden'); // Hide controls

            try {
                if (typeof pdfjsLib === 'undefined') {
                    throw new Error("PDF.js library is not loaded.");
                }
                const arrayBuffer = await file.arrayBuffer();
                currentPDFDocument = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
                currentPageNumber = 1; // Ensure it starts on page 1
                renderSinglePDFPage(); // Render the first page
            } catch (error) {
                console.error('Error loading PDF document:', error);
                pdfLoadingPlaceholder.textContent = 'Error loading PDF preview.';
                pdfLoadingPlaceholder.classList.remove('hidden');
            }
        }

        async function renderSinglePDFPage() {
            const pdfPreviewCanvas = document.getElementById('pdf-preview-canvas');
            const pdfNavigationControls = document.getElementById('pdf-navigation-controls');
            const pageInfoSpan = document.getElementById('page-info');
            const prevPageBtn = document.getElementById('prev-page-btn');
            const nextPageBtn = document.getElementById('next-page-btn');
            const pdfLoadingPlaceholder = document.getElementById('pdf-loading-placeholder');

            if (!currentPDFDocument || !pdfPreviewCanvas || !pageInfoSpan || !prevPageBtn || !nextPageBtn || !pdfNavigationControls || !pdfLoadingPlaceholder) {
                console.error("PDF preview elements not found or PDF not loaded.");
                return;
            }

            // Hide placeholder and show canvas
            pdfLoadingPlaceholder.classList.add('hidden');
            pdfPreviewCanvas.classList.remove('hidden');
            pdfNavigationControls.classList.remove('hidden');

            try {
                const page = await currentPDFDocument.getPage(currentPageNumber);
                const viewport = page.getViewport({ scale: 1.5 }); // Slightly larger scale for better preview
                const context = pdfPreviewCanvas.getContext('2d');

                // Set canvas dimensions to fit container, maintaining aspect ratio
                const containerWidth = pdfPreviewCanvas.parentElement.offsetWidth - (2 * 16); // Account for padding
                const scale = containerWidth / viewport.width;
                const scaledViewport = page.getViewport({ scale: scale });

                pdfPreviewCanvas.height = scaledViewport.height;
                pdfPreviewCanvas.width = scaledViewport.width;

                await page.render({
                    canvasContext: context,
                    viewport: scaledViewport
                }).promise;

                pageInfoSpan.textContent = `Page ${currentPageNumber} of ${currentPDFDocument.numPages}`;
                prevPageBtn.disabled = currentPageNumber <= 1;
                nextPageBtn.disabled = currentPageNumber >= currentPDFDocument.numPages;

            } catch (error) {
                console.error('Error rendering PDF page:', error);
                pdfPreviewCanvas.classList.add('hidden');
                pdfLoadingPlaceholder.textContent = 'Error rendering page. Please try again.';
                pdfLoadingPlaceholder.classList.remove('hidden');
                pdfNavigationControls.classList.add('hidden'); // Hide controls on error
            }
        }

        function goToNextPage() {
            if (currentPDFDocument && currentPageNumber < currentPDFDocument.numPages) {
                currentPageNumber++;
                renderSinglePDFPage();
            }
        }

        function goToPreviousPage() {
            if (currentPDFDocument && currentPageNumber > 1) {
                currentPageNumber--;
                renderSinglePDFPage();
            }
        }

        function renderImagePreview(file) {
            const imagePreviewImg = document.getElementById('image-preview-img');
            const imageLoadingPlaceholder = document.getElementById('image-loading-placeholder');

            if (!imagePreviewImg || !imageLoadingPlaceholder) {
                console.error("Image preview elements not found.");
                return;
            }

            imagePreviewImg.classList.add('hidden'); // Hide img until loaded
            imageLoadingPlaceholder.classList.remove('hidden'); // Show loading message

            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreviewImg.src = e.target.result;
                imagePreviewImg.onload = () => {
                    imageLoadingPlaceholder.classList.add('hidden');
                    imagePreviewImg.classList.remove('hidden');
                };
                imagePreviewImg.onerror = () => {
                    imageLoadingPlaceholder.textContent = 'Error loading image preview.';
                    imageLoadingPlaceholder.classList.remove('hidden');
                    imagePreviewImg.classList.add('hidden');
                };
            };
            reader.readAsDataURL(file);
        }

        // These functions now simply call the unified openMetadataEditModal
        function showDocumentPreviewFromTable(fileId) {
            openMetadataEditModal(fileId);
        }

        function editMetadataFromTable(fileId) {
            openMetadataEditModal(fileId);
        }

        function editMetadata(fileId) { // For calls from analysis results section
            openMetadataEditModal(fileId);
        }

        function closeMetadataModal() {
            const modal = document.getElementById('metadata-modal');
            if (modal) modal.classList.add('hidden');
            // Reset state when closing modal
            currentPDFDocument = null;
            currentPageNumber = 1;
            currentEditingFile = null;
        }

        // Open the Global File Number Modal
        function openFileNumberModal() {
            // Check if the GlobalFileNoModal is available
            if (typeof GlobalFileNoModal === 'undefined') {
                console.error('GlobalFileNoModal not found. Make sure the script is loaded.');
                alert('File Number Modal is not available. Please refresh the page.');
                return;
            }

            // Get current file number value from the hidden input
            const currentValue = document.getElementById('edit-fileNumber')?.value || '';

            // Open the modal with configuration
            GlobalFileNoModal.open({
                targetFields: ['#edit-fileNumber'], // Target the hidden input
                initialValue: currentValue,
                callback: function(result) {
                    console.log('File number selected:', result);
                    
                    // Update both the display field and the hidden input
                    const fileNumberInput = document.getElementById('edit-fileNumber');
                    const fileNumberDisplay = document.getElementById('edit-fileNumber-display');
                    
                    if (fileNumberInput) {
                        fileNumberInput.value = result.fileNumber;
                    }
                    
                    if (fileNumberDisplay) {
                        fileNumberDisplay.value = result.fileNumber;
                        fileNumberDisplay.style.color = '#374151'; // Darker text when populated
                    }

                    // Show success notification
                    showNotification(`File number "${result.fileNumber}" selected from ${result.system} system`, 'success');
                }
            });
        }

        // Utility function to show notifications
        function showNotification(message, type = 'info') {
            // Create a simple notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-[200] px-4 py-2 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
            
            // Set colors based on type
            switch (type) {
                case 'success':
                    notification.classList.add('bg-green-500', 'text-white');
                    break;
                case 'error':
                    notification.classList.add('bg-red-500', 'text-white');
                    break;
                case 'warning':
                    notification.classList.add('bg-yellow-500', 'text-white');
                    break;
                default:
                    notification.classList.add('bg-blue-500', 'text-white');
            }

            notification.textContent = message;
            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Auto remove after 3 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        function applyMetadataChanges() {
            if (!currentEditingFile) return;

            const trackingId = document.getElementById('edit-trackingId')?.value || '';
            const fileNumber = document.getElementById('edit-fileNumber')?.value || '';
            const plotNumber = document.getElementById('edit-plotNumber')?.value || '';
            const tpNumber = document.getElementById('edit-tpNumber')?.value || '';
            const owner = document.getElementById('edit-owner')?.value || '';
            const landUse = document.getElementById('edit-landUse')?.value || '';
            let district = document.getElementById('edit-district')?.value || '';
            const batchNo = document.getElementById('edit-batchNo')?.value || '';
            const shelfLocation = document.getElementById('edit-shelfLocation')?.value || '';
            const serialNo = document.getElementById('edit-serialNo')?.value || '';
            const lpknNo = document.getElementById('edit-lpknNo')?.value || '';
            let registry = document.getElementById('edit-registry')?.value || '';
            const customRegistry = document.getElementById('edit-custom-registry-input')?.value || '';
            if (registry === 'Other' && customRegistry) {
                registry = customRegistry;
            }
            const customDistrict = document.getElementById('edit-custom-district')?.value || '';
            if ((district === 'other' || district === '') && customDistrict) {
                // prefer explicit custom district when 'Other' selected or no selection
                district = customDistrict;
            }

            // Ensure metadata object exists then update
            extractedMetadata[currentEditingFile] = extractedMetadata[currentEditingFile] || {};
            extractedMetadata[currentEditingFile].trackingId = trackingId;
            extractedMetadata[currentEditingFile].extractedFileNumber = fileNumber;
            extractedMetadata[currentEditingFile].plotNumber = plotNumber;
            extractedMetadata[currentEditingFile].tpNumber = tpNumber;
            extractedMetadata[currentEditingFile].detectedOwner = owner;
            extractedMetadata[currentEditingFile].landUseType = landUse;
            extractedMetadata[currentEditingFile].district = district;
            // Save the explicit other value separately for clarity
            if (customDistrict) extractedMetadata[currentEditingFile].districtOther = customDistrict;
            extractedMetadata[currentEditingFile].batchNo = batchNo;
            extractedMetadata[currentEditingFile].shelfLocation = shelfLocation;
            extractedMetadata[currentEditingFile].serialNo = serialNo;
            extractedMetadata[currentEditingFile].lpknNo = lpknNo;
            extractedMetadata[currentEditingFile].registry = registry;

            // Always update the status in uploadedFiles if the file exists there
            const fileIndex = uploadedFiles.findIndex(f => f.id === currentEditingFile);
            if (fileIndex !== -1) {
                uploadedFiles[fileIndex].status = 'Metadata updated';
                // Propagate all edited metadata back to the uploadedFiles entry so the main UI shows the changes
                uploadedFiles[fileIndex].trackingId = extractedMetadata[currentEditingFile].trackingId || '';
                uploadedFiles[fileIndex].file_number = extractedMetadata[currentEditingFile].extractedFileNumber || uploadedFiles[fileIndex].file_number || '';
                uploadedFiles[fileIndex].plotNumber = extractedMetadata[currentEditingFile].plotNumber || '';
                uploadedFiles[fileIndex].tpNumber = extractedMetadata[currentEditingFile].tpNumber || '';
                uploadedFiles[fileIndex].detectedOwner = extractedMetadata[currentEditingFile].detectedOwner || '';
                uploadedFiles[fileIndex].landUseType = extractedMetadata[currentEditingFile].landUseType || '';
                uploadedFiles[fileIndex].district = extractedMetadata[currentEditingFile].district || '';
                uploadedFiles[fileIndex].batchNo = extractedMetadata[currentEditingFile].batchNo || '';
                uploadedFiles[fileIndex].shelfLocation = extractedMetadata[currentEditingFile].shelfLocation || '';
                uploadedFiles[fileIndex].serialNo = extractedMetadata[currentEditingFile].serialNo || '';
                uploadedFiles[fileIndex].lpknNo = extractedMetadata[currentEditingFile].lpknNo || '';
                uploadedFiles[fileIndex].registry = extractedMetadata[currentEditingFile].registry || '';
                uploadedFiles[fileIndex].documentType = extractedMetadata[currentEditingFile].documentType || uploadedFiles[fileIndex].documentType || '';
                // If a custom district label was provided, ensure it's reflected
                if (extractedMetadata[currentEditingFile].districtOther) {
                    uploadedFiles[fileIndex].district = extractedMetadata[currentEditingFile].districtOther;
                }

                updateUploadedFilesDisplay(); // Re-render uploaded files table
            }

            // Re-render analysis results to reflect the changes
            showAnalysisResults();

            closeMetadataModal();

            // Show success message
            showNotification('Metadata changes applied successfully!', 'success');
        }

        function createIndexingEntries() {
            if (uploadedFiles.length === 0) {
                showNotification('No files uploaded to create indexing entries for.', 'error');
                return;
            }

            // Validate required metadata fields
            let missingFields = [];
            let firstFileId = null;
            
            for (const file of uploadedFiles) {
                if (!firstFileId) firstFileId = file.id;
                
                const metadata = extractedMetadata[file.id] || {};
                
                if (!metadata.extractedFileNumber) missingFields.push('File Number');
                if (!metadata.detectedOwner) missingFields.push('File Name');
                if (!metadata.registry) missingFields.push('Registry');
                if (!metadata.serialNo) missingFields.push('Serial No');
                if (!metadata.batchNo) missingFields.push('Batch No');
                if (!metadata.shelfLocation) missingFields.push('Shelf/Rack Location');
                
                // Only check the first file as we only need one error notification
                if (missingFields.length > 0) break;
            }
            
            if (missingFields.length > 0) {
                // Open metadata editor for the first file if validation fails
                showNotification(`Please fill in the required fields: ${missingFields.join(', ')}`, 'error');
                openMetadataEditModal(firstFileId);
                return;
            }

            // Show loading state
            const createButton = document.querySelector('button[onclick="createIndexingEntries()"]');
            if (createButton) {
                createButton.disabled = true;
                createButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
            }

            // Prepare data for backend
            const formData = new FormData();

            // Add uploaded files data
            uploadedFiles.forEach((file, index) => {
                // Add the actual file if it exists
                if (file.file) {
                    formData.append(`documents[${index}]`, file.file);
                }

                // Ensure we always send a metadata object per file to keep indexes aligned
                const metadata = extractedMetadata[file.id] || {};
                formData.append(`extracted_metadata[${index}][trackingId]`, metadata.trackingId || '');
                formData.append(`extracted_metadata[${index}][extractedFileNumber]`, metadata.extractedFileNumber || '');
                formData.append(`extracted_metadata[${index}][plotNumber]`, metadata.plotNumber || '');
                formData.append(`extracted_metadata[${index}][tpNumber]`, metadata.tpNumber || '');
                formData.append(`extracted_metadata[${index}][detectedOwner]`, metadata.detectedOwner || '');
                formData.append(`extracted_metadata[${index}][landUseType]`, metadata.landUseType || '');
                formData.append(`extracted_metadata[${index}][district]`, metadata.district || '');
                formData.append(`extracted_metadata[${index}][districtOther]`, metadata.districtOther || '');
                formData.append(`extracted_metadata[${index}][batchNo]`, metadata.batchNo || '');
                formData.append(`extracted_metadata[${index}][shelfLocation]`, metadata.shelfLocation || '');
                formData.append(`extracted_metadata[${index}][documentType]`, metadata.documentType || '');
                formData.append(`extracted_metadata[${index}][extractedText]`, metadata.extractedText || '');
                // Optional new fields
                formData.append(`extracted_metadata[${index}][serialNo]`, metadata.serialNo || '');
                formData.append(`extracted_metadata[${index}][lpknNo]`, metadata.lpknNo || '');
                formData.append(`extracted_metadata[${index}][registry]`, metadata.registry || '');
            });

            // Send to backend
            fetch('/scanning/upload-unindexed', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const fileNumber = data.file_number || 'Unknown';
                    const fileCount = data.uploaded_documents?.length || 0;
                    showNotification(`Successfully uploaded ${fileCount} files under file number: ${fileNumber}`, 'success');

                    // Update uploaded files with the assigned file number
                    if (data.uploaded_documents) {
                        uploadedFiles = data.uploaded_documents.map(doc => ({
                            id: `UPLOAD-${Date.now()}-${Math.random()}`,
                            name: doc.filename,
                            size: formatFileSize(doc.size),
                            type: doc.type,
                            status: 'Uploaded',
                            date: new Date().toLocaleDateString(),
                            file_number: doc.file_number,
                            file_indexing_id: doc.file_indexing_id,
                            path: doc.path || '',
                            url: doc.path ? ('/storage/' + doc.path) : ''
                        }));
                    } else {
                        uploadedFiles = [];
                    }

                    extractedMetadata = {};
                    updateUploadedFilesDisplay();
                    showAnalysisResults();

                    // Reset file input
                    const fileInput = document.getElementById('fileInput');
                    if (fileInput) fileInput.value = '';

                    // Update statistics
                    updateStats();

                    // Redirect to page typing if we have created indexings
                    if (data.created_indexings && data.created_indexings.length > 0) {
                        setTimeout(() => {
                            window.location.href = data.redirect || '/pagetyping';
                        }, 2000);
                    }
                } else {
                    // Handle validation errors
                    if (data.errors) {
                        // Check if we have a formatted error summary from the server
                        if (data.error_summary) {
                            showNotification(data.error_summary, 'error');
                        } else {
                            // Otherwise, build our own error message
                            const errorMessages = [];
                            for (const field in data.errors) {
                                if (data.errors.hasOwnProperty(field)) {
                                    errorMessages.push(...data.errors[field]);
                                }
                            }
                            const errorMessage = errorMessages.length > 0 
                                ? 'Validation errors: ' + errorMessages.join(', ')
                                : 'Please check all required fields are filled in';
                            
                            showNotification(errorMessage, 'error');
                        }
                        
                        // If we have metadata fields with errors, open the metadata editor for the first file
                        if (uploadedFiles.length > 0) {
                            openMetadataEditModal(uploadedFiles[0].id);
                        }
                    } else {
                        showNotification('Error creating indexing entries: ' + (data.message || 'Unknown error'), 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Network error occurred while creating indexing entries.', 'error');
            })
            .finally(() => {
                // Reset button state
                if (createButton) {
                    createButton.disabled = false;
                    createButton.innerHTML = '<i class="fas fa-plus-circle mr-2"></i>Create File Number & Upload Files';
                }
            });
        }

        function sendToIndexing() {
            if (uploadedFiles.length === 0) {
                alert('No files to send to indexing');
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${filesToDisplay.map(file => `
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            ${file.file_number || 'Pending'}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${file.type.includes('pdf') ? 'bg-red-100 text-red-800' : file.type.includes('image') ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'}">
                                            ${file.type.includes('pdf') ? 'PDF' : file.type.includes('image') ? 'Image' : 'Other'}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${file.status === 'Ready for analysis' ? 'bg-yellow-100 text-yellow-800' : file.status === 'Metadata updated' || file.status === 'Analysis Complete' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                            ${file.status}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ${file.uploaded_by || 'Unknown'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${file.date}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="showDocumentPreviewFromTable('${file.id}')">
                                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                Preview
                                            </button>
                                            <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="openFullScreenViewer('${file.id}')">
                                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 3H5a2 2 0 00-2 2v3m0 8v3a2 2 0 002 2h3m8 0h3a2 2 0 002-2v-3m0-8V5a2 2 0 00-2-2h-3"></path></svg>
                                                Fullscreen
                                            </button>
                                            <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="editMetadataFromTable('${file.id}')">
                                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </button>
                                            <button class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" onclick="indexFile('${file.id}')">
                                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                                </svg>
                                                Index
                                            </button>
                                            <button class="inline-flex items-center px-2 py-1 text-xs text-red-600 hover:text-red-800 transition-colors" onclick="deleteFile('${file.id}')">
                                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Delete
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
            if (confirm('Are you sure you want to delete this file?')) {
                uploadedFiles = uploadedFiles.filter(file => file.id !== fileId);
                filteredFiles = filteredFiles.filter(file => file.id !== fileId);
                // Also remove from extractedMetadata
                delete extractedMetadata[fileId];
                updateUploadedFilesDisplay();
                updateStats();
            }
        }

        // Fullscreen viewer state
        let fsCurrentFile = null;
        let fsPDFDocument = null;
        let fsCurrentPage = 1;

        function openFullScreenViewer(fileId) {
            const file = uploadedFiles.find(f => f.id === fileId);
            if (!file) {
                showNotification('File not found', 'error');
                return;
            }

            fsCurrentFile = file;
            const viewer = document.getElementById('fullscreen-viewer');
            const canvas = document.getElementById('fullscreen-pdf-canvas');
            const img = document.getElementById('fullscreen-image');
            const unsupported = document.getElementById('fullscreen-unsupported');
            const pageInfo = document.getElementById('fullscreen-page-info');

            // Reset
            if (canvas) { canvas.style.display = 'none'; }
            if (img) { img.classList.add('hidden'); img.src = ''; }
            if (unsupported) { unsupported.classList.add('hidden'); }
            if (pageInfo) pageInfo.textContent = 'Page 0 / 0';

            if (viewer) viewer.classList.remove('hidden');

            // If original File object exists (from pre-upload), render from file
            if (file.file) {
                if (file.file.type === 'application/pdf') {
                    renderFullScreenPDFFromFile(file.file);
                } else if (file.file.type && file.file.type.startsWith('image/')) {
                    renderFullScreenImageFromFile(file.file);
                } else {
                    unsupported.classList.remove('hidden');
                }
            } else if (file.url) {
                // Try to fetch by URL (server-stored)
                const url = file.url;
                if (file.type && file.type.includes('pdf')) {
                    renderFullScreenPDFFromURL(url);
                } else if (file.type && file.type.startsWith('image')) {
                    renderFullScreenImageFromURL(url);
                } else {
                    // Fallback: show image if URL accessible
                    renderFullScreenImageFromURL(url);
                }
            } else {
                unsupported.classList.remove('hidden');
            }
        }

        function closeFullScreenViewer() {
            const viewer = document.getElementById('fullscreen-viewer');
            if (viewer) {
                viewer.classList.add('hidden');
                viewer.style.zIndex = '60'; // Reset to original z-index
            }
            
            // If we came from the metadata modal, show it again
            if (currentEditingFile) {
                const metadataModal = document.getElementById('metadata-modal');
                if (metadataModal) {
                    metadataModal.classList.remove('hidden');
                }
            }
            
            fsCurrentFile = null;
            fsPDFDocument = null;
            fsCurrentPage = 1;
        }
        
        function openFullScreenFromPreview() {
            if (currentEditingFile) {
                // Temporarily hide the metadata modal when opening fullscreen
                const metadataModal = document.getElementById('metadata-modal');
                if (metadataModal) {
                    metadataModal.classList.add('hidden');
                }
                
                // Open fullscreen viewer
                openFullScreenViewer(currentEditingFile);
                
                // Ensure the fullscreen viewer is visible and has higher z-index
                const fullscreenViewer = document.getElementById('fullscreen-viewer');
                if (fullscreenViewer) {
                    fullscreenViewer.style.zIndex = '999';
                }
            } else {
                showNotification('No file is currently being previewed', 'error');
            }
        }

        async function renderFullScreenPDFFromFile(file) {
            try {
                const arrayBuffer = await file.arrayBuffer();
                await renderFullScreenPDF(arrayBuffer);
            } catch (e) {
                console.error('Error rendering PDF from file', e);
                document.getElementById('fullscreen-unsupported').classList.remove('hidden');
            }
        }

        async function renderFullScreenPDFFromURL(url) {
            try {
                const resp = await fetch(url);
                const arrayBuffer = await resp.arrayBuffer();
                await renderFullScreenPDF(arrayBuffer);
            } catch (e) {
                console.error('Error fetching/rendering PDF from URL', e);
                document.getElementById('fullscreen-unsupported').classList.remove('hidden');
            }
        }

        async function renderFullScreenPDF(arrayBuffer) {
            const canvas = document.getElementById('fullscreen-pdf-canvas');
            const img = document.getElementById('fullscreen-image');
            const pageInfo = document.getElementById('fullscreen-page-info');

            if (!canvas) return;
            if (img) img.classList.add('hidden');

            try {
                fsPDFDocument = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
                fsCurrentPage = 1;
                await renderFullScreenPDFPage();
                document.getElementById('fullscreen-pdf-canvas').style.display = '';
            } catch (e) {
                console.error('Error rendering PDF', e);
                document.getElementById('fullscreen-unsupported').classList.remove('hidden');
            }
        }

        async function renderFullScreenPDFPage() {
            if (!fsPDFDocument) return;
            const canvas = document.getElementById('fullscreen-pdf-canvas');
            const context = canvas.getContext('2d');
            const page = await fsPDFDocument.getPage(fsCurrentPage);
            const viewport = page.getViewport({ scale: 1.5 });
            // Scale to fit container width
            const container = canvas.parentElement;
            const maxWidth = container.clientWidth - 40;
            const scale = Math.min(1.8, maxWidth / viewport.width);
            const scaled = page.getViewport({ scale });
            canvas.width = scaled.width;
            canvas.height = scaled.height;
            await page.render({ canvasContext: context, viewport: scaled }).promise;
            const pageInfo = document.getElementById('fullscreen-page-info');
            pageInfo.textContent = `Page ${fsCurrentPage} / ${fsPDFDocument.numPages}`;
        }

        function fullScreenNextPage(event) {
            event.stopPropagation();
            if (!fsPDFDocument) return;
            if (fsCurrentPage < fsPDFDocument.numPages) {
                fsCurrentPage++;
                renderFullScreenPDFPage();
            }
        }

        function fullScreenPrevPage(event) {
            event.stopPropagation();
            if (!fsPDFDocument) return;
            if (fsCurrentPage > 1) {
                fsCurrentPage--;
                renderFullScreenPDFPage();
            }
        }

        function renderFullScreenImageFromFile(file) {
            const img = document.getElementById('fullscreen-image');
            const canvas = document.getElementById('fullscreen-pdf-canvas');
            if (canvas) canvas.style.display = 'none';
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }

        function renderFullScreenImageFromURL(url) {
            const img = document.getElementById('fullscreen-image');
            const canvas = document.getElementById('fullscreen-pdf-canvas');
            if (canvas) canvas.style.display = 'none';
            img.src = url;
            img.classList.remove('hidden');
        }

        function downloadFullScreenFile(event) {
            event.stopPropagation();
            if (!fsCurrentFile) return;
            // Prefer server URL when available
            const url = fsCurrentFile.url;
            if (url) {
                const a = document.createElement('a');
                a.href = url;
                a.download = fsCurrentFile.name || 'download';
                document.body.appendChild(a);
                a.click();
                a.remove();
                return;
            }
            // Otherwise, if original File object exists, use blob
            if (fsCurrentFile.file) {
                const blobUrl = URL.createObjectURL(fsCurrentFile.file);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = fsCurrentFile.name || 'download';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(blobUrl);
            }
        }

        function indexFile(fileId) {
            alert(`Sending file to indexing...`);
            window.location.href = '/file-digital-registry/indexing-assistant';
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-[60] p-4 rounded-md shadow-lg transition-all duration-300 transform translate-x-full`;

            // Set colors based on type
            const colors = {
                success: 'bg-green-500 text-white',
                error: 'bg-red-500 text-white',
                warning: 'bg-yellow-500 text-white',
                info: 'bg-blue-500 text-white'
            };

            notification.classList.add(...colors[type].split(' '));

            // Add icon based on type
            const icons = {
                success: '<i class="fas fa-check-circle mr-2"></i>',
                error: '<i class="fas fa-exclamation-circle mr-2"></i>',
                warning: '<i class="fas fa-exclamation-triangle mr-2"></i>',
                info: '<i class="fas fa-info-circle mr-2"></i>'
            };

            notification.innerHTML = `${icons[type]}${message}`;

            // Add to page
            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }

        // Initialize Global File Number Modal
        function initializeGlobalFileNoModal() {
            if (typeof GlobalFileNoModal !== 'undefined') {
                try {
                    GlobalFileNoModal.init();
                    console.log('GlobalFileNoModal initialized successfully');
                } catch (error) {
                    console.error('Error initializing GlobalFileNoModal:', error);
                }
            } else {
                console.error('GlobalFileNoModal not available. Make sure the script is loaded.');
            }
        }

        // Initialize when page loads
        window.addEventListener('load', function() {
            // Initialize the modal after a short delay to ensure all scripts are loaded
            setTimeout(function() {
                initializeGlobalFileNoModal();
                
                // Initialize Lucide icons if available
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 500);
        });

        // Add keyboard event listener for modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('metadata-modal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeMetadataModal();
                }
            }
        });
    </script>
@endsection
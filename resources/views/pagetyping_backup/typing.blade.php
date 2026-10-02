@extends('layouts.app')
@section('page-title')
    {{ $PageTitle }}
@endsection

@section('content')
  @include('pagetyping.css.style')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Page Typing Interface -->
        <div class="p-6">
            <!-- File Information Header -->
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i data-lucide="type" class="h-5 w-5 text-purple-600 mr-2"></i>
                        <div>
                            <p class="font-medium text-purple-900">{{ $selectedFileIndexing->file_number }}</p>
                            <p class="text-sm text-purple-700">{{ $selectedFileIndexing->file_title }}</p>
                            <p class="text-xs text-purple-600">{{ $selectedFileIndexing->scannings->count() }} documents scanned</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('pagetyping.index') }}" class="btn btn-outline btn-sm">
                            <i data-lucide="arrow-left" class="h-4 w-4 mr-1"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Page Typing Interface -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 h-screen">
                <!-- Document Viewer -->
                <div class="xl:col-span-2 bg-white rounded-lg shadow-sm border">
                    <!-- Document Navigation -->
                    <div class="p-4 bg-white border-b flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <h3 class="font-medium">Document Preview</h3>
                            <div class="flex items-center space-x-2 text-sm text-gray-600">
                                <span id="current-document-info">No document selected</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- PDF Page Navigation (for multi-page PDFs) -->
                            <div class="flex items-center space-x-2" id="pdf-page-controls" style="display: none;">
                                <button class="btn btn-outline btn-sm" id="prev-pdf-page">
                                    <i data-lucide="chevron-up" class="h-4 w-4"></i>
                                </button>
                                <span class="text-sm px-2" id="pdf-page-counter">1 / 1</span>
                                <button class="btn btn-outline btn-sm" id="next-pdf-page">
                                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                                </button>
                            </div>
                            
                            <!-- Document Navigation -->
                            <div class="flex items-center space-x-2 border-l pl-2">
                                <button class="btn btn-outline btn-sm" id="prev-document">
                                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                    <span class="hidden sm:inline">Prev Doc</span>
                                </button>
                                <span class="text-sm px-2" id="document-counter">1 / 1</span>
                                <button class="btn btn-outline btn-sm" id="next-document">
                                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                    <span class="hidden sm:inline">Next Doc</span>
                                </button>
                            </div>
                            
                            <!-- Zoom Controls -->
                            <div class="flex items-center space-x-2 border-l pl-2">
                                <button class="btn btn-outline btn-sm" id="zoom-out">
                                    <i data-lucide="zoom-out" class="h-4 w-4"></i>
                                </button>
                                <span class="text-sm px-2" id="zoom-level">100%</span>
                                <button class="btn btn-outline btn-sm" id="zoom-in">
                                    <i data-lucide="zoom-in" class="h-4 w-4"></i>
                                </button>
                                <button class="btn btn-outline btn-sm" id="zoom-fit">
                                    <i data-lucide="maximize-2" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document Viewer Container -->
                    <div class="flex-1 overflow-auto bg-gray-100 relative" id="document-viewer-container" style="height: calc(100vh - 200px);">
                        <div class="min-h-full flex items-center justify-center p-4" id="document-viewer">
                            <div class="text-center text-gray-500">
                                <i data-lucide="file-text" class="h-16 w-16 mx-auto mb-4 text-gray-300"></i>
                                <p class="text-lg">Loading documents...</p>
                                <p class="text-sm">Please wait while we prepare your files</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Page Typing Form -->
                <div class="bg-white rounded-lg shadow-sm border flex flex-col">
                    <!-- Form Header -->
                    <div class="p-4 border-b">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-medium">Page Classification</h3>
                            <div class="text-sm text-gray-500" id="current-page-info">
                                Page 1 of Document 1
                            </div>
                        </div>
                        
                        <!-- Quick Page Type Buttons -->
                        <div class="grid grid-cols-2 gap-2 mt-3" id="quick-page-types">
                            <button type="button" class="btn btn-outline btn-sm quick-type-btn" data-type="Certificate">
                                <i data-lucide="award" class="h-4 w-4 mr-1"></i>
                                Certificate
                            </button>
                            <button type="button" class="btn btn-outline btn-sm quick-type-btn" data-type="Deed">
                                <i data-lucide="file-text" class="h-4 w-4 mr-1"></i>
                                Deed
                            </button>
                            <button type="button" class="btn btn-outline btn-sm quick-type-btn" data-type="Letter">
                                <i data-lucide="mail" class="h-4 w-4 mr-1"></i>
                                Letter
                            </button>
                            <button type="button" class="btn btn-outline btn-sm quick-type-btn" data-type="Application Form">
                                <i data-lucide="clipboard" class="h-4 w-4 mr-1"></i>
                                Application
                            </button>
                        </div>
                    </div>
                    
                    <!-- Form Content -->
                    <div class="flex-1 overflow-auto p-4">
                        <form id="page-typing-form" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-2">Page Number</label>
                                    <input type="number" id="page-number" class="input" min="1" value="1">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Serial Number</label>
                                    <input type="number" id="serial-number" class="input" min="1" value="1">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2">Page Type</label>
                                <select id="page-type" class="input">
                                    <option value="">Select page type...</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="Deed">Deed</option>
                                    <option value="Letter">Letter</option>
                                    <option value="Application Form">Application Form</option>
                                    <option value="Map">Map</option>
                                    <option value="Survey Plan">Survey Plan</option>
                                    <option value="Receipt">Receipt</option>
                                    <option value="Cover Page">Cover Page</option>
                                    <option value="Supporting Document">Supporting Document</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2">Page Subtype</label>
                                <input type="text" id="page-subtype" class="input" placeholder="e.g., Cover page, Main content, Attachment">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2">Page Code</label>
                                <input type="text" id="page-code" class="input" placeholder="Optional page code (e.g., COFO, ROFO)">
                            </div>
                            
                            <!-- Additional Metadata -->
                            <div class="border-t pt-4">
                                <h4 class="font-medium mb-3">Additional Information</h4>
                                
                                <div>
                                    <label class="block text-sm font-medium mb-2">Notes</label>
                                    <textarea id="page-notes" class="input" rows="3" placeholder="Optional notes about this page..."></textarea>
                                </div>
                                
                                <div class="mt-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" id="is-important" class="mr-2">
                                        <span class="text-sm">Mark as important page</span>
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="p-4 border-t bg-gray-50">
                        <div class="space-y-3">
                            <!-- Save Buttons -->
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" class="btn btn-outline" id="save-page">
                                    <i data-lucide="save" class="h-4 w-4 mr-2"></i>
                                    Save Page
                                </button>
                                <button type="button" class="btn btn-primary" id="save-and-next">
                                    <i data-lucide="arrow-right" class="h-4 w-4 mr-2"></i>
                                    Save & Next
                                </button>
                            </div>
                            
                            <!-- Progress Section -->
                            <div class="pt-3 border-t">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-sm">Progress</h4>
                                    <span class="text-sm text-gray-500" id="typing-progress">0 / 0 pages</span>
                                </div>
                                
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-blue-500" id="typing-progress-bar" style="width: 0%"></div>
                                </div>
                                
                                <button class="btn btn-success w-full" id="complete-typing" disabled>
                                    <i data-lucide="check-circle" class="h-4 w-4 mr-2"></i>
                                    Complete Page Typing
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PDF Page Extraction Modal -->
            <div id="pdf-extraction-modal" class="dialog-backdrop hidden" aria-hidden="true">
                <div class="dialog-content dialog-medium animate-fade-in">
                    <div class="p-4 border-b flex items-center justify-between">
                        <h2 class="text-lg font-semibold">PDF Page Extraction</h2>
                        <button class="btn btn-ghost btn-sm" id="close-pdf-modal">
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <div class="text-center">
                            <i data-lucide="file-text" class="h-16 w-16 mx-auto mb-4 text-blue-500"></i>
                            <h3 class="text-lg font-semibold mb-2">Extracting PDF Pages</h3>
                            <p class="text-gray-600 mb-4">Please wait while we extract individual pages from your PDF document...</p>
                            
                            <div class="progress mb-4">
                                <div class="progress-bar bg-blue-500" id="pdf-extraction-progress" style="width: 0%"></div>
                            </div>
                            
                            <div class="text-sm text-gray-500" id="pdf-extraction-status">
                                Initializing...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    <!-- Page Typing JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
    // Configure PDF.js worker
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Lucide icons
        lucide.createIcons();
        
        console.log('Page Typing Interface: Initializing...');
        console.log('Selected file ID: {{ $selectedFileIndexing->id }}');
        
        // Auto-start page typing for this file
        setTimeout(function() {
            console.log('Auto-starting page typing...');
            startPageTyping({{ $selectedFileIndexing->id }});
        }, 1000);
    });
    </script>
    
    @include('pagetyping.js.typing_interface_improved')
@endsection


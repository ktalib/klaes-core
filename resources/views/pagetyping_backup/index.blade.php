@extends('layouts.app')
@section('page-title')
    {{ __('Page Typing Dashboard') }}
@endsection

@section('content')
  @include('pagetyping.css.style')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
 <div class="container mx-auto py-6 space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col space-y-2">
      <h1 class="text-2xl font-bold tracking-tight">Page Typing Dashboard</h1>
      <p class="text-muted-foreground">Categorize and digitize file content</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      <!-- Pending Page Typing -->
      <div class="card">
        <div class="p-4 pb-2">
          <h3 class="text-sm font-medium">Pending Page Typing</h3>
        </div>
        <div class="p-4 pt-0">
          <div class="text-2xl font-bold" id="pending-count">{{ $stats['pending_count'] ?? 0 }}</div>
          <p class="text-xs text-muted-foreground mt-1">Files waiting for page typing</p>
        </div>
      </div>

      <!-- In Progress -->
      <div class="card">
        <div class="p-4 pb-2">
          <h3 class="text-sm font-medium">In Progress</h3>
        </div>
        <div class="p-4 pt-0">
          <div class="text-2xl font-bold" id="in-progress-count">{{ $stats['in_progress_count'] ?? 0 }}</div>
          <p class="text-xs text-muted-foreground mt-1">Files currently being typed</p>
        </div>
      </div>

      <!-- Completed -->
      <div class="card">
        <div class="p-4 pb-2">
          <h3 class="text-sm font-medium">Completed</h3>
        </div>
        <div class="p-4 pt-0">
          <div class="text-2xl font-bold" id="completed-count">{{ $stats['completed_count'] ?? 0 }}</div>
          <p class="text-xs text-muted-foreground mt-1">Files completed typing</p>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <div class="tabs-list grid w-full md:w-auto grid-cols-4">
        <button class="tab active" role="tab" aria-selected="true" data-tab="pending">Pending Page Typing</button>
        <button class="tab" role="tab" aria-selected="false" data-tab="in-progress">In Progress</button>
        <button class="tab" role="tab" aria-selected="false" data-tab="completed">Completed</button>
        <button class="tab" role="tab" aria-selected="false" data-tab="typing" id="typing-tab">Typing</button>
      </div>

      <!-- Pending Tab -->
      <div class="tab-content mt-6 active" role="tabpanel" aria-hidden="false" data-tab-content="pending">
        <div class="card">
          <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <h2 class="text-lg font-semibold">Files Pending Page Typing</h2>
                <p class="text-sm text-muted-foreground">Select a file to begin typing its content</p>
              </div>
              <div class="relative w-full md:w-64">
                <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                <input type="search" placeholder="Search files..." class="input w-full pl-8" id="search-pending-files">
              </div>
            </div>
          </div>
          <div class="p-6">
            <div id="pending-files-list" class="space-y-4">
              @if($pendingFiles && $pendingFiles->count() > 0)
                @foreach($pendingFiles as $file)
                  <div class="border rounded-lg p-4 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                          <i data-lucide="folder" class="h-8 w-8 text-blue-500"></i>
                        </div>
                        <div>
                          <h3 class="text-sm font-medium">{{ $file->file_number }}</h3>
                          <p class="text-sm text-gray-600">{{ $file->file_title }}</p>
                          <p class="text-xs text-gray-500">{{ $file->scannings->count() }} documents • {{ $file->district }}</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-2">
                        <span class="badge bg-yellow-500 text-white">Pending</span>
                        <a href="{{ route('pagetyping.index', ['file_indexing_id' => $file->id]) }}" class="btn btn-primary btn-sm">
                          <i data-lucide="type" class="h-4 w-4 mr-1"></i>
                          Start Typing
                        </a>
                      </div>
                    </div>
                  </div>
                @endforeach
              @else
                <div class="text-center py-8">
                  <i data-lucide="inbox" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
                  <p class="text-gray-500">No files pending page typing</p>
                  <p class="text-sm text-gray-400">Upload scanned documents first to begin page typing</p>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>

      <!-- In Progress Tab -->
      <div class="tab-content mt-6 hidden" role="tabpanel" aria-hidden="true" data-tab-content="in-progress">
        <div class="card">
          <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <h2 class="text-lg font-semibold">Files In Progress</h2>
                <p class="text-sm text-muted-foreground">Files that are partially typed</p>
              </div>
              <div class="relative w-full md:w-64">
                <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                <input type="search" placeholder="Search files..." class="input w-full pl-8" id="search-progress-files">
              </div>
            </div>
          </div>
          <div class="p-6">
            <div id="in-progress-files-list" class="space-y-4">
              @if($inProgressFiles && $inProgressFiles->count() > 0)
                @foreach($inProgressFiles as $file)
                  <div class="border rounded-lg p-4 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                          <i data-lucide="folder-open" class="h-8 w-8 text-orange-500"></i>
                        </div>
                        <div>
                          <h3 class="text-sm font-medium">{{ $file->file_number }}</h3>
                          <p class="text-sm text-gray-600">{{ $file->file_title }}</p>
                          @php
                            $totalPagesForFile = 0;
                            foreach($file->scannings as $scanning){
                                if(str_ends_with($scanning->document_path, '.pdf')){
                                    $pdfInfo = app('App\\Http\\Controllers\\EdmsController')->getPdfPageInfo($scanning->document_path);
                                    $pageCount = $pdfInfo['page_count'] ?? 1;
                                    $totalPagesForFile += $pageCount;
                                } else {
                                    $totalPagesForFile += 1;
                                }
                            }
                          @endphp
                          <p class="text-xs text-gray-500">{{ $file->pagetypings->count() }}/{{ $totalPagesForFile }} pages typed</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-2">
                        <span class="badge bg-orange-500 text-white">In Progress</span>
                        <a href="{{ route('pagetyping.index', ['file_indexing_id' => $file->id]) }}" class="btn btn-primary btn-sm">
                          <i data-lucide="edit" class="h-4 w-4 mr-1"></i>
                          Continue
                        </a>
                      </div>
                    </div>
                  </div>
                @endforeach
              @else
                <div class="text-center py-8">
                  <i data-lucide="inbox" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
                  <p class="text-gray-500">No files in progress</p>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>

      <!-- Completed Tab -->
      <div class="tab-content mt-6 hidden" role="tabpanel" aria-hidden="true" data-tab-content="completed">
        <div class="card">
          <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <h2 class="text-lg font-semibold">Completed Files</h2>
                <p class="text-sm text-muted-foreground">Files with completed page typing</p>
              </div>
              <div class="relative w-full md:w-64">
                <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                <input type="search" placeholder="Search files..." class="input w-full pl-8" id="search-completed-files">
              </div>
            </div>
          </div>
          <div class="p-6">
            @if($completedFiles && $completedFiles->count() > 0)
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Number</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Typed</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Typed By</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pages</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="completed-files-list" class="bg-white divide-y divide-gray-200">
                    @foreach($completedFiles as $file)
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          {{ $file->file_number }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {{ $file->file_title }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {{ $file->updated_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          @if($file->pagetypings->count() > 0 && $file->pagetypings->first() && $file->pagetypings->first()->typedBy)
                            {{ $file->pagetypings->first()->typedBy->name }}
                          @else
                            Unknown
                          @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Completed
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {{ $file->pagetypings->count() }} pages
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                          <div class="flex items-center space-x-2">
                            <button class="text-indigo-600 hover:text-indigo-900" onclick="togglePageDetails({{ $file->id }})">
                              <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                              View Pages
                            </button>
                            <a href="{{ route('pagetyping.index', ['file_indexing_id' => $file->id]) }}" class="text-gray-600 hover:text-gray-900">
                              <i data-lucide="external-link" class="h-4 w-4 mr-1"></i>
                              Open
                            </a>
                          </div>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <div class="text-center py-8">
                <i data-lucide="inbox" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
                <p class="text-gray-500">No completed files</p>
              </div>
            @endif
          </div>
        </div>
      </div>

      <!-- Typing Tab -->
      <div class="tab-content mt-6 hidden" role="tabpanel" aria-hidden="true" data-tab-content="typing">
        <div class="card" id="typing-card">
          @if(isset($selectedFileIndexing))
            @php
                $allPages = [];
                $pageIndex = 0;
                foreach($selectedFileIndexing->scannings as $docIndex => $scanning) {
                    if(str_ends_with($scanning->document_path, '.pdf')) {
                        $pdfInfo = app('App\\Http\\Controllers\\EdmsController')->getPdfPageInfo($scanning->document_path);
                        $pageCount = $pdfInfo['page_count'] ?? 1;
                        for($page = 1; $page <= $pageCount; $page++) {
                            $allPages[] = [
                                'type' => 'pdf_page',
                                'document_index' => $docIndex,
                                'page_number' => $page,
                                'file_path' => $scanning->document_path,
                                'page_index' => $pageIndex++,
                                'scanning_id' => $scanning->id
                            ];
                        }
                    } else {
                        $allPages[] = [
                            'type' => 'image',
                            'document_index' => $docIndex,
                            'page_number' => 1,
                            'file_path' => $scanning->document_path,
                            'page_index' => $pageIndex++,
                            'scanning_id' => $scanning->id
                        ];
                    }
                }
                $typingTotalPages = count($allPages);
                $typingSavedCount = $selectedFileIndexing->pagetypings->count();
            @endphp
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
                        <button onclick="switchTab('pending')" class="btn btn-outline btn-sm">
                            <i data-lucide="arrow-left" class="h-4 w-4 mr-1"></i>
                            Back to Dashboard
                        </button>
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
          @else
            <!-- No file selected state -->
            <div class="text-center py-12">
                <i data-lucide="type" class="h-16 w-16 mx-auto mb-4 text-gray-300"></i>
                <h3 class="text-lg font-semibold mb-2">No File Selected</h3>
                <p class="text-gray-600 mb-6">Select a file from the other tabs to begin page typing</p>
                <button onclick="switchTab('pending')" class="btn btn-primary">
                    <i data-lucide="folder" class="h-4 w-4 mr-2"></i>
                    Browse Files
                </button>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

    <!-- Page Typing Dashboard JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

@include('pagetyping.js.tab_functionality')

<!-- FIXED: Include complete typing interface JavaScript with PDF support -->
@include('pagetyping.js.typing_interface_debug')

@if(isset($selectedFileIndexing))
<script>
// Disable Save & Next when all pages are saved
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var totalPages = {{ $typingTotalPages ?? 0 }};
        var initialSaved = {{ $typingSavedCount ?? 0 }};
        var saveNextBtn = document.getElementById('save-and-next');
        var progressTextEl = document.getElementById('typing-progress');

        function setSaveNextDisabled(disabled) {
            if (!saveNextBtn) return;
            saveNextBtn.disabled = disabled;
            if (disabled) {
                saveNextBtn.innerHTML = '<i data-lucide="check-circle" class="h-4 w-4 mr-2"></i> All Pages Saved';
            } else {
                saveNextBtn.innerHTML = '<i data-lucide="arrow-right" class="h-4 w-4 mr-2"></i> Save & Next';
            }
            if (window.lucide && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }
        }

        function parseProgressText() {
            if (!progressTextEl) return null;
            var m = progressTextEl.textContent.match(/(\d+)\s*\/\s*(\d+)/);
            if (!m) return null;
            return { completed: parseInt(m[1], 10), total: parseInt(m[2], 10) };
        }

        function refreshStateFromProgress() {
            var prog = parseProgressText();
            if (prog && prog.total > 0) {
                setSaveNextDisabled(prog.completed >= prog.total);
            } else {
                if (totalPages > 0) {
                    setSaveNextDisabled(initialSaved >= totalPages);
                }
            }
        }

        // Initial state
        refreshStateFromProgress();

        // Observe progress text changes for live updates
        if (progressTextEl && 'MutationObserver' in window) {
            var obs = new MutationObserver(refreshStateFromProgress);
            obs.observe(progressTextEl, { childList: true, subtree: true, characterData: true });
        }
    });
})();
</script>
@endif
@endsection




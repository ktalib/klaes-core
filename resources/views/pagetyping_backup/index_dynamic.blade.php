@extends('layouts.app')
@section('page-title')
    {{ __('Page Typing') }}
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
      <h1 class="text-2xl font-bold tracking-tight">Page Typing</h1>
      <p class="text-muted-foreground">Categorize and digitize file content</p>
      
      @if($selectedFileIndexing)
          <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mt-4">
              <div class="flex items-center">
                  <i data-lucide="type" class="h-5 w-5 text-purple-600 mr-2"></i>
                  <div>
                      <p class="font-medium text-purple-900">Selected File: {{ $selectedFileIndexing->file_number }}</p>
                      <p class="text-sm text-purple-700">{{ $selectedFileIndexing->file_title }}</p>
                      <p class="text-xs text-purple-600">{{ $selectedFileIndexing->scannings->count() }} documents scanned</p>
                  </div>
              </div>
          </div>
      @endif
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
        <button class="tab" role="tab" aria-selected="false" data-tab="typing" aria-disabled="true" id="typing-tab">Typing</button>
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
                        <button class="btn btn-primary btn-sm" onclick="startPageTyping({{ $file->id }})">
                          <i data-lucide="type" class="h-4 w-4 mr-1"></i>
                          Start Typing
                        </button>
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
                          <p class="text-xs text-gray-500">{{ $file->pagetypings->count() }}/{{ $file->scannings->count() }} pages typed</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-2">
                        <span class="badge bg-orange-500 text-white">In Progress</span>
                        <button class="btn btn-primary btn-sm" onclick="continuePageTyping({{ $file->id }})">
                          <i data-lucide="edit" class="h-4 w-4 mr-1"></i>
                          Continue
                        </button>
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
            <div id="completed-files-list" class="space-y-4">
              @if($completedFiles && $completedFiles->count() > 0)
                @foreach($completedFiles as $file)
                  <div class="border rounded-lg p-4 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                          <i data-lucide="check-circle" class="h-8 w-8 text-green-500"></i>
                        </div>
                        <div>
                          <h3 class="text-sm font-medium">{{ $file->file_number }}</h3>
                          <p class="text-sm text-gray-600">{{ $file->file_title }}</p>
                          <p class="text-xs text-gray-500">{{ $file->pagetypings->count() }} pages typed • Completed {{ $file->updated_at->diffForHumans() }}</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-2">
                        <span class="badge bg-green-500 text-white">Completed</span>
                        <button class="btn btn-outline btn-sm" onclick="viewPageTyping({{ $file->id }})">
                          <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                          View
                        </button>
                      </div>
                    </div>
                  </div>
                @endforeach
              @else
                <div class="text-center py-8">
                  <i data-lucide="inbox" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
                  <p class="text-gray-500">No completed files</p>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>

      <!-- Typing Tab -->
      <div class="tab-content mt-6 hidden" role="tabpanel" aria-hidden="true" data-tab-content="typing">
        <div class="card" id="typing-card">
          <!-- Typing interface will be loaded here dynamically -->
          <div class="p-8 text-center">
            <i data-lucide="type" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
            <p class="text-gray-500">Select a file to start page typing</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Page Typing Interface Modal -->
    <div id="page-typing-modal" class="dialog-backdrop hidden" aria-hidden="true">
      <div class="dialog-content dialog-large animate-fade-in">
        <div class="p-4 border-b flex items-center justify-between">
          <h2 class="text-lg font-semibold" id="typing-modal-title">Page Typing</h2>
          <button class="btn btn-ghost btn-sm" id="close-typing-modal">
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
        </div>
        
        <div class="flex-1 overflow-hidden">
          <div class="grid grid-cols-1 lg:grid-cols-2 h-full">
            <!-- Document Viewer -->
            <div class="border-r p-4">
              <div class="mb-4 flex items-center justify-between">
                <h3 class="font-medium">Document Preview</h3>
                <div class="flex items-center space-x-2">
                  <button class="btn btn-outline btn-sm" id="prev-document">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                  </button>
                  <span class="text-sm" id="document-counter">1 / 1</span>
                  <button class="btn btn-outline btn-sm" id="next-document">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                  </button>
                </div>
              </div>
              
              <div class="border rounded-lg h-96 overflow-auto bg-gray-50 flex items-center justify-center" id="document-viewer">
                <p class="text-gray-500">No document selected</p>
              </div>
            </div>
            
            <!-- Page Typing Form -->
            <div class="p-4">
              <h3 class="font-medium mb-4">Page Classification</h3>
              
              <form id="page-typing-form" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium mb-2">Page Number</label>
                  <input type="number" id="page-number" class="input" min="1" value="1">
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
                    <option value="Other">Other</option>
                  </select>
                </div>
                
                <div>
                  <label class="block text-sm font-medium mb-2">Page Subtype</label>
                  <input type="text" id="page-subtype" class="input" placeholder="e.g., Cover page, Main content">
                </div>
                
                <div>
                  <label class="block text-sm font-medium mb-2">Serial Number</label>
                  <input type="number" id="serial-number" class="input" min="1" value="1">
                </div>
                
                <div>
                  <label class="block text-sm font-medium mb-2">Page Code</label>
                  <input type="text" id="page-code" class="input" placeholder="Optional page code">
                </div>
                
                <div class="flex space-x-2">
                  <button type="button" class="btn btn-outline flex-1" id="save-page">
                    <i data-lucide="save" class="h-4 w-4 mr-2"></i>
                    Save Page
                  </button>
                  <button type="button" class="btn btn-primary flex-1" id="save-and-next">
                    <i data-lucide="arrow-right" class="h-4 w-4 mr-2"></i>
                    Save & Next
                  </button>
                </div>
              </form>
              
              <div class="mt-6 pt-4 border-t">
                <div class="flex items-center justify-between mb-4">
                  <h4 class="font-medium">Progress</h4>
                  <span class="text-sm text-gray-500" id="typing-progress">0 / 0 pages</span>
                </div>
                
                <div class="progress mb-4">
                  <div class="progress-bar" id="typing-progress-bar" style="width: 0%"></div>
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
    </div>
  </div>

        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>
    @include('pagetyping.js.javascript_dynamic')
@endsection
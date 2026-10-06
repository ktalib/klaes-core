@extends('layouts.app')
@section('page-title')
    {{ __('Page Typing Dashboard') }}
@endsection

@php
    // Check if URL parameter 'url' is set to 'ptmore' to show page type more mode
    $showPageTypeMore = request()->get('url') === 'ptmore';
@endphp

@section('content')
    @include('scan_uploads.partials.large_format_browser')
    @include('pagetyping.css.style')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
          
          <div class="container mx-auto py-6 space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
              <div class="flex flex-col space-y-2">
                <h1 class="text-2xl font-bold tracking-tight">
                  @if($showPageTypeMore)
                    PageType More
                  @else
                    Page Typing
                  @endif
                </h1>
                <p class="text-muted-foreground">
                  @if($showPageTypeMore)
                    Advanced page type classification and document processing
                  @else
                    Categorize and digitize file content
                  @endif
                </p>
              </div>
            </div>
        
            @if(!$showPageTypeMore)
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
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
                  <p class="text-xs text-muted-foreground mt-1">Total files completed</p>
                </div>
              </div>

              <!-- PageType More -->
              <div class="card">
                <div class="p-4 pb-2">
                  <h3 class="text-sm font-medium">PageType More</h3>
                </div>
                <div class="p-4 pt-0">
                  <div class="text-2xl font-bold text-orange-600" id="pagetype-more-count">{{ $stats['pagetype_more_count'] ?? 0 }}</div>
                  <p class="text-xs text-muted-foreground mt-1">Files with new scans added</p>
                </div>
              </div>
            </div>
            @endif
        
            <!-- Tabs -->
            <div class="tabs">
              @if($showPageTypeMore)
                <div class="tabs-list grid w-full md:w-auto grid-cols-2">
                  <button class="tab active" role="tab" aria-selected="true" data-tab="pagetype-more">PageType More</button>
                  <button class="tab" role="tab" aria-selected="false" data-tab="typing" id="typing-tab">Typing</button>
                </div>
              @else
                <div class="tabs-list grid w-full md:w-auto grid-cols-6">
                  <button class="tab" role="tab" aria-selected="true" data-tab="pending">Pending Page Typing</button>
                  <button class="tab" role="tab" aria-selected="false" data-tab="in-progress">In Progress</button>
                  <button class="tab" role="tab" aria-selected="false" data-tab="completed">Completed</button>
                  <button class="tab" role="tab" aria-selected="false" data-tab="pagetype-more">PageType More</button>
                  <!-- <button class="tab" role="tab" aria-selected="false" data-tab="custom-types">Custom Types</button> -->
                  <button class="tab" role="tab" aria-selected="false" data-tab="typing" aria-disabled="true" id="typing-tab">Typing</button>
                </div>
              @endif
        
              @if(!$showPageTypeMore)
              <!-- Pending Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="false" data-tab-content="pending">
                <div class="card">
                  <div class="p-6 border-b">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h2 class="text-lg font-semibold">Files Pending Page Typing</h2>
                        <p class="text-sm text-muted-foreground">Select a file to begin typing its content</p>
                      </div>
                      <div class="flex items-center gap-2 w-full md:w-auto">
                        <div class="relative w-full md:w-48">
                          <select id="pending-registry-filter" class="input w-full">
                            <option value="">All Registries</option>
                            @foreach($registries ?? [] as $registry)
                              <option value="{{ $registry }}">{{ $registry }}</option>
                            @endforeach
                          </select>
                        </div>
                        <div class="relative w-full md:w-64">
                          <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                          <input type="search" id="pending-search" placeholder="Search files..." class="input w-full pl-8">
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="p-6">
                    <div id="pending-files-list" class="rounded-md border divide-y">
                      <!-- Loading state -->
                      <div class="p-8 text-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p class="text-sm text-gray-500">Loading pending files...</p>
                      </div>
                    </div>
                    <!-- Pagination -->
                    <div id="pending-pagination" class="mt-6 flex items-center justify-between">
                      <div class="text-sm text-gray-700">
                        <span id="pending-showing">Showing 0 to 0 of 0 entries</span>
                      </div>
                      <div class="flex items-center space-x-1">
                        <button id="pending-prev-btn" class="btn btn-outline btn-sm" disabled>
                          <i data-lucide="chevron-left" class="h-4 w-4"></i>
                          Previous
                        </button>
                        <div id="pending-page-numbers" class="flex items-center space-x-1">
                          <!-- Page numbers will be inserted here -->
                        </div>
                        <button id="pending-next-btn" class="btn btn-outline btn-sm" disabled>
                          Next
                          <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              @endif
        
              @if(!$showPageTypeMore)
              <!-- In Progress Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="true" data-tab-content="in-progress">
                <div class="card">
                  <div class="p-6 border-b">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h2 class="text-lg font-semibold">Files In Progress</h2>
                        <p class="text-sm text-muted-foreground">Files that are partially typed</p>
                      </div>
                      <div class="flex items-center gap-2 w-full md:w-auto">
                        <div class="relative w-full md:w-48">
                          <select id="in-progress-registry-filter" class="input w-full">
                            <option value="">All Registries</option>
                            @foreach($registries ?? [] as $registry)
                              <option value="{{ $registry }}">{{ $registry }}</option>
                            @endforeach
                          </select>
                        </div>
                        <div class="relative w-full md:w-64">
                          <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                          <input type="search" id="in-progress-search" placeholder="Search files..." class="input w-full pl-8">
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="p-6">
                    <div id="in-progress-files-list" class="rounded-md border divide-y">
                      <!-- Loading state -->
                      <div class="p-8 text-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p class="text-sm text-gray-500">Loading in-progress files...</p>
                      </div>
                    </div>
                    <!-- Pagination -->
                    <div id="in-progress-pagination" class="mt-6 flex items-center justify-between">
                      <div class="text-sm text-gray-700">
                        <span id="in-progress-showing">Showing 0 to 0 of 0 entries</span>
                      </div>
                      <div class="flex items-center space-x-1">
                        <button id="in-progress-prev-btn" class="btn btn-outline btn-sm" disabled>
                          <i data-lucide="chevron-left" class="h-4 w-4"></i>
                          Previous
                        </button>
                        <div id="in-progress-page-numbers" class="flex items-center space-x-1">
                          <!-- Page numbers will be inserted here -->
                        </div>
                        <button id="in-progress-next-btn" class="btn btn-outline btn-sm" disabled>
                          Next
                          <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              @endif
        
              @if(!$showPageTypeMore)
              <!-- Completed Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="true" data-tab-content="completed">
                <div class="card">
                  <div class="p-6 border-b">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h2 class="text-lg font-semibold">Completed Files</h2>
                        <p class="text-sm text-muted-foreground">Files that have been fully typed</p>
                      </div>
                      <div class="flex items-center gap-2 w-full md:w-auto">
                        <div class="relative w-full md:w-48">
                          <select id="completed-registry-filter" class="input w-full">
                            <option value="">All Registries</option>
                            @foreach($registries ?? [] as $registry)
                              <option value="{{ $registry }}">{{ $registry }}</option>
                            @endforeach
                          </select>
                        </div>
                        <div class="relative w-full md:w-64">
                          <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                          <input type="search" id="completed-search" placeholder="Search files..." class="input w-full pl-8">
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="p-6">
                    <!-- Replaced card-based layout with proper HTML table -->
                    <div class="overflow-x-auto">
                      <table class="w-full border-collapse">
                        <thead>
                          <tr class="border-b bg-muted/20">
                            <th class="text-left p-3 font-medium">File Number</th>
                            <th class="text-left p-3 font-medium">File Name</th>
                            <th class="text-left p-3 font-medium">Date Typed</th>
                            <th class="text-left p-3 font-medium">Typed By</th>
                            <th class="text-left p-3 font-medium">Status</th>
                            <th class="text-left p-3 font-medium">Pages</th>
                            <th class="text-left p-3 font-medium">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="completed-files-table-body">
                          <!-- Loading state -->
                          <tr>
                            <td colspan="7" class="text-center p-8">
                              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                              <p class="text-sm text-gray-500">Loading completed files...</p>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <!-- Pagination -->
                    <div id="completed-pagination" class="mt-6 flex items-center justify-between">
                      <div class="text-sm text-gray-700">
                        <span id="completed-showing">Showing 0 to 0 of 0 entries</span>
                      </div>
                      <div class="flex items-center space-x-1">
                        <button id="completed-prev-btn" class="btn btn-outline btn-sm" disabled>
                          <i data-lucide="chevron-left" class="h-4 w-4"></i>
                          Previous
                        </button>
                        <div id="completed-page-numbers" class="flex items-center space-x-1">
                          <!-- Page numbers will be inserted here -->
                        </div>
                        <button id="completed-next-btn" class="btn btn-outline btn-sm" disabled>
                          Next
                          <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              @endif

              <!-- PageType More Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="{{ $showPageTypeMore ? 'false' : 'true' }}" data-tab-content="pagetype-more">
                <div class="card">
                  <div class="p-6 border-b">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h2 class="text-lg font-semibold">PageType More Files</h2>
                        <p class="text-sm text-muted-foreground">Files with new scans added requiring page typing</p>
                      </div>
                      <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2 w-full md:w-auto">
                          <div class="relative w-full md:w-48">
                            <select id="pagetype-more-registry-filter" class="input w-full">
                              <option value="">All Registries</option>
                              @foreach($registries ?? [] as $registry)
                                <option value="{{ $registry }}">{{ $registry }}</option>
                              @endforeach
                            </select>
                          </div>
                          <div class="relative w-full md:w-64">
                            <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                            <input type="search" placeholder="Search files..." class="input w-full pl-8" id="pagetype-more-search">
                          </div>
                        </div>
                        <button class="btn btn-outline btn-sm" id="refresh-pagetype-more">
                          <i data-lucide="refresh-cw" class="h-4 w-4 mr-1"></i>
                          Refresh
                        </button>
                      </div>
                    </div>
                  </div>
                  <div class="p-6">
                    <div class="overflow-x-auto">
                      <table class="w-full border-collapse">
                        <thead>
                          <tr class="border-b bg-muted/20">
                            <th class="text-left p-3 font-medium">File Number</th>
                            <th class="text-left p-3 font-medium">File Name</th>
                            <th class="text-left p-3 font-medium">Existing Pages</th>
                            <th class="text-left p-3 font-medium">New Scans</th>
                            <th class="text-left p-3 font-medium">Total Pages</th>
                            <th class="text-left p-3 font-medium">Last Updated</th>
                            <th class="text-left p-3 font-medium">Status</th>
                            <th class="text-left p-3 font-medium">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="pagetype-more-table-body">
                          <!-- Loading state -->
                          <tr>
                            <td colspan="8" class="text-center p-8">
                              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                              <p class="text-sm text-gray-500">Loading PageType More files...</p>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <!-- Pagination -->
                    <div id="pagetype-more-pagination" class="mt-6 flex items-center justify-between">
                      <div class="text-sm text-gray-700">
                        <span id="pagetype-more-showing">Showing 0 to 0 of 0 entries</span>
                      </div>
                      <div class="flex items-center space-x-1">
                        <button id="pagetype-more-prev-btn" class="btn btn-outline btn-sm" disabled>
                          <i data-lucide="chevron-left" class="h-4 w-4"></i>
                          Previous
                        </button>
                        <div id="pagetype-more-page-numbers" class="flex items-center space-x-1">
                          <!-- Page numbers will be inserted here -->
                        </div>
                        <button id="pagetype-more-next-btn" class="btn btn-outline btn-sm" disabled>
                          Next
                          <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              @if(!$showPageTypeMore)
              <!-- Custom Types Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="true" data-tab-content="custom-types">
                <div class="card">
                  <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold">Custom Page Types & Subtypes</h3>
                    <p class="text-sm text-muted-foreground">
                      Manage custom page types and subtypes for your organization
                    </p>
                  </div>
                  <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                      
                      <!-- Custom Page Types Section -->
                      <div class="space-y-4">
                        <div class="flex items-center justify-between">
                          <h4 class="text-md font-medium">Custom Page Types</h4>
                          <button class="btn btn-primary btn-sm" id="addCustomPageType">
                            <i data-lucide="plus" class="h-4 w-4 mr-1"></i>
                            Add Page Type
                          </button>
                        </div>
                        
                        <div class="border rounded-md">
                          <div class="p-3 bg-gray-50 border-b">
                            <div class="grid grid-cols-4 gap-4 text-sm font-medium">
                              <div>Name</div>
                              <div>Code</div>
                              <div>Status</div>
                              <div>Actions</div>
                            </div>
                          </div>
                          <div class="divide-y" id="customPageTypesList">
                            <div class="p-4 text-center text-gray-500">
                              <p class="text-sm">No custom page types created yet</p>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Custom Page Subtypes Section -->
                      <div class="space-y-4">
                        <div class="flex items-center justify-between">
                          <h4 class="text-md font-medium">Custom Page Subtypes</h4>
                          <button class="btn btn-primary btn-sm" id="addCustomPageSubtype">
                            <i data-lucide="plus" class="h-4 w-4 mr-1"></i>
                            Add Subtype
                          </button>
                        </div>
                        
                        <div class="border rounded-md">
                          <div class="p-3 bg-gray-50 border-b">
                            <div class="grid grid-cols-5 gap-4 text-sm font-medium">
                              <div>Name</div>
                              <div>Code</div>
                              <div>Page Type</div>
                              <div>Status</div>
                              <div>Actions</div>
                            </div>
                          </div>
                          <div class="divide-y" id="customPageSubtypesList">
                            <div class="p-4 text-center text-gray-500">
                              <p class="text-sm">No custom page subtypes created yet</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              @endif
        
              <!-- Typing Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="true" data-tab-content="typing">
                <div class="card" id="typing-card">
                  <!-- Typing content will be added here dynamically -->
                  <div class="p-8 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                      <i data-lucide="type" class="h-6 w-6"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-medium">Select a file to start typing</h3>
                    <p class="mb-4 text-sm text-muted-foreground">Choose a file from the pending or in-progress tabs</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

    @include('components.edms.registry-transfer-modal')
    @include('components.edms.file-type-transfer-modal')
    @include('scan_uploads.partials.reassign_modal')
    {{-- The file picker both EDMS cards (and the reassignment dialog) open. Its
         plugin script is already loaded further down; without this markup
         GlobalFileNoModal.open() finds no #global-fileno-modal and bails. --}}
    @include('components.global-fileno-modal')

    <!-- Page Typing Dashboard JavaScript -->
    <script>
        // Load PDF.js with fallback
        (function() {
          function loadPDFJS() {
            return new Promise((resolve, reject) => {
              if (window.pdfjsLib) {
                resolve(window.pdfjsLib);
                return;
              }

              const script = document.createElement('script');
              script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
              script.onload = function() {
                if (window.pdfjsLib) {
                  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                  window.pdfjsLib = pdfjsLib;
                  resolve(pdfjsLib);
                } else {
                  reject(new Error('PDF.js loaded but not available'));
                }
              };
              script.onerror = function() {
                reject(new Error('Failed to load PDF.js'));
              };
              document.head.appendChild(script);
            });
          }

          // Try to load PDF.js
          loadPDFJS().then(function(pdfjs) {
            console.log('PDF.js loaded successfully');
          }).catch(function(error) {
            console.error('Failed to load PDF.js:', error);
          });
        })();
    </script>

    <!-- SweetAlert2 for better user feedback -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Enhanced PageTyping JavaScript with CoverType Integration -->
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Storage base URL from Laravel
        const STORAGE_BASE_URL = "{{ asset('storage') }}";
        const PAGE_TYPING_FOLDER_ORDER_URL_TEMPLATE = "{{ route('pagetyping.folder.order', ['file_indexing' => '__FILE_INDEXING__']) }}";
        const CURRENT_USER_ID = {{ auth()->id() ?? 'null' }};

        // Check if PDF.js is available
        console.log('PDF.js availability:', typeof pdfjsLib);
        if (typeof pdfjsLib !== 'undefined') {
          console.log('PDF.js version:', pdfjsLib.version);
        }

        // Application state
        let state = {
          activeTab: 'pending', // Will change to 'typing' when file is selected
          selectedFile: null,
          selectedFileData: null,
          pageTypeMoreMode: false,
          existingPageTypings: [],
          newScans: [],
          combinedPages: [],
          // Typing interface state
          typingState: null,
          // UI preferences
          uiPreferences: {
            hideFilePagesSection: false, // Show File Pages section for direct access
          },
          // Pagination state
          pagination: {
            pending: { currentPage: 1, total: 0, lastPage: 1, perPage: 10, registry: '', search: '' },
            inProgress: { currentPage: 1, total: 0, lastPage: 1, perPage: 10, registry: '', search: '' },
            completed: { currentPage: 1, total: 0, lastPage: 1, perPage: 10, registry: '', search: '' },
            pageTypeMore: { currentPage: 1, total: 0, lastPage: 1, perPage: 10, registry: '', search: '' }
          }
        };

        // Cover types, page types and subtypes - will be loaded from backend
        let coverTypes = [];
        let pageTypes = [];
        let pageSubTypes = {};

        function getFrontCoverId() {
          const fc = coverTypes.find(ct => (ct.code || '').toUpperCase() === 'FC');
          return fc ? (fc.id || '').toString() : '';
        }

        // Helper function to set form element states
        function setFormElementsState(enabled, buttonText = 'Process All Page(s)') {
          const coverTypeSelect = document.getElementById('cover-type');
          const pageTypeSelect = document.getElementById('page-type');
          const pageSubtypeSelect = document.getElementById('page-subtype');
          const serialNoInput = document.getElementById('serial-no');
          const processButton = document.querySelector('.process-all-pages');

          // In BC+FC mode, keep cover/page type locked but allow subtype selection (Old/New cover)
          const bcfcMode = state.typingState?.bcfcMode;
          
          if (coverTypeSelect) {
            // Disable all inputs in BC+FC mode
            const coverTypeEnabled = bcfcMode ? false : enabled;
            coverTypeSelect.disabled = !coverTypeEnabled;
            coverTypeSelect.classList.toggle('opacity-50', !coverTypeEnabled);
          }
          if (pageTypeSelect) {
            // Disable all inputs in BC+FC mode
            const pageTypeEnabled = bcfcMode ? false : enabled;
            pageTypeSelect.disabled = !pageTypeEnabled;
            pageTypeSelect.classList.toggle('opacity-50', !pageTypeEnabled);
          }
          if (pageSubtypeSelect) {
            const subtypeEnabled = enabled;
            pageSubtypeSelect.disabled = !subtypeEnabled;
            pageSubtypeSelect.classList.toggle('opacity-50', !subtypeEnabled);
          }
          if (serialNoInput) {
            serialNoInput.readOnly = true;
            serialNoInput.disabled = true;
            serialNoInput.classList.add('bg-gray-100', 'opacity-75', 'cursor-not-allowed');
          }
          if (processButton) {
            processButton.disabled = !enabled;
            processButton.textContent = buttonText;
            processButton.classList.toggle('opacity-50', !enabled);
            processButton.classList.toggle('cursor-not-allowed', !enabled);
          }
        }

        // Utility function to pick properties from object
        function pick(obj, keys, defaultValue = null) {
          if (!obj || typeof obj !== 'object') return defaultValue;
          for (const key of keys) {
            if (obj.hasOwnProperty(key) && obj[key] !== null && obj[key] !== undefined) {
              return obj[key];
            }
          }
          return defaultValue;
        }

        function serialFromParts(base, suffix = '') {
          const baseValue = (base ?? '').toString();
          const suffixValue = (suffix ?? '').toString();
          return `${baseValue}${suffixValue}`;
        }

        function parseSerial(serialValue) {
          const value = (serialValue ?? '').toString().trim();
          const match = value.match(/^(\d+)([a-z]+)?$/i);
          if (!match) {
            return { base: 0, suffix: '' };
          }
          return {
            base: parseInt(match[1], 10) || 0,
            suffix: (match[2] || '').toLowerCase()
          };
        }

        function suffixFromIndex(index) {
          let n = index;
          let result = '';
          do {
            result = String.fromCharCode(97 + (n % 26)) + result;
            n = Math.floor(n / 26) - 1;
          } while (n >= 0);
          return result;
        }

        function suffixToIndex(suffix) {
          if (!suffix) return -1;
          let n = 0;
          const chars = suffix.toLowerCase().split('');
          for (const ch of chars) {
            n = n * 26 + (ch.charCodeAt(0) - 97 + 1);
          }
          return n - 1;
        }

        function incrementSuffix(suffix) {
          const currentIndex = suffixToIndex(suffix);
          return suffixFromIndex(currentIndex + 1);
        }

        function getCurrentSerialDisplay() {
          if (!state.typingState) return '';
          if (state.typingState.bcfcMode) {
            return serialFromParts(0, state.typingState.bcfcCounter || 'a');
          }
          if (state.typingState.bookletMode) {
            return serialFromParts(state.typingState.bookletStartPage || '', state.typingState.bookletCounter || '');
          }
          return state.typingState.serialNo || '';
        }

        // Load data from backend
        async function loadPageTypingData() {
          try {
            const response = await fetch('{{ route("pagetyping.api.typing-data") }}');
            const data = await response.json();
            
            if (data.success) {
              // Normalize CoverTypes
              const rawCover = data.cover_types || [];
              coverTypes = rawCover.map(ct => {
                const id = pick(ct, ['id','Id']);
                const name = pick(ct, ['name','Name','title','Title'], 'Cover');
                let code = pick(ct, ['code','Code']);
                if (!code) {
                  const nm = (name || '').toLowerCase();
                  if (nm.includes('front')) code = 'FC';
                  else if (nm.includes('back')) code = 'BC';
                  else code = (name || 'CV').split(/\s+/).map(w => w[0]).join('').substring(0,3).toUpperCase();
                }
                return { id: id?.toString(), code, name };
              });

              // Normalize PageTypes
              const rawTypes = data.page_types || [];
              pageTypes = rawTypes.map(pt => {
                const id = pick(pt, ['id','Id']).toString();
                const code = pick(pt, ['code','Code','PageType'], 'PT');
                const name = pick(pt, ['name','Name'], code);
                return { id, code, name };
              });

              // Normalize PageSubTypes - handle both grouped object and flat array
              const rawSubs = data.page_sub_types ?? {};
              if (Array.isArray(rawSubs)) {
                const grouped = {};
                rawSubs.forEach(st => {
                  const id = pick(st, ['id','Id']).toString();
                  const code = pick(st, ['code','Code','PageSubType'], 'ST');
                  const name = pick(st, ['name','Name'], code);
                  let ptId = pick(st, ['page_type_id','PageTypeId','pageTypeId']);
                  if (!ptId) {
                    const ptCode = pick(st, ['PageType','page_type']);
                    if (ptCode) {
                      const found = pageTypes.find(t => (t.code || '').toString().toLowerCase() === ptCode.toString().toLowerCase());
                      if (found) ptId = found.id;
                    }
                  }
                  ptId = ptId?.toString();
                  if (!ptId) return;
                  if (!grouped[ptId]) grouped[ptId] = [];
                  grouped[ptId].push({ id, code, name });
                });
                pageSubTypes = grouped;
              } else {
                // Already grouped as { [PageTypeId]: [ items ] } - normalize items
                const grouped = {};
                Object.keys(rawSubs || {}).forEach(ptId => {
                  const arr = rawSubs[ptId] || [];
                  grouped[ptId.toString()] = arr.map(st => {
                    const id = pick(st, ['id','Id']).toString();
                    const code = pick(st, ['code','Code','PageSubType'], 'ST');
                    const name = pick(st, ['name','Name'], code);
                    return { id, code, name };
                  });
                });
                pageSubTypes = grouped;
              }

              console.log('Loaded page typing data:', { coverTypes, pageTypes, pageSubTypes });
            } else {
              console.error('Error loading page typing data:', data.message);
              coverTypes = [];
              pageTypes = [];
              pageSubTypes = {};
            }
          } catch (error) {
            console.error('Error loading page typing data:', error);
            coverTypes = [];
            pageTypes = [];
            pageSubTypes = {};
          }
        }

        // PageType More files will be loaded from backend
        let pageTypeMoreFiles = [];

        // DOM Elements
        const elements = {
          // Tabs
          tabs: document.querySelectorAll('[role="tab"]'),
          tabContents: document.querySelectorAll('[role="tabpanel"]'),
          typingTab: document.getElementById('typing-tab'),

          // File lists
          pendingFilesList: document.getElementById('pending-files-list'),
          inProgressFilesList: document.getElementById('in-progress-files-list'),
          completedFilesTableBody: document.getElementById('completed-files-table-body'),
          pageTypeMoreTableBody: document.getElementById('pagetype-more-table-body'),

          // Typing card
          typingCard: document.getElementById('typing-card'),

          // Counters
          pendingCount: document.getElementById('pending-count'),
          inProgressCount: document.getElementById('in-progress-count'),
          completedCount: document.getElementById('completed-count'),
          pageTypeMoreCount: document.getElementById('pagetype-more-count'),

          // PageType More specific
          pageTypeMoreSearch: document.getElementById('pagetype-more-search'),
          refreshPageTypeMore: document.getElementById('refresh-pagetype-more')
        };

        function setButtonLoadingState(button, text = 'Loading...') {
          if (!button) return;
          if (button.dataset.loading === 'true') return;
          button.dataset.loading = 'true';
          button.dataset.originalContent = button.innerHTML;
          button.disabled = true;
          button.classList.add('opacity-60', 'cursor-wait');
          button.innerHTML = `
            <span class="flex items-center gap-2 justify-center text-xs font-medium">
              <span class="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
              ${text}
            </span>
          `;
        }

        function resetButtonLoadingState(button) {
          if (!button || button.dataset.loading !== 'true') return;
          button.disabled = false;
          button.classList.remove('opacity-60', 'cursor-wait');
          if (button.dataset.originalContent) {
            button.innerHTML = button.dataset.originalContent;
            delete button.dataset.originalContent;
            if (typeof lucide !== 'undefined') {
              lucide.createIcons();
            }
          }
          delete button.dataset.loading;
        }

        function escapeAttribute(value) {
          return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        }

        /**
         * One row in the actions menu. `extraClass` carries the class the existing
         * row handlers already listen for (.view-file, .edit-file, ...), so moving
         * a button into the menu needs no new wiring.
         */
        function fileActionsMenuItem({ action, icon, label, fileId, fileNumber }) {
          return `
            <button type="button" class="${action} flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100"
                    data-id="${fileId}" data-file-number="${fileNumber}">
              <i data-lucide="${icon}" class="h-4 w-4 text-blue-600"></i>
              ${label}
            </button>`;
        }

        /**
         * The per-file actions menu.
         *
         * @param {object} file
         * @param {Array} extraItems  rows to put above the folder actions —
         *                            {action, icon, label}. Use this for View/Edit
         *                            rather than leaving loose icon buttons in the
         *                            row, so a file has exactly one action surface.
         *
         * The panel is positioned with `position: fixed` at open time
         * (positionFileActionsMenu) because the tables scroll inside
         * `overflow-x-auto` wrappers, which clip an absolutely-positioned child —
         * that is what made the menu appear cut off inside the table.
         */
        function fileActionsMenu(file, extraItems = []) {
          const fileId = escapeAttribute(file.id);
          const fileNumber = escapeAttribute(file.file_number);

          const rows = [
            ...extraItems.map(item => fileActionsMenuItem({ ...item, fileId, fileNumber })),
            fileActionsMenuItem({ action: 'move-registry-file', icon: 'folder-symlink', label: 'Move to NR', fileId, fileNumber }),
            fileActionsMenuItem({ action: 'file-master-folder', icon: 'folder-tree', label: 'File into Master Folder', fileId, fileNumber }),
            fileActionsMenuItem({ action: 'reassign-file-number', icon: 'unlink', label: 'Reassign File Number', fileId, fileNumber }),
          ];

          return `
            <details class="file-actions-menu">
              <summary class="btn btn-ghost btn-sm cursor-pointer list-none px-2" title="File actions" aria-label="File actions">
                <i data-lucide="more-vertical" class="h-4 w-4"></i>
              </summary>
              <div class="file-actions-panel fixed z-[9999] w-56 overflow-hidden rounded-md border bg-white py-1 shadow-lg">
                ${rows.join('')}
              </div>
            </details>
          `;
        }

        /**
         * Pin an open menu's panel next to its button.
         *
         * Fixed positioning takes the panel out of the scrolling table, so the
         * coordinates have to be supplied here. Flips to the left of the button, or
         * above it, when it would otherwise run off screen.
         */
        function positionFileActionsMenu(details) {
          const summary = details.querySelector('summary');
          const panel = details.querySelector('.file-actions-panel');
          if (!summary || !panel) return;

          const anchor = summary.getBoundingClientRect();
          const margin = 8;

          panel.style.visibility = 'hidden';
          const { width, height } = panel.getBoundingClientRect();

          let left = anchor.right - width;
          if (left < margin) left = Math.min(anchor.left, window.innerWidth - width - margin);
          left = Math.max(margin, left);

          let top = anchor.bottom + 4;
          if (top + height > window.innerHeight - margin) {
            const above = anchor.top - height - 4;
            top = above >= margin ? above : Math.max(margin, window.innerHeight - height - margin);
          }

          panel.style.left = `${left}px`;
          panel.style.top = `${top}px`;
          panel.style.visibility = '';
        }

        async function handleFileAction(button, options = {}) {
          if (!button || button.dataset.loading === 'true') return;
          const fileId = button.getAttribute('data-id');
          if (!fileId) {
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'error',
                title: 'Missing file reference',
                text: 'We could not determine which file to open. Please refresh and try again.',
                confirmButtonColor: '#dc3545'
              });
            }
            return;
          }

          const actionOptions = { ...options };
          if (button.classList.contains('edit-file')) {
            actionOptions.editMode = true;
          }
          const loadingText = button.classList.contains('pagetype-more-action') ? 'Opening...' : 'Loading...';
          setButtonLoadingState(button, loadingText);

          try {
            await startPageTyping(fileId, actionOptions);
          } catch (error) {
            console.error('Failed to open typing view:', error);
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'error',
                title: 'Unable to open file',
                text: 'Something went wrong while loading the file. Please try again.',
                confirmButtonColor: '#dc3545'
              });
            }
          } finally {
            resetButtonLoadingState(button);
          }
        }

        /**
         * Unlink this file's scans and attach them to a different file number.
         *
         * For documents captured against the WRONG FILE ENTIRELY — the folder
         * actions cannot fix that, because the file itself is wrong. The dialog is
         * keyed on scan ids, so the file's scans are fetched first.
         */
        async function openReassignForFile(fileIndexingId, fileNumber) {
          if (!fileIndexingId) {
            Swal.fire({ icon: 'warning', title: 'No file selected', timer: 2000 });
            return;
          }

          if (!window.scanReassignmentManager) {
            Swal.fire({ icon: 'error', title: 'Reassignment unavailable', text: 'The reassignment dialog did not load on this page.' });
            return;
          }

          try {
            const response = await fetch(`/scan-uploads/file-scans?file_indexing_id=${encodeURIComponent(fileIndexingId)}`, {
              headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json();
            const documents = payload?.data?.documents || [];

            if (!documents.length) {
              Swal.fire({
                icon: 'info',
                title: 'Nothing to reassign',
                text: `${fileNumber || 'This file'} has no scanned documents to move.`
              });
              return;
            }

            window.scanReassignmentManager.openModal(documents.map(doc => doc.id), documents);
          } catch (error) {
            Swal.fire({ icon: 'error', title: 'Could not load the documents', text: error.message });
          }
        }

        function setupActionHandlers() {
          // A fixed-position panel does not follow its button, so an open menu is
          // repositioned while the page or the table scrolls under it.
          const repositionOpenMenus = () => {
            document.querySelectorAll('.file-actions-menu[open]').forEach(positionFileActionsMenu);
          };
          window.addEventListener('scroll', repositionOpenMenus, true);
          window.addEventListener('resize', repositionOpenMenus);

          // `toggle` fires on every <details>, including ones rendered after load,
          // which is why this is captured on the document rather than bound per row.
          document.addEventListener('toggle', (event) => {
            const details = event.target;
            if (!(details instanceof HTMLElement) || !details.classList.contains('file-actions-menu')) return;
            if (!details.open) return;

            document.querySelectorAll('.file-actions-menu[open]').forEach(other => {
              if (other !== details) other.removeAttribute('open');
            });

            positionFileActionsMenu(details);
          }, true);

          document.addEventListener('click', (event) => {
            // Picking anything from the menu closes it. The click-outside handler
            // below cannot do this, since the target is inside the menu.
            event.target.closest('.file-actions-panel button')
              ?.closest('details.file-actions-menu')?.removeAttribute('open');

            const reassignButton = event.target.closest('.reassign-file-number');
            if (reassignButton) {
              event.preventDefault();
              openReassignForFile(reassignButton.dataset.id, reassignButton.dataset.fileNumber);
              return;
            }

            const transferButton = event.target.closest('.move-registry-file, .file-master-folder');
            if (transferButton) {
              event.preventDefault();
              const dialog = transferButton.classList.contains('move-registry-file')
                ? window.EdmsRegistryTransfer
                : window.EdmsFileType;
              transferButton.closest('details')?.removeAttribute('open');
              dialog.open(
                transferButton.dataset.id,
                transferButton.dataset.fileNumber,
                () => window.location.reload()
              );
              return;
            }

            document.querySelectorAll('.file-actions-menu[open]').forEach(menu => {
              if (!menu.contains(event.target)) menu.removeAttribute('open');
            });
          });

          if (elements.pendingFilesList) {
            elements.pendingFilesList.addEventListener('click', (event) => {
              const startBtn = event.target.closest('.start-typing');
              if (startBtn) {
                event.preventDefault();
                handleFileAction(startBtn);
              }
            });
          }

          if (elements.inProgressFilesList) {
            elements.inProgressFilesList.addEventListener('click', (event) => {
              const continueBtn = event.target.closest('.continue-typing');
              if (continueBtn) {
                event.preventDefault();
                handleFileAction(continueBtn);
              }
            });
          }

          if (elements.completedFilesTableBody) {
            elements.completedFilesTableBody.addEventListener('click', (event) => {
              const actionBtn = event.target.closest('.view-file, .edit-file');
              if (actionBtn) {
                event.preventDefault();
                handleFileAction(actionBtn);
              }
            });
          }

          if (elements.pageTypeMoreTableBody) {
            elements.pageTypeMoreTableBody.addEventListener('click', (event) => {
              const moreBtn = event.target.closest('.pagetype-more-action');
              if (moreBtn) {
                event.preventDefault();
                handleFileAction(moreBtn, { pageTypeMore: true });
                return;
              }

              const viewCombinedBtn = event.target.closest('.view-combined');
              if (viewCombinedBtn) {
                event.preventDefault();
                handleFileAction(viewCombinedBtn, { pageTypeMore: true });
              }
            });
          }
        }

        // Helper functions
        function getFileById(fileId) {
          return [...pageTypeMoreFiles].find(file => file.id === fileId);
        }

        function formatDate(dateString) {
          return new Date(dateString).toLocaleDateString();
        }

        function getCoverTypeById(typeId) {
          return coverTypes.find(type => type.id.toString() === typeId.toString());
        }

        function getPageTypeById(typeId) {
          return pageTypes.find(type => type.id.toString() === typeId.toString());
        }

        function isOtherSelection(value) {
          const normalized = (value ?? '').toString().trim().toLowerCase();
          return normalized === 'other' || normalized === 'others' || normalized === 'oth';
        }

        function getPageSubTypeById(typeId, subTypeId) {
          const key = (typeId ?? '').toString();
          const numericKey = parseInt(key, 10);
          const bucket = pageSubTypes[key] || (!Number.isNaN(numericKey) ? pageSubTypes[numericKey] : null) || [];
          return bucket.find(subType => subType.id.toString() === subTypeId.toString());
        }

        function getProcessedPageMeta(index = null) {
          if (!state.typingState) return null;
          const targetIndex = typeof index === 'number' ? index : state.typingState.selectedPageInFolder;
          if (targetIndex === null || targetIndex === undefined) return null;
          return state.typingState.processedPages[targetIndex] || null;
        }

        function getDefinitionSortValue(index = null) {
          const meta = getProcessedPageMeta(index);
          const value = meta?.definition;
          const parsed = parseInt(value ?? '0', 10);
          return Number.isNaN(parsed) ? 0 : parsed;
        }

        function getDefaultQuickBrowserOrder(scannings = []) {
          return scannings
            .map((scanning, index) => {
              const parsedDisplayOrder = parseInt(scanning?.display_order, 10);
              const parsedId = parseInt(scanning?.id, 10);

              return {
                index,
                displayOrder: Number.isNaN(parsedDisplayOrder) ? Number.MAX_SAFE_INTEGER : parsedDisplayOrder,
                id: Number.isNaN(parsedId) ? Number.MAX_SAFE_INTEGER : parsedId
              };
            })
            .sort((a, b) => {
              if (a.displayOrder !== b.displayOrder) {
                return a.displayOrder - b.displayOrder;
              }

              if (a.id !== b.id) {
                return a.id - b.id;
              }

              return a.index - b.index;
            })
            .map(item => item.index);
        }

        function isValidQuickBrowserOrder(order = [], total = 0) {
          if (!Array.isArray(order) || order.length !== total) return false;
          const normalized = order.map(v => parseInt(v, 10));
          if (normalized.some(v => Number.isNaN(v) || v < 0 || v >= total)) return false;
          return new Set(normalized).size === total;
        }

        function getQuickFileBrowserOrder(scannings = []) {
          if (!state.typingState) return getDefaultQuickBrowserOrder(scannings);

          const total = scannings.length;
          const savedOrder = state.typingState.quickBrowserOrder || [];
          const hasValidSavedOrder = isValidQuickBrowserOrder(savedOrder, total);

          if (state.typingState.quickBrowserOrderLocked && hasValidSavedOrder) {
            return savedOrder.map(v => parseInt(v, 10));
          }

          const computedOrder = getDefaultQuickBrowserOrder(scannings);
          state.typingState.quickBrowserOrder = computedOrder;
          if (!hasValidSavedOrder) {
            state.typingState.quickBrowserOrderLocked = false;
          }

          return computedOrder;
        }

        function getAutoDefinitionFromPosition(pageIndex, scannings = null) {
          const scans = scannings || state.selectedFileData?.scannings || [];
          const order = getQuickFileBrowserOrder(scans);
          const pos = order.indexOf(pageIndex);
          return pos >= 0 ? (pos + 1) : (pageIndex + 1);
        }

        async function persistPageTypingFolderOrder(order = [], scannings = []) {
          const fileIndexingId = state.selectedFileData?.id;
          if (!fileIndexingId || !Array.isArray(order) || !order.length) {
            return;
          }

          const payload = order
            .map((pageIndex, position) => {
              const scanning = scannings?.[pageIndex];
              const scanningId = parseInt(scanning?.id, 10);
              if (Number.isNaN(scanningId)) {
                return null;
              }

              return {
                scanning_id: scanningId,
                position: position + 1
              };
            })
            .filter(Boolean);

          if (!payload.length) {
            return;
          }

          const endpoint = PAGE_TYPING_FOLDER_ORDER_URL_TEMPLATE.replace('__FILE_INDEXING__', String(fileIndexingId));
          const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({ order: payload })
          });

          let result = {};
          try {
            result = await response.json();
          } catch (e) {
            result = {};
          }

          if (!response.ok || result.success === false) {
            throw new Error(result.message || 'Unable to save folder arrangement.');
          }

          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'success',
              title: 'Order saved',
              text: 'Page order updated successfully.',
              toast: true,
              position: 'top-end',
              showConfirmButton: false,
              timer: 1500,
              timerProgressBar: true
            });
          }
        }

        function isEditingCurrentPage() {
          if (!state.typingState?.editMode) return false;
          return !!getProcessedPageMeta();
        }

        // Get page type code, handling "Others" case
        function getPageTypeCode(typeId, othersValue = null) {
          if (isOtherSelection(typeId)) {
            // If both page type and subtype are "others", return "OTHER"
            if (isOtherSelection(state.typingState?.pageSubType)) {
              return 'OTHER';
            }
            // Use provided othersValue if available, otherwise get from current state
            const customValue = othersValue || state.typingState?.pageTypeOthers || '';
            return customValue.substring(0, 4).toUpperCase() || 'OTH';
          }
          const pageType = getPageTypeById(typeId);
          return pageType?.code || 'XX';
        }

        // Get page subtype code, handling both regular subtypes and "Others" case
        function getPageSubTypeCode(typeId, subTypeId, othersValue = null) {
          if (isOtherSelection(subTypeId)) {
            // If both page type and subtype are "others", return empty string (since page type already returns "OTHER")
            if (isOtherSelection(typeId)) {
              return '';
            }
            // Use provided othersValue if available, otherwise get from current state
            const customValue = othersValue || state.typingState?.pageSubTypeOthers || '';
            return customValue.substring(0, 4).toUpperCase() || 'OTH';
          }
          const subType = getPageSubTypeById(typeId, subTypeId);
          return subType?.code || 'XX';
        }

        // Calculate the next serial number for a file based on the new rules
        function calculateNextSerialNumber() {
          if (!state.selectedFileData || !state.selectedFileData.scannings) {
            return '01';
          }

          // SPECIAL CASE: BC+FC Mode
          if (state.typingState?.bcfcMode) {
            return getBcFcSerialNumber();
          }

          // SPECIAL CASE: Booklet Mode – always use base + current suffix
          if (state.typingState?.bookletMode) {
            const base = parseSerial(state.typingState.bookletStartPage || state.typingState.serialNo || '1').base || 1;
            const suffix = state.typingState.bookletCounter || 'a';
            return serialFromParts(base, suffix);
          }

          const currentCoverTypeId = state.typingState?.coverType;
          const currentPageTypeId = state.typingState?.pageType;
          const coverType = getCoverTypeById(currentCoverTypeId);
          const pageType = getPageTypeById(currentPageTypeId);
          const coverCode = coverType?.code;
          const pageTypeCode = pageType?.code;

          // SPECIAL CASE 1: Front Cover (FC) + File Cover (FC) = 0
          if (coverCode === 'FC' && pageTypeCode === 'FC') {
            return '0';
          }

          // SPECIAL CASE 1b: Back Cover (BC) + File Cover (FC) = 0
          if (coverCode === 'BC' && pageTypeCode === 'FC') {
            return '0';
          }

          // SPECIAL CASE 2: Back Cover (BC) + File Back Page (FBP) = 0
          // Check for multiple possible representations of "File Back Page":
          // 1. Page Type code "FBP" 
          // 2. Page Type "FC" (File Cover) with subtype "OFC" (Old File Cover)
          // 3. Any page type that contains "back" or "FBP" in code/name
          const currentPageSubTypeId = state.typingState?.pageSubType;
          const pageSubType = getPageSubTypeById(currentPageTypeId, currentPageSubTypeId);
          const pageSubTypeCode = pageSubType?.code;
          
          const isFileBackPage = (
            pageTypeCode === 'FBP' || // Direct FBP page type
            (pageTypeCode === 'FC' && pageSubTypeCode === 'OFC') || // File Cover + Old File Cover subtype
            pageTypeCode?.toLowerCase().includes('back') || // Any page type with "back" in code
            pageType?.name?.toLowerCase().includes('back') || // Any page type with "back" in name
            pageSubType?.name?.toLowerCase().includes('back') // Any subtype with "back" in name
          );
          
          if (coverCode === 'BC' && isFileBackPage) {
            return '0';
          }

          // Get all existing page typings for this file
          const existingTypings = [];
          
          // Collect all existing page typings from all scannings
          (state.selectedFileData.scannings || []).forEach((scan, idx) => {
            const pts = scan.page_typings || [];
            if (Array.isArray(pts)) {
              pts.forEach(pt => {
                // Extract numeric part from serial (e.g., "1a" -> 1, "02" -> 2)
                const serialValue = serialFromParts(pt.serial_number ?? '0', pt.serial_suffix ?? '').toString();
                const numericPart = parseInt(serialValue.match(/^(\d+)/)?.[1] || '0') || 0;
                
                existingTypings.push({
                  serial_number: numericPart,
                  serial_display: serialValue, // Keep original for letter suffix tracking
                  cover_type_id: pt.cover_type_id,
                  scanning_index: idx
                });
              });
            }
          });

          // Also check processedPages in state (for pages typed but not yet saved)
          if (state.typingState && state.typingState.processedPages) {
            Object.keys(state.typingState.processedPages).forEach(scanIdx => {
              const processed = state.typingState.processedPages[scanIdx];
              if (processed && processed.serialNo !== null && processed.serialNo !== undefined) {
                // Extract numeric part from serial (e.g., "1a" -> 1, "02" -> 2)
                const serialValue = processed.serialNo?.toString() || '0';
                const numericPart = parseInt(serialValue.match(/^(\d+)/)?.[1] || '0') || 0;
                
                existingTypings.push({
                  serial_number: numericPart,
                  serial_display: processed.serialNo,
                  cover_type_id: processed.coverType,
                  scanning_index: parseInt(scanIdx) || 0
                });
              }
            });
          }

          console.log('Serial number calculation:', {
            coverCode,
            pageTypeCode,
            pageSubTypeCode,
            isFileBackPage,
            existingTypingsCount: existingTypings.length
          });

          // For all other cases (Front Cover + other page types, Back Cover + other page types, etc.)
          // Use normal incrementing serial numbers
          let nextSerial = 1; // Start from 1
          
          // Find the next available serial number or add letter suffix if duplicate
          const usedSerials = existingTypings.map(t => t.serial_number).filter(n => !isNaN(n) && n >= 0).sort((a, b) => a - b);
          
          // Find gaps in the sequence or the next number after the highest
          const maxUsedSerial = usedSerials.length > 0 ? Math.max(...usedSerials) : 0;
          for (let i = nextSerial; i <= maxUsedSerial + 1; i++) {
            if (!usedSerials.includes(i)) {
              nextSerial = i;
              break;
            }
          }

          // Check if this serial number already exists for other pages
          const existingWithSameSerial = existingTypings.filter(t => t.serial_number === nextSerial);
          
          if (existingWithSameSerial.length > 0) {
            const suffixes = existingWithSameSerial
              .map(t => {
                const match = t.serial_display.toString().match(/^(\d+)([a-z]+)?$/i);
                return match ? (match[2] || '').toLowerCase() : '';
              })
              .filter(Boolean);

            if (suffixes.length === 0) {
              return nextSerial.toString().padStart(2, '0') + 'a';
            }

            const maxIndex = Math.max(...suffixes.map(s => suffixToIndex(s)));
            const nextSuffix = suffixFromIndex(maxIndex + 1);
            return nextSerial.toString().padStart(2, '0') + nextSuffix;
          }

          return nextSerial.toString().padStart(2, '0');
        }

        function hasUnprocessedPages() {
          if (!state.selectedFileData?.scannings || !state.typingState) {
            return false;
          }
          const processed = state.typingState.processedPages || {};
          return state.selectedFileData.scannings.some((_, idx) => !processed[idx]);
        }

        function getNextUnprocessedPageIndex(startFrom = null) {
          if (!state.selectedFileData?.scannings || !state.typingState) {
            return null;
          }

          const processed = state.typingState.processedPages || {};
          const total = state.selectedFileData.scannings.length;
          if (!total) {
            return null;
          }

          const baseIndex = typeof startFrom === 'number'
            ? startFrom + 1
            : typeof state.typingState.cursorIndex === 'number'
              ? state.typingState.cursorIndex + 1
              : typeof state.typingState.lastProcessedIndex === 'number'
                ? state.typingState.lastProcessedIndex + 1
                : 0;

          for (let idx = Math.max(0, baseIndex); idx < total; idx++) {
            if (!processed[idx]) {
              return idx;
            }
          }

          for (let idx = 0; idx < Math.max(0, baseIndex) && idx < total; idx++) {
            if (!processed[idx]) {
              return idx;
            }
          }

          return null;
        }

        function navigatePreviewPage(direction) {
          const file = state.selectedFileData;
          if (!file || !file.scannings || !state.typingState) return;

          const currentIdx = state.typingState.selectedPageInFolder;
          if (currentIdx === null || currentIdx === undefined) return;

          // Save current page's selections before navigating (if not already saved)
          if (!state.typingState.processedPages[currentIdx]) {
            const autoDefinition = getAutoDefinitionFromPosition(currentIdx, file.scannings);
            const coverCode = getCoverTypeById(state.typingState.coverType)?.code || 'XX';
            const ptCode = getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers);
            const pstCode = getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers);
            const pageSerial = getCurrentSerialDisplay();
            const pageCode = coverCode + '-' + ptCode + (pstCode ? '-' + pstCode : '') + '-' + pageSerial;
            const definitionCode = autoDefinition + '-' + pageCode;

            state.typingState.processedPages[currentIdx] = {
              coverType: state.typingState.coverType,
              pageType: state.typingState.pageType,
              pageTypeOthers: state.typingState.pageTypeOthers || '',
              pageSubType: state.typingState.pageSubType,
              pageSubTypeOthers: state.typingState.pageSubTypeOthers || '',
              serialNo: pageSerial,
              bookletId: state.typingState.bookletMode ? state.typingState.currentBooklet : null,
              isBookletPage: !!state.typingState.bookletMode,
              definition: String(autoDefinition ?? '0'),
              page_code: pageCode,
              definition_code: definitionCode,
              pending: true
            };

            if (state.typingState.bcfcMode) {
              incrementBcFcCounter();
            } else if (state.typingState.bookletMode) {
              incrementBookletCounter();
            } else {
              state.typingState.serialNo = calculateNextSerialNumber();
            }
          }

          const newIdx = currentIdx + direction;
          if (newIdx < 0 || newIdx >= file.scannings.length) return;

          // Update state
          state.typingState.selectedPageInFolder = newIdx;
          state.typingState.cursorIndex = newIdx;

          // Load or clear form based on whether this page was already classified
          const existingData = state.typingState.processedPages[newIdx];
          if (existingData) {
            state.typingState.coverType = existingData.coverType || '';
            state.typingState.pageType = existingData.pageType || (pageTypes[0]?.id || '1').toString();
            state.typingState.pageSubType = existingData.pageSubType || '1';
            state.typingState.pageTypeOthers = existingData.pageTypeOthers || '';
            state.typingState.pageSubTypeOthers = existingData.pageSubTypeOthers || '';
            state.typingState.serialNo = existingData.serialNo || calculateNextSerialNumber();
            setTimeout(() => {
              if (existingData.pending) {
                setFormElementsState(true); // Pending pages are editable
              } else if (state.typingState.editMode) {
                setFormElementsState(true, 'Update Page');
              } else {
                setFormElementsState(false);
              }
            }, 75);
          } else {
            // Clear form for unclassified page
            const defaultCover = state.typingState.bookletMode
              ? getFrontCoverId() || state.typingState.coverType
              : state.typingState.coverType;
            state.typingState.coverType = defaultCover || coverTypes[0]?.id || '';
            state.typingState.pageType = (pageTypes[0]?.id || '1').toString();
            state.typingState.pageTypeOthers = '';
            if (pageSubTypes[parseInt(state.typingState.pageType)]) {
              state.typingState.pageSubType = pageSubTypes[parseInt(state.typingState.pageType)][0]?.id.toString() || '1';
            } else {
              state.typingState.pageSubType = '1';
            }
            state.typingState.pageSubTypeOthers = '';
            state.typingState.serialNo = calculateNextSerialNumber();
            setTimeout(() => {
              setFormElementsState(true);
            }, 75);
          }
          syncBookletSerialForSelection();
          refreshSerialDisplay();

          // Re-render the preview
          const container = document.getElementById('document-preview-container');
          const scanning = file.scannings[newIdx];
          if (container && scanning) {
            renderDocumentPreview(scanning, container, newIdx);
          }

          // Update nav arrow states
          updatePreviewNavigation();

          // Update the rest of the UI (file browser highlight, form, etc.)
          updateUI({ partial: true, target: 'fileBrowser' });
        }

        function updatePreviewNavigation() {
          const file = state.selectedFileData;
          const prevBtn = document.getElementById('preview-prev');
          const nextBtn = document.getElementById('preview-next');
          const currentIdx = state.typingState?.selectedPageInFolder;
          const total = file?.scannings?.length || 0;

          if (prevBtn) {
            const hasPrev = currentIdx !== null && currentIdx !== undefined && currentIdx > 0;
            prevBtn.style.display = total > 1 ? '' : 'none';
            prevBtn.disabled = !hasPrev;
            prevBtn.style.opacity = hasPrev ? '1' : '0.3';
            prevBtn.style.cursor = hasPrev ? 'pointer' : 'not-allowed';
          }

          if (nextBtn) {
            const hasNext = currentIdx !== null && currentIdx !== undefined && currentIdx < total - 1;
            nextBtn.style.display = total > 1 ? '' : 'none';
            nextBtn.disabled = !hasNext;
            nextBtn.style.opacity = hasNext ? '1' : '0.3';
            nextBtn.style.cursor = hasNext ? 'pointer' : 'not-allowed';
          }
        }

        function goToNextPage(startFrom = null) {
          if (!state.selectedFileData || !state.typingState) {
            return null;
          }

          // Save current page's selections before navigating away
          const currentIdx = state.typingState.selectedPageInFolder;
          if (currentIdx !== null && currentIdx !== undefined && !state.typingState.processedPages[currentIdx]) {
            const autoDefinition = getAutoDefinitionFromPosition(currentIdx, state.selectedFileData.scannings);
            const coverCode = getCoverTypeById(state.typingState.coverType)?.code || 'XX';
            const ptCode = getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers);
            const pstCode = getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers);
            const pageSerial = getCurrentSerialDisplay();
            const serialParts = parseSerial(pageSerial);
            const pageCode = coverCode + '-' + ptCode + (pstCode ? '-' + pstCode : '') + '-' + pageSerial;
            const definitionCode = autoDefinition + '-' + pageCode;

            state.typingState.processedPages[currentIdx] = {
              coverType: state.typingState.coverType,
              pageType: state.typingState.pageType,
              pageTypeOthers: state.typingState.pageTypeOthers || '',
              pageSubType: state.typingState.pageSubType,
              pageSubTypeOthers: state.typingState.pageSubTypeOthers || '',
              serialNo: pageSerial,
              bookletId: state.typingState.bookletMode ? state.typingState.currentBooklet : null,
              isBookletPage: !!state.typingState.bookletMode,
              serialBase: serialParts.base,
              serialSuffix: serialParts.suffix || '',
              definition: String(autoDefinition ?? '0'),
              page_code: pageCode,
              definition_code: definitionCode,
              pending: true // Not yet submitted to backend
            };
            console.log('Saved page', currentIdx, 'selections:', state.typingState.processedPages[currentIdx]);

            // Advance serial for next page according to mode
            if (state.typingState.bcfcMode) {
              incrementBcFcCounter();
            } else if (state.typingState.bookletMode) {
              incrementBookletCounter();
            } else {
              state.typingState.serialNo = calculateNextSerialNumber();
            }
          }

          let nextIndex = getNextUnprocessedPageIndex(startFrom);

          // In edit mode, if no unprocessed pages, go to next sequential page
          if (nextIndex === null && state.typingState.editMode) {
            const currentIdx = state.typingState.selectedPageInFolder;
            const total = state.selectedFileData.scannings.length;
            if (currentIdx !== null && currentIdx !== undefined && currentIdx + 1 < total) {
              nextIndex = currentIdx + 1;
            }
          }

          if (nextIndex === null) {
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'info',
                title: 'All Pages Classified',
                text: 'All pages have been classified. Click "Process All Page(s)" to save them.',
                confirmButtonColor: '#0ea5e9'
              });
            }
            return null;
          } 

          state.typingState.selectedPageInFolder = nextIndex;
          state.typingState.cursorIndex = nextIndex;
          state.typingState.serialNo = calculateNextSerialNumber();
          syncBookletSerialForSelection();

          // In edit mode, restore existing data for already-processed pages
          const existingData = state.typingState.processedPages[nextIndex];
          if (existingData && state.typingState.editMode) {
            state.typingState.coverType = existingData.coverType || '';
            state.typingState.pageType = existingData.pageType || (pageTypes[0]?.id || '1').toString();
            state.typingState.pageTypeOthers = existingData.pageTypeOthers || '';
            state.typingState.pageSubType = existingData.pageSubType || '1';
            state.typingState.pageSubTypeOthers = existingData.pageSubTypeOthers || '';
            state.typingState.serialNo = existingData.serialNo || calculateNextSerialNumber();
            syncBookletSerialForSelection();

            setTimeout(() => {
              setFormElementsState(true, 'Update Page');
            }, 75);

            updateUI();
            return nextIndex;
          }

          // Clear form selections for the new page
          const defaultCover = state.typingState.bookletMode
            ? getFrontCoverId() || state.typingState.coverType
            : state.typingState.coverType;
          state.typingState.coverType = defaultCover || coverTypes[0]?.id || '';
          state.typingState.pageType = (pageTypes[0]?.id || '1').toString();
          state.typingState.pageTypeOthers = '';
          // Reset page sub type based on first page type
          if (pageSubTypes[parseInt(state.typingState.pageType)]) {
            state.typingState.pageSubType = pageSubTypes[parseInt(state.typingState.pageType)][0]?.id.toString() || '1';
          } else {
            state.typingState.pageSubType = '1';
          }
          state.typingState.pageSubTypeOthers = '';

          state.typingState.serialNo = calculateNextSerialNumber();
          refreshSerialDisplay();
          syncBookletSerialForSelection();

          setTimeout(() => {
            setFormElementsState(true);
          }, 75);

          updateUI();
          return nextIndex;
        }

        // Update serial number when cover type or other factors change
        function updateSerialNumber() {
          if (!state.typingState) return;

          const newSerial = calculateNextSerialNumber();
          state.typingState.serialNo = newSerial;
          refreshSerialDisplay();
        }

        function refreshSerialDisplay() {
          if (!state.typingState) return;

          const serialValue = state.typingState.bcfcMode
            ? serialFromParts(0, state.typingState.bcfcCounter || 'a')
            : state.typingState.bookletMode
              ? serialFromParts(state.typingState.bookletStartPage || '', state.typingState.bookletCounter || '')
              : state.typingState.serialNo || '';

          const serialInput = document.getElementById('serial-no');
          if (serialInput) {
            serialInput.value = serialValue;
          }

          const helperText = document.querySelector('[data-serial-helper]');
          if (helperText) {
            helperText.textContent = state.typingState.bcfcMode
              ? `BC+FC mode: Serial number is auto-generated as ${serialValue}`
              : state.typingState.bookletMode
                ? `Booklet mode: Serial number is auto-generated as ${serialValue}`
                : `Auto-calculated: ${state.typingState.serialNo || ''}`;
          }

          debouncedUpdatePageCodePreview();
        }

        // Horizontal File Browser functionality
        let _fileBrowserInitialized = false;

        function syncBookletSerialForSelection() {
          if (!state.typingState?.bookletMode || !state.typingState.currentBooklet) return;
          const fcId = getFrontCoverId();
          if (!state.typingState.coverType && fcId) {
            state.typingState.coverType = fcId;
          }
          const bookletId = state.typingState.currentBooklet;
          const processedSet = new Set();
          const processedPages = state.typingState.processedPages || {};
          Object.keys(processedPages).forEach(idx => {
            const pd = processedPages[idx];
            if (pd && (pd.bookletId === bookletId || pd.isBookletPage)) {
              processedSet.add(idx);
            }
          });
          const bookletRecords = state.typingState.bookletPages?.[bookletId] || [];
          bookletRecords.forEach(p => {
            if (p && p.pageIndex !== undefined && p.pageIndex !== null) {
              processedSet.add(p.pageIndex);
            }
          });
          const nextIdx = processedSet.size;
          state.typingState.bookletCounter = suffixFromIndex(nextIdx);
          const base = parseSerial(state.typingState.bookletStartPage || state.typingState.serialNo || '1').base || 1;
          state.typingState.serialNo = serialFromParts(base, state.typingState.bookletCounter);
          refreshSerialDisplay();
        }
        
        function initializeHorizontalFileBrowser(file) {
          if (!file || !file.scannings || file.scannings.length === 0) return;

          const filesPerView = 5;
          
          // Use state to persist scroll position across re-renders
          if (state.typingState && state.typingState.fileBrowserStartIndex === undefined) {
            state.typingState.fileBrowserStartIndex = 0;
          }

          function getStartIndex() {
            return state.typingState?.fileBrowserStartIndex || 0;
          }

          function setStartIndex(val) {
            if (state.typingState) {
              state.typingState.fileBrowserStartIndex = Math.max(0, Math.min(val, file.scannings.length - filesPerView));
            }
          }
          
          // Update navigation buttons
          function updateFileBrowserNavigation() {
            const prevBtn = document.querySelector('.file-browser-prev');
            const nextBtn = document.querySelector('.file-browser-next');
            const indicator = document.getElementById('file-browser-indicator');
            const startIdx = getStartIndex();
            
            if (prevBtn) {
              prevBtn.disabled = startIdx === 0;
              prevBtn.classList.toggle('opacity-50', startIdx === 0);
            }
            
            if (nextBtn) {
              nextBtn.disabled = startIdx + filesPerView >= file.scannings.length;
              nextBtn.classList.toggle('opacity-50', startIdx + filesPerView >= file.scannings.length);
            }
            
            if (indicator) {
              const endIndex = Math.min(startIdx + filesPerView, file.scannings.length);
              indicator.textContent = `${startIdx + 1}-${endIndex} of ${file.scannings.length}`;
            }
          }
          
          // Update file browser view
          function updateFileBrowserView() {
            const strip = document.getElementById('file-browser-strip');
            if (!strip) return;
            
            const startIdx = getStartIndex();
            const itemWidth = 88; // 80px width + 8px gap
            const translateX = -(startIdx * itemWidth);
            strip.style.transform = `translateX(${translateX}px)`;
            
            // Update active states
            document.querySelectorAll('.file-browser-item').forEach((item) => {
              const index = parseInt(item.getAttribute('data-file-index'), 10);
              const isCurrentPage = index === state.typingState.selectedPageInFolder;
              const isSelected = state.typingState.selectedPages.has(index);
              
              // Clear all selection classes
              item.classList.remove('ring-2', 'ring-blue-500', 'ring-purple-500', 'shadow-md', 'bg-purple-50');
              
              // Apply appropriate selection styling
              if (state.typingState.isMultiSelectMode && isSelected) {
                item.classList.add('ring-2', 'ring-purple-500', 'bg-purple-50');
              } else if (isCurrentPage) {
                item.classList.add('ring-2', 'ring-blue-500', 'shadow-md');
              }
              
              // Update border on inner container
              const innerContainer = item.querySelector('.relative.h-20');
              if (innerContainer) {
                innerContainer.className = innerContainer.className
                  .replace(/border-\w+-\d+/g, '')
                  .replace(/\s+/g, ' ');
                
                if (isSelected) {
                  innerContainer.classList.add('border-purple-500');
                } else if (isCurrentPage) {
                  innerContainer.classList.add('border-blue-500');
                } else {
                  innerContainer.classList.add('border-gray-200');
                }
              }
              
              // Legacy transform and shadow handling
              if (isCurrentPage && !state.typingState.isMultiSelectMode) {
                item.style.transform = 'translateY(-2px)';
                item.style.boxShadow = '0 4px 12px rgba(59, 130, 246, 0.2)';
              } else {
                item.style.transform = '';
                item.style.boxShadow = '';
              }
            });
            
            updateFileBrowserNavigation();
          }
          
          // Navigate to previous files
          document.querySelector('.file-browser-prev')?.addEventListener('click', () => {
            const startIdx = getStartIndex();
            if (startIdx > 0) {
              setStartIndex(startIdx - filesPerView);
              updateFileBrowserView();
            }
          });
          
          // Navigate to next files
          document.querySelector('.file-browser-next')?.addEventListener('click', () => {
            const startIdx = getStartIndex();
            if (startIdx + filesPerView < file.scannings.length) {
              setStartIndex(startIdx + filesPerView);
              updateFileBrowserView();
            }
          });
          
          // Keyboard navigation support - only bind once
          if (!_fileBrowserInitialized) {
            document.addEventListener('keydown', (e) => {
              // Only handle keys when typing tab is active and we're in page categorization view
              if (state.activeTab === 'typing' && state.typingState && state.typingState.selectedPageInFolder !== null) {
                // Don't interfere with fullscreen modal navigation
                const fullscreenModal = document.getElementById('fullscreen-modal');
                if (fullscreenModal && !fullscreenModal.classList.contains('hidden')) return;
                
                switch(e.key) {
                  case 'ArrowLeft':
                    e.preventDefault();
                    if (state.typingState.selectedPageInFolder > 0) {
                      state.typingState.selectedPageInFolder--;
                      updateUI();
                    }
                    break;
                  case 'ArrowRight':
                    e.preventDefault();
                    if (state.typingState.selectedPageInFolder < file.scannings.length - 1) {
                      state.typingState.selectedPageInFolder++;
                      updateUI();
                    }
                    break;
                  case 'Home':
                    e.preventDefault();
                    state.typingState.selectedPageInFolder = 0;
                    updateUI();
                    break;
                  case 'End':
                    e.preventDefault();
                    state.typingState.selectedPageInFolder = file.scannings.length - 1;
                    updateUI();
                    break;
                }
              }
            });
          }
          
          // Auto-scroll to selected file
          function scrollToSelectedFile() {
            if (state.typingState.selectedPageInFolder !== null) {
              const selectedIndex = state.typingState.selectedPageInFolder;
              const startIdx = getStartIndex();
              
              // Check if selected file is visible in current view
              if (selectedIndex < startIdx || selectedIndex >= startIdx + filesPerView) {
                // Scroll to make selected file visible
                setStartIndex(selectedIndex - Math.floor(filesPerView / 2));
                updateFileBrowserView();
              }
            }
          }
          
          // Generate thumbnails for file browser
          setTimeout(() => {
            file.scannings.forEach((scanning, index) => {
              const url = getDocumentUrl(scanning);
              const img = isImageFile(scanning.original_filename);
              const pdf = isPDFFile(scanning.original_filename);
              const canvasId = `file-browser-thumb-${index}`;
              const imgId = `file-browser-img-${index}`;

              if (url) {
                if (pdf) {
                  generateFileBrowserPDFThumbnail(url, canvasId);
                } else if (img) {
                  // Image thumbnail is already handled by img tag
                } else {
                  checkContentTypeAndGenerateFileBrowserThumbnail(url, canvasId, imgId, index);
                }
              }
            });
          }, 100);
          
          // Initial setup
          scrollToSelectedFile();
          updateFileBrowserView();
          
          // Only wrap updateUI once to avoid exponential stacking
          if (!_fileBrowserInitialized) {
            const originalUpdateUI = updateUI;
            updateUI = function(...args) {
              originalUpdateUI.apply(this, args);
              setTimeout(() => {
                scrollToSelectedFile();
                updateFileBrowserView();
              }, 50);
            };
            _fileBrowserInitialized = true;
          }
        }

        // Generate PDF thumbnail for file browser
        async function generateFileBrowserPDFThumbnail(url, canvasId) {
          try {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            // Hide loading indicator
            const loadingElement = canvas.parentElement?.querySelector('.loading');
            if (loadingElement) loadingElement.style.display = 'none';

            const loadingTask = pdfjsLib.getDocument(url);
            const pdf = await loadingTask.promise;
            const page = await pdf.getPage(1);

            const scale = 0.2; // Very small scale for compact thumbnails
            const viewport = page.getViewport({ scale: scale });

            canvas.height = viewport.height;
            canvas.width = viewport.width;
            canvas.style.display = 'block';

            const renderContext = {
              canvasContext: canvas.getContext('2d'),
              viewport: viewport
            };

            await page.render(renderContext).promise;
          } catch (error) {
            console.error(`Error generating file browser thumbnail for ${canvasId}:`, error);
            const canvas = document.getElementById(canvasId);
            if (canvas) {
              // Hide loading and show fallback
              const loadingElement = canvas.parentElement?.querySelector('.loading');
              if (loadingElement) loadingElement.style.display = 'none';
              
              const fallbackDiv = canvas.parentElement?.querySelector('.fallback-icon');
              if (fallbackDiv) {
                canvas.style.display = 'none';
                fallbackDiv.style.display = 'flex';
              }
            }
          }
        }

        // Check content type and generate thumbnail for file browser
        async function checkContentTypeAndGenerateFileBrowserThumbnail(url, canvasId, imgId, index) {
          try {
            const response = await fetch(url, { method: 'HEAD' });
            const contentType = response.headers.get('content-type');

            // Hide loading indicator
            const loadingElement = document.querySelector(`#loading-${index}`);
            if (loadingElement) loadingElement.style.display = 'none';

            if (contentType && contentType.includes('pdf')) {
              const canvasElement = document.getElementById(canvasId);
              const fallbackIcon = canvasElement?.parentElement?.querySelector('.fallback-icon');
              if (canvasElement && fallbackIcon) {
                canvasElement.style.display = 'block';
                fallbackIcon.style.display = 'none';
              }
              await generateFileBrowserPDFThumbnail(url, canvasId);
            } else if (contentType && contentType.startsWith('image/')) {
              const imgElement = document.getElementById(imgId);
              const fallbackIcon = imgElement?.parentElement?.querySelector('.fallback-icon');
              if (imgElement && fallbackIcon) {
                imgElement.src = url;
                imgElement.style.display = 'block';
                fallbackIcon.style.display = 'none';
              }
            } else {
              // Show fallback for unknown content types
              const fallbackIcon = document.querySelector(`#loading-${index}`)?.parentElement?.querySelector('.fallback-icon');
              if (fallbackIcon) fallbackIcon.style.display = 'flex';
            }
          } catch (error) {
            console.error('Error checking content-type for file browser thumbnail:', error);
            // Hide loading and show fallback
            const loadingElement = document.querySelector(`#loading-${index}`);
            if (loadingElement) loadingElement.style.display = 'none';
            
            const fallbackIcon = loadingElement?.parentElement?.querySelector('.fallback-icon');
            if (fallbackIcon) fallbackIcon.style.display = 'flex';
          }
        }

        // Filetype helpers and preview rendering
        function isImageFile(filename) {
          if (!filename) return false;
          const exts = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.tif', '.tiff'];
          const lower = filename.toLowerCase();
          return exts.some(ext => lower.endsWith(ext));
        }

        function isPDFFile(filename) {
          if (!filename) return false;
          const lower = filename.toLowerCase();
          const isPdf = lower.endsWith('.pdf');
          console.log('PDF detection for', filename, ':', isPdf);
          return isPdf;
        }

        /**
         * Resolve a document URL.
         *
         * Accepts either a scanning object or a raw path. When given an object,
         * the server-resolved `file_url` wins: the backend walks every EDMS layout
         * (SCAN_UPLOAD -> PAGETYPING -> ARCHIVE_Doc_WARE -> BLIND_SCAN) so a stale
         * document_path still points at a file that exists.
         */
        function getDocumentUrl(source) {
          if (!source) return null;

          if (typeof source === 'object') {
            if (source.file_url) return source.file_url;
            source = source.document_path;
          }

          if (!source) return null;

          const clean = String(source).replace(/^\/+/, '').replace(/\\/g, '/');
          return `${STORAGE_BASE_URL}/${clean}`;
        }

        function renderPDFPagePreview(pageIndex, containerEl) {
          // This function is now simplified since we don't split PDFs
          // Just render the PDF document as-is
          if (!containerEl) return;

          containerEl.innerHTML = `
            <div class="w-full h-full flex flex-col">
              <div class="flex justify-between mb-2">
                <span class="text-sm font-medium">PDF Document</span>
                <div class="flex items-center gap-1">
                  <button class="btn btn-ghost btn-icon zoom-out" title="Zoom Out"><i data-lucide="zoom-out" class="h-4 w-4"></i></button>
                  <span class="text-xs zoom-level">${state.typingState.zoomLevel}%</span>
                  <button class="btn btn-ghost btn-icon zoom-in" title="Zoom In"><i data-lucide="zoom-in" class="h-4 w-4"></i></button>
                  <span class="toolbar-separator"></span>
                  <button class="btn btn-ghost btn-icon rotate-left" title="Rotate Left"><i data-lucide="rotate-ccw" class="h-4 w-4"></i></button>
                  <button class="btn btn-ghost btn-icon rotate" title="Rotate Right"><i data-lucide="rotate-cw" class="h-4 w-4"></i></button>
                  <button class="btn btn-ghost btn-icon save-rotation" title="Save Rotation"><i data-lucide="save" class="h-4 w-4"></i></button>
                  <span class="toolbar-separator"></span>
                  <button class="btn btn-ghost btn-icon crop-toggle" title="Crop"><i data-lucide="crop" class="h-4 w-4"></i></button>
                  <button class="btn btn-ghost btn-icon replace-page" title="Replace Page"><i data-lucide="upload" class="h-4 w-4"></i></button>
                    <button type="button" class="btn btn-outline replace-lf-page"><i data-lucide="refresh-cw" class="h-4 w-4" aria-hidden="true"></i><span>Replace with LF Scan</span></button>
                    <button type="button" class="btn btn-outline add-lf-page"><i data-lucide="image-plus" class="h-4 w-4" aria-hidden="true"></i><span>Add LF Scan</span></button>
                  <button class="btn btn-ghost btn-icon delete-page btn-danger-icon" title="Delete Page"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                  <span class="toolbar-separator"></span>
                  <button class="btn btn-ghost btn-icon fullscreen-btn" onclick="openFullscreenView()" title="Full Screen"><i data-lucide="maximize" class="h-4 w-4"></i></button>
                </div>
              </div>
              <div class="flex-1 overflow-auto flex items-center justify-center bg-gray-50">
                <div class="text-center">
                  <i data-lucide="file-text" class="h-16 w-16 mx-auto mb-3 text-blue-500"></i>
                  <p class="text-sm">PDF document loaded</p>
                  <p class="text-xs text-muted-foreground">Use navigation controls above</p>
                </div>
              </div>
            </div>`;
          lucide.createIcons();
        }

        async function loadPDFViewer(url, containerEl) {
          try {
            // Check if PDF.js is available
            if (typeof pdfjsLib === 'undefined') {
              throw new Error('PDF.js library not loaded');
            }

            // Try to load PDF document with timeout
            const loadingTask = pdfjsLib.getDocument({
              url: url,
              cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/cmaps/',
              cMapPacked: true,
            });

            // Add timeout to prevent hanging
            const timeoutPromise = new Promise((_, reject) => {
              setTimeout(() => reject(new Error('PDF loading timeout')), 30000);
            });

            const pdf = await Promise.race([loadingTask.promise, timeoutPromise]);

            state.pdfViewer.pdfDoc = pdf;
            state.pdfViewer.totalPages = pdf.numPages;
            state.pdfViewer.currentPage = Math.min(state.pdfViewer.currentPage, pdf.numPages);

            // Update page counter
            updatePDFPageCounter(containerEl);

            // Render current page
            await renderPDFPage(containerEl);

          } catch (error) {
            console.error('Error loading PDF:', error);
            console.error('PDF URL:', url);
            console.error('Error details:', error.message);

            // Show error message in the container
            containerEl.innerHTML = `
              <div class="h-full flex items-center justify-center">
                <div class="text-center">
                  <i data-lucide="file-x" class="h-12 w-12 mx-auto mb-3 text-red-500"></i>
                  <p class="text-sm text-red-600">Failed to load PDF</p>
                  <p class="text-xs text-muted-foreground mt-1">${error.message}</p>
                  <button class="btn btn-outline btn-sm mt-2" onclick="window.open('${url}', '_blank')">
                    <i data-lucide="external-link" class="h-4 w-4 mr-1"></i> Open PDF
                  </button>
                </div>
              </div>`;
            lucide.createIcons();
          }
        }

        async function renderPDFPage(containerEl) {
          if (!state.pdfViewer.pdfDoc) return;

          try {
            const page = await state.pdfViewer.pdfDoc.getPage(state.pdfViewer.currentPage);
            const scale = state.typingState.zoomLevel / 100;
            const rotation = state.typingState.rotation;

            const viewport = page.getViewport({ scale: scale, rotation: rotation });

            const canvas = containerEl.querySelector('#pdf-viewer-canvas');
            if (!canvas) return;

            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
              canvasContext: canvas.getContext('2d'),
              viewport: viewport
            };

            await page.render(renderContext).promise;

          } catch (error) {
            console.error('Error rendering PDF page:', error);
          }
        }

        function updatePDFPageCounter(containerEl) {
          const currentPageEl = containerEl.querySelector('.pdf-current-page');
          const totalPagesEl = containerEl.querySelector('.pdf-total-pages');
          const prevBtn = containerEl.querySelector('.pdf-prev');
          const nextBtn = containerEl.querySelector('.pdf-next');

          if (currentPageEl) currentPageEl.textContent = state.pdfViewer.currentPage;
          if (totalPagesEl) totalPagesEl.textContent = state.pdfViewer.totalPages;

          if (prevBtn) {
            prevBtn.disabled = state.pdfViewer.currentPage <= 1;
            prevBtn.classList.toggle('opacity-50', state.pdfViewer.currentPage <= 1);
          }

          if (nextBtn) {
            nextBtn.disabled = state.pdfViewer.currentPage >= state.pdfViewer.totalPages;
            nextBtn.classList.toggle('opacity-50', state.pdfViewer.currentPage >= state.pdfViewer.totalPages);
          }
        }

        function renderDocumentPreview(scanning, containerEl, pageIndex = null) {
          if (!scanning || !containerEl) {
            console.error('Missing scanning or containerEl', { scanning, containerEl });
            return;
          }

          const url = getDocumentUrl(scanning);
          const isImg = isImageFile(scanning.original_filename);
          const isPdf = isPDFFile(scanning.original_filename);

          console.log('Rendering document preview:', {
            filename: scanning.original_filename,
            document_path: scanning.document_path,
            url: url,
            isImg: isImg,
            isPdf: isPdf,
            scanning: scanning
          });

          if (!url) {
            containerEl.innerHTML = `
              <div class="h-full flex items-center justify-center">
                <div class="text-center">
                  <i data-lucide="file-x" class="h-12 w-12 mx-auto mb-3 text-muted-foreground"></i>
                  <p class="text-sm">No preview available</p>
                </div>
              </div>`;
            lucide.createIcons();
            return;
          }

          if (isImg) {
            containerEl.innerHTML = `
              <div class="w-full h-full flex flex-col">
                <div class="flex justify-between mb-2">
                  <span class="text-sm font-medium truncate max-w-[120px]" title="${scanning.original_filename}">${scanning.original_filename}</span>
                  <div class="flex items-center gap-1">
                    <button class="btn btn-ghost btn-icon zoom-out" title="Zoom Out"><i data-lucide="zoom-out" class="h-4 w-4"></i></button>
                    <span class="text-xs zoom-level">${state.typingState.zoomLevel}%</span>
                    <button class="btn btn-ghost btn-icon zoom-in" title="Zoom In"><i data-lucide="zoom-in" class="h-4 w-4"></i></button>
                    <span class="toolbar-separator"></span>
                    <button class="btn btn-ghost btn-icon rotate-left" title="Rotate Left"><i data-lucide="rotate-ccw" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon rotate" title="Rotate Right"><i data-lucide="rotate-cw" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon save-rotation" title="Save Rotation"><i data-lucide="save" class="h-4 w-4"></i></button>
                    <span class="toolbar-separator"></span>
                    <button class="btn btn-ghost btn-icon crop-toggle" title="Crop"><i data-lucide="crop" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon replace-page" title="Replace Page"><i data-lucide="upload" class="h-4 w-4"></i></button>
                    <button type="button" class="btn btn-outline replace-lf-page"><i data-lucide="refresh-cw" class="h-4 w-4" aria-hidden="true"></i><span>Replace with LF Scan</span></button>
                    <button type="button" class="btn btn-outline add-lf-page"><i data-lucide="image-plus" class="h-4 w-4" aria-hidden="true"></i><span>Add LF Scan</span></button>
                    <button class="btn btn-ghost btn-icon delete-page btn-danger-icon" title="Delete Page"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    <span class="toolbar-separator"></span>
                    <button class="btn btn-ghost btn-icon fullscreen-btn" onclick="openFullscreenView()" title="Full Screen"><i data-lucide="maximize" class="h-4 w-4"></i></button>
                  </div>
                </div>
                <div class="flex-1 overflow-auto flex items-center justify-center bg-gray-50 preview-image-container">
                  <img src="${url}" alt="${scanning.original_filename}" class="max-h-full max-w-full object-contain transition-transform document-image"
                       style="transform: scale(${state.typingState.zoomLevel / 100}) rotate(${state.typingState.rotation}deg);"
                       onerror="this.parentElement.innerHTML='<div class=\'text-center\'><i data-lucide=\'image-off\' class=\'h-12 w-12 mx-auto mb-3 text-red-500\'></i><p class=\'text-sm text-red-600\'>Failed to load image</p></div>'; lucide.createIcons();" />
                </div>
              </div>`;

            // Add event listeners for zoom, rotate, and edit tool controls
            setTimeout(() => {
              const zoomInBtn = containerEl.querySelector('.zoom-in');
              const zoomOutBtn = containerEl.querySelector('.zoom-out');
              const rotateBtn = containerEl.querySelector('.rotate');
              const rotateLeftBtn = containerEl.querySelector('.rotate-left');
              const cropToggleBtn = containerEl.querySelector('.crop-toggle');
              const saveRotationBtn = containerEl.querySelector('.save-rotation');
              const replacePageBtn = containerEl.querySelector('.replace-page');
              const deletePageBtn = containerEl.querySelector('.delete-page');

              if (zoomInBtn) {
                zoomInBtn.addEventListener('click', () => {
                  if (state.typingState.zoomLevel < 200) {
                    state.typingState.zoomLevel += 25;
                    updateDocumentZoom();
                  }
                });
              }

              if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', () => {
                  if (state.typingState.zoomLevel > 50) {
                    state.typingState.zoomLevel -= 25;
                    updateDocumentZoom();
                  }
                });
              }

              if (rotateBtn) {
                rotateBtn.addEventListener('click', () => {
                  state.typingState.rotation = (state.typingState.rotation + 90) % 360;
                  updateDocumentRotation();
                });
              }

              if (rotateLeftBtn) {
                rotateLeftBtn.addEventListener('click', () => {
                  state.typingState.rotation = (state.typingState.rotation - 90 + 360) % 360;
                  updateDocumentRotation();
                });
              }

              if (cropToggleBtn) {
                cropToggleBtn.addEventListener('click', () => toggleCropMode(containerEl));
              }

              if (saveRotationBtn) {
                saveRotationBtn.addEventListener('click', () => saveRotatedPage());
              }

              if (replacePageBtn) {
                replacePageBtn.addEventListener('click', () => triggerReplacePage());
              }

              if (deletePageBtn) {
                deletePageBtn.addEventListener('click', () => deleteCurrentPage());
              }
            }, 100);
          } else if (isPdf) {
            // Check if PDF.js is available
            if (typeof pdfjsLib === 'undefined') {
              containerEl.innerHTML = `
                <div class="h-full flex items-center justify-center">
                  <div class="text-center">
                    <i data-lucide="file-x" class="h-12 w-12 mx-auto mb-3 text-red-500"></i>
                    <p class="text-sm text-red-600">PDF viewer not available</p>
                    <p class="text-xs text-muted-foreground mt-1">PDF.js library failed to load</p>
                    <button class="btn btn-outline btn-sm mt-2" onclick="window.open('${url}', '_blank')">
                      <i data-lucide="external-link" class="h-4 w-4 mr-1"></i> Open PDF
                    </button>
                  </div>
                </div>`;
              lucide.createIcons();
              return;
            }

            // Initialize PDF navigation state if not exists
            if (!state.pdfViewer) {
              state.pdfViewer = {
                currentPage: 1,
                totalPages: 1,
                pdfDoc: null,
                url: url
              };
            }

            containerEl.innerHTML = `
              <div class="w-full h-full flex flex-col">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-sm font-medium truncate max-w-[120px]" title="${scanning.original_filename}">${scanning.original_filename}</span>
                  <div class="flex items-center gap-1 flex-wrap">
                    <button class="btn btn-ghost btn-icon zoom-out" title="Zoom Out"><i data-lucide="zoom-out" class="h-4 w-4"></i></button>
                    <span class="text-xs zoom-level">${state.typingState.zoomLevel}%</span>
                    <button class="btn btn-ghost btn-icon zoom-in" title="Zoom In"><i data-lucide="zoom-in" class="h-4 w-4"></i></button>
                    <span class="toolbar-separator"></span>
                    <button class="btn btn-ghost btn-icon rotate-left" title="Rotate Left"><i data-lucide="rotate-ccw" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon rotate" title="Rotate Right"><i data-lucide="rotate-cw" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon save-rotation" title="Save Rotation"><i data-lucide="save" class="h-4 w-4"></i></button>
                    <span class="toolbar-separator"></span>
                    <button class="btn btn-ghost btn-icon crop-toggle" title="Crop"><i data-lucide="crop" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon replace-page" title="Replace Page"><i data-lucide="upload" class="h-4 w-4"></i></button>
                    <button type="button" class="btn btn-outline replace-lf-page"><i data-lucide="refresh-cw" class="h-4 w-4" aria-hidden="true"></i><span>Replace with LF Scan</span></button>
                    <button type="button" class="btn btn-outline add-lf-page"><i data-lucide="image-plus" class="h-4 w-4" aria-hidden="true"></i><span>Add LF Scan</span></button>
                    <button class="btn btn-ghost btn-icon delete-page btn-danger-icon" title="Delete Page"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    <span class="toolbar-separator"></span>
                    <button class="btn btn-ghost btn-icon fullscreen-btn" onclick="openFullscreenView()" title="Full Screen"><i data-lucide="maximize" class="h-4 w-4"></i></button>
                    <div class="flex items-center gap-1 ml-2">
                      <button class="btn btn-ghost btn-icon pdf-prev" ${state.pdfViewer.currentPage <= 1 ? 'disabled' : ''}>
                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                      </button>
                      <span class="text-xs px-2">
                        <span class="pdf-current-page">${state.pdfViewer.currentPage}</span> / <span class="pdf-total-pages">${state.pdfViewer.totalPages}</span>
                      </span>
                      <button class="btn btn-ghost btn-icon pdf-next" ${state.pdfViewer.currentPage >= state.pdfViewer.totalPages ? 'disabled' : ''}>
                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                      </button>
                    </div>
                  </div>
                </div>
                <div class="flex-1 overflow-auto flex items-center justify-center bg-gray-50">
                  <canvas id="pdf-viewer-canvas" class="max-h-full max-w-full border shadow-sm"></canvas>
                </div>
              </div>`;

            lucide.createIcons();

            // Load and render PDF
            loadPDFViewer(url, containerEl);

            // Add PDF navigation event listeners
            setTimeout(() => {
              const prevBtn = containerEl.querySelector('.pdf-prev');
              const nextBtn = containerEl.querySelector('.pdf-next');

              if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                  if (state.pdfViewer && state.pdfViewer.currentPage > 1) {
                    state.pdfViewer.currentPage--;
                    updatePDFPageCounter(containerEl);
                    renderPDFPage(containerEl);
                  }
                });
              }

              if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                  if (state.pdfViewer && state.pdfViewer.currentPage < state.pdfViewer.totalPages) {
                    state.pdfViewer.currentPage++;
                    updatePDFPageCounter(containerEl);
                    renderPDFPage(containerEl);
                  }
                });
              }

              // Add zoom, rotate, and edit tool event listeners
              const zoomInBtn = containerEl.querySelector('.zoom-in');
              const zoomOutBtn = containerEl.querySelector('.zoom-out');
              const rotateBtn = containerEl.querySelector('.rotate');
              const rotateLeftBtn = containerEl.querySelector('.rotate-left');
              const cropToggleBtn = containerEl.querySelector('.crop-toggle');
              const saveRotationBtn = containerEl.querySelector('.save-rotation');
              const replacePageBtn = containerEl.querySelector('.replace-page');
              const deletePageBtn = containerEl.querySelector('.delete-page');

              if (zoomInBtn) {
                zoomInBtn.addEventListener('click', () => {
                  if (state.typingState.zoomLevel < 200) {
                    state.typingState.zoomLevel += 25;
                    updateDocumentZoom();
                  }
                });
              }

              if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', () => {
                  if (state.typingState.zoomLevel > 50) {
                    state.typingState.zoomLevel -= 25;
                    updateDocumentZoom();
                  }
                });
              }

              if (rotateBtn) {
                rotateBtn.addEventListener('click', () => {
                  state.typingState.rotation = (state.typingState.rotation + 90) % 360;
                  updateDocumentRotation();
                });
              }

              if (rotateLeftBtn) {
                rotateLeftBtn.addEventListener('click', () => {
                  state.typingState.rotation = (state.typingState.rotation - 90 + 360) % 360;
                  updateDocumentRotation();
                });
              }

              if (cropToggleBtn) {
                cropToggleBtn.addEventListener('click', () => toggleCropMode(containerEl));
              }

              if (saveRotationBtn) {
                saveRotationBtn.addEventListener('click', () => saveRotatedPage());
              }

              if (replacePageBtn) {
                replacePageBtn.addEventListener('click', () => triggerReplacePage());
              }

              if (deletePageBtn) {
                deletePageBtn.addEventListener('click', () => deleteCurrentPage());
              }
            }, 100);
          } else {
            console.log('File detected as unsupported:', {
              filename: scanning.original_filename,
              isImg: isImg,
              isPdf: isPdf
            });

            // Try to detect file type by content-type
            if (url) {
              fetch(url, { method: 'HEAD' })
                .then(response => {
                  const contentType = response.headers.get('content-type');
                  console.log('Content-Type detected:', contentType);

                  if (contentType && contentType.includes('pdf')) {
                    console.log('Detected PDF by content-type:', contentType);
                    // Re-render as PDF by calling the function again with forced PDF detection
                    const fakeScanning = { ...scanning, original_filename: scanning.original_filename + '.pdf' };
                    renderDocumentPreview(fakeScanning, containerEl, pageIndex);
                  } else if (contentType && contentType.startsWith('image/')) {
                    console.log('Detected image by content-type:', contentType);
                    // Re-render as image by calling the function again with forced image detection
                    const fakeScanning = { ...scanning, original_filename: scanning.original_filename + '.jpg' };
                    renderDocumentPreview(fakeScanning, containerEl, pageIndex);
                  } else {
                    // Show unsupported file message
                    containerEl.innerHTML = `
                      <div class="h-full flex items-center justify-center">
                        <div class="text-center">
                          <i data-lucide="file" class="h-12 w-12 mx-auto mb-3 text-muted-foreground"></i>
                          <p class="text-sm">Unsupported file: ${scanning.original_filename}</p>
                          <p class="text-xs text-muted-foreground">Content-Type: ${contentType || 'unknown'}</p>
                          <button class="btn btn-outline btn-sm mt-2" onclick="window.open('${url}', '_blank')">
                            <i data-lucide="external-link" class="h-4 w-4 mr-1"></i> Open
                          </button>
                        </div>
                      </div>`;
                    lucide.createIcons();
                  }
                })
                .catch(error => {
                  console.error('Error checking file type:', error);
                  containerEl.innerHTML = `
                    <div class="h-full flex items-center justify-center">
                      <div class="text-center">
                        <i data-lucide="file-x" class="h-12 w-12 mx-auto mb-3 text-red-500"></i>
                        <p class="text-sm text-red-600">Error loading file</p>
                        <p class="text-xs text-muted-foreground">${error.message}</p>
                        <button class="btn btn-outline btn-sm mt-2" onclick="window.open('${url}', '_blank')">
                          <i data-lucide="external-link" class="h-4 w-4 mr-1"></i> Open
                        </button>
                      </div>
                    </div>`;
                  lucide.createIcons();
                });
            } else {
              containerEl.innerHTML = `
                <div class="h-full flex items-center justify-center">
                  <div class="text-center">
                    <i data-lucide="file" class="h-12 w-12 mx-auto mb-3 text-muted-foreground"></i>
                    <p class="text-sm">Unsupported file: ${scanning.original_filename}</p>
                    <button class="btn btn-outline btn-sm mt-2" onclick="window.open('${url}', '_blank')">
                      <i data-lucide="external-link" class="h-4 w-4 mr-1"></i> Open
                    </button>
                  </div>
                </div>`;
              lucide.createIcons();
            }
          }

          lucide.createIcons();
        }

        function updateDocumentZoom() {
          const span = document.querySelector('.zoom-level');
          const img = document.querySelector('.document-image');
          const canvas = document.getElementById('pdf-viewer-canvas');

          if (span) span.textContent = `${state.typingState.zoomLevel}%`;

          if (img) {
            img.style.transform = `scale(${state.typingState.zoomLevel / 100}) rotate(${state.typingState.rotation}deg)`;
          }

          if (canvas && state.pdfViewer && state.pdfViewer.pdfDoc) {
            // Re-render PDF page with new zoom
            const container = canvas.closest('[id*="preview"], [id*="container"]');
            if (container) renderPDFPage(container);
          }
        }

        function updateDocumentRotation() {
          const img = document.querySelector('.document-image');
          const canvas = document.getElementById('pdf-viewer-canvas');

          if (img) {
            img.style.transform = `scale(${state.typingState.zoomLevel / 100}) rotate(${state.typingState.rotation}deg)`;
          }

          if (canvas && state.pdfViewer && state.pdfViewer.pdfDoc) {
            // Re-render PDF page with new rotation
            const container = canvas.closest('[id*="preview"], [id*="container"]');
            if (container) renderPDFPage(container);
          }
        }

        // ============================================================
        // Edit Tools: Crop, Save Rotation, Replace Page, Delete Page
        // ============================================================

        // Hidden file input for replace page
        (function() {
          const input = document.createElement('input');
          input.type = 'file';
          input.id = 'pt-replace-file-input';
          input.accept = 'image/*,.pdf,.tif,.tiff';
          input.style.display = 'none';
          input.addEventListener('change', handleReplaceFileSelected);
          document.body.appendChild(input);
        })();

        // --- CROP TOOL ---
        let cropState = { active: false, startX: 0, startY: 0, selecting: false };

        function toggleCropMode(containerEl) {
          const imgContainer = containerEl || document.getElementById('document-preview-container');
          if (!imgContainer) return;

          const img = imgContainer.querySelector('.document-image');
          if (!img) {
            Swal.fire({ icon: 'info', title: 'Crop', text: 'Crop is only available for image files.', timer: 2000 });
            return;
          }

          cropState.active = !cropState.active;
          const cropBtn = imgContainer.querySelector('.crop-toggle');

          if (cropState.active) {
            // Enter crop mode
            if (cropBtn) cropBtn.classList.add('btn-active');

            // Create crop overlay
            const imageContainer = img.parentElement;
            imageContainer.style.position = 'relative';

            let overlay = imageContainer.querySelector('.crop-overlay');
            if (!overlay) {
              overlay = document.createElement('div');
              overlay.className = 'crop-overlay';
              overlay.innerHTML = '<div class="crop-selection" id="crop-selection"></div>' +
                '<div class="crop-actions" id="crop-action-buttons">' +
                  '<button class="btn btn-primary btn-xs crop-apply-btn">Apply Crop</button>' +
                  '<button class="btn btn-outline btn-xs crop-cancel-btn">Cancel</button>' +
                '</div>';
              imageContainer.appendChild(overlay);

              // Mouse events for crop selection
              overlay.addEventListener('mousedown', (e) => {
                // Don't reset selection when clicking on action buttons
                if (e.target.closest('.crop-actions')) return;

                e.preventDefault();
                const rect = overlay.getBoundingClientRect();
                cropState.startX = e.clientX - rect.left;
                cropState.startY = e.clientY - rect.top;
                cropState.selecting = true;

                const sel = overlay.querySelector('.crop-selection');
                sel.style.left = cropState.startX + 'px';
                sel.style.top = cropState.startY + 'px';
                sel.style.width = '0';
                sel.style.height = '0';
                sel.style.display = 'block';
              });

              overlay.addEventListener('mousemove', (e) => {
                if (!cropState.selecting) return;
                const rect = overlay.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const sel = overlay.querySelector('.crop-selection');
                sel.style.left = Math.min(x, cropState.startX) + 'px';
                sel.style.top = Math.min(y, cropState.startY) + 'px';
                sel.style.width = Math.abs(x - cropState.startX) + 'px';
                sel.style.height = Math.abs(y - cropState.startY) + 'px';

                // Show action buttons
                const actions = overlay.querySelector('.crop-actions');
                if (actions) actions.style.display = 'flex';
              });

              overlay.addEventListener('mouseup', () => {
                cropState.selecting = false;
              });

              // Apply crop button
              overlay.querySelector('.crop-apply-btn')?.addEventListener('click', () => applyCrop(imgContainer));

              // Cancel crop button
              overlay.querySelector('.crop-cancel-btn')?.addEventListener('click', () => {
                cancelCropMode(imgContainer);
              });
            }

            overlay.style.display = 'block';
          } else {
            cancelCropMode(imgContainer);
          }
        }

        function cancelCropMode(containerEl) {
          cropState.active = false;
          const imgContainer = containerEl || document.getElementById('document-preview-container');
          if (!imgContainer) return;

          const cropBtn = imgContainer.querySelector('.crop-toggle');
          if (cropBtn) cropBtn.classList.remove('btn-active');

          const overlay = imgContainer.querySelector('.crop-overlay');
          if (overlay) overlay.style.display = 'none';
        }

        async function applyCrop(containerEl) {
          const img = containerEl.querySelector('.document-image');
          const overlay = containerEl.querySelector('.crop-overlay');
          const selection = containerEl.querySelector('.crop-selection');

          if (!img || !overlay || !selection) return;

          const selRect = selection.getBoundingClientRect();
          const imgRect = img.getBoundingClientRect();

          // Calculate crop coords relative to the natural image
          const scaleX = img.naturalWidth / imgRect.width;
          const scaleY = img.naturalHeight / imgRect.height;

          const cropX = Math.max(0, (selRect.left - imgRect.left) * scaleX);
          const cropY = Math.max(0, (selRect.top - imgRect.top) * scaleY);
          const cropW = Math.min(img.naturalWidth - cropX, selRect.width * scaleX);
          const cropH = Math.min(img.naturalHeight - cropY, selRect.height * scaleY);

          if (cropW < 10 || cropH < 10) {
            Swal.fire({ icon: 'warning', title: 'Selection too small', text: 'Please select a larger area to crop.', timer: 2000 });
            return;
          }

          // Render cropped image to canvas
          const canvas = document.createElement('canvas');
          canvas.width = cropW;
          canvas.height = cropH;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

          try {
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
            if (!blob) throw new Error('Failed to create cropped image');

            cancelCropMode(containerEl);

            Swal.fire({ title: 'Applying crop...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            // Upload the cropped image as replacement
            await uploadReplacementFile(blob, 'cropped_page.png');

            Swal.fire({ icon: 'success', title: 'Crop Applied', timer: 1500, showConfirmButton: false });

          } catch (err) {
            console.error('Crop error:', err);
            Swal.fire({ icon: 'error', title: 'Crop Failed', text: err.message });
          }
        }

        // --- SAVE ROTATION ---
        async function saveRotatedPage() {
          if (!state.typingState || state.typingState.rotation === 0) {
            Swal.fire({ icon: 'info', title: 'No Rotation', text: 'Rotate the image first before saving.', timer: 2000 });
            return;
          }

          const pageIndex = state.typingState.selectedPageInFolder;
          if (pageIndex === null || pageIndex === undefined) {
            Swal.fire({ icon: 'warning', title: 'No Page Selected', text: 'Select a page first.', timer: 2000 });
            return;
          }

          const img = document.querySelector('.document-image');
          if (!img) {
            Swal.fire({ icon: 'info', title: 'Image Only', text: 'Save rotation is only available for image files.', timer: 2000 });
            return;
          }

          const { isConfirmed } = await Swal.fire({
            title: 'Save Rotation?',
            text: `Save the current ${state.typingState.rotation}° rotation permanently?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save',
            confirmButtonColor: '#3b82f6'
          });

          if (!isConfirmed) return;

          try {
            Swal.fire({ title: 'Saving rotation...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            const blob = await buildRotatedImageBlob(img, state.typingState.rotation);

            // Reset rotation BEFORE re-render so the already-rotated image isn't rotated again
            state.typingState.rotation = 0;

            await uploadReplacementFile(blob, 'rotated_page.png');
            Swal.fire({ icon: 'success', title: 'Rotation Saved', timer: 1500, showConfirmButton: false });

          } catch (err) {
            console.error('Save rotation error:', err);
            Swal.fire({ icon: 'error', title: 'Save Failed', text: err.message });
          }
        }

        function buildRotatedImageBlob(img, degrees) {
          return new Promise((resolve, reject) => {
            const rad = (degrees * Math.PI) / 180;
            const cos = Math.abs(Math.cos(rad));
            const sin = Math.abs(Math.sin(rad));
            const w = img.naturalWidth;
            const h = img.naturalHeight;
            const newW = Math.ceil(w * cos + h * sin);
            const newH = Math.ceil(w * sin + h * cos);

            const canvas = document.createElement('canvas');
            canvas.width = newW;
            canvas.height = newH;
            const ctx = canvas.getContext('2d');
            ctx.translate(newW / 2, newH / 2);
            ctx.rotate(rad);
            ctx.drawImage(img, -w / 2, -h / 2);

            canvas.toBlob(blob => {
              if (blob) resolve(blob);
              else reject(new Error('Failed to create rotated image'));
            }, 'image/png');
          });
        }

        // LF replacement retains all classification and selection state.
        document.addEventListener('click', event => {
          const button = event.target.closest('.replace-lf-page, .add-lf-page');
          if (!button) return;
          const file = state.selectedFileData;
          const index = state.typingState?.selectedPageInFolder;
          const scanning = file?.scannings?.[index];
          const replacing = button.classList.contains('replace-lf-page');
          if (!file || (replacing && !scanning)) return;
          LargeFormatScans.open({
            existingUrl: replacing ? getDocumentUrl(scanning.file_url || scanning.document_path) : '',
            title: replacing ? `Replace page ${index + 1} with LF Scan` : 'Add LF Scan — requires page typing',
            confirmLabel: replacing ? 'Confirm Replacement' : 'Add LF Scan',
            // LF scans are filed one folder per file number on the records
            // server, so the picker opens inside this file's own folder.
            fileIndexingId: file.id,
            onConfirm: async entry => {
              const result = await LargeFormatScans.save({ source: entry.path, file_indexing_id: file.id,
                registry: file.registry, ...(replacing ? { scanning_id: scanning.id,
                  expected_path: scanning.stored_document_path || scanning.document_path } : {}) });
              const doc = result.document;
              if (replacing) {
                Object.assign(scanning, { document_path: doc.document_path, stored_document_path: doc.document_path,
                  original_filename: doc.original_filename, file_url: doc.file_url });
              } else {
                file.scannings.push({ ...doc, total_pages: 1, typed_pages: 0, is_fully_typed: false, page_typings: [] });
                file.total_pages = (file.total_pages || 0) + 1;
                file.total_scannings = file.scannings.length;
                file.is_completed = false;
                file.progress = file.total_pages ? Math.round((file.typed_pages || 0) / file.total_pages * 100) : 0;
              }
              renderTypingView();
              if (replacing) {
                Swal.fire({ icon: 'success', title: 'LF image replaced', text: 'Page classification was retained. Check that the type and subtype are still correct.' });
              }
            }
          });
        });

        // --- REPLACE PAGE ---
        function triggerReplacePage() {
          const pageIndex = state.typingState?.selectedPageInFolder;
          if (pageIndex === null || pageIndex === undefined) {
            Swal.fire({ icon: 'warning', title: 'No Page Selected', text: 'Select a page first.', timer: 2000 });
            return;
          }

          const scanning = state.selectedFileData?.scannings?.[pageIndex];
          if (!scanning || !scanning.id) {
            Swal.fire({ icon: 'warning', title: 'Invalid Page', text: 'Cannot replace this page - no scanning record found.', timer: 2000 });
            return;
          }

          document.getElementById('pt-replace-file-input')?.click();
        }

        async function handleReplaceFileSelected(e) {
          const file = e.target.files?.[0];
          if (!file) return;
          e.target.value = ''; // Reset for future use

          try {
            await uploadReplacementFile(file, file.name);
          } catch (err) {
            console.error('Replace page error:', err);
            Swal.fire({ icon: 'error', title: 'Replace Failed', text: err.message });
          }
        }

        async function uploadReplacementFile(fileOrBlob, filename) {
          const pageIndex = state.typingState?.selectedPageInFolder;
          if (pageIndex === null || pageIndex === undefined) throw new Error('No page selected');

          const scanning = state.selectedFileData?.scannings?.[pageIndex];
          if (!scanning || !scanning.id) throw new Error('No scanning record found for this page');

          const formData = new FormData();
          formData.append('file', fileOrBlob, filename);
          formData.append('file_indexing_id', state.selectedFileData.id);
          formData.append('scanning_id', scanning.id);
          formData.append('page_index', pageIndex);
          formData.append('page_number', pageIndex + 1);

          const response = await fetch('{{ route("pagetyping.api.replace-page") }}', {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
          });

          const result = await response.json();

          if (!result.success) {
            throw new Error(result.message || 'Replace failed');
          }

          // Update scanning data in state
          // Backend returns data under 'document' key with file_path / display_name
          const doc = result.document || result.scanning || {};
          const newPath = doc.file_path || doc.document_path || scanning.document_path;
          const newFilename = doc.display_name || doc.original_filename || scanning.original_filename;

          state.selectedFileData.scannings[pageIndex] = {
            ...scanning,
            document_path: newPath,
            original_filename: newFilename,
            // Take the server's resolved URL; never keep the stale one
            file_url: doc.file_url || doc.url || null
          };

          console.log('Replacement uploaded – updated scanning state:', {
            pageIndex,
            newPath,
            newFilename,
            scanning: state.selectedFileData.scannings[pageIndex]
          });

          // Re-render the full view (which also re-renders document preview at the end)
          renderTypingView();
        }

        // --- DELETE PAGE ---
        async function deleteCurrentPage() {
          const pageIndex = state.typingState?.selectedPageInFolder;
          if (pageIndex === null || pageIndex === undefined) {
            Swal.fire({ icon: 'warning', title: 'No Page Selected', text: 'Select a page first.', timer: 2000 });
            return;
          }

          const scanning = state.selectedFileData?.scannings?.[pageIndex];
          if (!scanning || !scanning.id) {
            Swal.fire({ icon: 'warning', title: 'Invalid Page', text: 'Cannot delete this page - no scanning record found.', timer: 2000 });
            return;
          }

          if (state.selectedFileData.scannings.length <= 1) {
            Swal.fire({ icon: 'warning', title: 'Cannot Delete', text: 'Cannot delete the last remaining page of a file.', timer: 3000 });
            return;
          }

          const { isConfirmed } = await Swal.fire({
            title: 'Delete Page?',
            html: `<p>Are you sure you want to permanently delete <strong>Page ${pageIndex + 1}</strong>?</p>
                   <p class="text-sm text-gray-500 mt-2">File: ${scanning.original_filename}</p>
                   <p class="text-sm text-red-600 mt-1">This action cannot be undone.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel'
          });

          if (!isConfirmed) return;

          try {
            Swal.fire({ title: 'Deleting page...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            const response = await fetch('{{ route("pagetyping.api.delete-page") }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
              },
              body: JSON.stringify({
                file_indexing_id: state.selectedFileData.id,
                scanning_id: scanning.id,
                page_index: pageIndex
              })
            });

            const result = await response.json();

            if (!result.success) {
              throw new Error(result.message || 'Delete failed');
            }

            // Remove from local state
            state.selectedFileData.scannings.splice(pageIndex, 1);

            // Remove from processedPages and re-index
            const newProcessed = {};
            Object.keys(state.typingState.processedPages).forEach(key => {
              const idx = parseInt(key);
              if (idx < pageIndex) {
                newProcessed[idx] = state.typingState.processedPages[idx];
              } else if (idx > pageIndex) {
                newProcessed[idx - 1] = state.typingState.processedPages[idx];
              }
              // idx === pageIndex is the deleted page, skip it
            });
            state.typingState.processedPages = newProcessed;

            // Navigate to next available page or go back to folder view
            if (state.selectedFileData.scannings.length === 0) {
              state.typingState.selectedPageInFolder = null;
              state.typingState.cursorIndex = null;
            } else if (pageIndex >= state.selectedFileData.scannings.length) {
              state.typingState.selectedPageInFolder = state.selectedFileData.scannings.length - 1;
              state.typingState.cursorIndex = state.typingState.selectedPageInFolder;
            }
            // else stay on same index (next page shifts into position)

            renderTypingView();
            Swal.fire({ icon: 'success', title: 'Page Deleted', text: 'The page has been permanently removed.', timer: 1500, showConfirmButton: false });

          } catch (err) {
            console.error('Delete page error:', err);
            Swal.fire({ icon: 'error', title: 'Delete Failed', text: err.message });
          }
        }

        
        function openFullscreenView() {
          const modal = document.getElementById('fullscreen-modal');
          const content = document.getElementById('fullscreen-content');
          const originalContainer = document.getElementById('document-preview-container');

          if (!modal || !content || !originalContainer) return;

          // Clone the preview content
          const clonedContent = originalContainer.cloneNode(true);

          // Remove the fullscreen button from clone
          const fullscreenBtn = clonedContent.querySelector('.fullscreen-btn');
          if (fullscreenBtn) fullscreenBtn.remove();

          // Update clone styling for full screen
          clonedContent.className = 'w-full h-full flex flex-col p-6';
          clonedContent.style.height = '100%';
          clonedContent.style.maxHeight = 'none';

          // Clear and append cloned content
          content.innerHTML = '';
          content.appendChild(clonedContent);

          // Show modal
          modal.classList.remove('hidden');

          // Update nav arrows and page indicator
          updateFullscreenNavigation();

          // Re-render PDF if needed
          const canvas = clonedContent.querySelector('#pdf-viewer-canvas');
          if (canvas && state.pdfViewer && state.pdfViewer.pdfDoc) {
            renderPDFPage(clonedContent);
          }

          // Add event listeners to cloned controls
          addFullscreenEventListeners(clonedContent);

          // Create icons for cloned content
          lucide.createIcons();
        }

        function updateFullscreenNavigation() {
          const file = state.selectedFileData;
          const indicator = document.getElementById('fullscreen-page-indicator');
          const prevBtn = document.getElementById('fullscreen-prev');
          const nextBtn = document.getElementById('fullscreen-next');
          const currentIdx = state.typingState?.selectedPageInFolder;
          const total = file?.scannings?.length || 0;

          if (indicator) {
            indicator.textContent = (currentIdx !== null && currentIdx !== undefined)
              ? `Page ${currentIdx + 1} of ${total}`
              : `${total} page${total !== 1 ? 's' : ''}`;
          }

          if (prevBtn) {
            const hasPrev = currentIdx !== null && currentIdx > 0;
            prevBtn.style.display = total > 1 ? '' : 'none';
            prevBtn.disabled = !hasPrev;
            prevBtn.classList.toggle('opacity-30', !hasPrev);
            prevBtn.classList.toggle('cursor-not-allowed', !hasPrev);
          }

          if (nextBtn) {
            const hasNext = currentIdx !== null && currentIdx < total - 1;
            nextBtn.style.display = total > 1 ? '' : 'none';
            nextBtn.disabled = !hasNext;
            nextBtn.classList.toggle('opacity-30', !hasNext);
            nextBtn.classList.toggle('cursor-not-allowed', !hasNext);
          }
        }

        function navigateFullscreenPage(direction) {
          console.log('navigateFullscreenPage called, direction:', direction);
          const file = state.selectedFileData;
          if (!file || !file.scannings || !state.typingState) {
            console.warn('navigateFullscreenPage: missing data', {
              hasFile: !!file,
              hasScannings: !!file?.scannings,
              hasTypingState: !!state.typingState
            });
            return;
          }

          const currentIdx = state.typingState.selectedPageInFolder;
          console.log('navigateFullscreenPage: currentIdx =', currentIdx, 'total =', file.scannings.length);
          if (currentIdx === null || currentIdx === undefined) {
            console.warn('navigateFullscreenPage: no current page index, trying 0');
            // Fallback: if no current index, start from 0
            state.typingState.selectedPageInFolder = 0;
            state.typingState.cursorIndex = 0;
          }

          const safeCurrentIdx = state.typingState.selectedPageInFolder;
          const newIdx = safeCurrentIdx + direction;
          console.log('navigateFullscreenPage: newIdx =', newIdx);
          if (newIdx < 0 || newIdx >= file.scannings.length) {
            console.log('navigateFullscreenPage: out of bounds, skipping');
            return;
          }

          // Update state
          state.typingState.selectedPageInFolder = newIdx;
          state.typingState.cursorIndex = newIdx;

          // Update serial
          const existingData = state.typingState.processedPages[newIdx];
          if (existingData) {
            state.typingState.serialNo = existingData.serialNo || state.typingState.serialNo;
          } else {
            state.typingState.serialNo = calculateNextSerialNumber();
          }
          syncBookletSerialForSelection();
          refreshSerialDisplay();

          // Re-render the fullscreen content with new page
          const content = document.getElementById('fullscreen-content');
          const scanning = file.scannings[newIdx];
          if (content && scanning) {
            const previewContainer = document.createElement('div');
            previewContainer.id = 'document-preview-container';
            previewContainer.className = 'w-full h-full flex flex-col p-6';
            previewContainer.style.height = '100%';
            previewContainer.style.maxHeight = 'none';
            content.innerHTML = '';
            content.appendChild(previewContainer);
            renderDocumentPreview(scanning, previewContainer, newIdx);

            // Remove fullscreen button from re-rendered content and initialize icons
            setTimeout(() => {
              const fsBtn = previewContainer.querySelector('.fullscreen-btn');
              if (fsBtn) fsBtn.remove();
              lucide.createIcons();
            }, 150);
          }

          // Update nav arrows and indicator
          updateFullscreenNavigation();
          console.log('Fullscreen navigated to page:', newIdx, 'of', file.scannings.length);

          // Also update the main UI behind the modal
          updateUI({ partial: true, target: 'fileBrowser' });
        }

        function closeFullscreenView() {
          const modal = document.getElementById('fullscreen-modal');
          if (modal) {
            modal.classList.add('hidden');
          }
        }

        function addFullscreenEventListeners(container) {
          // Zoom controls
          container.querySelector('.zoom-in')?.addEventListener('click', () => {
            if (state.typingState.zoomLevel < 300) {
              state.typingState.zoomLevel += 25;
              updateFullscreenZoom(container);
            }
          });

          container.querySelector('.zoom-out')?.addEventListener('click', () => {
            if (state.typingState.zoomLevel > 25) {
              state.typingState.zoomLevel -= 25;
              updateFullscreenZoom(container);
            }
          });

          container.querySelector('.rotate')?.addEventListener('click', () => {
            state.typingState.rotation = (state.typingState.rotation + 90) % 360;
            updateFullscreenRotation(container);
          });

          // PDF navigation
          container.querySelector('.pdf-prev')?.addEventListener('click', () => {
            if (state.pdfViewer && state.pdfViewer.currentPage > 1) {
              state.pdfViewer.currentPage--;
              updatePDFPageCounter(container);
              renderPDFPage(container);
            }
          });

          container.querySelector('.pdf-next')?.addEventListener('click', () => {
            if (state.pdfViewer && state.pdfViewer.currentPage < state.pdfViewer.totalPages) {
              state.pdfViewer.currentPage++;
              updatePDFPageCounter(container);
              renderPDFPage(container);
            }
          });
        }

        function updateFullscreenZoom(container) {
          const span = container.querySelector('.zoom-level');
          const img = container.querySelector('.document-image');
          const canvas = container.querySelector('#pdf-viewer-canvas');

          if (span) span.textContent = `${state.typingState.zoomLevel}%`;

          if (img) {
            img.style.transform = `scale(${state.typingState.zoomLevel / 100}) rotate(${state.typingState.rotation}deg)`;
          }

          if (canvas && state.pdfViewer && state.pdfViewer.pdfDoc) {
            renderPDFPage(container);
          }
        }

        function updateFullscreenRotation(container) {
          const img = container.querySelector('.document-image');
          const canvas = container.querySelector('#pdf-viewer-canvas');

          if (img) {
            img.style.transform = `scale(${state.typingState.zoomLevel / 100}) rotate(${state.typingState.rotation}deg)`;
          }

          if (canvas && state.pdfViewer && state.pdfViewer.pdfDoc) {
            renderPDFPage(container);
          }
        }

        // UI update functions
        function updateUI(options = {}) {
          // Partial UI updates for better performance
          const partialUpdate = options.partial || false;
          
          if (partialUpdate && options.target === 'fileBrowser') {
            updateFileBrowserOnly();
            return;
          }

          updateStats();
          
          // Only render the active tab to improve performance
          switch(state.activeTab) {
            case 'pending':
              renderPendingFiles();
              break;
            case 'in-progress':
              renderInProgressFiles();
              break;
            case 'completed':
              renderCompletedFilesTable();
              break;
            case 'pagetype-more':
              renderPageTypeMoreFiles();
              break;
            case 'typing':
              renderTypingView();
              break;
          }
        }

        // Helper for partial updates - only updates visual selection/status without full re-render
        function updateFileBrowserOnly() {
          const items = document.querySelectorAll('.file-browser-item');
          if (!items.length) return;
          
          items.forEach(item => {
            const index = parseInt(item.getAttribute('data-file-index'));
            const isCurrentPage = index === state.typingState.selectedPageInFolder;
            const isSelected = state.typingState.selectedPages.has(index);
            const isProcessed = state.typingState.processedPages[index];
            const scanning = state.selectedFileData?.scannings?.[index];
            
            // Only update classes for items that might have changed
            const selectionClass = (state.typingState.isMultiSelectMode && isSelected) ? 'ring-2 ring-purple-500 bg-purple-50' : (isCurrentPage ? 'ring-2 ring-blue-500 shadow-md' : '');
            
            // Update class without blowing away the DOM
            item.className = `file-browser-item flex-shrink-0 cursor-pointer transition-all duration-200 ${selectionClass} hover:shadow-sm`;
            
            const borderContainer = item.querySelector('.relative.h-20.w-20');
            if (borderContainer) {
              borderContainer.className = `relative h-20 w-20 bg-gray-100 rounded-md overflow-hidden border ${isSelected ? 'border-purple-500' : isCurrentPage ? 'border-blue-500' : 'border-gray-200'}`;
            }

            // Update image src if path changed (e.g. after save/move)
            const img = item.querySelector('img');
            if (img && scanning) {
              const currentUrl = img.getAttribute('src');
              const newUrl = getDocumentUrl(scanning);
              if (currentUrl && newUrl && currentUrl !== newUrl) {
                console.log(`Updating image path for page ${index + 1}`);
                img.src = newUrl;
              }
            }
          });
        }

        // Lightweight function to update only the page code preview without re-rendering the entire UI
        function updatePageCodePreview() {
          if (state.activeTab === 'typing' && state.typingState) {
            const codePreviewElement = document.querySelector('.badge.bg-blue-500');
            if (codePreviewElement) {
              const coverCode = getCoverTypeById(state.typingState.coverType)?.code || 'XX';
              const pageTypeCode = getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers);
              const pageSubTypeCode = getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers);
              const serialNo = getCurrentSerialDisplay();
              
              const fullCode = `${coverCode}-${pageTypeCode}${pageSubTypeCode ? '-' + pageSubTypeCode : ''}-${serialNo}`;
              codePreviewElement.textContent = fullCode;
            }
          }
        }

        // Debounced version to prevent excessive updates
        let updatePageCodePreviewTimeout;
        function debouncedUpdatePageCodePreview() {
          clearTimeout(updatePageCodePreviewTimeout);
          updatePageCodePreviewTimeout = setTimeout(updatePageCodePreview, 50);
        }

        function updateStats() {
          // Update tabs
          elements.tabs.forEach(tab => {
            const tabId = tab.getAttribute('data-tab');
            tab.setAttribute('aria-selected', tabId === state.activeTab);
          });
          
          elements.tabContents.forEach(content => {
            const contentId = content.getAttribute('data-tab-content');
            content.setAttribute('aria-hidden', contentId !== state.activeTab);
          });

          // Update typing tab state
          elements.typingTab.setAttribute('aria-disabled', state.selectedFile ? 'false' : 'true');
        }

        // Start page typing for a file
        async function startPageTyping(fileId, options = {}) {
          try {
            // Reset PDF state for new file
            pdfDoc = null;
            pdfPages = [];
            
            state.pageTypeMoreMode = !!options.pageTypeMore;
            const isEditMode = !!options.editMode;

            // Load page typing data first
            await loadPageTypingData();
            
            // Load file details
            const response = await fetch(`{{ route("pagetyping.api.file-details") }}?file_indexing_id=${fileId}`);
            const data = await response.json();
            
            if (data.success) {
              state.selectedFile = fileId;
              state.selectedFileData = data.file;
              state.activeTab = 'typing';

              // Use server-calculated serial number directly from the API response
              @php
                // Global serial number calculation - increment across all files
                // Get all serial numbers and extract numeric parts to find the true maximum
                $serialNumbers = \App\Models\PageTyping::on('sqlsrv')->pluck('serial_number')->toArray();
                $numericSerials = [];
                foreach ($serialNumbers as $serial) {
                    if (preg_match('/^(\d+)/', (string)$serial, $matches)) {
                        $numericSerials[] = (int)$matches[1];
                    }
                }
                $maxSerial = !empty($numericSerials) ? max($numericSerials) : 0;
                $nextSerial = $maxSerial + 1;
                @endphp

              // Initialize typing state
              state.typingState = {
                currentPage: 1,
                typedContent: '',
                typingProgress: 0,
                zoomLevel: 100,
                rotation: 0,
                showFolderView: true,
                selectedPageInFolder: null, // Don't auto-select, show grid instead
                cursorIndex: null,
                lastProcessedIndex: null,
                // Multi-select support for Quick File Browser
                selectedPages: new Set(),
                isMultiSelectMode: false,
                coverType: '',
                pageType: (pageTypes[0]?.id || '1').toString(),
                pageTypeOthers: '', // For custom "Others" input when page type is "others"
                pageSubType: '1',
                pageSubTypeOthers: '', // For custom "Others" input when page subtype is "others"
                serialNo: '01', // Will be calculated properly below
                quickBrowserOrder: [],
                quickBrowserOrderLocked: false,
                isExistingFile: {{ isset($selectedFileIndexing) && $selectedFileIndexing->pagetypings->count() > 0 ? 'true' : 'false' }},
                processedPages: {},
                folderOrderSaving: false,
                bookletMode: false,
                currentBooklet: null,
                bookletStartPage: null,
                bookletPages: {},
                bookletCounter: 'a',
                bcfcMode: false,
                bcfcCounter: 'a',
                bcfcCardEnabled: false,
                bcfcId: null,
                bcfcPages: [],
                registry: data.file.registry || '',
                editMode: isEditMode
              };

              // Set initial page subtype based on page type
              if (pageSubTypes[parseInt(state.typingState.pageType)]) {
                state.typingState.pageSubType = pageSubTypes[parseInt(state.typingState.pageType)][0]?.id.toString() || '1';
              }

              // Default cover type - prefer Front Cover (FC), otherwise first available
              const defaultCoverId = getFrontCoverId() || coverTypes[0]?.id || '';
              if (!state.typingState.coverType && defaultCoverId) {
                state.typingState.coverType = defaultCoverId.toString();
              }

              // Pre-mark processed pages from backend (existing page typings)
              try {
                const scannings = state.selectedFileData.scannings || [];
                scannings.forEach((scan, idx) => {
                  const pts = (scan.page_typings || []);
                  if (Array.isArray(pts) && pts.length > 0) {
                    // Use the first typing for code display
                    const first = pts[0];
                    state.typingState.processedPages[idx] = {
                      pageTypingId: first.id?.toString() || null,
                      coverType: first.cover_type_id?.toString() || null,
                      pageType: first.page_type?.toString() || null,
                      pageTypeOthers: first.page_type_others || null, // For custom page types
                      pageSubType: first.page_subtype?.toString() || null,
                      pageSubTypeOthers: first.page_subtype_others || null, // For custom page subtypes
                      serialNo: serialFromParts(first.serial_number ?? '', first.serial_suffix ?? ''),
                      bookletId: first.booklet_id?.toString() || null,
                      isBookletPage: !!first.is_booklet_page,
                      bcfcId: first.bcfc_id?.toString() || null,
                      isBcfcPage: !!first.is_bcfc_page,
                      definition: (first.definition ?? 0).toString(),
                      page_code: first.page_code || null,
                      definition_code: first.definition_code || null
                    };
                  }
                });
              } catch (e) {
                console.warn('Could not initialize processed pages from backend', e);
              }

              const processedKeys = Object.keys(state.typingState.processedPages || {})
                .map(key => parseInt(key, 10))
                .filter(idx => !Number.isNaN(idx));
              if (processedKeys.length) {
                const lastIndex = Math.max(...processedKeys);
                state.typingState.lastProcessedIndex = lastIndex;
                state.typingState.cursorIndex = lastIndex;
              }

              // In edit mode, populate the form from the currently selected page's existing data
              if (state.typingState.editMode) {
                const currentPageIdx = state.typingState.selectedPageInFolder;
                const currentPageData = currentPageIdx !== null ? state.typingState.processedPages[currentPageIdx] : null;
                if (currentPageData) {
                  state.typingState.coverType = currentPageData.coverType || '';
                  state.typingState.pageType = currentPageData.pageType || (pageTypes[0]?.id || '1').toString();
                  state.typingState.pageTypeOthers = currentPageData.pageTypeOthers || '';
                  state.typingState.pageSubType = currentPageData.pageSubType || '1';
                  state.typingState.pageSubTypeOthers = currentPageData.pageSubTypeOthers || '';
                  state.typingState.serialNo = currentPageData.serialNo || calculateNextSerialNumber();
                }
              }
 
              // Calculate the proper serial number based on the new rules
              state.typingState.serialNo = calculateNextSerialNumber();
              
              updateUI();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error Loading File',
                text: 'Error loading file details: ' + data.message,
                confirmButtonColor: '#dc3545'
              });
            }
          } catch (error) {
            console.error('Error starting page typing:', error);
            Swal.fire({
              icon: 'error',
              title: 'Error Loading File',
              text: 'Error loading file details',
              confirmButtonColor: '#dc3545'
            });
          }
        }

        // Load and render pending files
        async function renderPendingFiles(page = 1) {
          if (!elements.pendingFilesList) return;
          const search = state.pagination.pending.search;
          const registry = state.pagination.pending.registry;
          try {
            const response = await fetch(`{{ route("pagetyping.api.files") }}?status=pending&page=${page}&limit=${state.pagination.pending.perPage}&search=${encodeURIComponent(search)}&registry=${encodeURIComponent(registry)}`);
            const data = await response.json();
            
            if (data.success) {
              // Update pagination state
              state.pagination.pending = {
                currentPage: data.pagination.current_page,
                total: data.pagination.total,
                lastPage: data.pagination.last_page,
                perPage: data.pagination.per_page,
                search: search,
                registry: registry
              };

              if (data.files.length > 0) {
                elements.pendingFilesList.innerHTML = data.files.map(file => `
                  <div class="p-4 border-b last:border-b-0 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                      <div class="flex-1">
                        <div class="flex items-center gap-3">
                          <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                              <i data-lucide="file-text" class="h-5 w-5 text-yellow-600"></i>
                            </div>
                          </div>
                          <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">${file.file_number}</p>
                            <p class="text-sm text-gray-500 truncate">${file.file_title}</p>
                            <div class="flex items-center gap-4 mt-1">
                              <span class="text-xs text-gray-400">${file.scannings_count} pages scanned</span>
                              <span class="text-xs text-gray-400">${file.created_at}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="badge bg-yellow-500 text-white">Pending</span>
                        <button class="btn btn-primary btn-sm start-typing" data-id="${file.id}">
                          <i data-lucide="type" class="h-4 w-4 mr-1"></i>
                          Start Typing
                        </button>
                        ${fileActionsMenu(file)}
                      </div>
                    </div>
                  </div>
                `).join('');
                
              } else {
                elements.pendingFilesList.innerHTML = `
                  <div class="rounded-md border p-8 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                      <i data-lucide="file-text" class="h-6 w-6"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-medium">No pending files</h3>
                    <p class="mb-4 text-sm text-muted-foreground">All files have been processed</p>
                  </div>
                `;
              }

              // Update pagination UI
              updatePaginationUI('pending');
            } else {
              elements.pendingFilesList.innerHTML = `
                <div class="rounded-md border p-8 text-center">
                  <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                    <i data-lucide="alert-circle" class="h-6 w-6 text-red-600"></i>
                  </div>
                  <h3 class="mb-2 text-lg font-medium">Error loading files</h3>
                  <p class="mb-4 text-sm text-muted-foreground">${data.message || 'Please try refreshing the page'}</p>
                </div>
              `;
            }
            lucide.createIcons();
          } catch (error) {
            console.error('Error loading pending files:', error);
            elements.pendingFilesList.innerHTML = `
              <div class="rounded-md border p-8 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                  <i data-lucide="alert-circle" class="h-6 w-6 text-red-600"></i>
                </div>
                <h3 class="mb-2 text-lg font-medium">Error loading files</h3>
                <p class="mb-4 text-sm text-muted-foreground">Please try refreshing the page</p>
              </div>
            `;
            lucide.createIcons();
          }
        }

        // Load and render in-progress files
        async function renderInProgressFiles(page = 1) {
          if (!elements.inProgressFilesList) return;
          const search = state.pagination.inProgress.search;
          const registry = state.pagination.inProgress.registry;
          try {
            const response = await fetch(`{{ route("pagetyping.api.files") }}?status=in_progress&page=${page}&limit=${state.pagination.inProgress.perPage}&search=${encodeURIComponent(search)}&registry=${encodeURIComponent(registry)}`);
            const data = await response.json();
            
            if (data.success) {
              // Update pagination state
              state.pagination.inProgress = {
                currentPage: data.pagination.current_page,
                total: data.pagination.total,
                lastPage: data.pagination.last_page,
                perPage: data.pagination.per_page,
                search: search,
                registry: registry
              };

              if (data.files.length > 0) {
                elements.inProgressFilesList.innerHTML = data.files.map(file => `
                  <div class="p-4 border-b last:border-b-0 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                      <div class="flex-1">
                        <div class="flex items-center gap-3">
                          <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                              <i data-lucide="clock" class="h-5 w-5 text-orange-600"></i>
                            </div>
                          </div>
                          <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">${file.file_number}</p>
                            <p class="text-sm text-gray-500 truncate">${file.file_title}</p>
                            <div class="flex items-center gap-4 mt-1">
                              <span class="text-xs text-gray-400">${file.page_typings_count}/${file.scannings_count} pages typed</span>
                              <span class="text-xs text-blue-500"><i data-lucide="user" class="h-3 w-3 inline mr-0.5"></i>${file.typed_by_name || 'Unknown'}</span>
                              <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-orange-500 h-2 rounded-full" style="width: ${file.progress}%"></div>
                              </div>
                              <span class="text-xs text-gray-400">${file.progress}%</span>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="badge bg-orange-500 text-white">In Progress</span>
                        <button class="btn btn-primary btn-sm continue-typing" data-id="${file.id}" ${file.typed_by_id && CURRENT_USER_ID && parseInt(file.typed_by_id) !== parseInt(CURRENT_USER_ID) ? 'disabled title="Only the user who started typing can continue"' : ''}>
                          <i data-lucide="edit" class="h-4 w-4 mr-1"></i>
                          Continue
                        </button>
                        ${fileActionsMenu(file)}
                      </div>
                    </div>
                  </div>
                `).join('');
                
              } else {
                elements.inProgressFilesList.innerHTML = `
                  <div class="rounded-md border p-8 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                      <i data-lucide="clock" class="h-6 w-6"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-medium">No files in progress</h3>
                    <p class="mb-4 text-sm text-muted-foreground">Start typing a file to see it here</p>
                  </div>
                `;
              }

              // Update pagination UI
              updatePaginationUI('inProgress');
            } else {
              elements.inProgressFilesList.innerHTML = `
                <div class="rounded-md border p-8 text-center">
                  <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                    <i data-lucide="alert-circle" class="h-6 w-6 text-red-600"></i>
                  </div>
                  <h3 class="mb-2 text-lg font-medium">Error loading files</h3>
                  <p class="mb-4 text-sm text-muted-foreground">${data.message || 'Please try refreshing the page'}</p>
                </div>
              `;
            }
            lucide.createIcons();
          } catch (error) {
            console.error('Error loading in-progress files:', error);
            elements.inProgressFilesList.innerHTML = `
              <div class="rounded-md border p-8 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                  <i data-lucide="alert-circle" class="h-6 w-6 text-red-600"></i>
                </div>
                <h3 class="mb-2 text-lg font-medium">Error loading files</h3>
                <p class="mb-4 text-sm text-muted-foreground">Please try refreshing the page</p>
              </div>
            `;
            lucide.createIcons();
          }
        }

        // Load and render completed files
        async function renderCompletedFilesTable(page = 1) {
          if (!elements.completedFilesTableBody) return;
          const search = state.pagination.completed.search;
          const registry = state.pagination.completed.registry;
          try {
            const response = await fetch(`{{ route("pagetyping.api.files") }}?status=completed&page=${page}&limit=${state.pagination.completed.perPage}&search=${encodeURIComponent(search)}&registry=${encodeURIComponent(registry)}`);
            const data = await response.json();
            
            if (data.success) {
              // Update pagination state
              state.pagination.completed = {
                currentPage: data.pagination.current_page,
                total: data.pagination.total,
                lastPage: data.pagination.last_page,
                perPage: data.pagination.per_page,
                search: search,
                registry: registry
              };

              if (data.files.length > 0) {
                elements.completedFilesTableBody.innerHTML = data.files.map(file => `
                  <tr class="hover:bg-gray-50">
                    <td class="p-3">
                      <span class="text-blue-600 font-medium">${file.file_number}</span>
                    </td>
                    <td class="p-3">
                      <div class="font-medium">${file.file_title}</div>
                      ${file.district ? `<div class="text-xs text-gray-500">${file.district}, ${file.lga || ''}</div>` : ''}
                    </td>
                    <td class="p-3 text-sm text-gray-500">${file.updated_at}</td>
                    <td class="p-3 text-sm text-gray-500">
                      ${file.typed_by_name || 'Unknown'}
                    </td>
                    <td class="p-3">
                      <span class="badge bg-green-500 text-white">
                        <i data-lucide="check-circle" class="h-3 w-3 mr-1"></i>
                        Completed
                      </span>
                    </td>
                    <td class="p-3">
                      <span class="badge badge-secondary">${file.page_typings_count} pages</span>
                    </td>
                    <td class="p-3">
                      <div class="flex items-center justify-end gap-2">
                        ${fileActionsMenu(file, [
                          { action: 'view-file', icon: 'eye', label: 'View File' },
                          { action: 'edit-file', icon: 'edit', label: 'Edit' },
                        ])}
                      </div>
                    </td>
                  </tr>
                `).join('');
                
              } else {
                elements.completedFilesTableBody.innerHTML = `
                  <tr>
                    <td colspan="7" class="text-center p-8">
                      <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                        <i data-lucide="check-circle" class="h-6 w-6"></i>
                      </div>
                      <h3 class="mb-2 text-lg font-medium">No completed files</h3>
                      <p class="mb-4 text-sm text-muted-foreground">Complete page typing to see files here</p>
                    </td>
                  </tr>
                `;
              }

              // Update pagination UI
              updatePaginationUI('completed');
            } else {
              elements.completedFilesTableBody.innerHTML = `
                <tr>
                  <td colspan="7" class="text-center p-8">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                      <i data-lucide="alert-circle" class="h-6 w-6 text-red-600"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-medium">Error loading files</h3>
                    <p class="mb-4 text-sm text-muted-foreground">${data.message || 'Please try refreshing the page'}</p>
                  </td>
                </tr>
              `;
            }
            lucide.createIcons();
          } catch (error) {
            console.error('Error loading completed files:', error);
            elements.completedFilesTableBody.innerHTML = `
              <tr>
                <td colspan="7" class="text-center p-8">
                  <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                    <i data-lucide="alert-circle" class="h-6 w-6 text-red-600"></i>
                  </div>
                  <h3 class="mb-2 text-lg font-medium">Error loading files</h3>
                  <p class="mb-4 text-sm text-muted-foreground">Please try refreshing the page</p>
                </td>
              </tr>
            `;
            lucide.createIcons();
          }
        }

        // Load and render PageType More files
        async function renderPageTypeMoreFiles(page = 1) {
          if (!elements.pageTypeMoreTableBody) return;
          const search = state.pagination.pageTypeMore.search;
          const registry = state.pagination.pageTypeMore.registry;
          try {
            const response = await fetch(`{{ route("pagetyping.api.pagetype-more-files") }}?page=${page}&limit=${state.pagination.pageTypeMore.perPage}&search=${encodeURIComponent(search)}&registry=${encodeURIComponent(registry)}`);
            const data = await response.json();
            
            console.log('PageType More API Response:', data); // Debug log
            
            if (data.success && data.files && data.files.length > 0) {
              // Update pagination state
              if (data.pagination) {
                state.pagination.pageTypeMore = {
                  currentPage: data.pagination.current_page,
                  total: data.pagination.total,
                  lastPage: data.pagination.last_page,
                  perPage: data.pagination.per_page,
                  search: search,
                  registry: registry
                };
              }

              pageTypeMoreFiles = data.files; // Store the files

              // Optional filtering by search term
              const term = (elements.pageTypeMoreSearch?.value || '').trim().toLowerCase();
              const files = term
                ? data.files.filter(f =>
                    (f.file_number || '').toLowerCase().includes(term) ||
                    (f.file_title || '').toLowerCase().includes(term) ||
                    (f.district || '').toLowerCase().includes(term) ||
                    (f.lga || '').toLowerCase().includes(term)
                  )
                : data.files;
              
              elements.pageTypeMoreTableBody.innerHTML = files.map(file => `
                <tr class="border-b hover:bg-muted/10">
                  <td class="p-3">
                    <span class="text-blue-600 font-medium">${file.file_number}</span>
                  </td>
                  <td class="p-3">
                    <div class="flex items-center gap-2">
                      <i data-lucide="file-plus" class="h-4 w-4 text-orange-500"></i>
                      <span class="font-medium">${file.file_title}</span>
                    </div>
                    ${file.district ? `<div class="text-xs text-gray-500">${file.district}, ${file.lga || ''}</div>` : ''}
                  </td>
                  <td class="p-3">
                    <span class="badge bg-green-500 text-white">${file.existing_pages}</span>
                  </td>
                  <td class="p-3">
                    <span class="badge bg-orange-500 text-white">${file.new_scans}</span>
                  </td>
                  <td class="p-3">
                    <span class="badge badge-secondary">${file.total_pages}</span>
                  </td>
                  <td class="p-3 text-sm text-muted-foreground">${file.last_updated}</td>
                  <td class="p-3">
                    <span class="badge bg-orange-500 text-white">
                      <i data-lucide="alert-circle" class="h-3 w-3 mr-1"></i>
                      ${file.status}
                    </span>
                  </td>
                  <td class="p-3">
                    <div class="flex items-center gap-2">
                      <button class="btn btn-primary btn-sm pagetype-more-action" data-id="${file.id}" title="PageType More">
                        <i data-lucide="edit" class="h-4 w-4 mr-1"></i>
                       More Pages
                      </button>
                      ${fileActionsMenu(file, [
                        { action: 'view-combined', icon: 'eye', label: 'View Combined File' },
                      ])}
                    </div>
                  </td>
                </tr>
              `).join('');
              
            } else {
              elements.pageTypeMoreTableBody.innerHTML = `
                <tr>
                  <td colspan="8" class="text-center p-8">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                      <i data-lucide="file-plus" class="h-6 w-6"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-medium">No files need additional page typing</h3>
                    <p class="mb-4 text-sm text-muted-foreground">Files with new scans (IsUpdated = 1) will appear here</p>
                    ${data.message ? `<p class="text-xs text-gray-400">${data.message}</p>` : ''}
                  </td>
                </tr>
              `;
            }

            // Update pagination UI
            updatePaginationUI('pageTypeMore');
            lucide.createIcons();
          } catch (error) {
            console.error('Error loading PageType More files:', error);
            elements.pageTypeMoreTableBody.innerHTML = `
              <tr>
                <td colspan="8" class="text-center p-8">
                  <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                    <i data-lucide="alert-circle" class="h-6 w-6 text-red-600"></i>
                  </div>
                  <h3 class="mb-2 text-lg font-medium">Error loading PageType More files</h3>
                  <p class="mb-4 text-sm text-muted-foreground">Please try refreshing the page</p>
                </td>
              </tr>
            `;
            lucide.createIcons();
          }
        }

        // PDF splitting functionality
        let pdfDoc = null;
        let pdfPages = [];

        async function checkPDFAccessibility(url) {
          try {
            const response = await fetch(url, { method: 'HEAD' });
            return response.ok;
          } catch (error) {
            console.error('Error checking PDF accessibility:', error);
            return false;
          }
        }

        async function checkExistingThumbnails(fileId) {
          try {
            const response = await fetch(`{{ route("pagetyping.api.thumbnails") }}?file_indexing_id=${fileId}`);
            const data = await response.json();
            return data.success ? data.thumbnails : [];
          } catch (error) {
            console.error('Error checking existing thumbnails:', error);
            return [];
          }
        }

        async function savePDFThumbnails(file, pdfPages) {
          try {
            const savePromises = [];
            
            for (let i = 0; i < pdfPages.length; i++) {
              const pageData = pdfPages[i];
              
              // Generate thumbnail image
              const scale = 0.5; // Higher quality for storage
              const viewport = pageData.page.getViewport({ scale: scale });
              
              const canvas = document.createElement('canvas');
              const context = canvas.getContext('2d');
              canvas.height = viewport.height;
              canvas.width = viewport.width;
              
              const renderContext = {
                canvasContext: context,
                viewport: viewport
              };
              
              await pageData.page.render(renderContext).promise;
              
              // Create a promise for saving this thumbnail
              const savePromise = new Promise((resolve, reject) => {
                canvas.toBlob(async (blob) => {
                  if (!blob) {
                    console.error('Failed to create blob for page', pageData.pageNum);
                    resolve(null);
                    return;
                  }
                  
                  // Create filename
                  const filename = `${file.file_number}_page_${pageData.pageNum.toString().padStart(3, '0')}.jpg`;
                  const thumbnailPath = `EDMS/PAGETYPING/thumbnails/${filename}`;
                  
                  // Save to server
                  const formData = new FormData();
                  formData.append('thumbnail', blob, filename);
                  formData.append('file_indexing_id', file.id);
                  formData.append('page_number', pageData.pageNum);
                  formData.append('thumbnail_path', thumbnailPath);
                  formData.append('original_filename', pageData.scanning.original_filename);
                  
                  try {
                    const response = await fetch('{{ route("pagetyping.api.save-thumbnail") }}', {
                      method: 'POST',
                      body: formData,
                      headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                      }
                    });
                    
                    if (response.ok) {
                      const result = await response.json();
                      resolve(result.thumbnail);
                    } else {
                      console.error('Failed to save thumbnail for page', pageData.pageNum, response.status);
                      resolve(null);
                    }
                  } catch (error) {
                    console.error('Error saving thumbnail for page', pageData.pageNum, error);
                    resolve(null);
                  }
                }, 'image/jpeg', 0.8);
              });
              
              savePromises.push(savePromise);
            }
            
            // Wait for all thumbnails to be saved
            const thumbnails = await Promise.all(savePromises);
            const validThumbnails = thumbnails.filter(thumb => thumb !== null);
            
            console.log(`Saved ${validThumbnails.length} thumbnails out of ${pdfPages.length} pages`);
            return validThumbnails;
          } catch (error) {
            console.error('Error saving PDF thumbnails:', error);
            return [];
          }
        }

        async function loadAndSplitPDF(file) {
          try {
            // Find the PDF file in scannings
            const pdfScanning = file.scannings.find(scanning => isPDFFile(scanning.original_filename));
            if (!pdfScanning) {
              console.log('No PDF file found in scannings');
              return false;
            }

            // Check if thumbnails already exist
            const existingThumbnails = await checkExistingThumbnails(file.id);
            if (existingThumbnails.length > 0) {
              console.log('Using existing thumbnails for file:', file.file_number);
              
              // Load existing thumbnails
              pdfPages = existingThumbnails.map((thumb, index) => ({
                pageNum: thumb.page_number,
                thumbnailUrl: `${STORAGE_BASE_URL}/${thumb.thumbnail_path}`,
                scanning: pdfScanning,
                isSplitPage: true,
                isFromCache: true
              }));
              
              return true;
            }

            console.log('No existing thumbnails found, splitting PDF...');
            const pdfUrl = getDocumentUrl(pdfScanning);
            console.log('Attempting to load PDF from URL:', pdfUrl);
            console.log('Original document path:', pdfScanning.document_path);

            // Check if the file exists
            const isAccessible = await checkPDFAccessibility(pdfUrl);
            if (!isAccessible) {
              console.error('PDF file not accessible at URL:', pdfUrl);
              return false;
            }
            console.log('PDF file is accessible');

            // Load PDF
            const loadingTask = pdfjsLib.getDocument({
              url: pdfUrl
            });

            pdfDoc = await loadingTask.promise;
            console.log('PDF loaded successfully. Pages:', pdfDoc.numPages);

            // Generate page data
            pdfPages = [];
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
              const page = await pdfDoc.getPage(pageNum);
              pdfPages.push({
                pageNum: pageNum,
                page: page,
                scanning: pdfScanning,
                isSplitPage: true
              });
            }

            // Save thumbnails for future use
            await savePDFThumbnails(file, pdfPages);
            console.log('Thumbnails saved successfully');

            return true;
          } catch (error) {
            console.error('Error loading PDF:', error);
            console.error('Error details:', {
              message: error.message,
              name: error.name,
              stack: error.stack
            });
            return false;
          }
        }

        async function generatePDFPageCards(file) {
          if (!pdfDoc || pdfPages.length === 0) return '';
          
          let cardsHtml = '';
          
          for (let i = 0; i < pdfPages.length; i++) {
            const pageData = pdfPages[i];
            const isProcessed = state.typingState.processedPages[i];
            
            cardsHtml += `
              <div class="border rounded-md overflow-hidden cursor-pointer hover:border-blue-500 transition-colors folder-page ${isProcessed ? 'border-green-500 bg-green-50' : ''}" data-index="${i}" data-pdf-page="${pageData.pageNum}">
                <div class="h-40 bg-muted flex items-center justify-center relative">
                  ${isProcessed ? `<div class=\"absolute top-2 right-2 z-10\"><span class=\"badge bg-green-500 text-white\"><i data-lucide=\"check-circle\" class=\"h-3 w-3 mr-1\"></i>Typed</span></div>` : ''}
                  <div class="pdf-page-thumbnail" data-page-num="${pageData.pageNum}">
                    <div class="loading">Loading...</div>
                  </div>
                </div>
                <div class="p-2 bg-gray-50 border-t">
                  <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Page ${pageData.pageNum}</span>
                    <span class="badge badge-outline text-xs">PDF</span>
                  </div>
                  <div class="mt-1 text-xs text-muted-foreground">${file.file_number}-${pageData.pageNum.toString().padStart(2, '0')}</div>
                  ${isProcessed ? `<div class=\"mt-1\"><span class=\"badge bg-blue-500 text-white text-xs w-full justify-center\">${isProcessed.page_code || 'Processed'}</span></div>` : ''}
                </div>
              </div>`;
          }
          
          return cardsHtml;
        }

        async function generatePDFThumbnails() {
          if (pdfPages.length === 0) return;
          
          for (let i = 0; i < pdfPages.length; i++) {
            const pageData = pdfPages[i];
            const thumbnailContainer = document.querySelector(`.pdf-page-thumbnail[data-page-num="${pageData.pageNum}"]`);
            
            if (thumbnailContainer) {
              try {
                // Check if we have cached thumbnail
                if (pageData.isFromCache && pageData.thumbnailUrl) {
                  const img = document.createElement('img');
                  img.src = pageData.thumbnailUrl;
                  img.alt = `Page ${pageData.pageNum} thumbnail`;
                  img.className = 'max-h-full max-w-full object-contain';
                  img.onerror = () => {
                    thumbnailContainer.innerHTML = `<i class="h-8 w-8 text-gray-400" data-lucide="file-text"></i>`;
                    lucide.createIcons();
                  };
                  
                  thumbnailContainer.innerHTML = '';
                  thumbnailContainer.appendChild(img);
                } else if (pageData.page) {
                  // Generate thumbnail from PDF page
                  const scale = 0.3;
                  const viewport = pageData.page.getViewport({ scale: scale });
                  
                  const canvas = document.createElement('canvas');
                  const context = canvas.getContext('2d');
                  canvas.height = viewport.height;
                  canvas.width = viewport.width;
                  
                  const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                  };
                  
                  await pageData.page.render(renderContext).promise;
                  
                  const img = document.createElement('img');
                  img.src = canvas.toDataURL('image/jpeg', 0.7);
                  img.alt = `Page ${pageData.pageNum} thumbnail`;
                  img.className = 'max-h-full max-w-full object-contain';
                  
                  thumbnailContainer.innerHTML = '';
                  thumbnailContainer.appendChild(img);
                } else {
                  thumbnailContainer.innerHTML = `<i class="h-8 w-8 text-gray-400" data-lucide="file-text"></i>`;
                  lucide.createIcons();
                }
                
              } catch (error) {
                console.error(`Error generating thumbnail for page ${pageData.pageNum}:`, error);
                thumbnailContainer.innerHTML = `<i class="h-8 w-8 text-gray-400" data-lucide="file-text"></i>`;
                lucide.createIcons();
              }
            }
          }
        }

        // Render typing view with full page typing interface including CoverType
        function renderTypingView() {
          if (!elements.typingCard || !state.selectedFileData) return;
          
          if (state.typingState?.bookletMode && !state.typingState.coverType) {
            const fcId = getFrontCoverId() || coverTypes[0]?.id || '';
            if (fcId) state.typingState.coverType = fcId.toString();
          }

          const file = state.selectedFileData;
          
          // Use actual scannings for pages; no placeholders
          // file.scannings already contains document_path and original_filename

          let content = '';

          // Header content
          const headerContent = `
            <div class="p-6 border-b">
              <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                  <h2 class="text-lg font-semibold">
                    <span class="text-blue-600">${file.file_number}</span> - ${file.file_title}
                  </h2>
                  <p class="text-sm text-muted-foreground">
                    ${state.typingState.showFolderView && state.typingState.selectedPageInFolder === null
                      ? "Select a page to type or categorize"
                      : state.typingState.selectedPageInFolder !== null
                        ? `Categorizing Page ${state.typingState.selectedPageInFolder + 1}`
                        : `Typing Page ${state.typingState.currentPage} of ${file.total_pages}`}
                  </p>
                </div>
                <div class="flex items-center gap-2">
                  <button class="btn btn-back btn-sm back-button">
                    <i data-lucide="arrow-left" class="h-4 w-4 mr-1.5"></i>
                    ${state.typingState.selectedPageInFolder !== null ? 'Back to Folder' : (state.pageTypeMoreMode ? 'Back to PageType More' : 'Back to Dashboard')}
                  </button>
                </div>
              </div>
            </div>
          `;

          if (state.typingState.showFolderView) {
            if (state.typingState.selectedPageInFolder !== null) {
              const pendingPagesCount = (file.scannings || []).filter((_, idx) => !state.typingState.processedPages[idx]).length;
              const classifiedPendingCount = Object.values(state.typingState.processedPages).filter(p => p && p.pending === true).length;
              const readyToProcessCount = classifiedPendingCount; // Pages classified but not yet saved
              const hasRemainingPages = pendingPagesCount > 0 || classifiedPendingCount > 0;
              const nextPageCandidate = pendingPagesCount > 0 ? getNextUnprocessedPageIndex(state.typingState.selectedPageInFolder) : null;
              // In edit mode, allow navigating to the next sequential page even if all pages are already processed
              const isEditMode = !!state.typingState.editMode;
              const hasNextSequentialPage = (state.typingState.selectedPageInFolder ?? 0) < (file.scannings || []).length - 1;
              const canGoNext = nextPageCandidate !== null || (isEditMode && hasNextSequentialPage);
              const nextButtonDisabledClass = !canGoNext ? 'opacity-50 cursor-not-allowed' : '';
              const processAllDisabledClass = !hasRemainingPages ? 'opacity-50 cursor-not-allowed' : '';
              // Page categorization view with CoverType
              content = `
                ${headerContent}
                <div class="p-6">
                  <div class="space-y-6">
                    <div class="flex justify-between items-center">
                      <h3 class="text-lg font-medium">Categorize Page ${state.typingState.selectedPageInFolder + 1}</h3>
                      <span class="badge bg-blue-500 text-white">${file.file_number}</span>
                    </div>

                    <!-- Booklet Management Section -->
                    <div class="p-4 bg-purple-50 rounded-lg border border-purple-200">
                      <h4 class="text-sm font-semibold text-purple-900 mb-3">Booklet Management</h4>
                      <div class="flex items-center space-x-4">
                        ${state.typingState.bookletMode ? `
                          <div class="flex items-center space-x-2">
                            <span class="text-sm text-purple-700">
                              <strong>Active Booklet:</strong> Pages ${state.typingState.bookletStartPage}a, ${state.typingState.bookletStartPage}b, ${state.typingState.bookletStartPage}c...
                            </span>
                            <button class="btn btn-outline btn-sm end-booklet">
                              <i data-lucide="x-circle" class="h-3 w-3 mr-1"></i>
                              End Booklet
                            </button>
                          </div>
                        ` : `
                          <button class="btn btn-outline btn-sm start-booklet">
                            <i data-lucide="book-open" class="h-3 w-3 mr-1"></i>
                            Start Booklet (e.g., PoA)
                          </button>
                          <span class="text-xs text-gray-600">
                            Use this when multiple pages belong to the same document (Power of Attorney, etc.)
                          </span>
                        `}
                      </div>
                      ${state.typingState.bookletMode ? `
                        <div class="mt-2 text-xs text-purple-600">
                          Next page will be numbered: <strong>${serialFromParts(state.typingState.bookletStartPage || state.typingState.serialNo || '', state.typingState.bookletCounter || 'a')}</strong>
                        </div>
                      ` : ''}
                    </div>

                    <!-- Enhanced Quick File Browser with Multi-Select -->
                    <div class="border rounded-lg p-4 bg-white shadow-sm">
                      <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                          <h4 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <i data-lucide="images" class="h-4 w-4 text-blue-600"></i>
                            Quick File Browser
                          </h4>
                          <div class="flex items-center gap-2">
                            <button class="btn btn-outline btn-xs toggle-multi-select" title="Toggle Multi-Select Mode">
                              <i data-lucide="check-square" class="h-3 w-3 mr-1"></i>
                              ${state.typingState.isMultiSelectMode ? 'Single Select' : 'Multi-Select'}
                            </button>
                            ${state.typingState.isMultiSelectMode && state.typingState.selectedPages.size > 0 ? `
                              <span class="text-xs text-purple-700 font-semibold">
                                ${state.typingState.selectedPages.size} selected
                              </span>
                              <button class="btn btn-outline btn-xs clear-selection" title="Clear Selection">
                                <i data-lucide="x" class="h-3 w-3"></i>
                              </button>
                            ` : ''}
                          </div>
                        </div>
                        <div class="flex items-center gap-2">
                          <button class="btn btn-outline btn-icon file-browser-prev" title="Previous 5 pages">
                            <i data-lucide="chevron-left" class="h-4 w-4"></i>
                          </button>
                          <span class="text-xs text-gray-600 font-medium" id="file-browser-indicator">1-5 of ${file.scannings.length}</span>
                          <button class="btn btn-outline btn-icon file-browser-next" title="Next 5 pages">
                            <i data-lucide="chevron-right" class="h-4 w-4"></i>
                          </button>
                        </div>
                      </div>
                      
                      <!-- Instruction for multi-select -->
                      ${state.typingState.isMultiSelectMode ? `
                        <div class="mb-3 p-3 bg-blue-50 border border-blue-100 rounded text-xs text-blue-700 flex items-center gap-2">
                          <i data-lucide="info" class="h-3 w-3"></i>
                          <span><strong>Multi-Select:</strong> Click to select/deselect. Ctrl+Click for individual, Shift+Click for ranges.</span>
                        </div>
                      ` : ''}
                      
                      <div class="horizontal-file-browser relative">
                        <div class="file-browser-container overflow-x-auto pb-2" style="scrollbar-width: thin; scroll-behavior: smooth;">
                          <div class="file-browser-strip flex gap-3 transition-transform duration-300" id="file-browser-strip" style="min-width: max-content;">
                            ${getQuickFileBrowserOrder(file.scannings)
                              .map((index, position) => {
                              const scanning = file.scannings[index];
                              const isCurrentPage = index === state.typingState.selectedPageInFolder;
                              const isSelected = state.typingState.selectedPages.has(index);
                              const isProcessed = state.typingState.processedPages[index];
                              const url = getDocumentUrl(scanning);
                              const img = isImageFile(scanning.original_filename);
                              const pdf = isPDFFile(scanning.original_filename);
                              const canvasId = 'file-browser-thumb-' + index;
                              const imgId = 'file-browser-img-' + index;
                              
                              // Multi-select styling
                              let selectionClass = '';
                              if (state.typingState.isMultiSelectMode && isSelected) {
                                selectionClass = 'ring-2 ring-purple-500 bg-purple-50';
                              } else if (isCurrentPage) {
                                selectionClass = 'ring-2 ring-blue-500 shadow-md';
                              }
                              
                              return '<div class="file-browser-item flex-shrink-0 cursor-pointer transition-all duration-200 ' + selectionClass + ' hover:shadow-md" ' +
                                     'data-file-index="' + index + '" ' +
                                  'data-order-position="' + position + '" ' +
                                  'draggable="' + (state.typingState.isMultiSelectMode ? 'false' : 'true') + '" ' +
                                     'style="width: 92px;" ' +
                                     'tabindex="0" ' +
                                     'role="button" ' +
                                  'aria-label="Page ' + (position + 1) + ' - ' + scanning.original_filename + '" ' +
                                  'title="' + (state.typingState.isMultiSelectMode ? 'Click to select/deselect page ' + (position + 1) : 'Click to select page ' + (position + 1)) + '">' +
                                  '<div class="relative h-24 w-24 bg-gray-100 rounded-md overflow-hidden border ' + (isSelected ? 'border-purple-500' : isCurrentPage ? 'border-blue-500' : 'border-gray-200') + '">' +
                                    // In multi-select (and BC+FC) mode every page shows a real
                                    // checkbox, ticked or not. Previously selection only appeared
                                    // as a ring AFTER clicking, so nothing on screen said the
                                    // pages were selectable at all.
                                    (state.typingState.isMultiSelectMode
                                      ? '<div class="absolute top-1 left-1 z-10 pointer-events-none">' +
                                          '<span class="flex h-4 w-4 items-center justify-center rounded-sm border-2 shadow-sm ' +
                                            (isSelected ? 'border-purple-600 bg-purple-600 text-white' : 'border-gray-500 bg-white') + '">' +
                                            (isSelected ? '<i data-lucide="check" class="h-2.5 w-2.5"></i>' : '') +
                                          '</span></div>'
                                      : '') +
                                    (isProcessed ? '<div class="absolute top-1 right-1 z-10"><span class="badge bg-green-500 text-white text-xs px-1 py-0.5"><i data-lucide="check" class="h-2 w-2"></i></span></div>' : '') +
                                    (isCurrentPage && !isSelected && !state.typingState.isMultiSelectMode ? '<div class="absolute top-1 left-1 z-10"><span class="badge bg-blue-500 text-white text-xs px-1 py-0.5"><i data-lucide="eye" class="h-2 w-2"></i></span></div>' : '') +
                                    '<div class="loading absolute inset-0 flex items-center justify-center" id="loading-' + index + '">' +
                                      '<div class="w-4 h-4 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin"></div>' +
                                    '</div>' +
                                    (img && url ? '<img id="' + imgId + '" src="' + url + '" alt="Page ' + (index + 1) + '" class="w-full h-full object-cover" style="display: none;" onload="document.getElementById(\'loading-' + index + '\').style.display=\'none\'; this.style.display=\'block\';" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\'; document.getElementById(\'loading-' + index + '\').style.display=\'none\';" />' +
                                      '<div class="w-full h-full flex flex-col items-center justify-center text-center hidden">' +
                                        '<i data-lucide="file-text" class="h-4 w-4 text-gray-400 mb-1"></i>' +
                                        '<span class="text-xs text-gray-500 truncate px-1">' + scanning.original_filename.substring(0, 8) + '...</span>' +
                                      '</div>'
                                    : pdf && url ? '<canvas id="' + canvasId + '" class="w-full h-full object-cover" style="background: white; display: none;"></canvas>' +
                                      '<div class="w-full h-full flex flex-col items-center justify-center text-center hidden absolute inset-0 bg-gray-100">' +
                                        '<i data-lucide="file-text" class="h-4 w-4 text-gray-400 mb-1"></i>' +
                                        '<span class="text-xs text-gray-500 truncate px-1">' + scanning.original_filename.substring(0, 8) + '...</span>' +
                                      '</div>'
                                    : url ? '<img id="' + imgId + '" src="' + url + '" alt="Page ' + (index + 1) + '" class="w-full h-full object-cover hidden" onerror="this.style.display=\'none\';" />' +
                                      '<canvas id="' + canvasId + '" class="w-full h-full object-cover hidden" style="background: white;"></canvas>' +
                                      '<div class="w-full h-full flex flex-col items-center justify-center text-center fallback-icon">' +
                                        '<i data-lucide="file" class="h-4 w-4 text-gray-400 mb-1"></i>' +
                                        '<span class="text-xs text-gray-500 truncate px-1">' + scanning.original_filename.substring(0, 8) + '...</span>' +
                                      '</div>'
                                    : '<div class="w-full h-full flex flex-col items-center justify-center text-center">' +
                                      '<i data-lucide="file" class="h-4 w-4 text-gray-400 mb-1"></i>' +
                                      '<span class="text-xs text-gray-500 truncate px-1">' + scanning.original_filename.substring(0, 8) + '...</span>' +
                                    '</div>') +
                                  '</div>' +
                                  '<div class="mt-1 text-center">' +
                                    '<span class="text-xs text-gray-600 font-medium">' + (position + 1) + '</span>' +
                                    (isProcessed ? '<div class="text-xs text-green-600 font-medium truncate">' + (isProcessed.definition_code || isProcessed.page_code || 'Typed') + '</div>' : '') +
                                  '</div>' +
                                '</div>';
                            }).join('')}
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Back Cover + File Cover Management Section -->
                    <div class="p-4 rounded-lg border transition-all duration-200 ${state.typingState.bcfcCardEnabled ? 'bg-orange-50 border-orange-200' : 'bg-gray-100 border-gray-300 opacity-60'}">
                      <h4 class="text-sm font-semibold mb-3 ${state.typingState.bcfcCardEnabled ? 'text-orange-900' : 'text-gray-500'}">Back Cover + File Cover Management</h4>
                      <div class="flex items-center space-x-4">
                        ${state.typingState.bcfcMode ? `
                          <div class="flex items-center space-x-2">
                            <span class="text-sm ${state.typingState.bcfcCardEnabled ? 'text-orange-700' : 'text-gray-500'}">
                              <strong>Active BC+FC Mode:</strong> Pages 0a, 0b, 0c, ...0N
                            </span>
                            <button class="btn btn-outline btn-sm end-bc-fc" ${!state.typingState.bcfcCardEnabled ? 'disabled' : ''}>
                              <i data-lucide="x-circle" class="h-3 w-3 mr-1"></i>
                              End BC+FC Mode
                            </button>
                          </div>
                        ` : `
                          <button class="btn btn-outline btn-sm start-bc-fc ${!state.typingState.bcfcCardEnabled ? 'opacity-50 cursor-not-allowed' : ''}" ${!state.typingState.bcfcCardEnabled ? 'disabled' : ''}>
                            <i data-lucide="file-type" class="h-3 w-3 mr-1"></i>
                            Start BC+FC Mode
                          </button>
                          <span class="text-xs ${state.typingState.bcfcCardEnabled ? 'text-gray-600' : 'text-gray-400'}">
                            ${state.typingState.bcfcCardEnabled ? 'Auto-enables when Back Cover + File Cover is selected.' : 'Select Back Cover + File Cover to enable this feature.'} Serial: 0a, 0b, 0c, ...0N
                          </span>
                        `}
                      </div>
                      ${state.typingState.bcfcMode ? `
                        <div class="mt-2 text-xs ${state.typingState.bcfcCardEnabled ? 'text-orange-600' : 'text-gray-500'}">
                          Next BC+FC page will be numbered: <strong>${serialFromParts(0, state.typingState.bcfcCounter || 'a')}</strong>
                        </div>
                        <div class="mt-2 text-xs ${state.typingState.bcfcCardEnabled ? 'text-blue-600' : 'text-gray-500'} italic">
                          Multi-Select Mode: Click pages to select/deselect. Hold Ctrl+Click for individual selection, or Shift+Click for range selection.
                        </div>
                      ` : ''}
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <div class="relative" id="preview-card-wrapper">
                          <div class="border rounded-md p-4 h-[400px] bg-white relative" id="document-preview-container">
                            <!-- Full screen button -->
                            <button class="absolute top-2 right-2 z-10 btn btn-ghost btn-icon fullscreen-btn" title="Full Screen View">
                              <i data-lucide="maximize" class="h-4 w-4"></i>
                            </button>
                            <!-- Document preview rendered dynamically -->
                          </div>
                          <!-- Card navigation arrows -->
                          <button id="preview-prev" onclick="navigatePreviewPage(-1)" class="absolute left-[-16px] top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-gray-800 hover:bg-gray-900 text-white flex items-center justify-center shadow-lg transition-all" title="Previous Page">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                          </button>
                          <button id="preview-next" onclick="navigatePreviewPage(1)" class="absolute right-[-16px] top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-gray-800 hover:bg-gray-900 text-white flex items-center justify-center shadow-lg transition-all" title="Next Page">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                          </button>
                        </div>
                      </div>

                      <div class="space-y-6">
                        ${!file.is_existing_file ? `
                  
                        ` : ''}

                        <div class="space-y-4">
                          <div>
                          <div class="grid grid-cols-1 gap-4">
                            <div>
                              <label for="file-registry" class="block text-sm font-medium mb-1.5">Registry</label>
                              <input type="text" id="file-registry" class="input bg-gray-50 opacity-75 cursor-not-allowed" value="${state.typingState.registry || ''}" readonly disabled>
                            </div>

                            <div>
                              <label for="cover-type" class="block text-sm font-medium mb-1.5">Cover Type</label>
                              <select id="cover-type" class="input ${state.typingState.bcfcMode ? 'opacity-50 cursor-not-allowed' : ''}" ${state.typingState.bcfcMode ? 'disabled' : ''}>
                              <option value="" ${!state.typingState.coverType ? 'selected' : ''} disabled>Select Cover Type</option>
                              ${coverTypes.map(type =>
                                `<option value="${type.id}" ${state.typingState.coverType == type.id ? 'selected' : ''}>
                                  ${type.name} (${type.code})
                                </option>`
                              ).join('')}
                            </select>
                            <p class="text-xs text-muted-foreground mt-1">
                              Front Cover: Main documents with pagination | Back Cover: Supporting documents without pagination
                            </p>
                          </div>

                          <div>
                            <label for="page-type" class="block text-sm font-medium mb-1.5">Page Type</label>
                            <select id="page-type" class="input ${state.typingState.bcfcMode ? 'opacity-50 cursor-not-allowed' : ''}" ${state.typingState.bcfcMode ? 'disabled' : ''}>
                              ${pageTypes.map(type =>
                                `<option value="${type.id}" ${state.typingState.pageType == type.id ? 'selected' : ''}>
                                  ${type.name} (${type.code})
                                </option>`
                              ).join('')}
                            </select>
                            
                            <!-- Others input field for Page Type - only show when "Others" is selected -->
                            <div id="page-type-others-container" class="mt-2" style="display: ${isOtherSelection(state.typingState.pageType) ? 'block' : 'none'};">
                              <label for="page-type-others" class="block text-sm font-medium mb-1">Specify Other Page Type</label>
                              <input id="page-type-others" value="${state.typingState.pageTypeOthers || ''}" 
                                     class="input ${state.typingState.bcfcMode ? 'opacity-50 cursor-not-allowed' : ''}" placeholder="Enter custom page type" maxlength="50" ${state.typingState.bcfcMode ? 'disabled' : ''}>
                            </div>
                          </div>

                          <div>
                            <label for="page-subtype" class="block text-sm font-medium mb-1.5">Page Subtype</label>
                            <select id="page-subtype" class="input">
                              ${(pageSubTypes[state.typingState.pageType] || pageSubTypes[parseInt(state.typingState.pageType)] || [])?.map(subtype =>
                                `<option value="${subtype.id}" ${state.typingState.pageSubType == subtype.id ? 'selected' : ''}>
                                  ${subtype.name} (${subtype.code})
                                </option>`
                              ).join('') || '<option value="">Select page type first</option>'}
                            </select>
                            
                            <!-- Others input field for Page Subtype - only show when "Others" is selected -->
                            <div id="page-subtype-others-container" class="mt-2" style="display: ${isOtherSelection(state.typingState.pageSubType) ? 'block' : 'none'};">
                              <label for="page-subtype-others" class="block text-sm font-medium mb-1">Specify Other Subtype</label>
                              <input id="page-subtype-others" value="${state.typingState.pageSubTypeOthers || ''}" 
                                     class="input ${state.typingState.bcfcMode ? 'opacity-50 cursor-not-allowed' : ''}" placeholder="Enter custom subtype" maxlength="50" ${state.typingState.bcfcMode ? 'disabled' : ''}>
                            </div>
                          </div>

                          <div>
                            <label for="serial-no" class="block text-sm font-medium mb-1.5">Serial Number</label>
                            <input id="serial-no" value="${getCurrentSerialDisplay()}" 
                                   class="input bg-gray-100 opacity-75 cursor-not-allowed" maxlength="3" readonly disabled>
                            <p class="text-xs text-muted-foreground mt-1" data-serial-helper>
                              ${state.typingState.bcfcMode
                                ? `BC+FC mode: Serial number is auto-generated as ${getCurrentSerialDisplay()}`
                                : state.typingState.bookletMode 
                                  ? `Booklet mode: Serial number is auto-generated as ${getCurrentSerialDisplay()}`
                                  : `Auto-calculated: ${state.typingState.serialNo}`
                              }
                            </p>
                          </div>

                        </div>

                        <div class="p-4 border rounded-md bg-muted/30">
                          <h4 class="font-medium mb-2">Page Code Preview</h4>
                          <div class="flex items-center gap-2">
                            <span class="badge bg-blue-500 text-white text-base py-1 px-3">
                              ${getCoverTypeById(state.typingState.coverType)?.code || 'XX'}-${getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers)}${getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) ? '-' + getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) : ''}-${getCurrentSerialDisplay()}
                            </span>
                          </div>
                          <div class="flex items-center gap-2 mt-2">
                            <span class="badge bg-emerald-600 text-white text-base py-1 px-3">
                              ${getAutoDefinitionFromPosition(state.typingState.selectedPageInFolder ?? 0, file.scannings)}-${getCoverTypeById(state.typingState.coverType)?.code || 'XX'}-${getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers)}${getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) ? '-' + getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) : ''}-${getCurrentSerialDisplay()}
                            </span>
                          </div>
                          <p class="text-xs text-muted-foreground mt-2">
                            Format: CoverType-PageType-SubType-SerialNo<br>
                            Definition Code: AutoPosition-PageCode (from drag/drop order)<br>
                            ${state.typingState.bcfcMode ? 'BC+FC mode: Pages use alphabetic suffixes (a, b, c...)' : state.typingState.bookletMode ? 'Booklet mode: Pages use alphabetic suffixes (a, b, c...)' : 'This code will be assigned to the page for easy identification and retrieval.'}
                          </p>
                        </div>

                        <div class="flex flex-col gap-1 items-center" style="width: 100%;">
                          <button class="btn btn-sm next-page-button ${nextButtonDisabledClass}" style="background-color: #16a34a; color: white; border-color: #16a34a; padding: 0.25rem 0.75rem; font-size: 0.8rem; width: 50%;" ${!canGoNext || !state.typingState.coverType ? 'disabled' : ''}>
                            <i data-lucide="arrow-right-circle" class="h-3.5 w-3.5 mr-1"></i>
                            Next Page
                            <i data-lucide="chevron-right" class="h-3.5 w-3.5 ml-auto"></i>
                          </button>
                          <button class="btn btn-primary btn-sm process-all-pages" style="padding: 0.25rem 0.75rem; font-size: 0.8rem; width: 50%;" ${(!readyToProcessCount && !pendingPagesCount) || !state.typingState.coverType ? 'disabled' : ''}>
                            <i data-lucide="check-circle" class="h-3.5 w-3.5 mr-1"></i>
                            Process All Page(s)
                            <i data-lucide="send" class="h-3.5 w-3.5 ml-auto"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              `;
            } else {
              // Folder view - Show original files without splitting
              const pagesHtml = file.scannings.map((scanning, index) => {
                const isProcessed = state.typingState.processedPages[index];
                const url = getDocumentUrl(scanning);
                const img = isImageFile(scanning.original_filename);
                const pdf = isPDFFile(scanning.original_filename);
                const canvasId = `pdf-thumb-${index}`;
                const imgId = `img-thumb-${index}`;
                return `
                  <div class="border rounded-md overflow-hidden cursor-pointer hover:border-blue-500 transition-colors folder-page ${isProcessed ? 'border-green-500 bg-green-50' : ''}" data-index="${index}">
                    <div class="h-40 bg-muted flex items-center justify-center relative">
                      ${isProcessed ? `<div class=\"absolute top-2 right-2 z-10\"><span class=\"badge bg-green-500 text-white\"><i data-lucide=\"check-circle\" class=\"h-3 w-3 mr-1\"></i>Typed</span></div>` : ''}
                      ${img && url ? `<img id=\"${imgId}\" src=\"${url}\" alt=\"Page ${index + 1}\" class=\"max-h-full max-w-full object-contain\" onerror=\"this.style.display='none'; this.nextElementSibling.style.display='flex';\" />
                        <div class=\"text-center hidden\"><i data-lucide=\"file-text\" class=\"h-8 w-8 text-gray-400 mb-2\"></i><p class=\"text-xs text-gray-500\">${scanning.original_filename}</p></div>`
                      : pdf && url ? `<canvas id=\"${canvasId}\" class=\"max-h-full max-w-full border\" style=\"background: white;\"></canvas>
                        <div class=\"text-center hidden absolute inset-0 flex flex-col items-center justify-center bg-gray-100\">
                          <i data-lucide=\"file-text\" class=\"h-8 w-8 text-gray-400 mb-2\"></i>
                          <p class=\"text-xs text-gray-500\">${scanning.original_filename}</p>
                        </div>`
                      : url ? `<img id=\"${imgId}\" src=\"${url}\" alt=\"Page ${index + 1}\" class=\"max-h-full max-w-full object-contain hidden\" onerror=\"this.style.display='none';\" />
                        <canvas id=\"${canvasId}\" class=\"max-h-full max-w-full border hidden\" style=\"background: white;\"></canvas>
                        <div class=\"text-center fallback-icon\"><i data-lucide=\"file\" class=\"h-8 w-8 text-gray-400 mb-2\"></i><p class=\"text-xs text-gray-500\">${scanning.original_filename}</p></div>`
                      : `<div class=\"text-center\"><i data-lucide=\"file\" class=\"h-8 w-8 text-gray-400 mb-2\"></i><p class=\"text-xs text-gray-500\">${scanning.original_filename}</p></div>`}
                    </div>
                    <div class="p-2 bg-gray-50 border-t">
                      <div class="flex justify-between items-center">
                        <span class="text-sm font-medium">Page ${index + 1}</span>
                        <span class="badge badge-outline text-xs file-type-badge" data-file-type="${pdf ? 'pdf' : (img ? 'image' : 'file')}" data-canvas-id="${canvasId}">${pdf ? 'PDF' : (img ? 'Image' : 'File')}</span>
                      </div>
                      <div class="mt-1 text-xs text-muted-foreground">${file.file_number}-${(index + 1).toString().padStart(2, '0')}</div>
                      ${isProcessed ? `<div class=\"mt-1\"><span class=\"badge bg-blue-500 text-white text-xs w-full justify-center\">${isProcessed.page_code ? isProcessed.page_code : `${getCoverTypeById(isProcessed.coverType)?.code || ''}-${getPageTypeCode(isProcessed.pageType, isProcessed.pageTypeOthers)}-${getPageSubTypeCode(isProcessed.pageType, isProcessed.pageSubType, isProcessed.pageSubTypeOthers)}-${isProcessed.serialNo || ''}`}</span></div>` : ''}
                    </div>
                  </div>`;
              }).join('');

              const remainingUnprocessed = file.scannings.filter((_, idx) => !state.typingState.processedPages[idx]).length;
              
              content = `
                ${headerContent}
                <div class="p-6">
                  <div class="space-y-6">
                    <!-- File Pages Grid - Always Visible -->
                    <div id="file-pages-section">
                      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                          <h3 class="text-lg font-medium">File Pages</h3>
                          ${state.typingState.bookletMode ? `
                            <p class="text-sm text-purple-600 mt-1">
                              <i data-lucide="book-open" class="h-4 w-4 inline mr-1"></i>
                              Booklet Mode Active - Next: ${state.typingState.bookletStartPage}${state.typingState.bookletCounter}
                            </p>
                          ` : ''}
                        </div>
                        <div class="flex flex-wrap items-center gap-2 justify-end">
                          <span class="badge bg-blue-500 text-white">${file.file_number}</span>
                        </div>
                      </div>

                      <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="folder-pages">
                        ${pagesHtml}
                      </div>
                    </div>
                  </div>
                </div>
              `;
            }
          }

          elements.typingCard.innerHTML = content;
          
          // Set registry value and state if it exists
          if (state.typingState.registry) {
            const registrySelect = document.getElementById('file-registry');
            if (registrySelect) {
              registrySelect.value = state.typingState.registry;
            }
          }

          lucide.createIcons();

          // Generate thumbnails for folder view (PDF and images)
          file.scannings.forEach((scanning, index) => {
            const url = getDocumentUrl(scanning);
            const img = isImageFile(scanning.original_filename);
            const pdf = isPDFFile(scanning.original_filename);
            const canvasId = `pdf-thumb-${index}`;
            const imgId = `img-thumb-${index}`;

            if (url) {
              if (pdf) {
                // Generate PDF thumbnail
                setTimeout(() => generateFolderPDFThumbnail(url, canvasId), 100 * index);
              } else if (img) {
                // Image thumbnail is already handled by img tag
              } else {
                // Check content-type for files without proper extensions
                setTimeout(() => checkContentTypeAndGenerateThumbnail(url, canvasId, imgId, index), 100 * index);
              }
            }
          });

          // Function to check content-type and generate appropriate thumbnail
          async function checkContentTypeAndGenerateThumbnail(url, canvasId, imgId, index) {
            try {
              const response = await fetch(url, { method: 'HEAD' });
              const contentType = response.headers.get('content-type');

              if (contentType && contentType.includes('pdf')) {
                // Generate PDF thumbnail and show canvas
                const canvasElement = document.getElementById(canvasId);
                const fallbackIcon = canvasElement?.parentElement?.querySelector('.fallback-icon');
                if (canvasElement && fallbackIcon) {
                  canvasElement.style.display = 'block';
                  fallbackIcon.style.display = 'none';
                }
                await generateFolderPDFThumbnail(url, canvasId);
              } else if (contentType && contentType.startsWith('image/')) {
                // Show image thumbnail
                const imgElement = document.getElementById(imgId);
                const fallbackIcon = imgElement?.parentElement?.querySelector('.fallback-icon');
                if (imgElement && fallbackIcon) {
                  imgElement.src = url;
                  imgElement.style.display = 'block';
                  fallbackIcon.style.display = 'none';

                  // Update badge for image
                  const badgeSelector = `[data-canvas-id="${canvasId}"]`;
                  const badge = document.querySelector(badgeSelector);
                  if (badge) {
                    badge.textContent = 'Image';
                  }
                }
              }
              // For other content types, keep the fallback icon
            } catch (error) {
              console.error('Error checking content-type for thumbnail:', error);
            }
          }

          // Generate PDF thumbnail for folder view
        async function generateFolderPDFThumbnail(url, canvasId) {
          try {
            const loadingTask = pdfjsLib.getDocument(url);
            const pdf = await loadingTask.promise;
            const page = await pdf.getPage(1); // Always get first page

            const scale = 0.3; // Small scale for thumbnails
            const viewport = page.getViewport({ scale: scale });

            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
              canvasContext: canvas.getContext('2d'),
              viewport: viewport
            };

            await page.render(renderContext).promise;

            // Add page count indicator
            const ctx = canvas.getContext('2d');
            const totalPages = pdf.numPages;

            // Add a small badge showing total pages
            if (totalPages > 1) {
              ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
              ctx.fillRect(canvas.width - 35, canvas.height - 20, 35, 20);

              ctx.fillStyle = 'white';
              ctx.font = '10px Arial';
              ctx.textAlign = 'center';
              ctx.fillText(`${totalPages}p`, canvas.width - 17.5, canvas.height - 7);
            }

            // Update the badge to show page count
            const badgeSelector = `[data-canvas-id="${canvasId}"]`;
            const badge = document.querySelector(badgeSelector);
            if (badge) {
              badge.textContent = `PDF (${totalPages} page${totalPages > 1 ? 's' : ''})`;
            }

            // Store page count for potential future use
            canvas.setAttribute('data-total-pages', totalPages);

          } catch (error) {
            console.error('Error generating PDF thumbnail:', error);
            const canvas = document.getElementById(canvasId);
            if (canvas) {
              const ctx = canvas.getContext('2d');
              canvas.width = 120;
              canvas.height = 160;
              ctx.fillStyle = '#f3f4f6';
              ctx.fillRect(0, 0, canvas.width, canvas.height);
              ctx.fillStyle = '#6b7280';
              ctx.font = '10px Arial';
              ctx.textAlign = 'center';
              ctx.fillText('PDF', canvas.width / 2, canvas.height / 2);
            }
          }
        }

          // Render preview if on a selected page
          if (state.typingState.selectedPageInFolder !== null) {
            const container = document.getElementById('document-preview-container');
            if (container) {
              // Render original scanning
              const scanning = file.scannings[state.typingState.selectedPageInFolder];
              if (scanning) renderDocumentPreview(scanning, container);
            }
            // Update card navigation arrows
            updatePreviewNavigation();
          }
          
          // Add event listeners
          addTypingEventListeners(file);
        }

        // Add event listeners for typing interface
        function addTypingEventListeners(file) {
          // Back button
          document.querySelector('.back-button')?.addEventListener('click', () => {
            if (state.typingState.selectedPageInFolder !== null) {
              state.typingState.selectedPageInFolder = null;

              // Re-enable form fields when going back to folder view
              setTimeout(() => {
                setFormElementsState(true, 'Process Page');
              }, 100);
            } else {
              state.selectedFile = null;
              state.selectedFileData = null;
              state.typingState = null;
              state.activeTab = state.pageTypeMoreMode ? 'pagetype-more' : 'pending';
              state.pageTypeMoreMode = false;
            }
            updateUI();
          });

          // Folder page selection
          document.querySelectorAll('.folder-page').forEach(page => {
            page.addEventListener('click', (e) => {
              const index = parseInt(page.getAttribute('data-index'));
              
              // Handle multi-select mode
              if (state.typingState.isMultiSelectMode) {
                e.preventDefault();
                
                if (e.ctrlKey || e.metaKey) {
                  // Ctrl+Click: Toggle individual selection
                  if (state.typingState.selectedPages.has(index)) {
                    state.typingState.selectedPages.delete(index);
                  } else {
                    state.typingState.selectedPages.add(index);
                  }
                } else if (e.shiftKey && state.typingState.selectedPages.size > 0) {
                  // Shift+Click: Range selection
                  const selectedArray = Array.from(state.typingState.selectedPages);
                  const lastSelected = Math.max(...selectedArray);
                  const start = Math.min(lastSelected, index);
                  const end = Math.max(lastSelected, index);
                  
                  for (let i = start; i <= end; i++) {
                    state.typingState.selectedPages.add(i);
                  }
                } else {
                  // Normal click: Clear selection and select only this item
                  state.typingState.selectedPages.clear();
                  state.typingState.selectedPages.add(index);
                }
                
                updateUI();
                return;
              }
              
              // Single select mode (original behavior)
              state.typingState.selectedPageInFolder = index;
              state.typingState.cursorIndex = index;

              // Check if this page is already processed
              const existingData = state.typingState.processedPages[index];
              if (existingData) {
                // Populate form fields with existing data
                state.typingState.coverType = existingData.coverType || '';
                state.typingState.pageType = existingData.pageType || (pageTypes[0]?.id || '1').toString();
                state.typingState.pageSubType = existingData.pageSubType || '1';
                state.typingState.pageTypeOthers = existingData.pageTypeOthers || '';
                state.typingState.pageSubTypeOthers = existingData.pageSubTypeOthers || '';
                state.typingState.serialNo = existingData.serialNo || calculateNextSerialNumber();
                if (state.typingState.bookletMode) {
                  const fc = coverTypes.find(ct => (ct.code || '').toUpperCase() === 'FC');
                  if (fc) state.typingState.coverType = (fc.id || '').toString();
                }
                syncBookletSerialForSelection();
                // Disable form fields for completed pages
                setTimeout(() => {
                  if (state.typingState.editMode) {
                    setFormElementsState(true, 'Update Page');
                  } else {
                    setFormElementsState(false, 'Already Processed');
                  }
                }, 100);
              } else {
                state.typingState.serialNo = calculateNextSerialNumber();
                if (state.typingState.bookletMode) {
                  const fc = coverTypes.find(ct => (ct.code || '').toUpperCase() === 'FC');
                  if (fc) state.typingState.coverType = (fc.id || '').toString();
                }
                syncBookletSerialForSelection();
                
                setTimeout(() => {
                  setFormElementsState(true);
                }, 100);
              }

              refreshSerialDisplay();

              if (!state.typingState.isMultiSelectMode) {
                updateUI();
              }
            });
          });

          // Helper for partial updates
          function updateFileBrowserOnly() {
            const items = document.querySelectorAll('.file-browser-item');
            items.forEach(item => {
              const index = parseInt(item.getAttribute('data-file-index'));
              const isCurrentPage = index === state.typingState.selectedPageInFolder;
              const isSelected = state.typingState.selectedPages.has(index);
              const isProcessed = state.typingState.processedPages[index];
              const scanning = file.scannings[index];
              
              // Only update if state actually changed for this item
              const selectionClass = (state.typingState.isMultiSelectMode && isSelected) ? 'ring-2 ring-purple-500 bg-purple-50' : (isCurrentPage ? 'ring-2 ring-blue-500 shadow-md' : '');
              
              // Use classList for efficient updates without re-rendering innerHTML
              item.className = `file-browser-item flex-shrink-0 cursor-pointer transition-all duration-200 ${selectionClass} hover:shadow-sm`;
              
              const borderContainer = item.querySelector('.relative.h-20.w-20');
              if (borderContainer) {
                borderContainer.className = `relative h-20 w-20 bg-gray-100 rounded-md overflow-hidden border ${isSelected ? 'border-purple-500' : isCurrentPage ? 'border-blue-500' : 'border-gray-200'}`;
              }

              // Update image src if path changed (e.g. after move)
              const img = item.querySelector('img');
              if (img && scanning) {
                const currentUrl = img.getAttribute('src');
                const newUrl = getDocumentUrl(scanning);
                if (currentUrl !== newUrl) {
                  console.log(`Updating image path for page ${index + 1}`);
                  img.src = newUrl;
                }
              }
            });
          }

          // Horizontal File Browser functionality
          initializeHorizontalFileBrowser(file);

          // File browser item click handlers
          document.querySelectorAll('.file-browser-item').forEach(item => {
            item.addEventListener('click', (e) => {
              const index = parseInt(item.getAttribute('data-file-index'));
              
              // Handle multi-select mode
              if (state.typingState.isMultiSelectMode) {
                e.preventDefault();
                
                if (e.ctrlKey || e.metaKey) {
                  // Ctrl+Click: Toggle individual selection
                  if (state.typingState.selectedPages.has(index)) {
                    state.typingState.selectedPages.delete(index);
                  } else {
                    state.typingState.selectedPages.add(index);
                  }
                } else if (e.shiftKey && state.typingState.selectedPages.size > 0) {
                  // Shift+Click: Range selection
                  const selectedArray = Array.from(state.typingState.selectedPages);
                  const lastSelected = Math.max(...selectedArray);
                  const start = Math.min(lastSelected, index);
                  const end = Math.max(lastSelected, index);
                  
                  for (let i = start; i <= end; i++) {
                    state.typingState.selectedPages.add(i);
                  }
                } else {
                  // Normal click: Clear selection and select only this item
                  state.typingState.selectedPages.clear();
                  state.typingState.selectedPages.add(index);
                }
                
                updateUI({ partial: true, target: 'fileBrowser' });
                return;
              }
              
              // Single select mode (original behavior)
              state.typingState.selectedPageInFolder = index;
              state.typingState.cursorIndex = index;

              // Check if this page is already processed
              const existingData = state.typingState.processedPages[index];
              if (existingData) {
                // Populate form fields with existing data
                state.typingState.coverType = existingData.coverType || '';
                state.typingState.pageType = existingData.pageType || (pageTypes[0]?.id || '1').toString();
                state.typingState.pageSubType = existingData.pageSubType || '1';
                state.typingState.pageTypeOthers = existingData.pageTypeOthers || '';
                state.typingState.pageSubTypeOthers = existingData.pageSubTypeOthers || '';
                state.typingState.serialNo = existingData.serialNo || calculateNextSerialNumber();

                // Disable form fields for completed pages
                setTimeout(() => {
                  if (state.typingState.editMode) {
                    setFormElementsState(true, 'Update Page');
                  } else {
                    setFormElementsState(false, 'Already Processed');
                  }
                }, 100);
              } else {
                state.typingState.serialNo = calculateNextSerialNumber();
                
                setTimeout(() => {
                  setFormElementsState(true);
                }, 100);
              }

              refreshSerialDisplay();

              if (!state.typingState.isMultiSelectMode) {
                updateUI();
              }
            });

            item.addEventListener('dragstart', (e) => {
              if (state.typingState.isMultiSelectMode) {
                e.preventDefault();
                return;
              }

              const sourceIndex = parseInt(item.getAttribute('data-file-index'), 10);
              if (Number.isNaN(sourceIndex)) {
                e.preventDefault();
                return;
              }

              item.classList.add('opacity-60');
              if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(sourceIndex));
              }
            });

            item.addEventListener('dragover', (e) => {
              if (state.typingState.isMultiSelectMode) return;
              e.preventDefault();
              item.classList.add('ring-2', 'ring-amber-400');
            });

            item.addEventListener('dragleave', () => {
              item.classList.remove('ring-2', 'ring-amber-400');
            });

            item.addEventListener('drop', async (e) => {
              if (state.typingState.isMultiSelectMode) return;
              if (state.typingState.folderOrderSaving) return;
              e.preventDefault();

              const sourceIndex = parseInt(e.dataTransfer?.getData('text/plain') || '', 10);
              const targetIndex = parseInt(item.getAttribute('data-file-index'), 10);

              document.querySelectorAll('.file-browser-item').forEach(el => {
                el.classList.remove('ring-amber-400', 'opacity-60');
              });

              if (Number.isNaN(sourceIndex) || Number.isNaN(targetIndex) || sourceIndex === targetIndex) {
                return;
              }

              const order = getQuickFileBrowserOrder(file.scannings).slice();
              const sourcePos = order.indexOf(sourceIndex);
              const targetPos = order.indexOf(targetIndex);

              if (sourcePos < 0 || targetPos < 0) {
                return;
              }

              const [moved] = order.splice(sourcePos, 1);
              order.splice(targetPos, 0, moved);

              state.typingState.quickBrowserOrder = order;
              state.typingState.quickBrowserOrderLocked = true;

              updateUI();

              try {
                state.typingState.folderOrderSaving = true;
                await persistPageTypingFolderOrder(order, file.scannings || []);
              } catch (error) {
                console.error('Failed to persist page typing order:', error);
                if (typeof Swal !== 'undefined') {
                  Swal.fire({
                    icon: 'error',
                    title: 'Save failed',
                    text: error.message || 'Could not persist page order. Please try again.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true
                  });
                }
              } finally {
                state.typingState.folderOrderSaving = false;
              }
            });

            item.addEventListener('dragend', () => {
              document.querySelectorAll('.file-browser-item').forEach(el => {
                el.classList.remove('ring-2', 'ring-amber-400', 'opacity-60');
              });
            });
          });
          
          // Multi-select toggle button
          document.querySelector('.toggle-multi-select')?.addEventListener('click', () => {
            state.typingState.isMultiSelectMode = !state.typingState.isMultiSelectMode;
            
            // Clear selection when switching modes
            state.typingState.selectedPages.clear();
            
            updateUI();
          });
          
          // Clear selection button
          document.querySelector('.clear-selection')?.addEventListener('click', () => {
            state.typingState.selectedPages.clear();
            updateUI();
          });

          // Cover type change
          document.querySelector('#file-registry')?.addEventListener('change', (e) => {
            if (state.typingState) {
              state.typingState.registry = e.target.value;
            }
          });

          document.querySelector('#cover-type')?.addEventListener('change', (e) => {
            state.typingState.coverType = e.target.value;
            
            // Enable/disable buttons based on cover type selection
            const hasCoverType = !!e.target.value;
            const file = state.selectedFileData;
            const isEditMode = !!state.typingState.editMode;
            const hasNextSequentialPage = file && (state.typingState.selectedPageInFolder ?? 0) < (file.scannings || []).length - 1;
            const pendingPagesCount = file ? (file.scannings || []).filter((_, idx) => !state.typingState.processedPages[idx]).length : 0;
            const canGoNext = pendingPagesCount > 0 || (isEditMode && hasNextSequentialPage);

            const nextBtn = document.querySelector('.next-page-button');
            const processBtn = document.querySelector('.process-all-pages');
            if (nextBtn) nextBtn.disabled = !hasCoverType || !canGoNext;
            if (processBtn) processBtn.disabled = !hasCoverType;
            
            // Check for BC+FC auto-enable
            checkAutoEnableBcFc();
            
            updateSerialNumber();
            updateUI();
          });

          // Page type change
          document.querySelector('#page-type')?.addEventListener('change', (e) => {
            const selectedValue = e.target.value;
            state.typingState.pageType = selectedValue;
            const isOtherPageType = isOtherSelection(selectedValue);

            const pageTypeOthersContainer = document.getElementById('page-type-others-container');
            if (pageTypeOthersContainer) {
              pageTypeOthersContainer.style.display = isOtherPageType ? 'block' : 'none';
            }

            if (!isOtherPageType) {
              state.typingState.pageTypeOthers = '';
              const pageTypeOthersInput = document.getElementById('page-type-others');
              if (pageTypeOthersInput) pageTypeOthersInput.value = '';
            }

            const pageSubtypeSelect = document.getElementById('page-subtype');
            const pageSubtypeOthersInput = document.getElementById('page-subtype-others');
            const pageSubtypeOthersContainer = document.getElementById('page-subtype-others-container');

            if (!isOtherPageType) {
              const numericId = parseInt(selectedValue, 10);
              const bucket = pageSubTypes[selectedValue] || (!Number.isNaN(numericId) ? pageSubTypes[numericId] : null) || [];
              const firstSubtype = bucket[0]?.id?.toString() || '';
              const resolvedSubtype = firstSubtype || '1';
              state.typingState.pageSubType = resolvedSubtype;
              if (pageSubtypeSelect) {
                pageSubtypeSelect.value = resolvedSubtype;
              }
              state.typingState.pageSubTypeOthers = '';
              if (pageSubtypeOthersInput) {
                pageSubtypeOthersInput.value = '';
              }
              if (pageSubtypeOthersContainer) {
                pageSubtypeOthersContainer.style.display = 'none';
              }
            } else {
              let otherOptionValue = '';
              if (pageSubtypeSelect) {
                const otherOption = Array.from(pageSubtypeSelect.options).find(opt => isOtherSelection(opt.value));
                if (otherOption) {
                  otherOptionValue = otherOption.value;
                  pageSubtypeSelect.value = otherOption.value;
                } else {
                  pageSubtypeSelect.value = '';
                }
              }
              state.typingState.pageSubType = otherOptionValue || 'others';
              if (pageSubtypeOthersContainer) {
                pageSubtypeOthersContainer.style.display = 'block';
              }
            }

            // Check for BC+FC auto-enable
            checkAutoEnableBcFc();
            
            updateSerialNumber();
            debouncedUpdatePageCodePreview();
            updateUI();
          });

          // Page subtype change
          document.querySelector('#page-subtype')?.addEventListener('change', (e) => {
            const selectedValue = e.target.value;
            state.typingState.pageSubType = selectedValue;

            const isOtherSubType = isOtherSelection(selectedValue);
            const pageSubtypeOthersContainer = document.getElementById('page-subtype-others-container');
            if (pageSubtypeOthersContainer) {
              pageSubtypeOthersContainer.style.display = isOtherSubType ? 'block' : 'none';
            }

            if (!isOtherSubType) {
              state.typingState.pageSubTypeOthers = '';
              const pageSubtypeOthersInput = document.getElementById('page-subtype-others');
              if (pageSubtypeOthersInput) pageSubtypeOthersInput.value = '';
            }

            updateSerialNumber();
            debouncedUpdatePageCodePreview();
            updateUI();
          });

          // Page type "Others" input change
          document.querySelector('#page-type-others')?.addEventListener('input', (e) => {
            state.typingState.pageTypeOthers = e.target.value;
            debouncedUpdatePageCodePreview(); // Use debounced lightweight update
          });

          // Page subtype "Others" input change
          document.querySelector('#page-subtype-others')?.addEventListener('input', (e) => {
            state.typingState.pageSubTypeOthers = e.target.value;
            debouncedUpdatePageCodePreview(); // Use debounced lightweight update
          });

          // Prevent any scrolling on focus for these input fields
          document.querySelector('#page-type-others')?.addEventListener('focus', (e) => {
            e.preventDefault();
            const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
            setTimeout(() => {
              window.scrollTo(0, currentScrollTop);
            }, 0);
          });

          document.querySelector('#page-subtype-others')?.addEventListener('focus', (e) => {
            e.preventDefault();
            const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
            setTimeout(() => {
              window.scrollTo(0, currentScrollTop);
            }, 0);
          });

          // Additional event listeners to prevent scroll jumping
          ['#page-type-others', '#page-subtype-others'].forEach(selector => {
            const element = document.querySelector(selector);
            if (element) {
              // Prevent scroll on keypress
              element.addEventListener('keydown', (e) => {
                const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
                requestAnimationFrame(() => {
                  window.scrollTo(0, currentScrollTop);
                });
              });
              
              // Prevent scroll on paste
              element.addEventListener('paste', (e) => {
                const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
                setTimeout(() => {
                  window.scrollTo(0, currentScrollTop);
                }, 0);
              });
            }
          });

          // Serial number change (keep editable but seeded from backend)
          document.querySelector('#serial-no')?.addEventListener('input', (e) => {
            // In booklet mode, the serial number input is readonly
            if (state.typingState.bookletMode) {
              e.target.value = state.typingState.bookletStartPage + state.typingState.bookletCounter;
              return;
            }
            
            // keep only digits and enforce two digits for normal mode
            const cleaned = (e.target.value || '').replace(/\D/g, '').slice(0, 2);
            e.target.value = cleaned;
            state.typingState.serialNo = cleaned.padStart(2, '0');
            refreshSerialDisplay();
          });

          document.querySelector('.next-page-button')?.addEventListener('click', () => {
            goToNextPage();
          });

          document.querySelector('.process-all-pages')?.addEventListener('click', async () => {
            await handleProcessAllPages();
          });

          async function handleProcessAllPages() {
            if (!state.typingState || !state.selectedFileData?.scannings) {
              return;
            }

            // Align booklet counter before calculating pending serials
            if (state.typingState.bookletMode) {
              syncBookletSerialForSelection();
            }

            // Booklet serial prep
            let bookletPendingCounter = state.typingState.bookletMode
              ? Math.max(0, suffixToIndex(state.typingState.bookletCounter || 'a'))
              : 0;
            const bookletBase = state.typingState.bookletMode
              ? parseSerial(state.typingState.bookletStartPage || state.typingState.serialNo).base || 0
              : null;

            // First, save current page's selections if not yet saved
            const currentIdx = state.typingState.selectedPageInFolder;
            if (currentIdx !== null && currentIdx !== undefined && !state.typingState.processedPages[currentIdx]) {
              const autoDefinition = getAutoDefinitionFromPosition(currentIdx, state.selectedFileData.scannings);
              const coverCode = getCoverTypeById(state.typingState.coverType)?.code || 'XX';
              const ptCode = getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers);
              const pstCode = getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers);
              const pageSerial = state.typingState.bookletMode && bookletBase !== null
                ? serialFromParts(bookletBase, suffixFromIndex(bookletPendingCounter++))
                : getCurrentSerialDisplay();
              const serialParts = parseSerial(pageSerial);
              const pageCode = coverCode + '-' + ptCode + (pstCode ? '-' + pstCode : '') + '-' + pageSerial;
              const definitionCode = autoDefinition + '-' + pageCode;

              state.typingState.processedPages[currentIdx] = {
                coverType: state.typingState.coverType,
                pageType: state.typingState.pageType,
                pageTypeOthers: state.typingState.pageTypeOthers || '',
                pageSubType: state.typingState.pageSubType,
                pageSubTypeOthers: state.typingState.pageSubTypeOthers || '',
                serialNo: pageSerial,
                bookletId: state.typingState.bookletMode ? state.typingState.currentBooklet : null,
                isBookletPage: !!state.typingState.bookletMode,
                serialBase: serialParts.base,
                serialSuffix: serialParts.suffix || '',
                definition: String(autoDefinition ?? '0'),
                page_code: pageCode,
                definition_code: definitionCode,
                pending: true
              };
            }

            const file = state.selectedFileData;

            // Find pages that have been classified (pending=true) but not yet sent to backend
            // Also include unprocessed pages that have NO classification yet
            const pendingPages = file.scannings
              .map((_, idx) => idx)
              .filter(idx => {
                const pd = state.typingState.processedPages[idx];
                return pd && pd.pending === true; // Only send pages that were classified by user
              });

            const unclassifiedPages = file.scannings
              .map((_, idx) => idx)
              .filter(idx => !state.typingState.processedPages[idx]);

            if (!pendingPages.length && !unclassifiedPages.length) {
              Swal.fire({
                icon: 'info',
                title: 'Nothing To Process',
                text: 'All pages in this file are already processed.',
                confirmButtonColor: '#0ea5e9'
              });
              return;
            }

            if (unclassifiedPages.length > 0) {
              const { isConfirmed } = await Swal.fire({
                title: 'Unclassified Pages',
                html: `
                  <div class="text-left">
                    <p class="mb-2"><strong>${unclassifiedPages.length}</strong> page${unclassifiedPages.length === 1 ? ' has' : 's have'} not been classified yet (page${unclassifiedPages.length === 1 ? '' : 's'} ${unclassifiedPages.map(i => i + 1).join(', ')}).</p>
                    <p class="mb-2"><strong>${pendingPages.length}</strong> page${pendingPages.length === 1 ? ' has' : 's have'} been classified and will be processed.</p>
                    <p class="text-sm text-gray-500">Use the Next Page button to classify remaining pages, or proceed to save the classified ones now.</p>
                  </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: pendingPages.length > 0 ? 'Process Classified Pages' : 'OK',
                cancelButtonText: 'Go Back',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280'
              });

              if (!isConfirmed || pendingPages.length === 0) {
                return;
              }
            }

            const pagesToProcess = pendingPages;

            // Normalize serials for pending pages before showing summary
            if (state.typingState.bookletMode && bookletBase !== null) {
              let counter = 0;
              pagesToProcess.forEach(idx => {
                const pd = state.typingState.processedPages[idx];
                if (!pd || !(pd.isBookletPage || pd.bookletId)) return;
                const suffix = suffixFromIndex(counter++);
                const serialDisplay = serialFromParts(bookletBase, suffix);
                const serialParts = parseSerial(serialDisplay);
                const coverCode = getCoverTypeById(pd.coverType)?.code || getCoverTypeById(state.typingState.coverType)?.code || 'XX';
                const ptCode = getPageTypeCode(pd.pageType, pd.pageTypeOthers);
                const pstCode = getPageSubTypeCode(pd.pageType, pd.pageSubType, pd.pageSubTypeOthers);
                const pageCode = `${coverCode}-${ptCode}${pstCode ? '-' + pstCode : ''}-${serialDisplay}`;
                const autoDefinition = getAutoDefinitionFromPosition(idx, file.scannings);
                const definitionCode = `${autoDefinition}-${pageCode}`;
                pd.serialNo = serialDisplay;
                pd.serialBase = serialParts.base;
                pd.serialSuffix = serialParts.suffix || '';
                pd.page_code = pageCode;
                pd.definition = String(autoDefinition ?? '0');
                pd.definition_code = definitionCode;
              });
            } else if (state.typingState.bcfcMode) {
              let counter = 0;
              pagesToProcess.forEach(idx => {
                const pd = state.typingState.processedPages[idx];
                if (!pd || !(pd.isBcfcPage || pd.bcfcId)) return;
                const suffix = suffixFromIndex(counter++);
                const serialDisplay = serialFromParts(0, suffix);
                const serialParts = parseSerial(serialDisplay);
                const coverCode = getCoverTypeById(pd.coverType)?.code || 'XX';
                const ptCode = getPageTypeCode(pd.pageType, pd.pageTypeOthers);
                const pstCode = getPageSubTypeCode(pd.pageType, pd.pageSubType, pd.pageSubTypeOthers);
                const pageCode = `${coverCode}-${ptCode}${pstCode ? '-' + pstCode : ''}-${serialDisplay}`;
                const autoDefinition = getAutoDefinitionFromPosition(idx, file.scannings);
                const definitionCode = `${autoDefinition}-${pageCode}`;
                pd.serialNo = serialDisplay;
                pd.serialBase = serialParts.base;
                pd.serialSuffix = serialParts.suffix || '';
                pd.page_code = pageCode;
                pd.definition = String(autoDefinition ?? '0');
                pd.definition_code = definitionCode;
              });
            }

            // Build summary of per-page classifications
            let summaryHtml = '<div class="text-left"><p class="mb-2">The following pages will be saved:</p><div class="max-h-48 overflow-y-auto">';
            summaryHtml += '<table class="w-full text-sm"><thead><tr class="border-b"><th class="text-left py-1">Page</th><th class="text-left py-1">Cover</th><th class="text-left py-1">Type</th><th class="text-left py-1">Serial</th><th class="text-left py-1">Page Code</th></tr></thead><tbody>';
            pagesToProcess.forEach(idx => {
              const pd = state.typingState.processedPages[idx];
              const coverName = getCoverTypeById(pd.coverType)?.code || getCoverTypeById(state.typingState.coverType)?.code || '?';
              const pageTypeName = getPageTypeCode(pd.pageType, pd.pageTypeOthers) || getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers) || '?';
              summaryHtml += `<tr class="border-b"><td class="py-1">${idx + 1}</td><td class="py-1">${coverName}</td><td class="py-1">${pageTypeName}</td><td class="py-1">${pd.serialNo}</td><td class="py-1">${pd.page_code || ''}</td></tr>`;
            });
            summaryHtml += '</tbody></table></div></div>';

            const { isConfirmed: confirmProcess } = await Swal.fire({
              title: 'Process All Pages?',
              html: summaryHtml,
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: `Yes, Process ${pagesToProcess.length} Page${pagesToProcess.length === 1 ? '' : 's'}`,
              cancelButtonText: 'Cancel',
              confirmButtonColor: '#2563eb',
              cancelButtonColor: '#6b7280'
            });

            if (!confirmProcess) {
              return;
            }

            // Build records array - each page uses its OWN classification
            let processedCount = 0;
            let failedCount = 0;
            const totalPages = pagesToProcess.length;

            // Show progress modal
            Swal.fire({
              title: 'Processing All Pages',
              html: `
                <div class="progress-container">
                  <div class="mb-3">Processing page <span id="current-page">1</span> of ${totalPages}</div>
                  <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                  </div>
                  <div class="mt-2 text-sm text-gray-600"><span id="progress-text">Preparing...</span></div>
                </div>
              `,
              allowOutsideClick: false,
              allowEscapeKey: false,
              showConfirmButton: false
            });

            function updateProgress(current, total, status = '') {
              const progressBar = document.getElementById('progress-bar');
              const currentPageEl = document.getElementById('current-page');
              const progressText = document.getElementById('progress-text');
              const percentage = Math.round((current / total) * 100);
              if (progressBar) progressBar.style.width = percentage + '%';
              if (currentPageEl) currentPageEl.textContent = current + 1;
              if (progressText) progressText.textContent = status || 'Processing page ' + (current + 1) + '...';
            }

            function delay(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }

            try {
              for (let i = 0; i < pagesToProcess.length; i++) {
                const pageIndex = pagesToProcess[i];
                const selected = file.scannings[pageIndex];
                const pd = state.typingState.processedPages[pageIndex]; // Use per-page data!
                const pageSerial = pd.serialNo || calculateNextSerialNumber();
                const serialParts = parseSerial(pageSerial);

                updateProgress(i, totalPages, 'Processing page ' + (i + 1) + ': ' + file.file_number);

                if (!selected || !selected.id) {
                  console.error('Scanning not found for page index:', pageIndex);
                  failedCount++;
                  continue;
                }

                // Use the per-page classification data
                const pageTypeOthersValue = isOtherSelection(pd.pageType) ? pd.pageTypeOthers : null;
                const pageSubTypeOthersValue = isOtherSelection(pd.pageSubType) ? pd.pageSubTypeOthers : null;
                const autoDefinition = getAutoDefinitionFromPosition(pageIndex, file.scannings);
                const coverCode = getCoverTypeById(pd.coverType)?.code || 'XX';
                const ptCode = getPageTypeCode(pd.pageType, pd.pageTypeOthers);
                const pstCode = getPageSubTypeCode(pd.pageType, pd.pageSubType, pd.pageSubTypeOthers);
                const pageCode = coverCode + '-' + ptCode + (pstCode ? '-' + pstCode : '') + '-' + pageSerial;
                const definitionCode = autoDefinition + '-' + pageCode;
                const isBookletPage = !!(pd.isBookletPage || pd.bookletId);
                const isBcfcPage = !!(pd.isBcfcPage || pd.bcfcId);

                const pageData = {
                  file_indexing_id: file.id,
                  scanning_id: selected.id,
                  page_number: pageIndex + 1,
                  cover_type_id: parseInt(pd.coverType || state.typingState.coverType),
                  page_type: pd.pageType || state.typingState.pageType,
                  page_type_other: pageTypeOthersValue,
                  page_type_others: pageTypeOthersValue,
                  page_subtype: pd.pageSubType || state.typingState.pageSubType,
                  page_subtype_other: pageSubTypeOthersValue,
                  page_subtype_others: pageSubTypeOthersValue,
                  serial_number: serialParts.base,
                  serial_suffix: serialParts.suffix || null,
                  page_code: pageCode,
                  definition: autoDefinition,
                  definition_code: definitionCode,
                  display_order: Math.max(0, autoDefinition - 1),
                  file_path: selected?.document_path || 'EDMS/PAGETYPING/' + file.file_number + '/' + file.file_number + '.pdf',
                  registry: state.typingState.registry,
                  booklet_id: isBookletPage ? (pd.bookletId || state.typingState.currentBooklet) : null,
                  is_booklet_page: isBookletPage,
                  booklet_sequence: isBookletPage ? (serialParts.suffix || null) : null,
                  is_bcfc_page: isBcfcPage,
                  bcfc_sequence: isBcfcPage ? (serialParts.suffix || null) : null,
                  bcfc_id: isBcfcPage ? (pd.bcfcId || state.typingState.bcfcId) : null
                };

                console.log('Processing page', pageIndex, 'with serial', pageSerial, ':', pageData);

                try {
                  const response = await fetch('{{ route("pagetyping.save-single") }}', {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(pageData)
                  });

                  const result = await response.json();

                  if (result.success) {
                    // Mark as fully processed (remove pending flag)
                    state.typingState.processedPages[pageIndex] = {
                      ...state.typingState.processedPages[pageIndex],
                      pending: false,
                      coverType: pd.coverType,
                      pageType: pd.pageType,
                      pageTypeOthers: pd.pageTypeOthers,
                      pageSubType: pd.pageSubType,
                      pageSubTypeOthers: pd.pageSubTypeOthers,
                      serialNo: pageSerial,
                      definition: String(pageData.definition ?? '0'),
                      page_code: pageCode,
                      definition_code: definitionCode
                    };
                    processedCount++;
                    updateProgress(i, totalPages, 'Completed page ' + (i + 1));
                  } else {
                    console.error('Failed page:', pageIndex, result.message);
                    failedCount++;
                    updateProgress(i, totalPages, 'Failed page ' + (i + 1) + ': ' + result.message);
                  }
                } catch (error) {
                  console.error('Error processing page:', pageIndex, error);
                  failedCount++;
                  updateProgress(i, totalPages, 'Error on page ' + (i + 1));
                }

                // Small delay between requests
                if (i < pagesToProcess.length - 1) {
                  await delay(50);
                }
              }

              Swal.close();

              if (processedCount > 0) {
                Swal.fire({
                  icon: 'success',
                  title: 'All Pages Processed',
                  html: '<div class="text-left"><p class="mb-2">Successfully processed: <strong>' + processedCount + '</strong> pages</p>' +
                    (failedCount > 0 ? '<p class="mb-2 text-red-600">Failed: <strong>' + failedCount + '</strong> pages</p>' : '') +
                    '</div>',
                  confirmButtonColor: '#28a745'
                });

                state.typingState.lastProcessedIndex = pagesToProcess[pagesToProcess.length - 1];
                state.typingState.cursorIndex = pagesToProcess[pagesToProcess.length - 1];
                state.typingState.selectedPages.clear();
                state.typingState.isMultiSelectMode = false;
                state.typingState.selectedPageInFolder = null;
                updateUI();
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Processing Failed',
                  text: 'Failed to process any pages. ' + failedCount + ' errors occurred.',
                  confirmButtonColor: '#dc3545'
                });
              }
            } catch (error) {
              Swal.close();
              console.error('Critical error in handleProcessAllPages:', error);
              Swal.fire({
                icon: 'error',
                title: 'Critical Error',
                text: 'A critical error occurred during processing. Please try again.',
                confirmButtonColor: '#dc3545'
              });
            }
          }

          // Process multiple pages for booklet mode - Optimized for large batches
          async function processMultiplePages() {
            const selectedPagesArray = Array.from(state.typingState.selectedPages).sort((a, b) => a - b);
            const baseSerialText = state.typingState.bookletStartPage || state.typingState.serialNo;
            const numericSerial = parseSerial(baseSerialText).base || 1;
            const processedBookletCount = Object.values(state.typingState.processedPages || {})
              .filter(p => p && (p.bookletId === state.typingState.currentBooklet || p.isBookletPage))
              .length;
            const startOffset = Math.max(processedBookletCount, suffixToIndex(state.typingState.bookletCounter || 'a'));
            
            let processedCount = 0;
            let failedCount = 0;
            const totalPages = selectedPagesArray.length;
            
            // Show progress modal
            Swal.fire({
              title: 'Processing Booklet Pages',
              html: `
                <div class="progress-container">
                  <div class="mb-3">Processing page <span id="current-page">1</span> of ${totalPages}</div>
                  <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                  </div>
                  <div class="mt-2 text-sm text-gray-600">
                    <span id="progress-text">Preparing...</span>
                  </div>
                </div>
              `,
              allowOutsideClick: false,
              allowEscapeKey: false,
              showConfirmButton: false,
              didOpen: () => {
                Swal.getPopup().querySelector('.swal2-html-container').style.textAlign = 'left';
              }
            });
            
            // Helper function to update progress
            function updateProgress(current, total, status = '') {
              const progressBar = document.getElementById('progress-bar');
              const currentPageEl = document.getElementById('current-page');
              const progressText = document.getElementById('progress-text');
              
              const percentage = Math.round((current / total) * 100);
              
              if (progressBar) progressBar.style.width = `${percentage}%`;
              if (currentPageEl) currentPageEl.textContent = current + 1;
              if (progressText) progressText.textContent = status || `Processing page ${current + 1}...`;
            }
            
            // Helper function to add delay between requests (prevents browser freeze)
            function delay(ms) {
              return new Promise(resolve => setTimeout(resolve, ms));
            }
            
            // Process pages in batches to prevent browser crashes
            // Adjust batch size based on total number of pages
            let BATCH_SIZE, DELAY_BETWEEN_BATCHES, DELAY_BETWEEN_PAGES;
            
            if (totalPages <= 3) {
              BATCH_SIZE = 3;
              DELAY_BETWEEN_BATCHES = 50;
              DELAY_BETWEEN_PAGES = 25;
            } else if (totalPages <= 6) {
              BATCH_SIZE = 2;
              DELAY_BETWEEN_BATCHES = 100;
              DELAY_BETWEEN_PAGES = 50;
            } else {
              BATCH_SIZE = 1; // Process one at a time for very large batches
              DELAY_BETWEEN_BATCHES = 150;
              DELAY_BETWEEN_PAGES = 75;
            }
            
            try {
              for (let batchStart = 0; batchStart < selectedPagesArray.length; batchStart += BATCH_SIZE) {
                const batchEnd = Math.min(batchStart + BATCH_SIZE, selectedPagesArray.length);
                const batch = selectedPagesArray.slice(batchStart, batchEnd);
                
                // Process current batch
                for (let i = 0; i < batch.length; i++) {
                  const pageIndex = batch[i];
                  const overallIndex = startOffset + batchStart + i;
                  const selected = state.selectedFileData.scannings[pageIndex];
                  
                  updateProgress(overallIndex, totalPages, `Processing page ${overallIndex + 1}: ${state.selectedFileData.file_number}`);
                  
                  // Skip if scanning doesn't exist
                  if (!selected || !selected.id) {
                    console.error('Scanning not found for page index:', pageIndex);
                    failedCount++;
                    continue;
                  }
                  
                  // Generate letter suffix: a, b, c, ... z, aa, ab, ...
                  const letterSuffix = suffixFromIndex(overallIndex);
                  const serialWithLetter = serialFromParts(baseSerialText, letterSuffix);
                  
                  const pageTypeOthersValue = isOtherSelection(state.typingState.pageType) ? state.typingState.pageTypeOthers : null;
                  const pageSubTypeOthersValue = isOtherSelection(state.typingState.pageSubType) ? state.typingState.pageSubTypeOthers : null;
                  const autoDefinition = getAutoDefinitionFromPosition(pageIndex, state.selectedFileData.scannings);
                  const autoDefinitionCode = `${autoDefinition}-${getCoverTypeById(state.typingState.coverType)?.code}-${getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers)}${getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) ? '-' + getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) : ''}-${serialWithLetter}`;
                  
                  const pageData = {
                    file_indexing_id: state.selectedFileData.id,
                    scanning_id: selected.id,
                    page_number: pageIndex + 1,
                    cover_type_id: parseInt(state.typingState.coverType),
                    page_type: state.typingState.pageType,
                    page_type_other: pageTypeOthersValue,
                    page_type_others: pageTypeOthersValue,
                    page_subtype: state.typingState.pageSubType,
                    page_subtype_other: pageSubTypeOthersValue,
                    page_subtype_others: pageSubTypeOthersValue,
                    serial_number: numericSerial,
                    serial_suffix: letterSuffix,
                    page_code: `${getCoverTypeById(state.typingState.coverType)?.code}-${getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers)}${getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) ? '-' + getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) : ''}-${serialWithLetter}`,
                    definition: autoDefinition,
                    definition_code: autoDefinitionCode,
                    display_order: Math.max(0, autoDefinition - 1),
                    file_path: selected?.document_path || `EDMS/PAGETYPING/${state.selectedFileData.file_number}/${state.selectedFileData.file_number}.pdf`,
                    registry: state.typingState.registry,
                    booklet_id: state.typingState.currentBooklet,
                    is_booklet_page: true,
                    booklet_sequence: letterSuffix
                  };

                  console.log('Sending pageData for page', pageIndex, ':', pageData);

                  try {
                    const response = await fetch('{{ route("pagetyping.save-single") }}', {
                      method: 'POST',
                      headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                      },
                      body: JSON.stringify(pageData)
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                      // Mark page as processed
                      state.typingState.processedPages[pageIndex] = {
                        coverType: state.typingState.coverType,
                        pageType: state.typingState.pageType,
                        pageTypeOthers: state.typingState.pageTypeOthers,
                        pageSubType: state.typingState.pageSubType,
                        pageSubTypeOthers: state.typingState.pageSubTypeOthers,
                        serialNo: serialWithLetter,
                        bookletId: state.typingState.bookletMode ? state.typingState.currentBooklet : null,
                        isBookletPage: !!state.typingState.bookletMode,
                        definition: String(pageData.definition ?? '0'),
                        page_code: pageData.page_code,
                        definition_code: pageData.definition_code
                      };

                      if (state.typingState.bookletMode && state.typingState.currentBooklet) {
                        if (!state.typingState.bookletPages[state.typingState.currentBooklet]) {
                          state.typingState.bookletPages[state.typingState.currentBooklet] = [];
                        }
                        state.typingState.bookletPages[state.typingState.currentBooklet].push({
                          pageIndex: pageIndex,
                          serialNumber: serialWithLetter,
                          pageCode: pageData.page_code
                        });
                      }
                      
                      processedCount++;
                      updateProgress(overallIndex, totalPages, `✓ Completed page ${overallIndex + 1}`);
                    } else {
                      console.error('Failed to process page:', pageIndex, result.message);
                      if (result.errors) {
                        console.error('Validation errors:', result.errors);
                      }
                      failedCount++;
                      updateProgress(overallIndex, totalPages, `✗ Failed page ${overallIndex + 1}: ${result.message}`);
                    }
                  } catch (error) {
                    console.error('Error processing page:', pageIndex, error);
                    failedCount++;
                    updateProgress(overallIndex, totalPages, `✗ Error on page ${overallIndex + 1}: ${error.message}`);
                  }
                  
                  // Small delay between individual page requests
                  if (i < batch.length - 1) {
                    await delay(DELAY_BETWEEN_PAGES);
                  }
                }
                
                // Delay between batches to prevent overwhelming the browser
                if (batchEnd < selectedPagesArray.length) {
                  await delay(DELAY_BETWEEN_BATCHES);
                }
              }
              
              // Close progress modal
              Swal.close();
              
              // Memory cleanup
              setTimeout(() => {
                if (window.gc) {
                  window.gc(); // Trigger garbage collection if available
                }
              }, 100);
              
              // Show result message
              if (processedCount > 0) {
                Swal.fire({
                  icon: 'success',
                  title: 'Booklet Pages Processed',
                  html: `
                    <div class="text-left">
                      <p class="mb-2">✅ Successfully processed: <strong>${processedCount}</strong> pages</p>
                      ${failedCount > 0 ? `<p class="mb-2 text-red-600">❌ Failed: <strong>${failedCount}</strong> pages</p>` : ''}
                      <p class="text-sm text-gray-600 mt-2">Serial numbers: ${serialFromParts(baseSerialText, suffixFromIndex(startOffset))} through ${serialFromParts(baseSerialText, suffixFromIndex(Math.max(startOffset + processedCount - 1, startOffset)))}</p>
                      ${failedCount > 0 ? '<p class="text-sm text-red-600 mt-2">Check browser console for detailed error information.</p>' : ''}
                    </div>
                  `,
                  confirmButtonColor: '#28a745'
                });

                if (selectedPagesArray.length) {
                  const lastProcessed = selectedPagesArray[selectedPagesArray.length - 1];
                  state.typingState.lastProcessedIndex = lastProcessed;
                  state.typingState.cursorIndex = lastProcessed;
                }

                // Prepare next booklet serial for subsequent pages
                const nextSuffixIndex = startOffset + processedCount;
                state.typingState.bookletCounter = suffixFromIndex(nextSuffixIndex);
                const baseForNext = parseSerial(state.typingState.bookletStartPage || state.typingState.serialNo || baseSerialText).base || numericSerial;
                state.typingState.serialNo = serialFromParts(baseForNext, state.typingState.bookletCounter);
                refreshSerialDisplay();
                debouncedUpdatePageCodePreview();
                
                // Clear selection and update UI
                state.typingState.selectedPages.clear();
                state.typingState.isMultiSelectMode = false;
                state.typingState.selectedPageInFolder = null;
                updateUI();
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Processing Failed',
                  html: `
                    <div class="text-left">
                      <p class="mb-2">Failed to process any pages. <strong>${failedCount}</strong> errors occurred.</p>
                      <p class="text-sm text-gray-600">Suggestions:</p>
                      <ul class="text-sm mt-1 ml-4 list-disc">
                        <li>Try processing fewer pages at a time</li>
                        <li>Check your internet connection</li>
                        <li>Refresh the page and try again</li>
                        <li>Check browser console for detailed errors</li>
                      </ul>
                    </div>
                  `,
                  confirmButtonColor: '#dc3545'
                });
              }
              
            } catch (error) {
              // Close progress modal
              Swal.close();
              
              console.error('Critical error in processMultiplePages:', error);
              Swal.fire({
                icon: 'error',
                title: 'Critical Error',
                text: 'A critical error occurred during processing. Please try again with fewer pages.',
                confirmButtonColor: '#dc3545'
              });
            }
          }

          // Process single page (original logic)
          async function processSinglePage() {

            // Save page typing to backend with CoverType
            const pageIndex = state.typingState.selectedPageInFolder;
            if (pageIndex === null || pageIndex === undefined) {
              return;
            }
            const selected = file.scannings[pageIndex];
            
            // Use the current serial number from state (already calculated)
            const currentSerial = getCurrentSerialDisplay();
            const serialParts = parseSerial(currentSerial);
            const existingPageMeta = getProcessedPageMeta(pageIndex);
            const editingExistingPage = !!existingPageMeta && state.typingState.editMode;
            
            const pageTypeOthersValue = isOtherSelection(state.typingState.pageType) ? state.typingState.pageTypeOthers : null;
            const pageSubTypeOthersValue = isOtherSelection(state.typingState.pageSubType) ? state.typingState.pageSubTypeOthers : null;
            const autoDefinition = getAutoDefinitionFromPosition(pageIndex, file.scannings);
            const autoDefinitionCode = `${autoDefinition}-${getCoverTypeById(state.typingState.coverType)?.code}-${getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers)}${getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) ? '-' + getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) : ''}-${currentSerial}`;
            
            const pageData = {
              file_indexing_id: file.id,
              scanning_id: selected?.id || null,
              page_number: state.typingState.selectedPageInFolder + 1,
              cover_type_id: parseInt(state.typingState.coverType),
              page_type: state.typingState.pageType,
              page_type_other: pageTypeOthersValue,
              page_type_others: pageTypeOthersValue,
              page_subtype: state.typingState.pageSubType,
              page_subtype_other: pageSubTypeOthersValue,
              page_subtype_others: pageSubTypeOthersValue,
              serial_number: serialParts.base,
              serial_suffix: serialParts.suffix || null,
              page_code: `${getCoverTypeById(state.typingState.coverType)?.code}-${getPageTypeCode(state.typingState.pageType, state.typingState.pageTypeOthers)}${getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) ? '-' + getPageSubTypeCode(state.typingState.pageType, state.typingState.pageSubType, state.typingState.pageSubTypeOthers) : ''}-${currentSerial}`,
              definition: autoDefinition,
              definition_code: autoDefinitionCode,
              display_order: Math.max(0, autoDefinition - 1),
              file_path: selected?.document_path || `EDMS/PAGETYPING/${file.file_number}/${file.file_number}.pdf`,
              registry: state.typingState.registry,
              page_typing_id: existingPageMeta?.pageTypingId || null,
              // Booklet management fields
              booklet_id: state.typingState.currentBooklet,
              is_booklet_page: state.typingState.bookletMode,
              booklet_sequence: state.typingState.bookletMode ? state.typingState.bookletCounter : null,
              // BC+FC management fields
              is_bcfc_page: state.typingState.bcfcMode,
              bcfc_sequence: state.typingState.bcfcMode ? state.typingState.bcfcCounter : null,
              bcfc_id: state.typingState.bcfcMode ? state.typingState.bcfcId : null
            };

            try {
              const response = await fetch('{{ route("pagetyping.save-single") }}', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(pageData)
              });

              const result = await response.json();
              
              if (result.success) {
                const previousPageMeta = state.typingState.processedPages[state.typingState.selectedPageInFolder] || {};
                const updatedPageTypingId = result.page_typing_id ? result.page_typing_id.toString() : (previousPageMeta.pageTypingId || null);

                // Sync scanning data with updated path from server if provided
                if (result.scanning && result.scanning.document_path) {
                  const scanningToUpdate = file.scannings.find(s => s.id === result.scanning.id);
                  if (scanningToUpdate) {
                    console.log(`Syncing document_path for scanning ${scanningToUpdate.id}:`, result.scanning.document_path);
                    scanningToUpdate.document_path = result.scanning.document_path;
                    scanningToUpdate.file_url = result.scanning.file_url || null;
                    // Force update the global file object as well
                    if (state.selectedFile && state.selectedFile.scannings) {
                      const globalScanning = state.selectedFile.scannings.find(s => s.id === result.scanning.id);
                      if (globalScanning) {
                        globalScanning.document_path = result.scanning.document_path;
                        globalScanning.file_url = result.scanning.file_url || null;
                      }
                    }
                  }
                }

                // Mark page as processed (store page_code for display)
                state.typingState.processedPages[state.typingState.selectedPageInFolder] = {
                  pageTypingId: updatedPageTypingId,
                  coverType: state.typingState.coverType,
                  pageType: state.typingState.pageType,
                  pageTypeOthers: state.typingState.pageTypeOthers,
                  pageSubType: state.typingState.pageSubType,
                  pageSubTypeOthers: state.typingState.pageSubTypeOthers,
                  serialNo: currentSerial,
                  bookletId: state.typingState.bookletMode ? state.typingState.currentBooklet : (previousPageMeta.bookletId || null),
                  isBookletPage: !!state.typingState.bookletMode,
                  bcfcId: state.typingState.bcfcMode ? state.typingState.bcfcId : (previousPageMeta.bcfcId || null),
                  isBcfcPage: !!state.typingState.bcfcMode,
                  definition: String(pageData.definition ?? '0'),
                  page_code: pageData.page_code,
                  definition_code: pageData.definition_code
                };

                state.typingState.lastProcessedIndex = pageIndex;
                state.typingState.cursorIndex = pageIndex;

                // Store booklet page information for tracking
                if (state.typingState.bookletMode && state.typingState.currentBooklet) {
                  if (!state.typingState.bookletPages[state.typingState.currentBooklet]) {
                    state.typingState.bookletPages[state.typingState.currentBooklet] = [];
                  }
                  state.typingState.bookletPages[state.typingState.currentBooklet].push({
                    pageIndex: state.typingState.selectedPageInFolder,
                    serialNumber: currentSerial,
                    pageCode: pageData.page_code
                  });
                }

                if (state.typingState.bcfcMode) {
                  state.typingState.bcfcPages = state.typingState.bcfcPages || [];
                  state.typingState.bcfcPages.push({
                    pageIndex: state.typingState.selectedPageInFolder,
                    serialNumber: currentSerial,
                    pageCode: pageData.page_code
                  });
                }

                if (!editingExistingPage) {
                  // Increment serial number (booklet-aware or BC+FC-aware) only for new pages
                  if (state.typingState.bcfcMode) {
                    incrementBcFcCounter();
                  } else {
                    incrementBookletCounter();
                  }
                  // Go back to folder view
                  state.typingState.selectedPageInFolder = null;
                }
                
                Swal.fire({
                  icon: 'success',
                  title: editingExistingPage ? 'Page Updated Successfully!' : 'Page Processed Successfully!',
                  text: editingExistingPage ? 'The existing classification has been updated.' : 'The page has been categorized and saved.',
                  confirmButtonColor: '#28a745',
                  timer: 2000,
                  timerProgressBar: true
                });
                updateUI();
                if (editingExistingPage) {
                  setTimeout(() => {
                    setFormElementsState(true, 'Update Page');
                  }, 100);
                }
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Error Processing Page',
                  text: 'Error processing page: ' + result.message,
                  confirmButtonColor: '#dc3545'
                });
              }
            } catch (error) {
              console.error('Error processing page:', error);
              Swal.fire({
                icon: 'error',
                title: 'Error Processing Page',
                text: 'An error occurred while processing the page. Please try again.',
                confirmButtonColor: '#dc3545'
              });
            }
          } // End of processSinglePage function

          // Booklet management event listeners
          document.querySelector('.start-booklet')?.addEventListener('click', startBooklet);
          document.querySelector('.end-booklet')?.addEventListener('click', endBooklet);

          // BC+FC management event listeners
          document.querySelector('.start-bc-fc')?.addEventListener('click', startBcFc);
          document.querySelector('.end-bc-fc')?.addEventListener('click', endBcFc);

          // Full screen functionality
          document.querySelector('.fullscreen-btn')?.addEventListener('click', () => {
            openFullscreenView();
          });

          document.querySelector('.close-fullscreen')?.addEventListener('click', () => {
            closeFullscreenView();
          });

          // Close fullscreen on escape key
          document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
              closeFullscreenView();
            }
          });
        }

        // Booklet Management Functions
        function startBooklet() {
          // Ensure we have a selected file
          if (!state.selectedFile) {
            Swal.fire({
              icon: 'warning',
              title: 'No File Selected',
              text: 'Please select a file first to start a booklet.',
              confirmButtonColor: '#f59e0b'
            });
            return;
          }
          
          // Enable booklet mode
          state.typingState.bookletMode = true;
          const fcId = getFrontCoverId() || coverTypes[0]?.id || '';
          if (fcId) {
            state.typingState.coverType = fcId.toString();
          }
          
          // Create a unique ID for this booklet
          state.typingState.currentBooklet = `booklet_${Date.now()}`;
          
          // Use the current serial number as the booklet start
          state.typingState.bookletStartPage = state.typingState.serialNo;
          
          // Reset the alphabetic counter
          state.typingState.bookletCounter = 'a';

          // Align serial display with booklet defaults
          syncBookletSerialForSelection();
          
          // Show success message
          Swal.fire({
            icon: 'success',
            title: 'Booklet Started!',
            text: `Started booklet with base serial ${state.typingState.bookletStartPage}. Pages will be numbered ${state.typingState.bookletStartPage}a, ${state.typingState.bookletStartPage}b, etc.`,
            confirmButtonColor: '#28a745',
            timer: 3000,
            timerProgressBar: true
          });
          
          // Update UI to reflect booklet mode
          updateUI();
        }

        function endBooklet() {
          // Check if we're actually in booklet mode
          if (!state.typingState.bookletMode) {
            return;
          }
          
          // Get booklet summary for user feedback
          const bookletPages = state.typingState.bookletPages[state.typingState.currentBooklet] || [];
          const serials = bookletPages.map(p => p.serialNumber).filter(Boolean);
          const serialSummary = serials.length
            ? `${serials[0]}${serials.length > 1 ? ' ... ' + serials[serials.length - 1] : ''}`
            : 'None';
          
          // Disable booklet mode
          state.typingState.bookletMode = false;
          
          // Clear current booklet reference
          state.typingState.currentBooklet = null;
          state.typingState.bookletStartPage = null;
          
          // Reset the alphabetic counter for next time
          state.typingState.bookletCounter = 'a';
          
          // Increment the main serial number for the next non-booklet page
          const currentSerial = state.typingState.serialNo?.toString() || '1';
          const numericPart = parseInt(currentSerial.match(/^(\d+)/)?.[1] || '1') || 1;
          const nextSerialNo = numericPart + 1;
          state.typingState.serialNo = nextSerialNo.toString().padStart(2, '0');
          
          // Show completion message
          Swal.fire({
            icon: 'success',
            title: 'Booklet Completed!',
            html: `
              <div class="text-left">
                <p class="mb-1"><strong>Total pages:</strong> ${bookletPages.length}</p>
                <p class="mb-1"><strong>Serials:</strong> ${serialSummary}</p>
                <p class="text-sm text-gray-500 mt-2">Next serial: <strong>${state.typingState.serialNo}</strong></p>
              </div>
            `,
            confirmButtonColor: '#28a745'
          });
          
          // Update UI to reflect normal mode
          updateUI();
        }

        function getBookletSerialNumber() {
          // This function is deprecated - use calculateNextSerialNumber() instead
          return state.typingState.serialNo;
        }

        function incrementBookletCounter() {
          const current = state.typingState.bookletCounter || 'a';
          state.typingState.bookletCounter = incrementSuffix(current);
          const base = parseSerial(state.typingState.bookletStartPage || state.typingState.serialNo || '1').base || 1;
          state.typingState.serialNo = serialFromParts(base, state.typingState.bookletCounter);
          refreshSerialDisplay();
        }

        // BC+FC Management Functions
        function startBcFc() {
          // Ensure we have a selected file
          if (!state.selectedFile) {
            Swal.fire({
              icon: 'warning',
              title: 'No File Selected',
              text: 'Please select a file first to start BC+FC mode.',
              confirmButtonColor: '#f59e0b'
            });
            return;
          }
          
          // Enable BC+FC mode
          state.typingState.bcfcMode = true;
          
          // Create a unique ID for this BC+FC session
          state.typingState.bcfcId = `bcfc_${Date.now()}`;

          // Reset BC+FC pages tracking
          state.typingState.bcfcPages = [];

          // Reset the alphabetic counter to start from 'a'
          state.typingState.bcfcCounter = 'a';
          
          // Disable form inputs during BC+FC mode
          setFormElementsState(true, 'Process BC+FC Pages');
          
          // Enable multi-select mode for BC+FC
          state.typingState.isMultiSelectMode = true;
          
          // Show success message
          Swal.fire({
            icon: 'success',
            title: 'BC+FC Mode Started!',
            text: 'Back Cover + File Cover Management activated. All form inputs are disabled during BC+FC mode. Pages will be numbered 0a, 0b, 0c, etc.',
            confirmButtonColor: '#f97316',
            timer: 4000,
            timerProgressBar: true
          });
          
          // Update UI to reflect BC+FC mode
          updateUI();
        }

        function endBcFc() {
          // Check if we're actually in BC+FC mode
          if (!state.typingState.bcfcMode) {
            return;
          }
          
          // Disable BC+FC mode
          state.typingState.bcfcMode = false;
          
          // Hide BC+FC card when manually ended
          state.typingState.bcfcCardEnabled = false;
          
          // Reset BC+FC counter
          state.typingState.bcfcCounter = 'a';

          // Reset BC+FC session ID
          const bcfcPages = state.typingState.bcfcPages || [];
          state.typingState.bcfcId = null;
          state.typingState.bcfcPages = [];
          
          // Re-enable form inputs
          setFormElementsState(true, 'Process Page');
          
          // Disable multi-select mode
          state.typingState.isMultiSelectMode = false;
          state.typingState.selectedPages.clear();
          
          // Reset serial for normal flow
          state.typingState.serialNo = calculateNextSerialNumber();

          const serials = bcfcPages.map(p => p.serialNumber).filter(Boolean);
          const serialSummary = serials.length
            ? `${serials[0]}${serials.length > 1 ? ' ... ' + serials[serials.length - 1] : ''}`
            : 'None';

          // Show completion message with summary
          Swal.fire({
            icon: 'success',
            title: 'BC+FC Mode Ended!',
            html: `
              <div class="text-left">
                <p class="mb-1"><strong>Total pages:</strong> ${bcfcPages.length}</p>
                <p class="mb-1"><strong>Serials:</strong> ${serialSummary}</p>
                <p class="text-sm text-gray-500 mt-2">Next serial: <strong>${state.typingState.serialNo}</strong></p>
              </div>
            `,
            confirmButtonColor: '#f97316'
          });
          
          // Update UI to reflect normal mode
          updateUI();
        }

        function getBcFcSerialNumber() {
          if (!state.typingState.bcfcMode) {
            return state.typingState.serialNo;
          }
          return serialFromParts(0, state.typingState.bcfcCounter || 'a');
        }

        function incrementBcFcCounter() {
          if (!state.typingState.bcfcMode) {
            return;
          }
          state.typingState.bcfcCounter = incrementSuffix(state.typingState.bcfcCounter || 'a');
        }

        // Auto-enable BC+FC mode when Back Cover + File Cover is selected
        function checkAutoEnableBcFc() {
          const coverType = state.typingState.coverType;
          const pageType = state.typingState.pageType;
          
          const coverCode = (getCoverTypeById(coverType)?.code || '').toString().toUpperCase();
          const pageTypeCode = (getPageTypeCode(pageType, state.typingState.pageTypeOthers) || '').toString().toUpperCase();
          
          // Check if both Back Cover and File Cover codes are selected
          const isBackCover = coverCode === 'BC';
          const isFilecover = pageTypeCode === 'FC';
          
          if (isBackCover && isFilecover) {
            // Enable BC+FC card and auto-start BC+FC mode
            state.typingState.bcfcCardEnabled = true;
            if (!state.typingState.bcfcMode) {
              setTimeout(() => {
                startBcFc();
              }, 100);
            }
          } else {
            // Disable BC+FC card and auto-end BC+FC mode if selection changes away from BC+FC
            state.typingState.bcfcCardEnabled = false;
            if (state.typingState.bcfcMode) {
              setTimeout(() => {
                endBcFc();
              }, 100);
            }
          }
        }

        // Event handlers
        function switchTab(tabId) {
          state.activeTab = tabId;
          updateUI();
        }

        // Filtering and Searching
        function setupFiltering() {
          const filters = [
            { id: 'pending-search', tab: 'pending', field: 'search' },
            { id: 'pending-registry-filter', tab: 'pending', field: 'registry' },
            { id: 'in-progress-search', tab: 'in-progress', field: 'search' },
            { id: 'in-progress-registry-filter', tab: 'in-progress', field: 'registry' },
            { id: 'completed-search', tab: 'completed', field: 'search' },
            { id: 'completed-registry-filter', tab: 'completed', field: 'registry' },
            { id: 'pagetype-more-search', tab: 'pagetype-more', field: 'search' },
            { id: 'pagetype-more-registry-filter', tab: 'pagetype-more', field: 'registry' }
          ];

          filters.forEach(filter => {
            const el = document.getElementById(filter.id);
            if (el) {
              const eventType = el.tagName === 'SELECT' ? 'change' : 'input';
              el.addEventListener(eventType, debounce(() => {
                const tabKey = filter.tab.includes('-') ? filter.tab.replace(/-([a-z])/g, (g) => g[1].toUpperCase()) : filter.tab;
                state.pagination[tabKey][filter.field] = el.value;
                state.pagination[tabKey].currentPage = 1;
                
                if (filter.tab === 'pending') renderPendingFiles(1);
                if (filter.tab === 'in-progress') renderInProgressFiles(1);
                if (filter.tab === 'completed') renderCompletedFilesTable(1);
                if (filter.tab === 'pagetype-more') renderPageTypeMoreFiles(1);
              }, 500));
            }
          });
        }
        // Pagination helpers
        function changePage(tab, page) {
          if (tab === 'pending') renderPendingFiles(page);
          if (tab === 'inProgress') renderInProgressFiles(page);
          if (tab === 'completed') renderCompletedFilesTable(page);
          if (tab === 'pageTypeMore') renderPageTypeMoreFiles(page);
        }

        function updatePaginationUI(tab) {
          const tabState = state.pagination[tab];
          if (!tabState) return;

          // Convert camelCase tab name to hyphenated ID if needed
          const tabId = tab === 'inProgress' ? 'in-progress' : 
                        tab === 'pageTypeMore' ? 'pagetype-more' : tab;

          const showingEl = document.getElementById(`${tabId}-showing`);
          const prevBtn = document.getElementById(`${tabId}-prev-btn`);
          const nextBtn = document.getElementById(`${tabId}-next-btn`);
          const pageNumbersEl = document.getElementById(`${tabId}-page-numbers`);

          if (showingEl) {
            const from = tabState.total === 0 ? 0 : (tabState.currentPage - 1) * tabState.perPage + 1;
            const to = Math.min(tabState.currentPage * tabState.perPage, tabState.total);
            showingEl.textContent = `Showing ${from} to ${to} of ${tabState.total} entries`;
          }

          if (prevBtn) prevBtn.disabled = tabState.currentPage <= 1;
          if (nextBtn) nextBtn.disabled = tabState.currentPage >= tabState.lastPage;

          if (pageNumbersEl) {
            let html = '';
            const maxPages = 5;
            let start = Math.max(1, tabState.currentPage - Math.floor(maxPages / 2));
            let end = Math.min(tabState.lastPage, start + maxPages - 1);
            
            if (end - start + 1 < maxPages) {
              start = Math.max(1, end - maxPages + 1);
            }

            for (let i = start; i <= end; i++) {
              html += `
                <button class="btn ${i === tabState.currentPage ? 'btn-primary' : 'btn-outline'} btn-sm" onclick="changePage('${tab}', ${i})">
                  ${i}
                </button>
              `;
            }
            pageNumbersEl.innerHTML = html;
          }
        }

        // Debounce helper
        function debounce(func, wait) {
          let timeout;
          return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
          };
        }

        // Initialize the application
        function setupTabHandlers() {
          // Add tab event listeners
          elements.tabs.forEach(tab => {
            tab.addEventListener('click', () => {
              const tabId = tab.getAttribute('data-tab');
              if (tabId !== 'typing' || state.selectedFile) {
                switchTab(tabId);
              }
            });
          });
        }

        async function init() {
          setupTabHandlers();
          setupActionHandlers();
          setupFiltering();
          @if($showPageTypeMore)
          // In PageType More mode, activate the PageType More tab
          switchTab('pagetype-more');
          @else
          // Load page typing data on startup
          loadPageTypingData();
          @endif
          
          // Add tab event listeners
          elements.tabs.forEach(tab => {
            tab.addEventListener('click', () => {
              const tabId = tab.getAttribute('data-tab');
              if (tabId !== 'typing' || state.selectedFile) {
                switchTab(tabId);
              }
            });
          });

          // PageType More specific event listeners
          if (elements.refreshPageTypeMore) {
            elements.refreshPageTypeMore.addEventListener('click', () => {
              renderPageTypeMoreFiles();
            });
          }

          if (elements.pageTypeMoreSearch) {
            elements.pageTypeMoreSearch.addEventListener('input', () => {
              // Re-render list with filter
              renderPageTypeMoreFiles();
            });
          }

          // Add pagination event listeners
          // Pending pagination
          const pendingPrevBtn = document.getElementById('pending-prev-btn');
          const pendingNextBtn = document.getElementById('pending-next-btn');
          
          if (pendingPrevBtn) {
            pendingPrevBtn.addEventListener('click', () => {
              const currentPage = state.pagination.pending.currentPage;
              if (currentPage > 1) {
                changePage('pending', currentPage - 1);
              }
            });
          }
          
          if (pendingNextBtn) {
            pendingNextBtn.addEventListener('click', () => {
              const currentPage = state.pagination.pending.currentPage;
              const lastPage = state.pagination.pending.lastPage;
              if (currentPage < lastPage) {
                changePage('pending', currentPage + 1);
              }
            });
          }

          // In-progress pagination
          const inProgressPrevBtn = document.getElementById('in-progress-prev-btn');
          const inProgressNextBtn = document.getElementById('in-progress-next-btn');
          
          if (inProgressPrevBtn) {
            inProgressPrevBtn.addEventListener('click', () => {
              const currentPage = state.pagination.inProgress.currentPage;
              if (currentPage > 1) {
                changePage('inProgress', currentPage - 1);
              }
            });
          }
          
          if (inProgressNextBtn) {
            inProgressNextBtn.addEventListener('click', () => {
              const currentPage = state.pagination.inProgress.currentPage;
              const lastPage = state.pagination.inProgress.lastPage;
              if (currentPage < lastPage) {
                changePage('inProgress', currentPage + 1);
              }
            });
          }

          // Completed pagination
          const completedPrevBtn = document.getElementById('completed-prev-btn');
          const completedNextBtn = document.getElementById('completed-next-btn');
          
          if (completedPrevBtn) {
            completedPrevBtn.addEventListener('click', () => {
              const currentPage = state.pagination.completed.currentPage;
              if (currentPage > 1) {
                changePage('completed', currentPage - 1);
              }
            });
          }
          
          if (completedNextBtn) {
            completedNextBtn.addEventListener('click', () => {
              const currentPage = state.pagination.completed.currentPage;
              const lastPage = state.pagination.completed.lastPage;
              if (currentPage < lastPage) {
                changePage('completed', currentPage + 1);
              }
            });
          }

          // PageType More pagination
          const pageTypeMorePrevBtn = document.getElementById('pagetype-more-prev-btn');
          const pageTypeMoreNextBtn = document.getElementById('pagetype-more-next-btn');
          
          if (pageTypeMorePrevBtn) {
            pageTypeMorePrevBtn.addEventListener('click', () => {
              const currentPage = state.pagination.pageTypeMore.currentPage;
              if (currentPage > 1) {
                changePage('pageTypeMore', currentPage - 1);
              }
            });
          }
          
          if (pageTypeMoreNextBtn) {
            pageTypeMoreNextBtn.addEventListener('click', () => {
              const currentPage = state.pagination.pageTypeMore.currentPage;
              const lastPage = state.pagination.pageTypeMore.lastPage;
              if (currentPage < lastPage) {
                changePage('pageTypeMore', currentPage + 1);
              }
            });
          }

          // Initial UI update
          updateUI();

          // Add fullscreen modal event listeners
          const closeFullscreenBtn = document.querySelector('.close-fullscreen');
          if (closeFullscreenBtn) {
            closeFullscreenBtn.addEventListener('click', closeFullscreenView);
          }

          // Fullscreen navigation arrows - also use event delegation as backup
          const fsModal = document.getElementById('fullscreen-modal');
          if (fsModal) {
            fsModal.addEventListener('click', (e) => {
              const prevBtn = e.target.closest('#fullscreen-prev');
              const nextBtn = e.target.closest('#fullscreen-next');
              if (prevBtn) {
                e.stopPropagation();
                console.log('Fullscreen prev (delegation) clicked');
                navigateFullscreenPage(-1);
              } else if (nextBtn) {
                e.stopPropagation();
                console.log('Fullscreen next (delegation) clicked');
                navigateFullscreenPage(1);
              }
            });
          }

          // Keyboard navigation for fullscreen modal
          document.addEventListener('keydown', (e) => {
            const modal = document.getElementById('fullscreen-modal');
            if (!modal || modal.classList.contains('hidden')) return;

            if (e.key === 'Escape') {
              closeFullscreenView();
            } else if (e.key === 'ArrowLeft') {
              e.preventDefault();
              navigateFullscreenPage(-1);
            } else if (e.key === 'ArrowRight') {
              e.preventDefault();
              navigateFullscreenPage(1);
            }
          });
        }
        
        // Backend Integration Functions
        // Load real statistics from backend
        async function loadRealStats() {
            try {
                const response = await fetch('{{ route("pagetyping.api.stats") }}');
                const data = await response.json();
                
                if (data.success) {
                    // Update the stats in the UI
                    if (elements.pendingCount) elements.pendingCount.textContent = data.stats.pending_count || 0;
                    if (elements.inProgressCount) elements.inProgressCount.textContent = data.stats.in_progress_count || 0;
                    if (elements.completedCount) elements.completedCount.textContent = data.stats.completed_count || 0;
                    if (elements.pageTypeMoreCount) elements.pageTypeMoreCount.textContent = data.stats.pagetype_more_count || 0;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        // Initialize real data loading
        setTimeout(() => {
            loadRealStats();
            // Load PageType More files when that tab is active
            if (state.activeTab === 'pagetype-more') {
                renderPageTypeMoreFiles();
            }
        }, 1000);
        
        // Refresh data every 30 seconds
        setInterval(() => {
            loadRealStats();
            // Only refresh the active tab
            if (state.activeTab === 'pagetype-more') {
                renderPageTypeMoreFiles();
            }
        }, 30000);

        // Initialize the dashboard
        init();
    </script>

    <!-- Full Screen Modal -->
    <div id="fullscreen-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-90">
      <div class="relative w-full h-full flex items-center justify-center">
        <!-- Close button -->
        <button class="absolute top-4 right-4 z-[70] btn btn-ghost btn-icon text-white hover:bg-white hover:bg-opacity-20 close-fullscreen" style="pointer-events: auto;" title="Exit Full Screen (Esc)">
          <i data-lucide="x" class="h-6 w-6"></i>
        </button>

        <!-- Page indicator -->
        <div class="absolute top-4 left-1/2 -translate-x-1/2 z-[70] flex items-center gap-3">
          <span id="fullscreen-page-indicator" class="text-white text-sm font-medium bg-black bg-opacity-50 px-4 py-1.5 rounded-full"></span>
        </div>

        <!-- Left navigation arrow -->
        <button id="fullscreen-prev" onclick="navigateFullscreenPage(-1)" class="absolute left-4 top-1/2 -translate-y-1/2 z-[70] w-14 h-14 rounded-full bg-white bg-opacity-20 hover:bg-opacity-50 text-white flex items-center justify-center transition-all cursor-pointer shadow-lg border-2 border-white border-opacity-40" style="pointer-events: auto;" title="Previous Page (←)">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>

        <!-- Right navigation arrow -->
        <button id="fullscreen-next" onclick="navigateFullscreenPage(1)" class="absolute right-4 top-1/2 -translate-y-1/2 z-[70] w-14 h-14 rounded-full bg-white bg-opacity-20 hover:bg-opacity-50 text-white flex items-center justify-center transition-all cursor-pointer shadow-lg border-2 border-white border-opacity-40" style="pointer-events: auto;" title="Next Page (→)">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>

        <!-- Full screen content container -->
        <div class="w-full h-full max-w-6xl max-h-screen p-8 px-20">
          <div class="w-full h-full bg-white rounded-lg shadow-2xl overflow-hidden" id="fullscreen-content">
            <!-- Content will be cloned here -->
          </div>
        </div>
      </div>
    </div>

  {{-- Reassignment dialog dependencies. Order matters: the dialog's file picker
       (GlobalFileNoModal) is a select2 widget, so jQuery must load first, then
       select2, then the picker, then the manager. Loading scan-reassignment.js
       ahead of these throws "$(...).select2 is not a function". --}}
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
  <script src="{{ asset('js/scan-reassignment.js') }}"></script>

@endsection

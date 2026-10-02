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
              <h1 class="text-2xl font-bold tracking-tight">Page Typing</h1>
              <p class="text-muted-foreground">Categorize and digitize file content</p>
            </div>
        
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
                  <p class="text-xs text-muted-foreground mt-1">Files completed typing</p>
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
        
            <!-- Tabs -->
            <div class="tabs">
              <div class="tabs-list grid w-full md:w-auto grid-cols-5">
                <button class="tab" role="tab" aria-selected="true" data-tab="pending">Pending Page Typing</button>
                <button class="tab" role="tab" aria-selected="false" data-tab="in-progress">In Progress</button>
                <button class="tab" role="tab" aria-selected="false" data-tab="completed">Completed</button>
                <button class="tab" role="tab" aria-selected="false" data-tab="pagetype-more">PageType More</button>
                <button class="tab" role="tab" aria-selected="false" data-tab="typing" aria-disabled="true" id="typing-tab">Typing</button>
              </div>
        
              <!-- Pending Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="false" data-tab-content="pending">
                <div class="card">
                  <div class="p-6 border-b">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h2 class="text-lg font-semibold">Files Pending Page Typing</h2>
                        <p class="text-sm text-muted-foreground">Select a file to begin typing its content</p>
                      </div>
                      <div class="relative w-full md:w-64">
                        <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                        <input type="search" placeholder="Search files..." class="input w-full pl-8">
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
        
              <!-- In Progress Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="true" data-tab-content="in-progress">
                <div class="card">
                  <div class="p-6 border-b">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h2 class="text-lg font-semibold">Files In Progress</h2>
                        <p class="text-sm text-muted-foreground">Files that are partially typed</p>
                      </div>
                      <div class="relative w-full md:w-64">
                        <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                        <input type="search" placeholder="Search files..." class="input w-full pl-8">
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
        
              <!-- Completed Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="true" data-tab-content="completed">
                <div class="card">
                  <div class="p-6 border-b">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h2 class="text-lg font-semibold">Completed Files</h2>
                        <p class="text-sm text-muted-foreground">Files that have been fully typed</p>
                      </div>
                      <div class="relative w-full md:w-64">
                        <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                        <input type="search" placeholder="Search files..." class="input w-full pl-8">
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

              <!-- PageType More Tab -->
              <div class="tab-content mt-6" role="tabpanel" aria-hidden="true" data-tab-content="pagetype-more">
                <div class="card">
                  <div class="p-6 border-b">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h2 class="text-lg font-semibold">PageType More Files</h2>
                        <p class="text-sm text-muted-foreground">Files with new scans that need additional page typing (IsUpdated = 1)</p>
                      </div>
                      <div class="flex items-center gap-4">
                        <div class="relative w-full md:w-64">
                          <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                          <input type="search" placeholder="Search files..." class="input w-full pl-8" id="pagetype-more-search">
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

        // Check if PDF.js is available
        console.log('PDF.js availability:', typeof pdfjsLib);
        if (typeof pdfjsLib !== 'undefined') {
          console.log('PDF.js version:', pdfjsLib.version);
        }

        // Application state
        let state = {
          activeTab: 'pending',
          selectedFile: null,
          selectedFileData: null,
          pageTypeMoreMode: false,
          existingPageTypings: [],
          newScans: [],
          combinedPages: [],
          // Typing interface state
          typingState: null,
          // Pagination state
          pagination: {
            pending: { currentPage: 1, total: 0, lastPage: 1, perPage: 10 },
            inProgress: { currentPage: 1, total: 0, lastPage: 1, perPage: 10 },
            completed: { currentPage: 1, total: 0, lastPage: 1, perPage: 10 },
            pageTypeMore: { currentPage: 1, total: 0, lastPage: 1, perPage: 10 }
          }
        };

        // Cover types, page types and subtypes - will be loaded from backend
        let coverTypes = [];
        let pageTypes = [];
        let pageSubTypes = {};

        // Helper function to set form element states
        function setFormElementsState(enabled, buttonText = 'Process Page') {
          const coverTypeSelect = document.getElementById('cover-type');
          const pageTypeSelect = document.getElementById('page-type');
          const pageSubtypeSelect = document.getElementById('page-subtype');
          const serialNoInput = document.getElementById('serial-no');
          const processButton = document.querySelector('.process-page');

          if (coverTypeSelect) {
            coverTypeSelect.disabled = !enabled;
            coverTypeSelect.classList.toggle('opacity-50', !enabled);
          }
          if (pageTypeSelect) {
            pageTypeSelect.disabled = !enabled;
            pageTypeSelect.classList.toggle('opacity-50', !enabled);
          }
          if (pageSubtypeSelect) {
            pageSubtypeSelect.disabled = !enabled;
            pageSubtypeSelect.classList.toggle('opacity-50', !enabled);
          }
          if (serialNoInput) {
            serialNoInput.disabled = !enabled;
            serialNoInput.classList.toggle('opacity-50', !enabled);
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
              // Fallback to default data
              setDefaultPageTypingData();
            }
          } catch (error) {
            console.error('Error loading page typing data:', error);
            // Fallback to default data
            setDefaultPageTypingData();
          }
        }

        // Fallback default data
        function setDefaultPageTypingData() {
          coverTypes = [
            { id: 1, code: "FC", name: "Front Cover" },
            { id: 2, code: "BC", name: "Back Cover" }
          ];
          
          pageTypes = [
            { id: 1, code: "FC", name: "File Cover" },
            { id: 2, code: "APP", name: "Application" },
            { id: 3, code: "BN", name: "Bill Notice" },
            { id: 4, code: "COR", name: "Correspondence" },
            { id: 5, code: "LT", name: "Land Title" },
            { id: 6, code: "LEG", name: "Legal" },
            { id: 7, code: "PE", name: "Payment Evidence" },
            { id: 8, code: "REP", name: "Report" },
            { id: 9, code: "SUR", name: "Survey" },
            { id: 10, code: "MISC", name: "Miscellaneous" }
          ];

          pageSubTypes = {
            1: [{ id: 1, code: "NFC", name: "New File Cover" }, { id: 2, code: "OFC", name: "Old File Cover" }],
            2: [{ id: 3, code: "CO", name: "Certificate of Occupancy" }, { id: 4, code: "REV", name: "Revalidation" }],
            3: [{ id: 7, code: "DGR", name: "Demand for Ground Rent" }, { id: 34, code: "DN", name: "Demand Notice" }],
            4: [{ id: 8, code: "AL", name: "Acknowledgment Letter" }, { id: 9, code: "ASR", name: "Application Submission" }],
            5: [{ id: 5, code: "CO", name: "Certificate of Occupancy" }, { id: 6, code: "SP", name: "Survey Plan" }],
            6: [{ id: 18, code: "AGR", name: "Agreement" }, { id: 44, code: "POA", name: "Power of Attorney" }],
            7: [{ id: 19, code: "AOF", name: "Assessment of Fees" }, { id: 20, code: "BT", name: "Bank Teller" }],
            8: [{ id: 23, code: "RR", name: "Reinspection Report" }, { id: 65, code: "IPVR", name: "Inspection Report" }],
            9: [{ id: 24, code: "TDP", name: "Title Deed Plan" }, { id: 25, code: "SP", name: "Survey Plan" }],
            10: [{ id: 27, code: "MISC", name: "Miscellaneous" }, { id: 43, code: "OC", name: "Other Certificates" }]
          };
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

        function getPageSubTypeById(typeId, subTypeId) {
          return pageSubTypes[parseInt(typeId)]?.find(subType => subType.id.toString() === subTypeId.toString());
        }

        // Horizontal File Browser functionality
        function initializeHorizontalFileBrowser(file) {
          if (!file || !file.scannings || file.scannings.length === 0) return;

          // Initialize file browser state
          const filesPerView = 5; // Number of files to show at once
          let currentStartIndex = 0;
          
          // Update navigation buttons
          function updateFileBrowserNavigation() {
            const prevBtn = document.querySelector('.file-browser-prev');
            const nextBtn = document.querySelector('.file-browser-next');
            const indicator = document.getElementById('file-browser-indicator');
            
            if (prevBtn) {
              prevBtn.disabled = currentStartIndex === 0;
              prevBtn.classList.toggle('opacity-50', currentStartIndex === 0);
            }
            
            if (nextBtn) {
              nextBtn.disabled = currentStartIndex + filesPerView >= file.scannings.length;
              nextBtn.classList.toggle('opacity-50', currentStartIndex + filesPerView >= file.scannings.length);
            }
            
            if (indicator) {
              const endIndex = Math.min(currentStartIndex + filesPerView, file.scannings.length);
              indicator.textContent = `${currentStartIndex + 1}-${endIndex} of ${file.scannings.length}`;
            }
          }
          
          // Update file browser view
          function updateFileBrowserView() {
            const strip = document.getElementById('file-browser-strip');
            if (!strip) return;
            
            const itemWidth = 88; // 80px width + 8px gap
            const translateX = -(currentStartIndex * itemWidth);
            strip.style.transform = `translateX(${translateX}px)`;
            
            // Update active states
            document.querySelectorAll('.file-browser-item').forEach((item, index) => {
              const isSelected = index === state.typingState.selectedPageInFolder;
              item.classList.toggle('active', isSelected);
              
              if (isSelected) {
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
            if (currentStartIndex > 0) {
              currentStartIndex = Math.max(0, currentStartIndex - filesPerView);
              updateFileBrowserView();
            }
          });
          
          // Navigate to next files
          document.querySelector('.file-browser-next')?.addEventListener('click', () => {
            if (currentStartIndex + filesPerView < file.scannings.length) {
              currentStartIndex = Math.min(file.scannings.length - filesPerView, currentStartIndex + filesPerView);
              updateFileBrowserView();
            }
          });
          
          // Keyboard navigation support
          document.addEventListener('keydown', (e) => {
            // Only handle keys when typing tab is active and we're in page categorization view
            if (state.activeTab === 'typing' && state.typingState && state.typingState.selectedPageInFolder !== null) {
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
          
          // Auto-scroll to selected file
          function scrollToSelectedFile() {
            if (state.typingState.selectedPageInFolder !== null) {
              const selectedIndex = state.typingState.selectedPageInFolder;
              
              // Check if selected file is visible in current view
              if (selectedIndex < currentStartIndex || selectedIndex >= currentStartIndex + filesPerView) {
                // Scroll to make selected file visible
                currentStartIndex = Math.max(0, selectedIndex - Math.floor(filesPerView / 2));
                currentStartIndex = Math.min(currentStartIndex, file.scannings.length - filesPerView);
                updateFileBrowserView();
              }
            }
          }
          
          // Generate thumbnails for file browser
          setTimeout(() => {
            file.scannings.forEach((scanning, index) => {
              const url = getDocumentUrl(scanning.document_path);
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
          updateFileBrowserView();
          
          // Watch for changes in selected page
          const originalUpdateUI = updateUI;
          updateUI = function() {
            originalUpdateUI.call(this);
            setTimeout(() => {
              scrollToSelectedFile();
              updateFileBrowserView();
            }, 50);
          };
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

        function getDocumentUrl(documentPath) {
          if (!documentPath) return null;
          // Use the correct Laravel storage path
          const clean = documentPath.replace(/^\/+/, '').replace(/\\/g, '/');
          const url = `/storage/app/public/${clean}`;
          console.log('Generated document URL:', url, 'from path:', documentPath);

          // Test if the URL is accessible
          fetch(url, { method: 'HEAD' })
            .then(response => {
              console.log('File accessibility check:', {
                url: url,
                status: response.status,
                contentType: response.headers.get('content-type'),
                contentLength: response.headers.get('content-length')
              });
            })
            .catch(error => {
              console.error('File not accessible:', url, error);
            });

          return url;
        }

        function renderPDFPagePreview(pageIndex, containerEl) {
          // This function is now simplified since we don't split PDFs
          // Just render the PDF document as-is
          if (!containerEl) return;

          containerEl.innerHTML = `
            <div class="w-full h-full flex flex-col">
              <div class="flex justify-between mb-2">
                <span class="text-sm font-medium">PDF Document</span>
                <div class="flex items-center gap-2">
                  <button class="btn btn-ghost btn-icon zoom-out"><i data-lucide="zoom-out" class="h-4 w-4"></i></button>
                  <span class="text-xs zoom-level">${state.typingState.zoomLevel}%</span>
                  <button class="btn btn-ghost btn-icon zoom-in"><i data-lucide="zoom-in" class="h-4 w-4"></i></button>
                  <button class="btn btn-ghost btn-icon rotate"><i data-lucide="rotate-cw" class="h-4 w-4"></i></button>
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

          const url = getDocumentUrl(scanning.document_path);
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
                  <span class="text-sm font-medium">${scanning.original_filename}</span>
                  <div class="flex items-center gap-2">
                    <button class="btn btn-ghost btn-icon zoom-out"><i data-lucide="zoom-out" class="h-4 w-4"></i></button>
                    <span class="text-xs zoom-level">${state.typingState.zoomLevel}%</span>
                    <button class="btn btn-ghost btn-icon zoom-in"><i data-lucide="zoom-in" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon rotate"><i data-lucide="rotate-cw" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon fullscreen-btn" onclick="openFullscreenView()" title="Full Screen"><i data-lucide="maximize" class="h-4 w-4"></i></button>
                  </div>
                </div>
                <div class="flex-1 overflow-auto flex items-center justify-center bg-gray-50">
                  <img src="${url}" alt="${scanning.original_filename}" class="max-h-full max-w-full object-contain transition-transform document-image"
                       style="transform: scale(${state.typingState.zoomLevel / 100}) rotate(${state.typingState.rotation}deg);"
                       onerror="this.parentElement.innerHTML='<div class=\'text-center\'><i data-lucide=\'image-off\' class=\'h-12 w-12 mx-auto mb-3 text-red-500\'></i><p class=\'text-sm text-red-600\'>Failed to load image</p></div>'; lucide.createIcons();" />
                </div>
              </div>`;

            // Add event listeners for zoom and rotate controls
            setTimeout(() => {
              const zoomInBtn = containerEl.querySelector('.zoom-in');
              const zoomOutBtn = containerEl.querySelector('.zoom-out');
              const rotateBtn = containerEl.querySelector('.rotate');

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
                  <span class="text-sm font-medium">${scanning.original_filename}</span>
                  <div class="flex items-center gap-2">
                    <button class="btn btn-ghost btn-icon zoom-out"><i data-lucide="zoom-out" class="h-4 w-4"></i></button>
                    <span class="text-xs zoom-level">${state.typingState.zoomLevel}%</span>
                    <button class="btn btn-ghost btn-icon zoom-in"><i data-lucide="zoom-in" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon rotate"><i data-lucide="rotate-cw" class="h-4 w-4"></i></button>
                    <button class="btn btn-ghost btn-icon fullscreen-btn" onclick="openFullscreenView()" title="Full Screen"><i data-lucide="maximize" class="h-4 w-4"></i></button>
                    <div class="flex items-center gap-1 ml-4">
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

              // Add zoom and rotate event listeners
              const zoomInBtn = containerEl.querySelector('.zoom-in');
              const zoomOutBtn = containerEl.querySelector('.zoom-out');
              const rotateBtn = containerEl.querySelector('.rotate');

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

        // Full screen functionality
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
        function updateUI() {
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
                $maxSerial = \App\Models\PageTyping::on('sqlsrv')->max('serial_number');
                $nextSerial = ($maxSerial ? $maxSerial : 0) + 1;
                @endphp
                serialNo: '{{ str_pad($nextSerial, 2, "0", STR_PAD_LEFT) }}',
              
              // Initialize typing state
              state.typingState = {
                currentPage: 1,
                typedContent: '',
                typingProgress: 0,
                zoomLevel: 100,
                rotation: 0,
                showFolderView: true,
                selectedPageInFolder: null,
                coverType: (coverTypes[0]?.id || '1').toString(),
                pageType: (pageTypes[0]?.id || '1').toString(),
                pageSubType: '1',
                serialNo: '{{ str_pad($nextSerial, 2, "0", STR_PAD_LEFT) }}',
                isExistingFile: {{ isset($selectedFileIndexing) && $selectedFileIndexing->pagetypings->count() > 0 ? 'true' : 'false' }},
                batchMode: false,
                batchTypedPages: {},
                batchSubmitReady: false,
                batchProgress: 0,
                batchProcessing: false,
                processedPages: {},
                bookletMode: false,
                currentBooklet: null,
                bookletStartPage: null,
                bookletPages: {},
                bookletCounter: 'a'
              };
              
              // Set initial page subtype based on page type
              if (pageSubTypes[parseInt(state.typingState.pageType)]) {
                state.typingState.pageSubType = pageSubTypes[parseInt(state.typingState.pageType)][0]?.id.toString() || '1';
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
                      coverType: first.cover_type_id?.toString() || null,
                      pageType: first.page_type?.toString() || null,
                      pageSubType: first.page_subtype?.toString() || null,
                      serialNo: first.serial_number?.toString() || null,
                      page_code: first.page_code || null
                    };
                  }
                });
              } catch (e) {
                console.warn('Could not initialize processed pages from backend', e);
              }
              
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
          
          try {
            const response = await fetch(`{{ route("pagetyping.api.files") }}?status=pending&page=${page}&limit=${state.pagination.pending.perPage}`);
            const data = await response.json();
            
            if (data.success) {
              // Update pagination state
              state.pagination.pending = {
                currentPage: data.pagination.current_page,
                total: data.pagination.total,
                lastPage: data.pagination.last_page,
                perPage: data.pagination.per_page
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
                      </div>
                    </div>
                  </div>
                `).join('');
                
                // Add event listeners
                document.querySelectorAll('.start-typing').forEach(btn => {
                  btn.addEventListener('click', () => {
                    const fileId = btn.getAttribute('data-id');
                    startPageTyping(fileId);
                  });
                });
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
          
          try {
            const response = await fetch(`{{ route("pagetyping.api.files") }}?status=in_progress&page=${page}&limit=${state.pagination.inProgress.perPage}`);
            const data = await response.json();
            
            if (data.success) {
              // Update pagination state
              state.pagination.inProgress = {
                currentPage: data.pagination.current_page,
                total: data.pagination.total,
                lastPage: data.pagination.last_page,
                perPage: data.pagination.per_page
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
                        <button class="btn btn-primary btn-sm continue-typing" data-id="${file.id}">
                          <i data-lucide="edit" class="h-4 w-4 mr-1"></i>
                          Continue
                        </button>
                      </div>
                    </div>
                  </div>
                `).join('');
                
                // Add event listeners
                document.querySelectorAll('.continue-typing').forEach(btn => {
                  btn.addEventListener('click', () => {
                    const fileId = btn.getAttribute('data-id');
                    startPageTyping(fileId);
                  });
                });
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
          
          try {
            const response = await fetch(`{{ route("pagetyping.api.files") }}?status=completed&page=${page}&limit=${state.pagination.completed.perPage}`);
            const data = await response.json();
            
            if (data.success) {
              // Update pagination state
              state.pagination.completed = {
                currentPage: data.pagination.current_page,
                total: data.pagination.total,
                lastPage: data.pagination.last_page,
                perPage: data.pagination.per_page
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
                      ${file.main_application?.applicant_name || 'Unknown'}
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
                      <div class="flex items-center gap-2">
                        <button class="btn btn-ghost btn-sm view-file" data-id="${file.id}" title="View File">
                          <i data-lucide="eye" class="h-4 w-4"></i>
                        </button>
                        <button class="btn btn-outline btn-sm edit-file" data-id="${file.id}" title="Edit">
                          <i data-lucide="edit" class="h-4 w-4"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                `).join('');
                
                // Add event listeners
                document.querySelectorAll('.view-file').forEach(btn => {
                  btn.addEventListener('click', () => {
                    const fileId = btn.getAttribute('data-id');
                    startPageTyping(fileId);
                  });
                });
                
                document.querySelectorAll('.edit-file').forEach(btn => {
                  btn.addEventListener('click', () => {
                    const fileId = btn.getAttribute('data-id');
                    startPageTyping(fileId);
                  });
                });
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

        // Update pagination UI for a specific tab
        function updatePaginationUI(tabType) {
          const pagination = state.pagination[tabType];
          const prefix = tabType === 'inProgress' ? 'in-progress' : 
                        tabType === 'pageTypeMore' ? 'pagetype-more' : tabType;

          // Update showing text
          const showingEl = document.getElementById(`${prefix}-showing`);
          if (showingEl) {
            const from = pagination.total > 0 ? ((pagination.currentPage - 1) * pagination.perPage) + 1 : 0;
            const to = Math.min(pagination.currentPage * pagination.perPage, pagination.total);
            showingEl.textContent = `Showing ${from} to ${to} of ${pagination.total} entries`;
          }

          // Update page numbers
          const pageNumbersEl = document.getElementById(`${prefix}-page-numbers`);
          if (pageNumbersEl) {
            pageNumbersEl.innerHTML = generatePageNumbers(tabType);
          }

          // Update prev/next buttons
          const prevBtn = document.getElementById(`${prefix}-prev-btn`);
          const nextBtn = document.getElementById(`${prefix}-next-btn`);
          
          if (prevBtn) {
            prevBtn.disabled = pagination.currentPage <= 1;
            prevBtn.classList.toggle('opacity-50', pagination.currentPage <= 1);
          }
          
          if (nextBtn) {
            nextBtn.disabled = pagination.currentPage >= pagination.lastPage;
            nextBtn.classList.toggle('opacity-50', pagination.currentPage >= pagination.lastPage);
          }

          // Add page number event listeners
          document.querySelectorAll(`.${prefix}-page-btn`).forEach(btn => {
            btn.addEventListener('click', () => {
              const page = parseInt(btn.getAttribute('data-page'));
              changePage(tabType, page);
            });
          });
        }

        // Generate page number buttons
        function generatePageNumbers(tabType) {
          const pagination = state.pagination[tabType];
          const currentPage = pagination.currentPage;
          const lastPage = pagination.lastPage;
          
          if (lastPage <= 1) return '';

          let pages = [];
          
          // Always show first page
          if (lastPage > 1) {
            pages.push(`<button class="${tabType === 'inProgress' ? 'in-progress' : tabType === 'pageTypeMore' ? 'pagetype-more' : tabType}-page-btn btn btn-outline btn-sm ${currentPage === 1 ? 'bg-blue-500 text-white' : ''}" data-page="1">1</button>`);
          }
          
          // Add ellipsis if needed
          if (currentPage > 3) {
            pages.push('<span class="px-2 text-gray-500">...</span>');
          }
          
          // Add pages around current page
          for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) {
            pages.push(`<button class="${tabType === 'inProgress' ? 'in-progress' : tabType === 'pageTypeMore' ? 'pagetype-more' : tabType}-page-btn btn btn-outline btn-sm ${currentPage === i ? 'bg-blue-500 text-white' : ''}" data-page="${i}">${i}</button>`);
          }
          
          // Add ellipsis if needed
          if (currentPage < lastPage - 2) {
            pages.push('<span class="px-2 text-gray-500">...</span>');
          }
          
          // Always show last page
          if (lastPage > 1) {
            pages.push(`<button class="${tabType === 'inProgress' ? 'in-progress' : tabType === 'pageTypeMore' ? 'pagetype-more' : tabType}-page-btn btn btn-outline btn-sm ${currentPage === lastPage ? 'bg-blue-500 text-white' : ''}" data-page="${lastPage}">${lastPage}</button>`);
          }
          
          return pages.join('');
        }

        // Change page for a specific tab
        function changePage(tabType, page) {
          const pagination = state.pagination[tabType];
          
          if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
            return;
          }
          
          switch (tabType) {
            case 'pending':
              renderPendingFiles(page);
              break;
            case 'inProgress':
              renderInProgressFiles(page);
              break;
            case 'completed':
              renderCompletedFilesTable(page);
              break;
            case 'pageTypeMore':
              renderPageTypeMoreFiles(page);
              break;
          }
        }

        // Load and render PageType More files
        async function renderPageTypeMoreFiles(page = 1) {
          if (!elements.pageTypeMoreTableBody) return;
          
          try {
            const response = await fetch(`{{ route("pagetyping.api.pagetype-more-files") }}?page=${page}&limit=10`);
            const data = await response.json();
            
            console.log('PageType More API Response:', data); // Debug log
            
            if (data.success && data.files && data.files.length > 0) {
              // Update pagination state
              if (data.pagination) {
                state.pagination.pageTypeMore = {
                  currentPage: data.pagination.current_page,
                  total: data.pagination.total,
                  lastPage: data.pagination.last_page,
                  perPage: data.pagination.per_page
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
                      <button class="btn btn-ghost btn-sm view-combined" data-id="${file.id}" title="View Combined File">
                        <i data-lucide="eye" class="h-4 w-4"></i>
                      </button>
                      <button class="btn btn-primary btn-sm pagetype-more-action" data-id="${file.id}" title="PageType More">
                        <i data-lucide="edit" class="h-4 w-4 mr-1"></i>
                        PageType More
                      </button>
                    </div>
                  </td>
                </tr>
              `).join('');
              
              // Add event listeners for PageType More actions
              document.querySelectorAll('.pagetype-more-action').forEach(btn => {
                btn.addEventListener('click', () => {
                  const fileId = btn.getAttribute('data-id');
                  // Open typing view in PageType More mode
                  startPageTyping(fileId, { pageTypeMore: true });
                });
              });
              
              document.querySelectorAll('.view-combined').forEach(btn => {
                btn.addEventListener('click', () => {
                  const fileId = btn.getAttribute('data-id');
                  // For now, open typing view; later this could open a read-only combined preview
                  startPageTyping(fileId, { pageTypeMore: true });
                });
              });
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
                thumbnailUrl: `/storage/app/public/${thumb.thumbnail_path}`,
                scanning: pdfScanning,
                isSplitPage: true,
                isFromCache: true
              }));
              
              return true;
            }

            console.log('No existing thumbnails found, splitting PDF...');
            const pdfUrl = getDocumentUrl(pdfScanning.document_path);
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
                      ? state.typingState.batchMode
                        ? "Select pages to type in batch mode"
                        : "Select a page to type or categorize"
                      : state.typingState.selectedPageInFolder !== null
                        ? `Categorizing Page ${state.typingState.selectedPageInFolder + 1}`
                        : `Typing Page ${state.typingState.currentPage} of ${file.total_pages}`}
                  </p>
                </div>
                <div class="flex items-center gap-2">
                  ${state.typingState.showFolderView && state.typingState.selectedPageInFolder === null
                    ? `<button class="btn ${state.typingState.batchMode ? 'btn-primary' : 'btn-outline'} btn-sm toggle-batch-mode">
                        <i data-lucide="check-square" class="h-4 w-4 mr-1"></i>
                        ${state.typingState.batchMode ? 'Exit Batch Mode' : 'Batch Mode'}
                      </button>`
                    : ''}
                  <button class="btn btn-outline btn-sm back-button">
                    ${state.typingState.selectedPageInFolder !== null ? 'Back to Folder' : (state.pageTypeMoreMode ? 'Back to PageType More' : 'Back to Dashboard')}
                  </button>
                </div>
              </div>
            </div>
          `;

          if (state.typingState.showFolderView) {
            if (state.typingState.selectedPageInFolder !== null) {
              // Page categorization view with CoverType
              content = `
                ${headerContent}
                <div class="p-6">
                  <div class="space-y-6">
                    <div class="flex justify-between items-center">
                      <h3 class="text-lg font-medium">Categorize Page ${state.typingState.selectedPageInFolder + 1}</h3>
                      <span class="badge bg-blue-500 text-white">${file.file_number}</span>
                    </div>

                    <!-- Horizontal File Browser -->
                    <div class="border rounded-lg p-4 bg-gray-50">
                      <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-medium text-gray-700">Quick File Browser</h4>
                        <div class="flex items-center gap-2">
                          <button class="btn btn-ghost btn-icon file-browser-prev" title="Previous Files">
                            <i data-lucide="chevron-left" class="h-4 w-4"></i>
                          </button>
                          <span class="text-xs text-gray-500" id="file-browser-indicator">1-5 of ${file.scannings.length}</span>
                          <button class="btn btn-ghost btn-icon file-browser-next" title="Next Files">
                            <i data-lucide="chevron-right" class="h-4 w-4"></i>
                          </button>
                        </div>
                      </div>
                      
                      <div class="horizontal-file-browser relative">
                        <div class="file-browser-container overflow-hidden">
                          <div class="file-browser-strip flex gap-2 transition-transform duration-300" id="file-browser-strip">
                            ${file.scannings.map((scanning, index) => {
                              const isCurrentPage = index === state.typingState.selectedPageInFolder;
                              const isProcessed = state.typingState.processedPages[index];
                              const url = getDocumentUrl(scanning.document_path);
                              const img = isImageFile(scanning.original_filename);
                              const pdf = isPDFFile(scanning.original_filename);
                              const canvasId = 'file-browser-thumb-' + index;
                              const imgId = 'file-browser-img-' + index;
                              
                              return '<div class="file-browser-item flex-shrink-0 cursor-pointer transition-all duration-200 ' + (isCurrentPage ? 'ring-2 ring-blue-500 shadow-md' : 'hover:shadow-sm') + '" ' +
                                     'data-file-index="' + index + '" ' +
                                     'style="width: 80px;" ' +
                                     'tabindex="0" ' +
                                     'role="button" ' +
                                     'aria-label="Page ' + (index + 1) + ' - ' + scanning.original_filename + '" ' +
                                     'title="Click to select page ' + (index + 1) + '">' +
                                  '<div class="relative h-20 w-20 bg-gray-100 rounded-md overflow-hidden border ' + (isCurrentPage ? 'border-blue-500' : 'border-gray-200') + '">' +
                                    (isProcessed ? '<div class="absolute top-1 right-1 z-10"><span class="badge bg-green-500 text-white text-xs px-1 py-0.5"><i data-lucide="check" class="h-2 w-2"></i></span></div>' : '') +
                                    (isCurrentPage ? '<div class="absolute top-1 left-1 z-10"><span class="badge bg-blue-500 text-white text-xs px-1 py-0.5"><i data-lucide="eye" class="h-2 w-2"></i></span></div>' : '') +
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
                                    '<span class="text-xs text-gray-600 font-medium">' + (index + 1) + '</span>' +
                                    (isProcessed ? '<div class="text-xs text-green-600 font-medium truncate">' + (isProcessed.page_code || 'Typed') + '</div>' : '') +
                                  '</div>' +
                                '</div>';
                            }).join('')}
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <div class="border rounded-md p-4 h-[400px] bg-white relative" id="document-preview-container">
                          <!-- Full screen button -->
                          <button class="absolute top-2 right-2 z-10 btn btn-ghost btn-icon fullscreen-btn" title="Full Screen View">
                            <i data-lucide="maximize" class="h-4 w-4"></i>
                          </button>
                          <!-- Document preview rendered dynamically -->
                        </div>
                      </div>

                      <div class="space-y-6">
                        ${!file.is_existing_file ? `
                  
                        ` : ''}

                        <div class="space-y-4">
                          <div>
                            <label for="cover-type" class="block text-sm font-medium mb-1.5">Cover Type</label>
                            <select id="cover-type" class="input">
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
                            <select id="page-type" class="input">
                              ${pageTypes.map(type =>
                                `<option value="${type.id}" ${state.typingState.pageType == type.id ? 'selected' : ''}>
                                  ${type.name} (${type.code})
                                </option>`
                              ).join('')}
                            </select>
                          </div>

                          <div>
                            <label for="page-subtype" class="block text-sm font-medium mb-1.5">Page Subtype</label>
                            <select id="page-subtype" class="input">
                              ${pageSubTypes[parseInt(state.typingState.pageType)]?.map(subtype =>
                                `<option value="${subtype.id}" ${state.typingState.pageSubType == subtype.id ? 'selected' : ''}>
                                  ${subtype.name} (${subtype.code})
                                </option>`
                              ).join('') || '<option value="">Select page type first</option>'}
                            </select>
                          </div>

                          <div>
                            <label for="serial-no" class="block text-sm font-medium mb-1.5">Serial Number</label>
                            <input id="serial-no" value="${state.typingState.serialNo}" class="input" maxlength="2" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            <p class="text-xs text-muted-foreground mt-1">Auto-calculated: ${state.typingState.serialNo}</p>
                          </div>
                        </div>

                        <div class="p-4 border rounded-md bg-muted/30">
                          <h4 class="font-medium mb-2">Page Code Preview</h4>
                          <div class="flex items-center gap-2">
                            <span class="badge bg-blue-500 text-white text-base py-1 px-3">
                              ${getCoverTypeById(state.typingState.coverType)?.code || 'XX'}-${getPageTypeById(state.typingState.pageType)?.code || 'XX'}-${getPageSubTypeById(state.typingState.pageType, state.typingState.pageSubType)?.code || 'XX'}-${state.typingState.serialNo}
                            </span>
                          </div>
                          <p class="text-xs text-muted-foreground mt-2">
                            Format: CoverType-PageType-SubType-SerialNo<br>
                            This code will be assigned to the page for easy identification and retrieval.
                          </p>
                        </div>

                        <button class="btn btn-primary w-full process-page">
                          Process Page
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              `;
            } else {
              // Folder view - Show original files without splitting
              const pagesHtml = file.scannings.map((scanning, index) => {
                const isProcessed = state.typingState.processedPages[index];
                const url = getDocumentUrl(scanning.document_path);
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
                      ${isProcessed ? `<div class=\"mt-1\"><span class=\"badge bg-blue-500 text-white text-xs w-full justify-center\">${isProcessed.page_code ? isProcessed.page_code : `${getCoverTypeById(isProcessed.coverType)?.code || ''}-${getPageTypeById(isProcessed.pageType)?.code || ''}-${getPageSubTypeById(isProcessed.pageType, isProcessed.pageSubType)?.code || ''}-${isProcessed.serialNo || ''}`}</span></div>` : ''}
                    </div>
                  </div>`;
              }).join('');
              
              content = `
                ${headerContent}
                <div class="p-6">
                  <div class="space-y-6">
                    <div class="flex justify-between items-center">
                      <h3 class="text-lg font-medium">File Pages</h3>
                      <span class="badge bg-blue-500 text-white">${file.file_number}</span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="folder-pages">
                      ${pagesHtml}
                    </div>
                  </div>
                </div>
              `;
            }
          }

          elements.typingCard.innerHTML = content;
          lucide.createIcons();

          // Generate thumbnails for folder view (PDF and images)
          file.scannings.forEach((scanning, index) => {
            const url = getDocumentUrl(scanning.document_path);
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
            page.addEventListener('click', () => {
              const index = parseInt(page.getAttribute('data-index'));
              state.typingState.selectedPageInFolder = index;

              // Check if this page is already processed
              const existingData = state.typingState.processedPages[index];
              if (existingData) {
                // Populate form fields with existing data
                state.typingState.coverType = existingData.coverType || state.typingState.coverType;
                state.typingState.pageType = existingData.pageType || state.typingState.pageType;
                state.typingState.pageSubType = existingData.pageSubType || state.typingState.pageSubType;
                state.typingState.serialNo = existingData.serialNo || state.typingState.serialNo;

                // Disable form fields and process button for completed pages
                setTimeout(() => {
                  setFormElementsState(false, 'Already Processed');
                }, 100);
              } else {
                // Enable form fields and process button for new pages
                setTimeout(() => {
                  setFormElementsState(true, 'Process Page');
                }, 100);
              }

              updateUI();
            });
          });

          // Horizontal File Browser functionality
          initializeHorizontalFileBrowser(file);

          // File browser item click handlers
          document.querySelectorAll('.file-browser-item').forEach(item => {
            item.addEventListener('click', () => {
              const index = parseInt(item.getAttribute('data-file-index'));
              state.typingState.selectedPageInFolder = index;

              // Check if this page is already processed
              const existingData = state.typingState.processedPages[index];
              if (existingData) {
                // Populate form fields with existing data
                state.typingState.coverType = existingData.coverType || state.typingState.coverType;
                state.typingState.pageType = existingData.pageType || state.typingState.pageType;
                state.typingState.pageSubType = existingData.pageSubType || state.typingState.pageSubType;
                state.typingState.serialNo = existingData.serialNo || state.typingState.serialNo;

                // Disable form fields and process button for completed pages
                setTimeout(() => {
                  setFormElementsState(false, 'Already Processed');
                }, 100);
              } else {
                // Enable form fields and process button for new pages
                setTimeout(() => {
                  setFormElementsState(true, 'Process Page');
                }, 100);
              }

              updateUI();
            });
          });

          // Cover type change
          document.querySelector('#cover-type')?.addEventListener('change', (e) => {
            state.typingState.coverType = e.target.value;
            updateUI();
          });

          // Page type change
          document.querySelector('#page-type')?.addEventListener('change', async (e) => {
            state.typingState.pageType = e.target.value;
            state.typingState.pageSubType = pageSubTypes[parseInt(e.target.value)]?.[0]?.id.toString() || '1';
            
            // If this is an existing file, update serial number based on page type
            if (state.typingState.isExistingFile && e.target.value) {
              try {
                const response = await fetch(`{{ route("pagetyping.api.next-serial-for-page-type") }}?file_indexing_id=${file.id}&page_type_id=${e.target.value}`);
                const data = await response.json();
                
                if (data.success) {
                  state.typingState.serialNo = data.next_serial_formatted;
                  console.log('Updated serial for existing file page type:', data.next_serial, 'logic:', data.logic_used);
                } else {
                  console.warn('Could not get serial for page type:', data.message);
                }
              } catch (error) {
                console.error('Error fetching serial for page type:', error);
              }
            }
            
            updateUI();
          });

          // Page subtype change
          document.querySelector('#page-subtype')?.addEventListener('change', (e) => {
            state.typingState.pageSubType = e.target.value;
            updateUI();
          });

          // Serial number change (keep editable but seeded from backend)
          document.querySelector('#serial-no')?.addEventListener('input', (e) => {
            // keep only digits and enforce two digits
            const cleaned = (e.target.value || '').replace(/\D/g, '').slice(0, 2);
            e.target.value = cleaned;
            state.typingState.serialNo = cleaned.padStart(2, '0');
            updateUI();
          });

          // Process page
          document.querySelector('.process-page')?.addEventListener('click', async () => {
            if (state.typingState.selectedPageInFolder === null) return;

            // Save page typing to backend with CoverType
            const selected = file.scannings[state.typingState.selectedPageInFolder];
            const pageData = {
              file_indexing_id: file.id,
              scanning_id: selected?.id || null,
              page_number: state.typingState.selectedPageInFolder + 1,
              cover_type_id: parseInt(state.typingState.coverType),
              page_type: state.typingState.pageType,
              page_subtype: state.typingState.pageSubType,
              serial_number: parseInt(state.typingState.serialNo),
              page_code: `${getCoverTypeById(state.typingState.coverType)?.code}-${getPageTypeById(state.typingState.pageType)?.code}-${getPageSubTypeById(state.typingState.pageType, state.typingState.pageSubType)?.code}-${state.typingState.serialNo}`,
              file_path: `storage\\app\\public\\EDMS\\PAGETYPING\\${file.file_number}.pdf`
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
                // Mark page as processed (store page_code for display)
                state.typingState.processedPages[state.typingState.selectedPageInFolder] = {
                  coverType: state.typingState.coverType,
                  pageType: state.typingState.pageType,
                  pageSubType: state.typingState.pageSubType,
                  serialNo: state.typingState.serialNo,
                  page_code: pageData.page_code
                };

                // Increment serial number
                const nextSerialNo = parseInt(state.typingState.serialNo) + 1;
                state.typingState.serialNo = nextSerialNo.toString().padStart(2, '0');

                // Go back to folder view
                state.typingState.selectedPageInFolder = null;
                
                Swal.fire({
                  icon: 'success',
                  title: 'Page Processed Successfully!',
                  text: 'The page has been categorized and saved.',
                  confirmButtonColor: '#28a745',
                  timer: 2000,
                  timerProgressBar: true
                });
                updateUI();
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
          });

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

        // Event handlers
        function switchTab(tabId) {
          state.activeTab = tabId;
          updateUI();
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', () => {
          // Load page typing data on startup
          loadPageTypingData();
          
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

          // Close fullscreen on escape key
          document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !document.getElementById('fullscreen-modal').classList.contains('hidden')) {
              closeFullscreenView();
            }
          });
        });
        
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
    </script>

    <!-- Full Screen Modal -->
    <div id="fullscreen-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-90">
      <div class="relative w-full h-full flex items-center justify-center">
        <!-- Close button -->
        <button class="absolute top-4 right-4 z-10 btn btn-ghost btn-icon text-white hover:bg-white hover:bg-opacity-20 close-fullscreen" title="Exit Full Screen">
          <i data-lucide="x" class="h-6 w-6"></i>
        </button>

        <!-- Full screen content container -->
        <div class="w-full h-full max-w-6xl max-h-screen p-8">
          <div class="w-full h-full bg-white rounded-lg shadow-2xl overflow-hidden" id="fullscreen-content">
            <!-- Content will be cloned here -->
          </div>
        </div>
      </div>
    </div>

@endsection
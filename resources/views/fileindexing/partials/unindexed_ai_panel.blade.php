     <div class="container py-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-4 gap-6 mb-6">
      <!-- Unindexed Files Card -->
      <div class="card p-6 border-l-4 border-orange-500 bg-gradient-to-r from-orange-50 to-white">
        <div class="flex items-center justify-between">
          <div>
            <div class="card-title mb-2 text-orange-700">Unindexed Files</div>
            <div class="text-3xl font-bold mb-2 text-orange-600" id="pending-files-count">3</div>
            <div class="text-sm text-orange-500">Files waiting to be indexed</div>
          </div>
          <div class="p-3 bg-orange-100 rounded-full">
            <i data-lucide="folder-open" class="h-8 w-8 text-orange-600"></i>
          </div>
        </div>
      </div>

      <!-- Indexed Today Card -->
      <div class="card p-6 border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-white">
        <div class="flex items-center justify-between">
          <div>
            <div class="card-title mb-2 text-green-700">Indexed Today</div>
            <div class="text-3xl font-bold mb-2 text-green-600" id="indexed-files-count">2</div>
            <div class="text-sm text-green-500">Files indexed today</div>
          </div>
          <div class="p-3 bg-green-100 rounded-full">
            <i data-lucide="check-circle" class="h-8 w-8 text-green-600"></i>
          </div>
        </div>
      </div>

      <!-- Total Indexed Files Card -->
      <div class="card p-6 border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-white">
        <div class="flex items-center justify-between">
          <div>
            <div class="card-title mb-2 text-blue-700">Total Indexed Files</div>
            <div class="text-3xl font-bold mb-2 text-blue-600" id="total-indexed-count">0</div>
            <div class="text-sm text-blue-500">All time indexed files</div>
          </div>
          <div class="p-3 bg-blue-100 rounded-full">
            <i data-lucide="database" class="h-8 w-8 text-blue-600"></i>
          </div>
        </div>
      </div>

      <!-- Next Steps Card -->
      <div class="card p-6 border-l-4 border-purple-500 bg-gradient-to-r from-purple-50 to-white">
        <div class="flex items-center justify-between">
          <div>
            <div class="card-title mb-2 text-purple-700">Next Steps</div>
            <div class="text-3xl font-bold mb-2 flex items-center text-purple-600">
              Scanning
              <span class="badge badge-purple ml-2 text-xs">Stage 2</span>
            </div>
            <div class="text-sm text-purple-500">After indexing, proceed to scanning</div>
          </div>
          <div class="p-3 bg-purple-100 rounded-full">
            <i data-lucide="scan-line" class="h-8 w-8 text-purple-600"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs and New File Button -->
    <div class="flex justify-between items-center mb-6">
        <div class="tabs" id="main-tabs">
        <div class="tab active" data-tab="pending">Unindexed Files</div>
        <div class="tab" data-tab="indexing">Digital Index (AI)</div>
       
        <a href="{{ route('indexed-files.index') }}"
           >Indexed Files</a>
       <div class="tab" data-tab="batch-history">Tracking Sheet</div>
      </div>
      <div class="flex items-center gap-3">
        <!-- <a href="/unindexed-scanning" class="btn btn-outline">
          <i data-lucide="upload" class="h-4 w-4 mr-2"></i>
          Go to Unindexed Files
        </a> -->
        <!-- <a href="{{ route('fileindexing.signin') }}" class="btn btn-outline">
          <i data-lucide="clipboard-list" class="h-4 w-4 mr-2"></i>
          Sign In & Out
        </a> -->
        <a href="http://tool.klaes.com.ng/" id="import-csv-btn" class="bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-medium py-2.5 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none" target="_blankho">
          <i data-lucide="upload" class="h-4 w-4"></i>
          Import CSV
        </a>

{{-- 
          <a href="{{ route('fileindexing.import.form') }}" id="import-csv-btn" class="bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-medium py-2.5 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none">
          <i data-lucide="upload" class="h-4 w-4"></i>
          Import CSV
        </a> --}}
        <a href="{{ route('fileindexing.create') }}" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-medium py-2.5 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-2" id="new-file-index-btn">
          <i data-lucide="folder-plus" class="h-4 w-4"></i>
          Index a New File
        </a>
      </div>
    </div>

    <!-- Pending Files Tab Content -->
    <div class="tab-content active" id="pending-tab">
      @include('fileindexing.partials.unindexed_pending_panel')
    </div>

    <!-- Digital Index (AI) Tab Content -->
    <div class="tab-content hidden" id="indexing-tab">
      @include('fileindexing.partials.digital_index_content')
    </div>

    <!-- Tracking Sheet Tab Content -->
    <div class="tab-content hidden" id="batch-history-tab">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Tracking Sheet Management</h3>
          <p class="text-sm text-gray-500">Manage tracking sheet generation and view history.</p>
        </div>
        
        <!-- Sub-tabs for Tracking Sheet -->
        <div class="tracking-sub-tabs border-b border-gray-200 px-6">
          <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button class="tracking-sub-tab-btn active border-transparent text-blue-600 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm" data-subtab="not-generated">
              Not Generated
            </button>
            <button class="tracking-sub-tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm" data-subtab="generated">
              Generated
            </button>
          </nav>
        </div>

        <!-- Not Generated Sub-tab Content -->
        <div class="tracking-sub-content" id="not-generated-subtab">
          <div class="card-content">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
              <div>
                <h4 class="text-lg font-medium">Indexed Files - Not Generated</h4>
                <p class="text-sm text-gray-500">Files that have not yet had tracking sheets generated.</p>
              </div>
              <div class="flex items-center gap-4 w-full md:w-auto">
                <button class="btn btn-outline gap-2" id="refresh-not-generated">
                  <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                  Refresh
                </button>
              </div>
            </div>
            
            <!-- Selection Controls for Not Generated Files -->
            <div class="mb-4 p-4 bg-gray-50 rounded-lg border">
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex flex-wrap items-center gap-4">
                  <div class="flex items-center">
                    <input type="checkbox" id="select-all-not-generated-main-checkbox" class="mr-2">
                    <label for="select-all-not-generated-main-checkbox" class="text-sm font-medium">Select All</label>
                  </div>
                  <div class="flex items-center gap-2">
                    <label for="batch-selection-dropdown" class="text-sm font-medium text-gray-700">Select Batch:</label>
                    <select id="batch-selection-dropdown" class="border border-gray-300 rounded px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                      <option value="">-- Select a Batch --</option>
                      <!-- Options will be populated by JavaScript -->
                    </select>
                    <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium py-1.5 px-3 rounded transition-colors duration-200" id="load-batch-files-btn" disabled>
                      <i data-lucide="download" class="h-3 w-3 mr-1"></i>
                      Load Batch
                    </button>
                  </div>
                  <span class="text-sm text-gray-500" id="selected-not-generated-files-count">0 selected</span>
                </div>
                <div class="flex items-center gap-3">
                  <button class="btn btn-primary gap-2" id="generate-tracking-sheets-btn" disabled>
                    <i data-lucide="file-check" class="h-4 w-4"></i>
                    <span id="tracking-btn-text">Generate Batch Tracking Sheets</span>
                  </button>
                </div>
              </div>
            </div>

            <div id="not-generated-empty-state" class="rounded-md border p-8 text-center" style="display: none;">
              <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                <i data-lucide="file-text" class="h-6 w-6 text-gray-400"></i>
              </div>
              <h3 class="mb-2 text-lg font-medium">All files have tracking sheets</h3>
              <p class="mb-4 text-sm text-gray-500">
                All indexed files have tracking sheets generated.
              </p>
            </div>
            
            <div id="not-generated-table-container" class="rounded-md border overflow-x-auto">
              <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-gray-50">
                  <tr class="border-b">
                    <th class="p-3 w-10">
                      <input type="checkbox" id="select-all-not-generated-checkbox" />
                    </th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-120">Tracking ID</th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-100">Indexed Date</th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-150">File No</th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-200">File Name</th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-100">Registry</th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-100">Batch No</th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-100">Status</th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-120">Actions</th>
                  </tr>
                </thead>
                <tbody id="not-generated-table-body">
                  <!-- Table rows will be inserted here by JavaScript -->
                </tbody>
              </table>
            </div>

            <!-- Pagination for Not Generated -->
            <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6 mt-4" id="not-generated-pagination" style="display: none;">
              <div class="flex-1 flex justify-between sm:hidden">
                <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50" id="not-generated-prev-mobile">
                  Previous
                </button>
                <button class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50" id="not-generated-next-mobile">
                  Next
                </button>
              </div>
              <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                  <p class="text-sm text-gray-700">
                    Showing <span class="font-medium" id="not-generated-start">1</span> to <span class="font-medium" id="not-generated-end">10</span> of <span class="font-medium" id="not-generated-total">0</span> results
                  </p>
                </div>
                <div>
                  <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination" id="not-generated-pagination-nav">
                    <button class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" id="not-generated-prev">
                      <span class="sr-only">Previous</span>
                      <i data-lucide="chevron-left" class="h-4 w-4"></i>
                    </button>
                    <!-- Page numbers will be inserted here by JavaScript -->
                    <button class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" id="not-generated-next">
                      <span class="sr-only">Next</span>
                      <i data-lucide="chevron-right" class="h-4 w-4"></i>
                    </button>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Generated Sub-tab Content (existing batch history) -->
        <div class="tracking-sub-content hidden" id="generated-subtab">
          <div class="card-content">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
              <div>
                <h4 class="text-lg font-medium">Generated Tracking Sheets</h4>
                <p class="text-sm text-gray-500">View and reprint previously generated batch tracking sheets.</p>
              </div>
              <div class="flex items-center gap-4 w-full md:w-auto">
                <button class="btn btn-outline gap-2" id="refresh-batch-history">
                  <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                  Refresh
                </button>
              </div>
            </div>

            <div id="batch-history-empty-state" class="rounded-md border p-8 text-center" style="display: none;">
              <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                <i data-lucide="file-stack" class="h-6 w-6 text-gray-400"></i>
              </div>
              <h3 class="mb-2 text-lg font-medium">No batch history yet</h3>
              <p class="mb-4 text-sm text-gray-500">
                Generate batch tracking sheets to see them here
              </p>
              <a href="{{ route('indexed-files.index') }}" class="btn btn-primary gap-2" id="go-to-indexed-from-batch">
                Go to Indexed Files
              </a>
            </div>
            
            <div id="batch-history-table-container" class="rounded-md border overflow-x-auto">
              <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-gray-50">
                  <tr class="border-b">
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide cursor-pointer min-w-150" data-sort="batch_id">
                      <div class="flex items-center">
                        Batch ID
                        <i data-lucide="arrow-up-down" class="ml-2 h-4 w-4"></i>
                      </div>
                    </th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide cursor-pointer min-w-200" data-sort="batch_name">
                      <div class="flex items-center">
                        Batch Name
                        <i data-lucide="arrow-up-down" class="ml-2 h-4 w-4"></i>
                      </div>
                    </th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide cursor-pointer min-w-100" data-sort="file_count">
                      <div class="flex items-center">
                        File Count
                        <i data-lucide="arrow-up-down" class="ml-2 h-4 w-4"></i>
                      </div>
                    </th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide cursor-pointer min-w-120" data-sort="batch_type">
                      <div class="flex items-center">
                        Type
                        <i data-lucide="arrow-up-down" class="ml-2 h-4 w-4"></i>
                      </div>
                    </th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide cursor-pointer min-w-100" data-sort="status">
                      <div class="flex items-center">
                        Status
                        <i data-lucide="arrow-up-down" class="ml-2 h-4 w-4"></i>
                      </div>
                    </th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide cursor-pointer min-w-120" data-sort="generated_at">
                      <div class="flex items-center">
                        Generated
                        <i data-lucide="arrow-up-down" class="ml-2 h-4 w-4"></i>
                      </div>
                    </th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide cursor-pointer min-w-100" data-sort="print_count">
                      <div class="flex items-center">
                        Print Count
                        <i data-lucide="arrow-up-down" class="ml-2 h-4 w-4"></i>
                      </div>
                    </th>
                    <th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide min-w-120">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody id="batch-history-table-body">
                  <!-- Table rows will be inserted here by JavaScript -->
                </tbody>
              </table>
            </div>

            <!-- Pagination for Batch History -->
            <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6 mt-4" id="batch-history-pagination" style="display: none;">
              <div class="flex-1 flex justify-between sm:hidden">
                <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50" id="batch-history-prev-mobile">
                  Previous
                </button>
                <button class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50" id="batch-history-next-mobile">
                  Next
                </button>
              </div>
              <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                  <p class="text-sm text-gray-700">
                    Showing <span class="font-medium" id="batch-history-start">1</span> to <span class="font-medium" id="batch-history-end">10</span> of <span class="font-medium" id="batch-history-total">0</span> results
                  </p>
                </div>
                <div>
                  <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination" id="batch-history-pagination-nav">
                    <button class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" id="batch-history-prev">
                      <span class="sr-only">Previous</span>
                      <i data-lucide="chevron-left" class="h-4 w-4"></i>
                    </button>
                    <!-- Page numbers will be inserted here by JavaScript -->
                    <button class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" id="batch-history-next">
                      <span class="sr-only">Next</span>
                      <i data-lucide="chevron-right" class="h-4 w-4"></i>
                    </button>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal for batch details -->
    <div id="batch-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-90vh overflow-y-auto m-4">
        <div class="flex justify-between items-center p-6 border-b">
          <h3 class="text-lg font-semibold" id="batch-modal-title">Batch Details</h3>
          <button class="text-gray-400 hover:text-gray-600" id="close-batch-modal" onclick="closeBatchDetailsModal()">
            <i data-lucide="x" class="h-6 w-6"></i>
          </button>
        </div>
        <div id="batch-modal-content" class="p-6">
          <!-- Batch details will be inserted here -->
        </div>
        <div class="flex justify-end gap-3 p-6 border-t">
          <button class="btn btn-outline" onclick="closeBatchDetailsModal()">Close</button>
          <button class="btn btn-primary" id="reprint-batch-btn">
            <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
            Reprint Batch
          </button>
        </div>
      </div>
    </div>

    <!-- Modal for file details -->
    <div id="file-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-90vh overflow-y-auto m-4">
        <div class="flex justify-between items-center p-6 border-b">
          <h3 class="text-lg font-semibold" id="modal-title">File Details</h3>
          <button class="text-gray-400 hover:text-gray-600" id="close-modal" onclick="closeFileDetailsModal()">
            <i data-lucide="x" class="h-6 w-6"></i>
          </button>
        </div>
        <div id="modal-content" class="p-6">
          <!-- File details will be inserted here -->
        </div>
      </div>
    </div>

  {{-- Property Record Transaction Modal --}}

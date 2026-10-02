@extends('layouts.app')
@section('page-title')
    {{ __('Survey Plan Extraction') }}
@endsection

 
@section('content')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<!-- Tesseract.js for OCR -->
<script src="https://unpkg.com/tesseract.js@4/dist/tesseract.min.js"></script>
 <script>
// Tailwind config
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: '#3b82f6',
        'primary-foreground': '#ffffff',
        muted: '#f3f4f6',
        'muted-foreground': '#6b7280',
        border: '#e5e7eb',
        destructive: '#ef4444',
        'destructive-foreground': '#ffffff',
        secondary: '#f1f5f9',
        'secondary-foreground': '#0f172a',
      }
    }
  }
}
</script>

<style>
/* Loading spinner animation */
.loading-spinner {
  width: 1rem;
  height: 1rem;
  border: 2px solid #e5e7eb;
  border-top: 2px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* File drop zone styles */
.file-drop-zone {
  border: 2px dashed #d1d5db;
  transition: all 0.3s ease;
}

.file-drop-zone:hover {
  border-color: #3b82f6;
  background-color: #f8fafc;
}

.file-drop-zone.dragover {
  border-color: #3b82f6;
  background-color: #eff6ff;
}

/* Progress bar animation */
.progress-bar {
  transition: width 0.5s ease-in-out;
}

/* AI stage indicator animations */
.stage-indicator {
  transition: all 0.3s ease;
}

.stage-indicator.active {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Tab styles */
.tab-trigger {
  transition: all 0.2s ease;
}

.tab-trigger.active {
  background-color: white;
  color: #1f2937;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

/* Modal backdrop */
.modal-backdrop {
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

/* Badge styles */
.badge {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-success {
  background-color: #dcfce7;
  color: #166534;
}

.badge-warning {
  background-color: #fef3c7;
  color: #92400e;
}

.badge-error {
  background-color: #fee2e2;
  color: #991b1b;
}

.badge-default {
  background-color: #f3f4f6;
  color: #374151;
}

/* Course and beacon coordinate cards */
.data-card {
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 0.75rem;
}

/* Collapsible content */
.collapsible-content {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease;
}

.collapsible-content.expanded {
  max-height: 1000px;
}
</style>
<div class="flex-1 overflow-auto">
    <!-- Header -->
   @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
      <div class="container mx-auto py-6 space-y-6 max-w-7xl px-4 sm:px-6 lg:px-8">
        <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow border border-gray-200">
      <div class="p-6 pb-2">
        <h3 class="text-sm font-medium text-gray-600">Plans Selected</h3>
      </div>
      <div class="px-6 pb-6">
        <div id="selected-count" class="text-2xl font-bold">0</div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200">
      <div class="p-6 pb-2">
        <h3 class="text-sm font-medium text-gray-600">Processed / Extracted</h3>
      </div>
      <div class="px-6 pb-6">
        <div id="processed-count" class="text-2xl font-bold">0</div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200">
      <div class="p-6 pb-2">
        <h3 class="text-sm font-medium text-gray-600">AI Analysis Status</h3>
      </div>
      <div class="px-6 pb-6">
        <div class="text-lg font-bold flex items-center">
          <span id="ai-status-text">Ready</span>
          <span id="ai-status-badge" class="badge badge-default ml-2">Idle</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Tabs -->
  <div class="w-full">
    <!-- Tab Navigation -->
    <div class="grid grid-cols-2 bg-gray-100 rounded-lg p-1 mb-6">
      <button id="tab-upload" class="tab-trigger px-4 py-2 rounded-md bg-white text-gray-900 shadow-sm font-medium text-sm transition-all active">
        Upload Survey Plans
      </button>
      <button id="tab-extracted" class="tab-trigger px-4 py-2 rounded-md bg-transparent text-gray-600 hover:bg-gray-50 font-medium text-sm transition-all" disabled>
        Extracted Data
      </button>
    </div>

    <!-- Upload Tab Content -->
    <div id="content-upload" class="tab-content active">
      <div class="bg-white rounded-lg shadow border border-gray-200">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-900">Upload Survey Plans</h2>
          <p class="text-sm text-gray-600 mt-1">Upload scanned survey plan documents (PDF, JPG, PNG, TIFF, WebP). Max 5 files.</p>
        </div>
        
        <div class="p-6 space-y-6">
          <!-- File Upload Area -->
          <div id="upload-area" class="file-drop-zone rounded-lg p-8 text-center cursor-pointer">
            <input
              id="file-input"
              type="file"
              multiple
              accept=".pdf,.jpg,.jpeg,.png,.tiff,.tif,.webp"
              class="hidden"
            />
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
              <i data-lucide="scan-line" class="h-6 w-6 text-gray-600"></i>
            </div>
            <h3 class="mb-2 text-lg font-medium">Drag and drop survey plans here</h3>
            <p class="mb-4 text-sm text-gray-500">or click to browse files</p>
            <button id="browse-btn" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2">
              <i data-lucide="upload" class="h-4 w-4"></i>
              Browse Files
            </button>
            <p class="text-xs text-gray-500 mt-2">Max 5 files. Supported: PDF, JPG, PNG, TIFF, WebP.</p>
          </div>

          <!-- Selected Files List -->
          <div id="selected-files" class="hidden">
            <div class="rounded-md border divide-y">
              <div class="p-3 bg-gray-50 flex justify-between items-center">
                <span id="files-count" class="font-medium">0 survey plan(s) selected</span>
                <button id="clear-all-btn" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent text-gray-700 hover:bg-gray-100">
                  Clear All
                </button>
              </div>
              <div id="files-list" class="divide-y">
                <!-- Files will be inserted here -->
              </div>
            </div>
          </div>

          <!-- Upload Progress -->
          <div id="upload-progress" class="hidden space-y-2">
            <div class="flex justify-between text-sm">
              <span id="upload-status-text">Uploading files...</span>
              <span id="upload-percentage">0%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div id="upload-progress-bar" class="bg-blue-600 h-2 rounded-full progress-bar" style="width: 0%"></div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-col md:flex-row gap-4 justify-center">
            <button id="start-upload-btn" class="hidden inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2">
              <i data-lucide="brain" class="h-4 w-4"></i>
              Start Upload & Extraction
            </button>
            <button id="cancel-upload-btn" class="hidden inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-red-600 text-white hover:bg-red-700 gap-2">
              <i data-lucide="alert-circle" class="h-4 w-4"></i>
              Cancel Upload
            </button>
            <button id="new-batch-btn" class="hidden inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
              <i data-lucide="refresh-cw" class="h-4 w-4"></i>
              Start New Batch
            </button>
          </div>

          <!-- AI Processing Visualizer -->
          <div id="ai-processing" class="hidden mt-4 p-4 bg-gray-50 border rounded-md">
            <div class="flex justify-between mb-2">
              <span class="text-sm font-medium">Survey Plan AI Analysis Pipeline</span>
              <span id="ai-progress-text" class="text-sm">0% Complete</span>
            </div>
            <div class="relative">
              <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div id="ai-progress-bar" class="h-full bg-blue-500 rounded-full transition-all duration-500 ease-in-out" style="width: 0%"></div>
              </div>
              <div class="flex justify-between mt-2">
                <div class="flex flex-col items-center stage-indicator" data-stage="0">
                  <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                  <span class="text-xs text-gray-500">Init</span>
                </div>
                <div class="flex flex-col items-center stage-indicator" data-stage="1">
                  <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                  <span class="text-xs text-gray-500">OCR</span>
                </div>
                <div class="flex flex-col items-center stage-indicator" data-stage="2">
                  <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                  <span class="text-xs text-gray-500">Layout</span>
                </div>
                <div class="flex flex-col items-center stage-indicator" data-stage="3">
                  <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                  <span class="text-xs text-gray-500">Extract</span>
                </div>
                <div class="flex flex-col items-center stage-indicator" data-stage="4">
                  <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                  <span class="text-xs text-gray-500">Assemble</span>
                </div>
                <div class="flex flex-col items-center stage-indicator" data-stage="5">
                  <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                  <span class="text-xs text-gray-500">Done</span>
                </div>
              </div>
            </div>
            <div class="mt-4 flex items-start gap-3">
              <div class="p-2 rounded-full bg-blue-100">
                <i id="ai-stage-icon" data-lucide="brain" class="h-5 w-5 text-blue-600"></i>
              </div>
              <div>
                <p id="ai-stage-title" class="text-sm font-medium mb-1">Current Stage: Initializing</p>
                <p id="ai-stage-description" class="text-xs text-gray-600">Preparing for AI analysis...</p>
                <p id="current-file-processing" class="text-xs text-blue-600 mt-1 hidden">Processing: </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Extracted Data Tab Content -->
    <div id="content-extracted" class="tab-content">
      <!-- AI Processing (when active) -->
      <div id="ai-processing-extracted" class="hidden mb-6">
        <!-- Same AI processing visualizer as above -->
      </div>

      <!-- Extracted Data Results -->
      <div id="extracted-results" class="hidden">
        <div class="bg-white rounded-lg shadow border border-gray-200">
          <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Extracted Survey Plan Data</h2>
            <p class="text-sm text-gray-600 mt-1">Review and edit the data extracted from the uploaded survey plans.</p>
          </div>
          <div class="p-6 space-y-6">
            <div id="extracted-data-list">
              <!-- Extracted data items will be inserted here -->
            </div>
            <div class="flex justify-end border-t pt-4 mt-4">
              <button id="save-to-db-btn" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2">
                <i data-lucide="database" class="h-4 w-4"></i>
                Save Extracted Data to Cadastral Records
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- No Data State -->
      <div id="no-data-state" class="text-center py-10">
        <i data-lucide="file-search" class="mx-auto h-12 w-12 text-gray-400 mb-4"></i>
        <h3 class="text-sm font-medium text-gray-900 mb-2">No data extracted yet</h3>
        <p class="text-sm text-gray-500">Upload survey plans to see extracted data here.</p>
      </div>
    </div>
  </div>
</div>

<!-- Document Preview Modal -->
<div id="preview-modal" class="fixed inset-0 z-50 hidden">
  <div class="modal-backdrop fixed inset-0" onclick="closePreviewModal()"></div>
  <div class="fixed inset-0 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
      <div class="p-6 border-b border-gray-200">
        <div class="flex justify-between items-center">
          <h2 class="text-xl font-semibold text-gray-900">Document Preview</h2>
          <button onclick="closePreviewModal()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-2 py-2 transition-all cursor-pointer bg-transparent text-gray-700 hover:bg-gray-100 h-8 w-8 p-0">
            <i data-lucide="x" class="h-4 w-4"></i>
          </button>
        </div>
      </div>
      <div class="flex-1 overflow-y-auto p-6">
        <div id="preview-content">
          <!-- Preview content will be inserted here -->
        </div>
        
        <!-- Raw OCR Text Toggle -->
        <div class="mt-6 border rounded-md">
          <button id="toggle-raw-text" onclick="toggleRawText()" class="flex justify-between items-center w-full p-3 bg-gray-50 hover:bg-gray-100 transition-colors">
            <span class="font-medium text-sm">View Raw OCR Text (for Debugging)</span>
            <i data-lucide="chevron-down" class="h-5 w-5"></i>
          </button>
          <div id="raw-text-content" class="collapsible-content">
            <div class="p-3 bg-gray-900 text-white rounded-b-md">
              <pre id="raw-text" class="text-xs whitespace-pre-wrap font-mono">No OCR text available.</pre>
            </div>
          </div>
        </div>
      </div>
      <div class="p-6 border-t border-gray-200">
        <button onclick="closePreviewModal()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Metadata Modal -->
<div id="edit-modal" class="fixed inset-0 z-50 hidden">
  <div class="modal-backdrop fixed inset-0" onclick="closeEditModal()"></div>
  <div class="fixed inset-0 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-[90vw] md:w-[80vw] lg:w-[70vw] h-[90vh] flex flex-col">
      <div class="p-4 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Edit Extracted Data & Preview</h2>
      </div>
      <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
        <!-- Form Section -->
        <div class="w-full md:w-1/2 lg:w-2/5 p-4 space-y-3 overflow-y-auto border-r">
          <h3 id="edit-file-name" class="text-md font-semibold mb-2 border-b pb-1">File: </h3>
          
          <!-- Basic Fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">File No.</label>
              <input id="edit-file-no" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Applicant Name</label>
              <input id="edit-applicant-name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Approved Plan No.</label>
              <input id="edit-approved-plan-no" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Starting Beacon No.</label>
              <input id="edit-starting-beacon-no" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" />
            </div>
          </div>

          <!-- Beacon Coordinates -->
          <div class="mt-3 space-y-3">
            <div class="flex justify-between items-center">
              <label class="text-base font-medium text-gray-700">Beacon Coordinates</label>
              <button onclick="addBeaconCoordinate()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                Add Beacon
              </button>
            </div>
            <div id="beacon-coordinates-list" class="space-y-2">
              <!-- Beacon coordinates will be inserted here -->
            </div>
          </div>

          <!-- Courses -->
          <div class="mt-4 space-y-3">
            <div class="flex justify-between items-center">
              <label class="text-base font-medium text-gray-700">Courses</label>
              <button onclick="addCourse()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                Add Course
              </button>
            </div>
            <div id="courses-list" class="space-y-2">
              <!-- Courses will be inserted here -->
            </div>
          </div>
        </div>

        <!-- Preview Section -->
        <div class="w-full md:w-1/2 lg:w-3/5 p-4 flex flex-col bg-gray-50 overflow-hidden">
          <div id="edit-preview-content" class="flex-1 overflow-y-auto mb-2 border rounded-md bg-white shadow-inner">
            <!-- Preview content will be inserted here -->
          </div>
          
          <!-- Page Navigation -->
          <div id="edit-page-nav" class="hidden flex items-center justify-center gap-2 mt-2">
            <button id="edit-prev-page" onclick="changeEditPreviewPage(-1)" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
              <i data-lucide="chevron-left" class="h-4 w-4"></i>
            </button>
            <span id="edit-page-info" class="text-sm">Page 1 of 1</span>
            <button id="edit-next-page" onclick="changeEditPreviewPage(1)" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
              <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="p-4 border-t border-gray-200 flex justify-end gap-2">
        <button onclick="closeEditModal()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
          Cancel
        </button>
        <button onclick="saveEditChanges()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700">
          Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<!-- COGO Export Modal -->
<div id="cogo-modal" class="fixed inset-0 z-50 hidden">
  <div class="modal-backdrop fixed inset-0" onclick="closeCogoModal()"></div>
  <div class="fixed inset-0 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
      <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">COGO Data Format</h2>
        <p class="text-sm text-gray-600 mt-1">Copy the formatted data below or download it as a .txt file.</p>
      </div>
      <div class="p-6">
        <textarea id="cogo-output" readonly rows="15" class="w-full font-mono text-xs whitespace-pre bg-gray-50 border border-gray-300 rounded-md p-3"></textarea>
      </div>
      <div class="p-6 border-t border-gray-200 flex flex-col sm:flex-row sm:justify-between gap-2">
        <button onclick="downloadCogoFile()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 w-full sm:w-auto gap-2">
          <i data-lucide="download" class="h-4 w-4"></i>
          Download COGO File
        </button>
        <div class="flex gap-2 w-full sm:w-auto">
          <button onclick="copyCogoData()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 flex-1">
            Copy
          </button>
          <button onclick="closeCogoModal()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-gray-200 text-gray-700 hover:bg-gray-300 flex-1">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="fixed inset-0 z-50 hidden">
  <div class="modal-backdrop fixed inset-0"></div>
  <div class="fixed inset-0 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full mx-4">
      <div class="text-center">
        <div class="loading-spinner mx-auto mb-4"></div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Processing</h3>
        <p id="loading-message" class="text-gray-600">Please wait...</p>
      </div>
    </div>
  </div>
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
  <!-- Toast messages will be inserted here -->
</div>

 
    
    </div>
    <!-- Footer -->
    @include('admin.footer')
  </div>
 @include('survey_plan_extraction.js')
@endsection

 
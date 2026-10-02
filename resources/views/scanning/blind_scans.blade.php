@extends('layouts.app')

@section('page-title')
  {{ $PageTitle ?? 'Blind Scanning' }}
@endsection

@section('content')
  <!-- Header -->

  <style>
    .text-muted-foreground {
      color: #64748b;
    }

    .bg-card {
      background: #ffffff;
    }

    .bg-muted {
      background: #f1f5f9;
    }

    .border {
      border-color: #e2e8f0;
    }

    .progress-bar {
      transition: width .3s ease;
    }

    .row:hover {
      background: #f8fafc;
    }

    .pill {
      font-size: 11px;
      border: 1px solid #e2e8f0;
      background: #f8fafc;
      border-radius: 999px;
      padding: .15rem .5rem
    }

    .btn {
      border: 1px solid #e2e8f0;
      background: #fff;
      padding: .5rem .75rem;
      border-radius: .5rem;
      font-size: .9rem;
    }

    .btn:hover {
      background: #f8fafc;
    }

    .btn-primary {
      background: #4f46e5;
      border-color: #4f46e5;
      color: #fff;
    }

    .btn-primary:hover {
      background: #4338ca;
    }

    .btn-success {
      background: #10b981;
      border-color: #10b981;
      color: #fff;
    }

    .btn-success:hover {
      background: #059669;
    }

    .btn-danger {
      background: #dc2626;
      border-color: #dc2626;
      color: #fff;
    }

    .btn-danger:hover {
      background: #b91c1c;
    }

    .preview-box {
      min-height: 200px;
    }

    /* Preview Modal Styles */
    .preview-modal {
      background: rgba(0, 0, 0, 0.9);
    }

    .preview-content {
      max-width: 95vw;
      max-height: 95vh;
    }

    .preview-toolbar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
    }

    .preview-nav-btn {
      background: rgba(255, 255, 255, 0.9);
      border: none;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      transition: all 0.2s ease;
    }

    .preview-nav-btn:hover {
      background: rgba(255, 255, 255, 1);
      transform: scale(1.1);
    }

    .preview-image {
      max-width: 100%;
      max-height: 80vh;
      object-fit: contain;
    }

    .pdf-iframe {
      width: 100%;
      height: 80vh;
      border: none;
    }

    /* Image editing styles */
    .edit-toolbar {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 8px;
      padding: 8px;
      z-index: 10;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .edit-toolbar button {
      margin: 2px;
      padding: 6px 8px;
    }

    .preview-container {
      position: relative;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .preview-image-editable {
      max-width: 100%;
      max-height: 80vh;
      transition: transform 0.3s ease;
      object-fit: contain;
    }

    .crop-overlay {
      position: absolute;
      border: 2px dashed #4f46e5;
      background: rgba(79, 70, 229, 0.1);
      cursor: move;
    }

    .crop-handle {
      position: absolute;
      width: 12px;
      height: 12px;
      background: #4f46e5;
      border: 2px solid white;
      border-radius: 50%;
    }

    .crop-handle-nw {
      top: -6px;
      left: -6px;
      cursor: nw-resize;
    }

    .crop-handle-ne {
      top: -6px;
      right: -6px;
      cursor: ne-resize;
    }

    .crop-handle-sw {
      bottom: -6px;
      left: -6px;
      cursor: sw-resize;
    }

    .crop-handle-se {
      bottom: -6px;
      right: -6px;
      cursor: se-resize;
    }
  </style>

  <div class="flex-1 overflow-auto">
    {{-- Header --}}
    @include('admin.header')

    {{-- Dashboard Content --}}
    <div class="p-6 space-y-6">
      <!-- Page Header -->
      <div
        class="bg-gradient-to-r @if($isSTEDMS ?? false) from-green-50 to-emerald-50 border-l-4 border-green-500 @else from-indigo-50 to-blue-50 border-l-4 border-indigo-500 @endif rounded-lg p-6">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-start gap-4 flex-1">
            <div class="flex-shrink-0">
              <div
                class="w-12 h-12 @if($isSTEDMS ?? false) bg-green-500 @else bg-indigo-500 @endif rounded-lg flex items-center justify-center">
                <i
                  class="@if($isSTEDMS ?? false) fa-solid fa-building @else fa-solid fa-scanner @endif text-white text-xl"></i>
              </div>
            </div>
            <div class="flex-1">
              <h1 class="text-2xl font-bold text-gray-900 mb-2">
                @if($isSTEDMS ?? false)
                  ST BLIND SCANNING - Sectional Titling EDMS
                @else
                  BLIND SCANNING - Document Upload & Migration
                @endif
              </h1>
              <p class="text-gray-700 mb-4">
                @if($isSTEDMS ?? false)
                  Process and migrate Sectional Titling documents from primary application to EDMS workflow.
                @else
                  Upload and migrate scanned documents to the server's document management system.
                @endif
              </p>

              <!-- Step-by-step instructions -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="flex items-start gap-3">
                  <div
                    class="flex-shrink-0 w-8 h-8 @if($isSTEDMS ?? false) bg-green-500 @else bg-indigo-500 @endif text-white rounded-full flex items-center justify-center text-sm font-bold">
                    1
                  </div>
                  <div class="flex-1">
                    @if($isSTEDMS ?? false)
                      <h3 class="font-semibold text-gray-900 text-sm">Select NP File Number</h3>
                      <p class="text-xs text-gray-600">Use ST file number from primary application</p>
                    @else
                      <h3 class="font-semibold text-gray-900 text-sm">Select File Number</h3>
                      <p class="text-xs text-gray-600">Choose from existing files or enter manually</p>
                    @endif
                  </div>
                </div>

                <div class="flex items-start gap-3">
                  <div
                    class="flex-shrink-0 w-8 h-8 @if($isSTEDMS ?? false) bg-green-500 @else bg-indigo-500 @endif text-white rounded-full flex items-center justify-center text-sm font-bold">
                    2
                  </div>
                  <div class="flex-1">
                    @if($isSTEDMS ?? false)
                      <h3 class="font-semibold text-gray-900 text-sm">Process ST Documents</h3>
                      <p class="text-xs text-gray-600">Review uploaded ST application documents</p>
                    @else
                      <h3 class="font-semibold text-gray-900 text-sm">Pick Scanned Folder</h3>
                      <p class="text-xs text-gray-600">Select folder with A4/A3 subfolders</p>
                    @endif
                  </div>
                </div>

                <div class="flex items-start gap-3">
                  <div
                    class="flex-shrink-0 w-8 h-8 @if($isSTEDMS ?? false) bg-green-500 @else bg-indigo-500 @endif text-white rounded-full flex items-center justify-center text-sm font-bold">
                    3
                  </div>
                  <div class="flex-1">
                    @if($isSTEDMS ?? false)
                      <h3 class="font-semibold text-gray-900 text-sm">Process to BLIND_SCAN</h3>
                      <p class="text-xs text-gray-600">Files stored in blind scanning folder for review</p>
                    @else
                      <h3 class="font-semibold text-gray-900 text-sm">Migrate to Server</h3>
                      <p class="text-xs text-gray-600">System creates structured storage</p>
                    @endif
                  </div>
                </div>
              </div>

              <!-- Destination info -->
              <div class="flex items-center gap-2 text-sm">
                <i
                  class="fa-solid fa-folder-open @if($isSTEDMS ?? false) text-green-600 @else text-indigo-600 @endif"></i>
                <span class="text-gray-600">Destination:</span>
                <code
                  class="bg-white px-2 py-1 rounded border @if($isSTEDMS ?? false) text-green-600 @else text-indigo-600 @endif font-mono text-xs">
                    storage/EDMS/BLIND_SCAN/[FileNumber]
                  </code>
              </div>
              @if($isSTEDMS ?? false)
                <div class="flex items-center gap-2 text-sm mt-2">
                  <i class="fa-solid fa-arrow-right text-green-600"></i>
                  <span class="text-gray-600">Then moved to:</span>
                  <code class="bg-green-50 px-2 py-1 rounded border border-green-200 text-green-600 font-mono text-xs">
                              storage/EDMS/SCAN_UPLOAD/[NP_FileNumber]
                            </code>
                </div>
              @endif
            </div>
            @if($isSTEDMS ?? false)
              <!-- Quick Back Button in Header -->
              <div class="flex-shrink-0">
                <a href="#" id="headerBackBtn"
                  class="inline-flex items-center gap-2 bg-white/80 hover:bg-white border border-green-200 text-green-700 px-3 py-2 rounded-lg transition-colors duration-200 text-sm">
                  <i class="fa-solid fa-arrow-left text-xs"></i>
                  <span>Back to Dashboard</span>
                </a>
              </div>
            @endif
          </div>
        </div>

        @if($isSTEDMS ?? false)
          <!-- ST EDMS Navigation -->
          <div class="flex items-center justify-between bg-white border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-route text-green-600 text-sm"></i>
              </div>
              <div>
                <h3 class="font-semibold text-gray-900">ST EDMS Workflow Navigation</h3>
                <p class="text-sm text-gray-600">Return to the main ST EDMS workflow dashboard</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <a href="#" id="navBackBtn"
                class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                <i class="fa-solid fa-arrow-left text-sm"></i>
                <span>Back to ST EDMS Dashboard</span>
              </a>
            </div>
          </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
          <!-- Left: Upload + Migrate (reduced width) -->
          <div class="bg-card border rounded-lg p-6 lg:col-span-2">
            <h2 class="text-xl font-bold mb-4"><i class="fa-solid fa-cloud-arrow-up mr-2"></i>Upload & Migrate</h2>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium mb-1">File Number (target on server)</label>
                <div class="flex gap-2">
                  <input id="fileNo" type="text" class="flex-1 border rounded-md px-3 py-2"
                    placeholder="e.g., ST-COM-2025-0001" />
                  <button type="button" id="openFileNoModalBtn" class="btn btn-primary">
                    <i class="fa-solid fa-search mr-1"></i>Select
                  </button>
                </div>
                <p class="text-xs text-muted-foreground mt-1">Click 'Select' to choose or enter a file number manually</p>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">Registry</label>
                <select id="registryType" class="w-full border rounded-md px-3 py-2 bg-white">
                  @foreach($registries as $registry)
                    <option value="{{ $registry->code ?? Str::slug($registry->name, '_') }}">{{ $registry->name }}</option>
                  @endforeach
                </select>
                <p class="text-xs text-muted-foreground mt-1">Select the target registry for storage</p>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">Pick local folder</label>
                <input id="folderInput" type="file" webkitdirectory directory multiple
                  class="w-full border rounded-md px-3 py-2" />
                <p class="text-xs text-muted-foreground mt-1">
                  Use Chrome/Edge on desktop. The folder name should match the File Number.
                </p>
              </div>

              <div id="status" class="text-sm text-muted-foreground">No folder selected.</div>

              <button id="migrateBtn" class="btn btn-primary w-full disabled:opacity-50" disabled>
                <i class="fa-solid fa-paper-plane mr-2"></i>Migrate to Server
              </button>
            </div>
          </div>

          <!-- Right: Server Browser & Logs (increased width) -->
          <div class="bg-card border rounded-lg p-0 lg:col-span-3">
            <div class="p-6 pb-2">
              <div class="flex items-center justify-between mb-2">
                <div>
                  <h2 class="text-xl font-bold">Server Storage & Logs</h2>
                  <p class="text-muted-foreground">Browse <code>storage/EDMS/BLIND_SCAN</code>, preview files, and review
                    migration logs.</p>
                </div>
                <div id="userModeIndicator" class="hidden">
                  <!-- Will be populated by JavaScript -->
                </div>
              </div>
            </div>

            <div class="px-6 pb-0">
              <div class="flex gap-2">
                <button id="tabServer" class="btn">Server Browser</button>
                <button id="tabLogs" class="btn">Migration Logs</button>
              </div>
            </div>

            <!-- Server Browser -->
            <div id="serverPanel" class="p-6">
              <div class="flex items-center justify-between mb-2">
                <div>
                  <div class="text-sm"><span class="mr-2">Path:</span><span id="srvPath"
                      class="font-mono">/storage/EDMS/BLIND_SCAN</span></div>
                  <div id="srvCrumbs" class="text-xs text-muted-foreground mt-1">Root</div>
                </div>
                <div class="flex items-center gap-2">
                  <button id="syncMigrationBtn" class="btn">
                    <i class="fa-solid fa-list-check mr-2"></i>Sync Migration Log
                  </button>
                  <button id="srvRefresh" class="btn"><i class="fa-solid fa-rotate mr-2"></i>Refresh</button>
                </div>
              </div>
              <div id="srvStatus" class="text-xs text-muted-foreground mb-3 hidden"></div>

              <div class="border rounded-lg overflow-hidden">
                <table id="srvTable" class="w-full text-sm">
                  <thead class="bg-muted">
                    <tr class="border-b">
                      <th class="text-left p-3 w-10">S/N</th>
                      <th class="text-left p-3 w-10"></th>
                      <th class="text-left p-3">Name</th>
                      <th class="text-left p-3">Size</th>
                      <th class="text-left p-3">Modified</th>
                      <th class="text-left p-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="srvRows"></tbody>
                </table>
              </div>

              <!-- Small Preview Box -->
              <div class="mt-4 border rounded-lg p-3">
                <div class="font-medium mb-2">Quick Preview</div>
                <div id="previewBox" class="preview-box text-sm text-muted-foreground flex items-center justify-center">
                  Select a file to preview.
                </div>
              </div>
            </div>

            <!-- Logs -->
            <div id="logsPanel" class="p-6 hidden">
              <div id="logsContent"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Aggregated Summary -->
      <div class="p-6">
        <div class="bg-card border rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <div>
              <h3 class="font-semibold text-lg">Blind Scanning Summary</h3>
              <p class="text-xs text-muted-foreground">Grouped by file number — counts per paper size and total files.
                Click a row to expand details.</p>
            </div>
            <div>
              <button id="refreshAggregates" class="btn">Refresh</button>
            </div>
          </div>

          <div class="border rounded-lg overflow-hidden">
            <table id="aggregatesTable" class="w-full text-sm">
              <thead class="bg-muted">
                <tr class="border-b">
                  <th class="text-left p-3 w-10">S/N</th>
                  <th class="text-left p-3 w-10"></th>
                  <th class="text-left p-3">File Number</th>
                  <th class="text-left p-3">Registry</th>
                  <th class="text-left p-3">A4</th>
                  <th class="text-left p-3">A3</th>
                  <th class="text-left p-3">Other</th>
                  <th class="text-left p-3">Total</th>
                </tr>
              </thead>
              <tbody id="aggregatesBody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Progress Modal -->
      <div id="progressModal" class="fixed inset-0 bg-black/30 hidden items-center justify-center z-40">
        <div class="bg-white border rounded-lg w-full max-w-md mx-4">
          <div class="p-6">
            <h3 id="progressTitle" class="text-xl font-bold">Working...</h3>
            <p id="progressSub" class="text-muted-foreground">Please wait</p>
          </div>
          <div class="px-6 pb-6">
            <div class="space-y-4">
              <div class="w-full bg-muted rounded-full h-2">
                <div class="progress-bar bg-indigo-600 h-2 rounded-full" style="width:0%"></div>
              </div>
              <div id="progressText" class="text-sm text-center text-muted-foreground">0%</div>
              <div id="progressDone" class="flex items-center justify-center gap-2 text-green-700 hidden">
                <i class="fa-solid fa-circle-check"></i><span>Done!</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Preview Modal -->
      <div id="previewModal" class="fixed inset-0 preview-modal hidden items-center justify-center z-50">
        <div class="preview-content bg-white rounded-lg w-full max-w-7xl mx-4 flex flex-col">
          <!-- Header -->
          <div class="preview-toolbar flex items-center justify-between p-4 border-b rounded-t-lg">
            <div class="flex items-center space-x-4">
              <button id="modalClose" class="text-gray-500 hover:text-gray-700">
                <i class="fa-solid fa-times text-xl"></i>
              </button>
              <h3 id="modalFileName" class="text-lg font-semibold"></h3>
              <div id="modalFileInfo" class="text-sm text-muted-foreground"></div>
            </div>
            <div class="edit-toolbar flex items-center space-x-2" id="imageEditToolbar" style="display: none;">
              <button class="btn btn-sm" title="Rotate Left" onclick="rotateImage(-90)">
                <i class="fa-solid fa-rotate-left"></i>
              </button>
              <button class="btn btn-sm" title="Rotate Right" onclick="rotateImage(90)">
                <i class="fa-solid fa-rotate-right"></i>
              </button>
              <button class="btn btn-sm" title="Crop" onclick="toggleCropMode()">
                <i class="fa-solid fa-crop"></i>
              </button>
              <button class="btn btn-sm" title="Reset" onclick="resetImage()">
                <i class="fa-solid fa-refresh"></i>
              </button>
              <button class="btn btn-sm btn-success" title="Save Edited" onclick="saveEditedImage()">
                <i class="fa-solid fa-floppy-disk"></i> Save
              </button>
              <button class="btn btn-sm" title="Download Edited" onclick="downloadEditedImage()">
                <i class="fa-solid fa-download"></i> Download
              </button>
              <button class="btn btn-sm btn-danger" title="Delete File" onclick="deleteCurrentFile()">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
              <div class="zoom-controls-static flex items-center space-x-1">
                <button class="btn btn-sm" title="Zoom Out" onclick="zoomImage(0.8)">
                  <i class="fa-solid fa-magnifying-glass-minus"></i>
                </button>
                <span class="text-xs px-2" id="zoomLevel">100%</span>
                <button class="btn btn-sm" title="Zoom In" onclick="zoomImage(1.2)">
                  <i class="fa-solid fa-magnifying-glass-plus"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Content Area -->
          <div class="preview-container flex-1 p-4 relative">
            <!-- Navigation Buttons -->
            <button id="modalPrev" class="preview-nav-btn absolute left-4 top-1/2 transform -translate-y-1/2">
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button id="modalNext" class="preview-nav-btn absolute right-4 top-1/2 transform -translate-y-1/2">
              <i class="fa-solid fa-chevron-right"></i>
            </button>

            <!-- Content will be inserted here -->
            <div id="modalContent" class="w-full h-full flex items-center justify-center">
              <div class="text-muted-foreground">Loading preview...</div>
            </div>
          </div>

          <!-- Footer -->
          <div class="preview-toolbar flex items-center justify-between p-4 border-t rounded-b-lg">
            <div class="text-sm text-muted-foreground">
              <span id="modalCounter">0/0</span> files
            </div>
            <div class="flex space-x-2">
              <button class="btn btn-sm" onclick="fitToScreen()">
                <i class="fa-solid fa-expand mr-1"></i> Fit
              </button>
              <button class="btn btn-sm" onclick="actualSize()">
                <i class="fa-solid fa-arrows-alt mr-1"></i> Actual Size
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Include Global File Number Modal -->
      @include('components.global-fileno-modal')

      <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
      <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
      @include('scanning.blind_scan_js')

      <script>
        // Initialize GlobalFileNoModal when document is ready
        $(document).ready(function () {
          // Initialize the modal
          if (typeof GlobalFileNoModal !== 'undefined') {
            GlobalFileNoModal.init();
            console.log('GlobalFileNoModal initialized for blind scanning');
          }

          // Check for ST EDMS context and pre-populate data
          @if($isSTEDMS ?? false)
            console.log('🏢 ST EDMS context detected');

            // Get URL parameters for applicant info from redirect
            const urlParams = new URLSearchParams(window.location.search);

            // Check for applicant info in localStorage (from form submission)
            const storedApplicantInfo = localStorage.getItem('st_applicant_info');
            if (storedApplicantInfo) {
              try {
                const applicantInfo = JSON.parse(storedApplicantInfo);
                console.log('📋 ST Applicant info loaded:', applicantInfo);

                // Pre-populate file number if available
                if (applicantInfo.np_fileno) {
                  $('#fileNo').val(applicantInfo.np_fileno);
                  console.log('📝 File number pre-populated:', applicantInfo.np_fileno);
                }

                // Show applicant info banner
                if (applicantInfo.title || applicantInfo.fname || applicantInfo.lname) {
                  const applicantName = `${applicantInfo.title || ''} ${applicantInfo.fname || ''} ${applicantInfo.lname || ''}`.trim();
                  const banner = `
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4" id="stApplicantBanner">
                              <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                  <i class="fa-solid fa-building text-green-600 text-lg"></i>
                                </div>
                                <div class="flex-1">
                                  <h4 class="font-semibold text-green-900 mb-1">ST Application Context</h4>
                                  <p class="text-sm text-green-700 mb-2">
                                    <strong>Applicant:</strong> ${applicantName}<br>
                                    <strong>Type:</strong> ${applicantInfo.applicant_type || 'N/A'}<br>
                                    <strong>Land Use:</strong> ${applicantInfo.land_use || 'N/A'}<br>
                                    <strong>NP File No:</strong> ${applicantInfo.np_fileno || 'N/A'}
                                  </p>
                                  <p class="text-xs text-green-600">
                                    <i class="fa-solid fa-info-circle mr-1"></i>
                                    Documents from this ST application are ready for blind scanning workflow.
                                  </p>
                                </div>
                                <button type="button" class="text-green-400 hover:text-green-600" onclick="$('#stApplicantBanner').hide()">
                                  <i class="fa-solid fa-times"></i>
                                </button>
                              </div>
                            </div>
                          `;
                  $('.container > *:first').after(banner);
                }

                // Set up navigation URLs using application ID
                const applicationId = applicantInfo.application_id || urlParams.get('app_id') || '';
                if (applicationId) {
                  const edmsUrl = `/edms/${applicationId}`;
                  $('#headerBackBtn').attr('href', edmsUrl);
                  $('#navBackBtn').attr('href', edmsUrl);
                  console.log('🔗 Navigation URLs set to:', edmsUrl);
                } else {
                  // Fallback to general ST dashboard
                  $('#headerBackBtn').attr('href', '/sectional-titling');
                  $('#navBackBtn').attr('href', '/sectional-titling');
                  console.log('⚠️ No application ID found, using fallback URL');
                }

                // Clear the localStorage after use
                localStorage.removeItem('st_applicant_info');

              } catch (e) {
                console.error('Error parsing ST applicant info:', e);
                // Fallback navigation for error cases
                $('#headerBackBtn').attr('href', '/sectional-titling');
                $('#navBackBtn').attr('href', '/sectional-titling');
              }
            } else {
              // Check URL parameters for app_id if no localStorage data
              const urlParams = new URLSearchParams(window.location.search);
              const applicationId = urlParams.get('app_id');
              if (applicationId) {
                const edmsUrl = `/edms/${applicationId}`;
                $('#headerBackBtn').attr('href', edmsUrl);
                $('#navBackBtn').attr('href', edmsUrl);
                console.log('🔗 Navigation URLs set from URL param:', edmsUrl);
              } else {
                // Final fallback
                $('#headerBackBtn').attr('href', '/sectional-titling');
                $('#navBackBtn').attr('href', '/sectional-titling');
                console.log('⚠️ No application ID available, using fallback URL');
              }
            }
          @endif

          // Handle the "Select" button click
          $('#openFileNoModalBtn').on('click', function () {
            if (typeof GlobalFileNoModal === 'undefined') {
              alert('File number modal not loaded. Please refresh the page.');
              return;
            }

            // Open the modal with callback
            GlobalFileNoModal.open({
              callback: function (fileData) {
                console.log('File number selected:', fileData);

                // The modal returns fileData.fileNumber directly
                const fileNumber = fileData.fileNumber || '';

                // Update the file number input field
                if (fileNumber) {
                  $('#fileNo').val(fileNumber);

                  // Trigger validation if function exists
                  if (typeof validateUploadSection === 'function') {
                    validateUploadSection();
                  } else {
                    // Fallback: trigger input event to validate
                    $('#fileNo').trigger('input');
                  }

                  // Show success message
                  console.log('File number applied:', fileNumber);

                  // Close the modal
                  if (typeof GlobalFileNoModal !== 'undefined' && typeof GlobalFileNoModal.close === 'function') {
                    GlobalFileNoModal.close();
                  }
                } else {
                  console.error('No file number in callback data:', fileData);
                  alert('No file number selected or entered.');
                }
              },
              initialTab: 'mls', // Start with MLPP tab
              initialValue: $('#fileNo').val() || '' // Pre-populate with current value if any
            });
          });
        });
      </script>

      <script>
        // Aggregates table loader
        function renderAggregates(items) {
          const $table = $('#aggregatesTable');
          const $body = $('#aggregatesBody');

          // Destroy existing DataTable if it exists
          if ($.fn.DataTable.isDataTable('#aggregatesTable')) {
            $table.DataTable().destroy();
          }

          $body.empty();

          if (!items || !items.length) {
            // Let DataTables handle the empty state
            $table.DataTable({
              pageLength: 50,
              language: {
                emptyTable: "No blind scan records found.",
                search: "_INPUT_",
                searchPlaceholder: "Search aggregates..."
              }
            });
            return;
          }

          // Initialize main aggregates table
          const dt = $table.DataTable({
            pageLength: 50,
            order: [[0, 'asc']],
            columnDefs: [
              { orderable: false, targets: [1] } // chevron column
            ],
            language: {
              search: "_INPUT_",
              searchPlaceholder: "Search aggregates..."
            },
            drawCallback: function () {
              // Re-initialize nested details tables for visible rows
              $('.details-table').each(function () {
                if (!$.fn.DataTable.isDataTable(this)) {
                  $(this).DataTable({
                    pageLength: 50,
                    order: [[0, 'asc']],
                    language: {
                      search: "_INPUT_",
                      searchPlaceholder: "Search files..."
                    }
                  });
                }
              });
            }
          });

          items.forEach(function (group, idx) {
            const fn = group.file_number || 'UNASSIGNED';
            const registry = group.registry_type === 'cadastral' ? 'Cadastral' : 'Lands';
            const a4 = group.counts['A4'] || 0;
            const a3 = group.counts['A3'] || 0;
            const total = group.total || 0;
            const other = Math.max(0, total - (a4 + a3));
            const sn = idx + 1;

            const detailId = 'agg_detail_' + idx;
            const registryPill = `<span class="pill ${registry === 'Cadastral' ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-blue-700 bg-blue-50 border-blue-200'}">${registry}</span>`;

            // Add row to DataTable
            const rowNode = dt.row.add([
              sn,
              '<i class="fa-solid fa-chevron-right"></i>',
              `<span class="font-mono">${fn}</span>`,
              registryPill,
              a4,
              a3,
              other,
              `<strong>${total}</strong>`
            ]).draw(false).node();

            $(rowNode).addClass('agg-summary hover:bg-gray-50 cursor-pointer');
            $(rowNode).find('td:eq(1)').addClass('text-center');

            const recordsHtml = (group.records || []).map((r, i) => {
              const url = r.document_path ? '/' + r.document_path : '#';
              const rSn = i + 1;
              const uploadedDate = r.created_at ? new Date(r.created_at).toLocaleString('en-US', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
              }) : 'N/A';
              return `<tr class="border-b">
                    <td class="p-2">${rSn}</td>
                    <td class="p-2">${r.original_filename}</td>
                    <td class="p-2">${r.paper_size}</td>
                    <td class="p-2">${uploadedDate}</td>
                    <td class="p-2"><a href="${url}" target="_blank" class="text-indigo-600">Open</a></td>
                  </tr>`;
            }).join('');

            const detailHtml = `
                  <div class="p-3 bg-white border rounded shadow-inner">
                    <div class="text-xs text-muted-foreground mb-2">Files for ${fn} (showing ${group.records.length}):</div>
                    <div class="overflow-auto">
                      <table id="detailsTable_${group.file_number_slug || idx}" class="w-full text-xs details-table">
                        <thead>
                          <tr class="bg-muted">
                            <th class="p-2 text-left">S/N</th>
                            <th class="p-2 text-left">Filename</th>
                            <th class="p-2 text-left">Paper</th>
                            <th class="p-2 text-left">Uploaded</th>
                            <th class="p-2 text-left">Actions</th>
                          </tr>
                        </thead>
                        <tbody>${recordsHtml}</tbody>
                      </table>
                    </div>
                  </div>`;

            // Expandable row logic
            $(rowNode).on('click', function () {
              const row = dt.row(this);
              const $icon = $(this).find('i.fa-chevron-right');

              if (row.child.isShown()) {
                row.child.hide();
                $icon.removeClass('fa-rotate-90');
              } else {
                row.child(detailHtml).show();
                $icon.addClass('fa-rotate-90');

                // Initialize the table inside the child row if not already done
                const $childTable = row.child().find('.details-table');
                if ($childTable.length && !$.fn.DataTable.isDataTable($childTable)) {
                  $childTable.DataTable({
                    pageLength: 50,
                    order: [[0, 'asc']],
                    language: {
                      search: "_INPUT_",
                      searchPlaceholder: "Search files..."
                    }
                  });
                }
              }
            });
          });
        }

        function loadAggregates() {
          fetch('/blind-scanning/aggregates', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(res => {
              if (res && res.success && Array.isArray(res.data)) {
                renderAggregates(res.data);
              } else {
                // Initial empty state call
                renderAggregates([]);
              }
            })
            .catch(err => {
              console.error('Failed to load aggregates', err);
              $('#aggregatesBody').html('<tr><td colspan="8" class="p-3 text-sm text-muted-foreground">Error loading data</td></tr>');
            });
        }

        $(function () {
          // initial load
          loadAggregates();
          // refresh button
          $('#refreshAggregates').on('click', loadAggregates);
        });
      </script>

@endsection
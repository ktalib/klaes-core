@extends('layouts.app')

@section('page-title')
{{ $PageTitle ?? 'Blind Scanning' }}
@endsection

@section('content')
<style>
  .text-muted-foreground { color:#64748b; }
  .bg-card { background:#ffffff; }
  .bg-muted { background:#f1f5f9; }
  .border { border-color:#e2e8f0; }
  .progress-bar { transition: width .3s ease; }
  .row:hover { background:#f8fafc; }
  .pill{font-size:11px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:999px;padding:.15rem .5rem}
  .btn { border:1px solid #e2e8f0; background:#fff; padding:.5rem .75rem; border-radius:.5rem; font-size:.9rem; }
  .btn:hover { background:#f8fafc; }
  .btn-primary{ background:#4f46e5; border-color:#4f46e5; color:#fff; }
  .btn-primary:hover{ background:#4338ca; }
  .btn-success{ background:#10b981; border-color:#10b981; color:#fff; }
  .btn-success:hover{ background:#059669; }
  .btn-danger{ background:#dc2626; border-color:#dc2626; color:#fff; }
  .btn-danger:hover{ background:#b91c1c; }
  .preview-box { min-height: 200px; }
  
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
    background: rgba(255,255,255,0.95);
    border-radius: 8px;
    padding: 8px;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
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
  .crop-handle-nw { top: -6px; left: -6px; cursor: nw-resize; }
  .crop-handle-ne { top: -6px; right: -6px; cursor: ne-resize; }
  .crop-handle-sw { bottom: -6px; left: -6px; cursor: sw-resize; }
  .crop-handle-se { bottom: -6px; right: -6px; cursor: se-resize; }
</style>

<div class="container mx-auto py-6 space-y-6">
  <div>
    <h1 class="text-3xl font-bold mb-2">
      <i class="fa-solid fa-scanner mr-2"></i>
      Blind Scanning Workflow — Upload & Migrate
    </h1>
    <p class="text-muted-foreground">
      Step 1: Enter the <b>File Number</b>. Step 2: Pick the whole <b>local folder</b> you scanned (it must include A4/A3 subfolders).
      Step 3: Click <b>Migrate</b>. The server will create <code>storage/EDMS/BLIND_SCAN/[FileNo]</code> and extract all contents.
    </p>
    <p class="text-sm mt-1">Destination: <code>storage/EDMS/BLIND_SCAN/[FileNo]</code></p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
    <!-- Left: Upload + Migrate (reduced width) -->
    <div class="bg-card border rounded-lg p-6 lg:col-span-2">
      <h2 class="text-xl font-bold mb-4"><i class="fa-solid fa-cloud-arrow-up mr-2"></i>Upload & Migrate</h2>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">File Number (target on server)</label>
          <input id="fileNo" type="text" class="w-full border rounded-md px-3 py-2" placeholder="e.g., ST-COM-2025-0001" />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Pick local folder</label>
          <input id="folderInput" type="file" webkitdirectory directory multiple class="w-full border rounded-md px-3 py-2"/>
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
        <h2 class="text-xl font-bold">Server Storage & Logs</h2>
        <p class="text-muted-foreground">Browse <code>storage/EDMS/BLIND_SCAN</code>, preview files, and review migration logs.</p>
      </div>

      <div class="px-6 pb-0">
        <div class="flex gap-2">
          <button id="tabServer" class="btn">Server Browser</button>
          <button id="tabLogs" class="btn">Migration Logs</button>
        </div>
      </div>

      <!-- Server Browser -->
      <div id="serverPanel" class="p-6">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-sm"><span class="mr-2">Path:</span><span id="srvPath" class="font-mono">/storage/EDMS/BLIND_SCAN</span></div>
            <div id="srvCrumbs" class="text-xs text-muted-foreground mt-1">Root</div>
          </div>
          <button id="srvRefresh" class="btn"><i class="fa-solid fa-rotate mr-2"></i>Refresh</button>
        </div>

        <div class="border rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-muted">
              <tr class="border-b">
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

  <!-- Progress Modal -->
  <div id="progressModal" class="fixed inset-0 bg-black/30 hidden items-center justify-center z-40">
    <div class="bg-white border rounded-lg w-full max-w-md mx-4">
      <div class="p-6">
        <h3 id="progressTitle" class="text-xl font-bold">Working...</h3>
        <p id="progressSub" class="text-muted-foreground">Please wait</p>
      </div>
      <div class="px-6 pb-6">
        <div class="space-y-4">
          <div class="w-full bg-muted rounded-full h-2"><div class="progress-bar bg-indigo-600 h-2 rounded-full" style="width:0%"></div></div>
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
          <button class="btn btn-sm" title="Delete File" onclick="deleteCurrentFile()">
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

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script>
  // Config
  const MIGRATE_ENDPOINT = '{{ route("blind-scanning.migrate") }}';
  const API_LIST = '{{ route("blind_scan.api.list") }}';
  const API_LOGS = '{{ route("blind_scan.api.logs") }}';
  const API_DELETE_FILE = '{{ route("blind_scan.api.delete-file") }}';
  const CSRF_TOKEN = '{{ csrf_token() }}';

  // Preview Modal State
  let previewState = {
    currentIndex: 0,
    fileList: [],
    isOpen: false,
    currentDirItems: [],
    currentFilePath: ''
  };

  // Image Editing State
  let currentImageState = {
    element: null,
    originalSrc: null,
    rotation: 0,
    scale: 1,
    currentFileName: ''
  };

  @include('scanning.blind_scan_js')
</script>
@endpush

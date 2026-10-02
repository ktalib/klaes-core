@extends('layouts.app')
@section('page-title')
{{ __('Blind Scannings') }}
@endsection

@section('content')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  
  <!-- Global File Number Modal CSS -->
  <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
  
  <style>
    /* Custom styles with Tailwind fallbacks */
    .preview-box { min-height: 520px; }
    .progress-bar { transition: width .3s ease; }
    
    /* File status indicator animations */
    .status-success { 
      @apply border-green-200 bg-green-50 text-green-800;
      animation: fadeIn 0.3s ease-in;
    }
    .status-warning { 
      @apply border-yellow-200 bg-yellow-50 text-yellow-800;
      animation: fadeIn 0.3s ease-in;
    }
    .status-info { 
      @apply border-blue-200 bg-blue-50 text-blue-800;
      animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-4px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* File upload area hover effect */
    .upload-area:hover {
      @apply border-blue-300 bg-blue-50;
    }
    
    /* Table row hover effect */
    .table-row:hover {
      @apply bg-gray-50;
    }
  </style>
@php
// This page uses Laravel controllers for backend functionality
@endphp

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-4 sm:p-6">

       <div class="container mx-auto py-8 space-y-8">
    <!-- Header Section -->
    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
      <div class="flex items-start space-x-4">
        <div class="bg-blue-100 p-3 rounded-lg">
          <i class="fa-solid fa-cloud-arrow-up text-blue-600 text-2xl"></i>
        </div>
        <div class="flex-1">
          <h1 class="text-3xl font-bold text-gray-900 mb-2">Blind Scanning Workflow — Upload & Migrate</h1>
          <div class="space-y-2 text-gray-600">
            <p class="text-base leading-relaxed">
              <span class="inline-flex items-center bg-blue-50 text-blue-700 px-2 py-1 rounded-md text-sm font-medium mr-2">Step 1</span>
              Enter the <strong>File Number</strong>
              <span class="mx-2">→</span>
              <span class="inline-flex items-center bg-blue-50 text-blue-700 px-2 py-1 rounded-md text-sm font-medium mr-2">Step 2</span>
              Pick the whole <strong>local folder</strong> you scanned (it must include A4/A3 subfolders)
              <span class="mx-2">→</span>
              <span class="inline-flex items-center bg-blue-50 text-blue-700 px-2 py-1 rounded-md text-sm font-medium">Step 3</span>
              Click <strong>Migrate</strong>
            </p>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-3">
              <div class="flex items-center space-x-2">
                <i class="fa-solid fa-info-circle text-gray-500"></i>
                <span class="text-sm font-medium text-gray-700">Destination:</span>
                <code class="bg-gray-100 px-2 py-1 rounded text-sm font-mono">storage\app\public\EDMS\BLIND_SCAN\[FileNo]</code>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
      <!-- Left: Upload + Migrate (reduced width) -->
      <div class="lg:col-span-2">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
              <i class="fa-solid fa-cloud-arrow-up mr-2 text-blue-600"></i>
              Upload & Migrate
            </h2>
            <p class="text-sm text-gray-600 mt-1">Follow the 3-step process to migrate your scanned files</p>
          </div>

          <!-- Step 1: File Number -->
          <div class="p-6 border-b border-gray-100">
            <div class="flex items-center mb-3">
              <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 text-blue-600 text-sm font-medium rounded-full mr-2">1</span>
              <h3 class="text-lg font-medium text-gray-900">Select File Number</h3>
            </div>
            <div class="space-y-3">
              <button id="selectFileNoBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                <i class="fa-solid fa-folder-plus"></i>
                <span>Select File Number</span>
              </button>
              <div id="fileNoDisplay" class="hidden bg-green-50 border border-green-200 rounded-lg p-3">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-check-circle text-green-600"></i>
                    <span id="selectedFileNo" class="font-medium text-green-800">No file number selected</span>
                  </div>
                  <button id="changeFileNoBtn" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fa-solid fa-edit mr-1"></i>Change
                  </button>
                </div>
              </div>
              <input id="fileNo" type="hidden" />
              <p class="text-xs text-gray-500">
                Choose from MLS, KANGIS, or New KANGIS file number formats
              </p>
            </div>
          </div>

          <!-- Step 2: Folder Selection -->
          <div class="p-6 border-b border-gray-100">
            <div class="flex items-center mb-3">
              <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 text-blue-600 text-sm font-medium rounded-full mr-2">2</span>
              <h3 class="text-lg font-medium text-gray-900">Pick Local Folder</h3>
            </div>
            <div class="space-y-3">
              <div class="relative">
                <input id="folderInput" type="file" webkitdirectory directory multiple 
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors duration-200"/>
              </div>
              <p class="text-xs text-gray-500">
                <i class="fa-solid fa-info-circle mr-1"></i>
                Use Chrome/Edge on desktop. The folder name should match the File Number.
              </p>
            </div>
          </div>

          <!-- Status & Step 3 -->
          <div class="p-6">
            <div class="flex items-center mb-3">
              <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 text-blue-600 text-sm font-medium rounded-full mr-2">3</span>
              <h3 class="text-lg font-medium text-gray-900">Migration Status</h3>
            </div>
            
            <div id="status" class="mb-4 p-3 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-600">
              <i class="fa-solid fa-folder-open mr-2"></i>
              No folder selected.
            </div>

            <button id="migrateBtn" class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2" disabled>
              <i class="fa-solid fa-paper-plane mr-2"></i>
              <span>Migrate to Server</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Server Browser & Logs (increased width) -->
      <div class="lg:col-span-3">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
              <i class="fa-solid fa-server mr-2 text-blue-600"></i>
              Server Storage & Logs
            </h2>
            <p class="text-sm text-gray-600 mt-1">Browse storage\app\public\EDMS\BLIND_SCAN, preview files, and review migration logs</p>
          </div>

          <!-- Tab Navigation -->
          <div class="px-6 py-3 border-b border-gray-200">
            <div class="flex gap-2">
              <button id="tabServer" class="px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                <i class="fa-solid fa-folder mr-1"></i>Server Browser
              </button>
              <button id="tabLogs" class="px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                <i class="fa-solid fa-history mr-1"></i>Migration Logs
              </button>
            </div>
          </div>

          <!-- Server Browser Panel -->
          <div id="serverPanel" class="p-6">
            <!-- Path and Controls -->
            <div class="flex items-center justify-between mb-4">
              <div class="flex-1">
                <div class="flex items-center space-x-2 text-sm text-gray-700">
                  <i class="fa-solid fa-folder-open text-blue-600"></i>
                  <span class="font-medium">Path:</span>
                  <span id="srvPath" class="font-mono bg-gray-100 px-2 py-1 rounded">/storage</span>
                </div>
                <div id="srvCrumbs" class="text-xs text-gray-500 mt-1">Root</div>
              </div>
              <button id="srvRefresh" class="px-3 py-2 text-sm font-medium border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                <i class="fa-solid fa-rotate mr-1"></i>Refresh
              </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
              <!-- File list (reduced width) -->
              <div class="lg:col-span-2">
                <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                  <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center space-x-2">
                      <i class="fa-solid fa-folder-tree text-blue-600"></i>
                      <span class="font-medium text-gray-900">File Explorer</span>
                    </div>
                  </div>
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                          <th class="text-left px-4 py-3 w-12 font-semibold text-gray-700 uppercase tracking-wide text-xs"></th>
                          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Name</th>
                          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs min-w-[100px]">Modified</th>
                          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs min-w-[120px]">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="srvRows" class="divide-y divide-gray-100 bg-white"></tbody>
                    </table>
                  </div>
                </div>
              </div>
              
              <!-- Preview (increased width) -->
              <div class="lg:col-span-3">
                <div class="border border-gray-200 rounded-lg bg-white shadow-sm">
                  <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-eye text-blue-600"></i>
                        <span class="font-semibold text-gray-900">File Preview</span>
                      </div>
                      <div class="text-xs text-gray-500">
                        <i class="fa-solid fa-info-circle mr-1"></i>
                        Supports PDF, images (PNG, JPG, GIF, WebP)
                      </div>
                    </div>
                  </div>
                  <div id="previewBox" class="preview-box bg-gray-50">
                    <div class="flex flex-col items-center justify-center h-full space-y-4 p-8">
                      <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-file-image text-2xl text-blue-600"></i>
                      </div>
                      <div class="text-center space-y-2">
                        <p class="text-gray-700 font-medium">No file selected</p>
                        <p class="text-sm text-gray-500">Click "Preview" next to any file to view it here</p>
                      </div>
                      <div class="flex items-center space-x-4 text-xs text-gray-400">
                        <div class="flex items-center space-x-1">
                          <i class="fa-solid fa-file-pdf"></i>
                          <span>PDF</span>
                        </div>
                        <div class="flex items-center space-x-1">
                          <i class="fa-solid fa-file-image"></i>
                          <span>Images</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Logs Panel -->
          <div id="logsPanel" class="p-6 hidden">
            <div id="logsContent"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Progress Modal -->
    <div id="progressModal" class="fixed inset-0 bg-black/30 hidden items-center justify-center">
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

  
 <!-- Include Global File Number Modal -->
  @include('components.global-fileno-modal')
    <!-- Footer -->
    @include('admin.footer')
</div>



  <!-- Global File Number Modal JavaScript -->
  <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
  <script>
    // ---- Config
    const MIGRATE_ENDPOINT = window.location.pathname;
    const API_LIST = '{{ route("blind_scan.api.list") }}';
    const API_LOGS = '{{ route("blind_scan.api.logs") }}';

    // ---- DOM
    const fileNoEl   = document.getElementById('fileNo');
    const folderEl   = document.getElementById('folderInput');
    const statusEl   = document.getElementById('status');
    const migrateBtn = document.getElementById('migrateBtn');

    // Progress modal helpers
    function openProgress(title, sub, pct=0){
      document.getElementById('progressTitle').textContent = title;
      document.getElementById('progressSub').textContent = sub || '';
      document.querySelector('#progressModal .progress-bar').style.width = pct+'%';
      document.getElementById('progressText').textContent = pct+'%';
      document.getElementById('progressDone').classList.add('hidden');
      const m = document.getElementById('progressModal');
      m.classList.remove('hidden'); m.classList.add('flex');
    }
    function setProgress(pct){
      document.querySelector('#progressModal .progress-bar').style.width = pct+'%';
      document.getElementById('progressText').textContent = pct+'%';
    }
    function doneProgress(){
      document.getElementById('progressDone').classList.remove('hidden');
      setTimeout(()=> {
        const m = document.getElementById('progressModal');
        m.classList.add('hidden'); m.classList.remove('flex');
      }, 600);
    }

    // ---- Upload state
    let selectedFiles = [];
    let ready = false;
    let localParentName = null; // may be null when not detectable

    const norm = s => (s || '').trim();

    // Try to infer a root name from the picked files (may return null)
    function detectSelectedRoot(files){
      const segs = [];
      for (const f of files) {
        const rel = f.webkitRelativePath || f.name;
        const first = (rel.split('/')[0] || '').trim();
        if (first) segs.push(first.toLowerCase());
      }
      if (!segs.length) return null;
      const unique = Array.from(new Set(segs));
      // If the first segment is consistent AND not "a3"/"a4", assume it's the parent
      if (unique.length === 1 && unique[0] !== 'a3' && unique[0] !== 'a4') {
        return unique[0];
      }
      // Common case: browser only exposes A3/A4 as first segment → cannot infer
      return null;
    }

    function validateUploadSection(){
      const typedRaw = norm(fileNoEl.value);
      const typed = typedRaw.toLowerCase();
      const hasFiles = selectedFiles.length > 0;

      if (!hasFiles){
        statusEl.textContent = 'No folder selected.';
        migrateBtn.disabled = true; ready = false; return;
      }

      const inferred = detectSelectedRoot(selectedFiles); // may be null
      localParentName = inferred;

      if (!typed){
        statusEl.textContent = inferred
          ? `Detected local folder "${inferred}". Enter the File Number to proceed.`
          : 'Folder selected. Enter the File Number to proceed.';
        migrateBtn.disabled = true; ready = false; return;
      }

      if (inferred){
        const match = (typed === inferred);
        if (!match){
          statusEl.innerHTML = `Folder appears to be <code>${inferred}</code> but you typed <code>${typedRaw}</code>.`;
          migrateBtn.disabled = true; ready = false; return;
        }
        statusEl.innerHTML = `Ready: <b>${typedRaw}</b> will be migrated with all subfolders and files (${selectedFiles.length} items).`;
        migrateBtn.disabled = false; ready = true; return;
      } else {
        statusEl.innerHTML = `Ready: <b>${typedRaw}</b> (parent name not detectable by browser). `
          + `All selected items (${selectedFiles.length}) will be zipped and migrated.`;
        migrateBtn.disabled = false; ready = true; return;
      }
    }

    folderEl.addEventListener('change', () => {
      selectedFiles = Array.from(folderEl.files || []);
      validateUploadSection();
    });
    fileNoEl.addEventListener('input', validateUploadSection);

    // ---- Upload files directly without zipping
    async function migrateNow(){
      if (!ready) return;

      const folderName = norm(fileNoEl.value);
      openProgress('Preparing Upload', `${folderName} → storage\\app\\public\\EDMS\\BLIND_SCAN`, 5);

      try {
        const form = new FormData();
        form.append('_token', '{{ csrf_token() }}');
        form.append('folderName', folderName);
        form.append('uploadMethod', 'direct');

        // Add all files with their relative paths
        let fileCount = 0;
        for (const file of selectedFiles) {
          const relativePath = file.webkitRelativePath || file.name;
          form.append(`files[${fileCount}]`, file);
          form.append(`paths[${fileCount}]`, relativePath);
          fileCount++;
        }

        setProgress(20);

        const res = await fetch(MIGRATE_ENDPOINT, { 
          method: 'POST', 
          body: form,
          // Add progress tracking
          onUploadProgress: (progressEvent) => {
            if (progressEvent.lengthComputable) {
              const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
              setProgress(20 + (percentCompleted * 0.7)); // 20% to 90%
            }
          }
        });

        setProgress(95);

        const data = await res.json().catch(()=>({}));
        if (res.ok && data.ok) {
          setProgress(100);
          doneProgress();
          // refresh server view & logs
          await Promise.all([ fetchServerList(currentSrvPath), fetchLogs() ]);
          statusEl.innerHTML = `<span class="text-green-700">Migration complete.</span> Saved to <code>${data.serverPath || '/storage/'+folderName}</code>.`;
          // Clear selection
          folderEl.value = '';
          selectedFiles = [];
          ready = false;
          migrateBtn.disabled = true;
        } else {
          document.getElementById('progressModal').classList.add('hidden');
          const msg = (data && (data.error || data.message)) || `HTTP ${res.status}`;
          alert('Migration failed: ' + msg);
        }
      } catch (error) {
        document.getElementById('progressModal').classList.add('hidden');
        alert('Migration failed: ' + error.message);
      }
    }

    migrateBtn.addEventListener('click', migrateNow);

    // ----------------------------
    // Server browser + logs (GET)
    // ----------------------------
    let currentSrvPath = ''; // '' means /storage

    function srvRow(html){ const tr=document.createElement('tr'); tr.className='border-b table-row hover:bg-gray-50 transition-colors duration-150'; tr.innerHTML=html; return tr; }
    function fmtBytes(n){
      if (!n && n!==0) return '-';
      if (n < 1024) return n + ' B';
      if (n < 1024*1024) return (n/1024).toFixed(1)+' KB';
      return (n/(1024*1024)).toFixed(1)+' MB';
    }
    function fmtDate(ts){
      if (!ts) return '-';
      const d = new Date(ts*1000);
      return d.toLocaleString();
    }
    function clearPreview(){
      document.getElementById('previewBox').innerHTML =
        `<div class="flex flex-col items-center justify-center h-full space-y-3">
          <i class="fa-solid fa-file-image text-4xl text-gray-300"></i>
          <p class="text-center text-gray-500">Select a PDF or image to preview</p>
          <p class="text-xs text-gray-400 text-center">Click "Preview" next to any file in the list</p>
        </div>`;
    }
    function previewFile(href, name){
      const box = document.getElementById('previewBox');
      const ext = (name.split('.').pop() || '').toLowerCase();
      
      // Show loading state
      box.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full space-y-4 p-8">
          <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center animate-pulse">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-600"></i>
          </div>
          <div class="text-center space-y-2">
            <p class="text-gray-700 font-medium">Loading preview...</p>
            <p class="text-sm text-gray-500">${name}</p>
          </div>
        </div>`;
      
      setTimeout(() => {
        box.innerHTML = '';
        if (['png','jpg','jpeg','gif','webp'].includes(ext)) {
          const imgContainer = document.createElement('div');
          imgContainer.className = 'flex items-center justify-center h-full p-4';
          
          const img = document.createElement('img');
          img.src = href; 
          img.alt = name; 
          img.className = 'max-w-full max-h-full rounded-lg border shadow-sm object-contain';
          img.style.maxHeight = '480px';
          
          img.onload = () => {
            imgContainer.innerHTML = '';
            imgContainer.appendChild(img);
          };
          
          img.onerror = () => {
            imgContainer.innerHTML = `
              <div class="text-center space-y-2">
                <i class="fa-solid fa-exclamation-triangle text-3xl text-yellow-500"></i>
                <p class="text-gray-600">Failed to load image</p>
                <a href="${href}" target="_blank" class="text-blue-600 hover:underline text-sm">Open in new tab</a>
              </div>`;
          };
          
          box.appendChild(imgContainer);
        } else if (ext === 'pdf') {
          const iframe = document.createElement('iframe');
          iframe.src = href; 
          iframe.className='w-full rounded-lg border';
          iframe.style.minHeight='480px';
          iframe.style.height='480px';
          box.appendChild(iframe);
        } else {
          box.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full space-y-4 p-8">
              <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-file text-2xl text-gray-400"></i>
              </div>
              <div class="text-center space-y-2">
                <p class="text-gray-700 font-medium">Preview not available</p>
                <p class="text-sm text-gray-500">File type: ${ext||'unknown'}</p>
                <a class="inline-flex items-center space-x-2 text-blue-600 hover:text-blue-800 font-medium" href="${href}" target="_blank" rel="noopener">
                  <i class="fa-solid fa-external-link"></i>
                  <span>Open in new tab</span>
                </a>
              </div>
            </div>`;
        }
      }, 300);
    }

    async function fetchServerList(subPath=''){
      const url = new URL(API_LIST, window.location.href);
      if (subPath) url.searchParams.set('path', subPath);
      const res = await fetch(url);
      const data = await res.json();
      if (!res.ok || !data.ok) { alert(data.error || ('HTTP '+res.status)); return; }

      currentSrvPath = data.sub || '';
      document.getElementById('srvPath').textContent = '/storage/EDMS/BLIND_SCAN' + (currentSrvPath ? '/'+currentSrvPath : '');

      const crumbs = data.crumbs || [];
      const crumbsEl = document.getElementById('srvCrumbs');
      if (!crumbs.length) {
        crumbsEl.textContent = 'Root';
      } else {
        crumbsEl.innerHTML = crumbs.map((c,i)=>(
          `<button class="text-blue-700 hover:underline" data-path="${c.path}">${c.name}</button>${i<crumbs.length-1?' / ':''}`
        )).join('');
        crumbsEl.querySelectorAll('button').forEach(b=>{
          b.addEventListener('click', ()=> fetchServerList(b.dataset.path));
        });
      }

      const tbody = document.getElementById('srvRows');
      tbody.innerHTML = '';

      if (currentSrvPath){
        const up = currentSrvPath.split('/').slice(0,-1).join('/');
        const trUp = srvRow(`
          <td class="px-4 py-3">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-50">
              <i class="fa-solid fa-arrow-left text-blue-600 text-sm"></i>
            </div>
          </td>
          <td class="px-4 py-3">
            <button class="srv-nav text-blue-700 hover:text-blue-800 font-medium flex items-center space-x-2 hover:underline transition-colors duration-200" data-path="${up}">
              <span>← Go Back</span>
            </button>
          </td>
          <td class="px-4 py-3 text-gray-500">-</td>
          <td class="px-4 py-3"></td>
        `);
        trUp.querySelector('.srv-nav').addEventListener('click', ()=> fetchServerList(up));
        tbody.appendChild(trUp);
      }

      const items = data.items || [];
      if (!items.length){
        tbody.appendChild(srvRow(`<td class="px-4 py-3 text-gray-500 text-center" colspan="4">
          <div class="flex flex-col items-center py-4 space-y-2">
            <i class="fa-solid fa-folder-open text-2xl text-gray-300"></i>
            <span>Empty folder</span>
          </div>
        </td>`));
      } else {
        for (const item of items){
          if (item.type === 'dir'){
            const tr = srvRow(`
              <td class="px-4 py-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-50">
                  <i class="fa-solid fa-folder text-blue-600 text-sm"></i>
                </div>
              </td>
              <td class="px-4 py-3">
                <button class="srv-nav text-blue-700 hover:text-blue-800 font-medium hover:underline transition-colors duration-200" data-path="${(currentSrvPath?currentSrvPath+'/':'')+item.name}">
                  ${item.name}
                </button>
              </td>
              <td class="px-4 py-3 text-gray-600 text-sm">${fmtDate(item.mtime)}</td>
              <td class="px-4 py-3"></td>
            `);
            tr.querySelector('.srv-nav').addEventListener('click', ()=> fetchServerList((currentSrvPath?currentSrvPath+'/':'')+item.name));
            tbody.appendChild(tr);
          } else {
            const href = item.href || '#';
            const fileExt = (item.name.split('.').pop() || '').toLowerCase();
            let fileIcon = 'fa-file';
            let iconColor = 'text-gray-500';
            
            // Set specific icons and colors based on file type
            if (['pdf'].includes(fileExt)) {
              fileIcon = 'fa-file-pdf';
              iconColor = 'text-red-500';
            } else if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(fileExt)) {
              fileIcon = 'fa-file-image';
              iconColor = 'text-green-500';
            } else if (['doc', 'docx'].includes(fileExt)) {
              fileIcon = 'fa-file-word';
              iconColor = 'text-blue-500';
            } else if (['xls', 'xlsx'].includes(fileExt)) {
              fileIcon = 'fa-file-excel';
              iconColor = 'text-green-600';
            }
            
            const tr = srvRow(`
              <td class="px-4 py-3">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-50">
                  <i class="fa-solid ${fileIcon} ${iconColor} text-sm"></i>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-col">
                  <span class="font-medium text-gray-900">${item.name}</span>
                  <span class="text-xs text-gray-500 uppercase">${fileExt || 'file'}</span>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-600 text-sm">${fmtDate(item.mtime)}</td>
              <td class="px-4 py-3">
                <div class="flex gap-2">
                  <button class="srv-preview px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-md text-xs font-medium transition-colors duration-200 flex items-center space-x-1" data-href="${href}" data-name="${item.name}">
                    <i class="fa-solid fa-eye"></i>
                    <span>Preview</span>
                  </button>
                  <a class="px-3 py-1.5 bg-gray-50 text-gray-600 hover:bg-gray-100 rounded-md text-xs font-medium no-underline transition-colors duration-200 flex items-center space-x-1" href="${href}" target="_blank" rel="noopener">
                    <i class="fa-solid fa-external-link"></i>
                    <span>Open</span>
                  </a>
                </div>
              </td>
            `);
            tr.querySelector('.srv-preview').addEventListener('click', (e)=> previewFile(e.currentTarget.dataset.href, e.currentTarget.dataset.name));
            tbody.appendChild(tr);
          }
        }
      }
      clearPreview();
    }

    async function fetchLogs(){
      const res = await fetch(API_LOGS);
      const data = await res.json();
      if (!res.ok || !data.ok) { alert(data.error || ('HTTP '+res.status)); return; }
      const logs = data.logs || [];
      const root = document.getElementById('logsContent');
      if (!logs.length) {
        root.innerHTML = `
          <div class="text-center py-8">
            <i class="fa-solid fa-clock-rotate-left text-3xl text-gray-400 mx-auto mb-4"></i>
            <p class="text-gray-500">No migrations yet</p>
            <p class="text-sm text-gray-400">After migrating, entries will appear here.</p>
          </div>`;
        return;
      }
      root.innerHTML = `
        <div class="border border-gray-200 rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50">
              <tr class="border-b border-gray-200">
                <th class="text-left p-3 font-medium text-gray-700">When</th>
                <th class="text-left p-3 font-medium text-gray-700">Parent Folder</th>
                <th class="text-left p-3 font-medium text-gray-700">Server Path</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              ${logs.map(m=>`
                <tr class="hover:bg-gray-50">
                  <td class="p-3">${m.when || '-'}</td>
                  <td class="p-3 font-mono">${m.folder || '-'}</td>
                  <td class="p-3 font-mono"><a class="text-blue-700 hover:underline" href="${m.serverPath||'#'}" target="_blank" rel="noopener">${m.serverPath||'-'}</a></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>`;
    }

    // Tabs
    const serverPanel = document.getElementById('serverPanel');
    const logsPanel   = document.getElementById('logsPanel');
    document.getElementById('tabServer').addEventListener('click', ()=>{ serverPanel.classList.remove('hidden'); logsPanel.classList.add('hidden'); });
    document.getElementById('tabLogs').addEventListener('click', ()=>{ logsPanel.classList.remove('hidden'); serverPanel.classList.add('hidden'); });
    document.getElementById('srvRefresh').addEventListener('click', ()=> fetchServerList(currentSrvPath));

    // Boot
    (async function(){
      await fetchServerList('');
      await fetchLogs();
      
      // Initialize file number modal after everything else is loaded
      initializeFileNumberModal();
    })();
    
    // File Number Modal Initialization
    function initializeFileNumberModal() {
      console.log('Initializing file number modal...');
      console.log('Select button exists:', $('#selectFileNoBtn').length > 0);
      console.log('Modal exists:', $('#global-fileno-modal').length > 0);
      
      // Initialize Lucide icons
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
        console.log('Lucide icons initialized');
      } else {
        console.log('Lucide not available');
      }
      
      // File number button click handler
      $('#selectFileNoBtn, #changeFileNoBtn').off('click').on('click', function(e) {
        e.preventDefault();
        console.log('File number button clicked!');
        
        try {
          // Use the global object directly
          if (typeof GlobalFileNoModal !== 'undefined' && typeof GlobalFileNoModal.open === 'function') {
            console.log('Opening modal via GlobalFileNoModal.open()...');
            
            // Set the callback function that will be called when the Apply button is clicked
            GlobalFileNoModal.config.callback = function(data) {
              const fileNumber = data.fileNumber;
              const tabName = data.tab;
              
              console.log('File number selected:', fileNumber, 'from', tabName);
              
                  // Update the display and hidden input
                  $('#selectedFileNo').text(fileNumber);
                  $('#fileNo').val(fileNumber);
                  
                  // Show the display area and hide the select button
                  $('#fileNoDisplay').show();
                  $('#selectFileNoBtn').hide();              // Trigger validation update for migrate button
              if (typeof validateUploadSection === 'function') {
                validateUploadSection();
              }
              
              // Show success feedback
              const notification = $(`
                <div class="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 flex items-center">
                  <i class="fa-solid fa-check-circle mr-2"></i>
                  File number selected: <strong>${fileNumber}</strong>
                </div>
              `);
              
              $('body').append(notification);
              setTimeout(() => {
                notification.fadeOut(() => notification.remove());
              }, 3000);
            };
            
            // Open the modal
            GlobalFileNoModal.open({
              initialTab: 'mls'
            });
          } else {
            console.error('GlobalFileNoModal global object not available');
            alert('File Number Modal is not available. Please refresh the page.');
          }
        } catch (error) {
          console.error('Error opening modal:', error);
          alert('Error opening modal: ' + error.message);
        }
      });
      
      console.log('File number modal initialization complete');
    }
  </script>

 
@endsection
{{-- Upload & Migrate Section --}}
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
        Choose from MLPP, KANGIS, or New KANGIS file number formats
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
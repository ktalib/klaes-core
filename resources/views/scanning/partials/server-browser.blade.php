{{-- Server Browser Panel --}}
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
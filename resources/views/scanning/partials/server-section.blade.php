{{-- Server Storage & Logs Section --}}
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

  @include('scanning.partials.server-browser')
  @include('scanning.partials.logs-panel')
</div>
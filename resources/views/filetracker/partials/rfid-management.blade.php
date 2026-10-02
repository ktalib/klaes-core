<div class="bg-white rounded-lg shadow-sm border">
  <div class="px-6 py-4 border-b">
    <h2 class="text-lg font-semibold">RFID Management</h2>
    <p class="text-sm text-gray-500">Manage RFID tags for physical files</p>
  </div>
  <div class="p-6 space-y-4">
    <div class="space-y-2">
      <label class="text-sm font-medium" for="rfid-search">Search by RFID Tag</label>
      <div class="flex gap-2">
        <input id="rfid-search" placeholder="e.g. RFID-00125478" class="border rounded-md px-3 py-2 text-sm w-full">
        <button class="border rounded-md p-2">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </button>
      </div>
    </div>
    
    <div class="grid grid-cols-2 gap-4">
      <button class="border rounded-md h-auto py-4 flex flex-col items-center justify-center">
        <svg class="h-5 w-5 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
        </svg>
        <span class="text-xs">Register New Tag</span>
      </button>
      <button class="border rounded-md h-auto py-4 flex flex-col items-center justify-center">
        <svg class="h-5 w-5 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
        </svg>
        <span class="text-xs">Scan Tags</span>
      </button>
      <button class="border rounded-md h-auto py-4 flex flex-col items-center justify-center">
        <svg class="h-5 w-5 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
        </svg>
        <span class="text-xs">RFID Reports</span>
      </button>
      <button class="border rounded-md h-auto py-4 flex flex-col items-center justify-center">
        <svg class="h-5 w-5 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
        </svg>
        <span class="text-xs">Test RFID Reader</span>
      </button>
    </div>
  </div>
</div>

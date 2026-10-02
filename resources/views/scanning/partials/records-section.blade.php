{{-- Records Table Section --}}
<div class="bg-white border border-gray-200 rounded-lg shadow-sm">
  <!-- Header -->
  <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
          <i class="fa-solid fa-database mr-2 text-blue-600"></i>
          Blind Scanning Records
        </h2>
        <p class="text-sm text-gray-600 mt-1">View all blind scanning uploads and their status</p>
      </div>
      <button id="refreshRecords" class="px-3 py-2 text-sm font-medium border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors duration-200">
        <i class="fa-solid fa-refresh mr-1"></i>Refresh
      </button>
    </div>
  </div>

  <!-- Content -->
  <div class="p-6">
    @include('scanning.partials.records-filters')
    @include('scanning.partials.records-table')
    @include('scanning.partials.records-pagination')
  </div>
</div>
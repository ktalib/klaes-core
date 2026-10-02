{{-- Records Filters --}}
<div class="mb-4 p-4 bg-gray-50 rounded-lg">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
      <select id="filterStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="converted">Converted</option>
        <option value="archived">Archived</option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Paper Size</label>
      <select id="filterPaperSize" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All Sizes</option>
        <option value="A4">A4</option>
        <option value="A3">A3</option>
        <option value="Letter">Letter</option>
        <option value="Legal">Legal</option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
      <select id="filterDocType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All Types</option>
        <option value="application">Application</option>
        <option value="survey">Survey</option>
        <option value="plan">Plan</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
      <input id="filterSearch" type="text" placeholder="Search filename, notes..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
  </div>
  <div class="mt-3 flex gap-2">
    <button id="applyFilters" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200">
      <i class="fa-solid fa-filter mr-1"></i>Apply Filters
    </button>
    <button id="clearFilters" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors duration-200">
      <i class="fa-solid fa-times mr-1"></i>Clear
    </button>
  </div>
</div>
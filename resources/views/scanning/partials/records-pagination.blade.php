{{-- Records Pagination --}}
<div id="recordsPagination" class="mt-4 flex items-center justify-between text-sm text-gray-600">
  <div>
    @if(isset($initialRecords))
    Showing <span id="recordsFrom">{{ $initialRecords->firstItem() ?: 0 }}</span> to <span id="recordsTo">{{ $initialRecords->lastItem() ?: 0 }}</span> of <span id="recordsTotal">{{ $initialRecords->total() }}</span> results
    @else
    Showing <span id="recordsFrom">0</span> to <span id="recordsTo">0</span> of <span id="recordsTotal">0</span> results
    @endif
  </div>
  <div class="flex gap-2">
    <button id="prevPage" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed" @if(isset($initialRecords) && $initialRecords->currentPage() <= 1) disabled @endif>
      <i class="fa-solid fa-chevron-left"></i> Previous
    </button>
    <button id="nextPage" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed" @if(isset($initialRecords) && $initialRecords->currentPage() >= $initialRecords->lastPage()) disabled @endif>
      Next <i class="fa-solid fa-chevron-right"></i>
    </button>
  </div>
</div>
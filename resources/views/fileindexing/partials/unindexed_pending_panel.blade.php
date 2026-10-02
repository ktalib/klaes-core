<div class="card">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h2 class="text-xl font-bold">Unindexed Files</h2>
                <p class="text-sm text-gray-500">Select files to begin the indexing process</p>
            </div>
            <div class="relative group">
                <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-500 group-focus-within:text-blue-500 transition-colors"></i>
                <input type="search"
                       placeholder="ƒs­ Instant search... (file number, name, district, LGA)"
                       class="input pl-10 pr-12 transition-all duration-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       id="search-pending-files"
                       autocomplete="off"
                       spellcheck="false"
                       title="dYs? Ultra-fast search with instant results - Press Ctrl+F for focus">
                <div class="absolute right-3 top-1/2 transform -translate-y-1/2" id="search-pending-indicator">
                    <div class="hidden animate-spin h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full" id="search-pending-spinner"></div>
                    <div class="text-xs text-green-500 font-medium hidden" id="search-pending-count"></div>
                </div>
            </div>
        </div>

        <div class="border rounded-md">
            <div class="flex justify-between items-center p-4 border-b bg-gray-50">
                <div class="flex items-center">
                    <input type="checkbox" id="select-all-checkbox" class="mr-2">
                    <label for="select-all-checkbox" class="text-sm font-medium">Select All</label>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-200">
                        <i data-lucide="files" class="h-4 w-4 text-blue-600 mr-2"></i>
                        <span class="text-sm font-medium text-blue-800" id="selected-files-count">0 of 0 selected</span>
                    </div>
                    <button class="bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-medium py-2.5 px-6 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" id="begin-indexing-btn" disabled>
                        <i data-lucide="play-circle" class="h-4 w-4"></i>
                        Begin Indexing
                    </button>
                </div>
            </div>

            <div id="pending-files-list">
                <!-- File items will be populated here by JavaScript -->
            </div>
        </div>

        <!-- Pagination for File Index -->
        <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6 mt-4" id="pending-pagination" style="display: none;">
            <div class="flex-1 flex justify-between sm:hidden">
                <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50" id="pending-prev-mobile">
                    Previous
                </button>
                <button class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50" id="pending-next-mobile">
                    Next
                </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium" id="pending-start">1</span> to <span class="font-medium" id="pending-end">10</span> of <span class="font-medium" id="pending-total">0</span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination" id="pending-pagination-nav">
                        <button class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" id="pending-prev">
                            <span class="sr-only">Previous</span>
                            <i data-lucide="chevron-left" class="h-4 w-4"></i>
                        </button>
                        <!-- Page numbers will be inserted here by JavaScript -->
                        <button class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" id="pending-next">
                            <span class="sr-only">Next</span>
                            <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

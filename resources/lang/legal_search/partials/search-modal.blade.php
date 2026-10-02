  <!-- Search Modal -->
  <div id="search-modal" class="hidden modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Search Land Records</h2>
      </div>

      <div class="flex flex-col h-full overflow-hidden">
        <!-- Search Section -->
        <div class="search-section">

          {{-- ── Primary Search: File Number ────────────────────────────── --}}
          <div class="mb-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">File Number</label>
            <div class="flex items-center gap-2">
              <div class="relative flex-1 max-w-xs">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </div>
                <input type="text" id="fileNumber" placeholder="Use the selector to pick a file number..."
                  class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm font-mono uppercase bg-gray-100 text-gray-500 cursor-not-allowed"
                  autocomplete="off" disabled readonly>
              </div>
              <button type="button" id="open-fileno-selector-btn"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition shadow-sm whitespace-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Select File Number
              </button>
              <button type="button" id="clear-fileno-btn"
                class="hidden p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition" title="Clear file number">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
              <button type="button" id="search-now-btn" disabled
                class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-gray-300 text-gray-500 text-sm font-semibold rounded-lg cursor-not-allowed transition shadow-sm whitespace-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Search
              </button>
            </div>
            {{-- File number info badge (shown after selector picks a number) --}}
            <div id="fileno-info-badge" class="hidden mt-2 flex items-center gap-2">
              <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span id="fileno-type-label">MLS</span>
              </span>
              <span id="fileno-owner-label" class="text-xs text-gray-500"></span>
            </div>
          </div>

          {{-- ── Additional Filters (dropdown-based) ──────────────────── --}}
          <div class="border-t border-gray-100 pt-3 mb-3">
            <button type="button" id="toggle-filters-btn" class="flex items-center gap-2 mb-2 cursor-pointer group">
              <svg id="filters-chevron" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
              <span class="text-sm font-medium text-gray-600 group-hover:text-gray-800">Additional Filters</span>
              <span id="active-filter-count" class="hidden inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">0</span>
            </button>

            {{-- Filter selector dropdown (hidden by default) --}}
            <div id="filters-panel" class="hidden">
            <select id="filter-selector" class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-white mb-3">
              <option value="">— Select a filter to add —</option>
              <option value="guarantorName">Party 1</option>
              <option value="guaranteeName">Party 2</option>
              <option value="lga">LGA</option>
              <option value="district">District</option>
              <option value="location">Location</option>
              <option value="plotNumber">Plot Number</option>
              <option value="planNumber">Plan Number</option>
              <option value="size">Size</option>
              <option value="caveat">Caveat</option>
            </select>

            {{-- Active filters container (3-column grid) --}}
            <div id="filters-container" class="grid grid-cols-3 gap-3">
              {{-- Dynamically populated filter cells go here --}}
            </div>

            {{-- Hidden filter templates (used by JS to build the input rows) --}}
            <template id="tpl-guarantorName">
              <input type="text" id="guarantorName" placeholder="Enter party 1 name"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </template>
            <template id="tpl-guaranteeName">
              <input type="text" id="guaranteeName" placeholder="Enter party 2 name"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </template>
            <template id="tpl-lga">
              <select id="lga" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-white">
                <option value="">All LGAs</option>
                <option value="Dala">Dala</option>
                <option value="Fagge">Fagge</option>
                <option value="Gwale">Gwale</option>
                <option value="Kano Municipal">Kano Municipal</option>
                <option value="Nassarawa">Nassarawa</option>
                <option value="Tarauni">Tarauni</option>
                <option value="Ungogo">Ungogo</option>
              </select>
            </template>
            <template id="tpl-district">
              <input type="text" id="district" placeholder="Enter district"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </template>
            <template id="tpl-location">
              <input type="text" id="location" placeholder="Enter location"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </template>
            <template id="tpl-plotNumber">
              <input type="text" id="plotNumber" placeholder="Enter plot number"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </template>
            <template id="tpl-planNumber">
              <input type="text" id="planNumber" placeholder="Enter plan number"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </template>
            <template id="tpl-size">
              <input type="text" id="size" placeholder="Enter size"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </template>
            <template id="tpl-caveat">
              <select id="caveat" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-white">
                <option value="">Any</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
              </select>
            </template>
            </div>{{-- end #filters-panel --}}
          </div>

          {{-- ── Results toolbar ────────────────────────────────────────── --}}
          <div class="flex justify-between items-center border-t border-gray-100 pt-3">
            <div class="flex items-center gap-3">
              <div class="text-sm text-gray-500"><span id="results-count">0</span> results found</div>
              {{-- Source breakdown badges (shown after search) --}}
              <div id="source-badges" class="hidden flex items-center gap-1.5">
                <span id="badge-file-history" class="hidden inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">FH: 0</span>
                <span id="badge-cofo" class="hidden inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">CofO: 0</span>
                <span id="badge-pra" class="hidden inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">PRA: 0</span>
                <span id="badge-deed" class="hidden inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Deed: 0</span>
              </div>
              <span id="active-filters-summary" class="hidden text-xs text-gray-400"></span>
            </div>
            <div class="flex items-center gap-2">
              <button id="reset-search-btn" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Reset
              </button>
              <div class="tabs">
                <button class="tab active" data-view="table">Table View</button>
                <button class="tab" data-view="cards">Card View</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
          <!-- Loading Spinner -->
          <div id="search-loading" class="hidden flex justify-center items-center py-8">
            <div class="spinner"></div>
          </div>

          <!-- No Results Message -->
          <div id="no-results-message" class="hidden text-center py-8">
            <p class="text-gray-500">No results found. Please try a different search.</p>
          </div>

          <!-- File Information (shown when the file is indexed but has no transactions) -->
          <div id="file-info-fallback" class="hidden py-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 max-w-xl mx-auto">
              <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">File Information</h3>
              </div>
              <div id="file-info-fallback-body" class="px-6 py-4 space-y-3 text-sm"></div>
            </div>
          </div>

          <!-- Table View Results -->
          <div id="table-results" class="hidden border rounded-md overflow-hidden">
            <div class="overflow-x-auto overflow-y-auto" style="max-height: 50vh;">
              <table class="w-full min-w-[1000px]">
                <thead class="bg-gray-100">
                  <tr>
                    <th>ST Unit FileNo</th>
                    <th>New FileNo</th>
                    <th>MLS File No</th>
                    <th>KANGIS File No</th>
                    <th>New KANGIS</th>
                    <th>File No</th>
                    <th>Party 1</th>
                    <th>Party 2</th>
                    <th>Party 3</th>
                    <th>LGA</th>
                    <th>Location</th>
                    <th>Plot No</th>
                    <th>Instrument/Transaction Type</th>
                    <th>Size</th>
                    <th>Caveat</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="table-results-body"></tbody>
              </table>
            </div>
          </div>

          <!-- Card View Results -->
          <div id="card-results" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4"></div>

          <!-- File Details View -->
          <div id="file-details-view" class="hidden space-y-6"></div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
  <div id="delete-confirm-dialog" class="hidden modal-overlay">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full">
      <h3 class="text-lg font-semibold mb-2">Confirm Deletion</h3>
      <p class="text-gray-500 mb-4">Are you sure you want to delete this record? This action cannot be undone.</p>
      <div class="flex justify-end gap-2">
        <button id="cancel-delete-btn" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium">Cancel</button>
        <button id="confirm-delete-btn" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">Delete</button>
      </div>
    </div>
  </div>

  {{-- Global File Number Selector Modal --}}
  @include('components.global-fileno-modal')

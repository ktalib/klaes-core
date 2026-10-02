<div id="batchRegisterModal" class="modal ">
    <div class="modal-content modal-center-y lg:max-w-[1100px]" style="width: 90%; max-width: 800px;">
      <div class="modal-header flex justify-between items-center p-4 border-b">
        <h2 class="text-lg font-semibold modal-title">Batch Registration</h2>
        <button onclick="closeBatchRegisterModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
 
    <p class="text-gray-500 mb-6 batch-instruction-text">Select multiple instruments to register in batch.</p>
    
    <div id="batchSearchSection" class="batch-filter-section available-instruments-section">
      <div class="flex justify-between mb-4">
        <h3 class="text-md font-medium">Available Instruments</h3> 
        <div class="flex gap-2">
          <div class="relative w-64">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
            <input type="search" id="batchSearchInput" placeholder="Search instruments..." 
                  class="w-full pl-8 pr-3 py-2 border rounded-md">
          </div>
          <select id="batchStatusFilter" class="border rounded-md px-3 py-2">
            <option value="other">Other Instruments</option>
            <option value="stAssignment">ST Assignment (Transfer of Title)</option>
            <option value="regular">Regular CofO</option>
            <option value="sectional">Sectional Titling CofO</option>
            <option value="sltr">SLTR CofO</option>
            <option value="batch" selected>Batch Registration</option>
          </select>
        </div>
      </div>

      <div class="border rounded-md overflow-hidden mb-6">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <input type="checkbox" class="rounded" id="selectAllAvailable" onchange="toggleSelectAllAvailable(this)">
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                File No
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Grantor
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Grantee
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
            </tr>
          </thead>
          <tbody id="availablePropertiesTable">
            <tr>
              <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                <div class="flex items-center justify-center">
                  <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Loading available instruments...
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-end mb-6">
        <button id="addSelectedBtn" class="bg-blue-600 text-white px-4 py-2 rounded-md disabled:opacity-50 disabled:cursor-not-allowed" onclick="addSelectedToBatch()" disabled>
          Add Selected Instruments (0)
        </button>
      </div>
    </div>

      <!-- Common Registration Details -->
      <div id="batchRegistrationForm" class="mt-8 border-t pt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Common Registration Details</h3>
        
        <!-- Serial Number Information -->
        <div class="p-4 bg-blue-50 rounded-lg mb-6">
          <div class="flex items-center justify-between mb-2">
            <h4 class="font-medium text-blue-800">Serial Number Information</h4>
            <span class="badge bg-blue-100 text-blue-800 border-blue-200">Auto-Generated for Each Record</span>
          </div>
 
          <p class="text-sm text-gray-600 mb-4">The system will automatically assign sequential RegNo to each record following the pattern: SerialNo/PageNo/VolumeNo</p>
          
          <div class="bg-white p-3 rounded-md border border-blue-200">
            <p class="text-sm font-medium">Next available Registration number: <span id="batchNextSerialNo" class="font-bold">Loading...</span></p>
            <p class="text-xs text-gray-500 mt-1">This is the starting serial number. Each entry will increment sequentially.</p>
            <div id="serialNumberDebug" class="text-xs mt-2 text-red-500 hidden">
              <button onclick="retryFetchSerialNumber()" class="underline text-blue-600">Retry fetch</button>
              <span class="ml-2" id="serialNumberDebugMsg"></span>
            </div>
          </div>
        </div>
        
        <!-- Common Fields -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div>
            <label for="batchDeedsTime" class="block text-sm font-medium text-gray-700 mb-1">Deeds Time <span class="text-red-500">*</span></label>
            <input type="text" id="batchDeedsTime" name="deeds_time" class="w-full px-3 py-2 border rounded-md" placeholder="eg. 10:30 AM" required>
            <div class="text-xs text-gray-500">Format: HH:MM AM/PM</div>
            <div class="text-xs text-red-500 hidden" id="batchDeedsTimeError"></div>
          </div>
          
          <div>
            <label for="batchDeedsDate" class="block text-sm font-medium text-gray-700 mb-1">Deeds Date <span class="text-red-500">*</span></label>
            <input type="date" id="batchDeedsDate" name="deeds_date" class="w-full px-3 py-2 border rounded-md" required>
            <div class="text-xs text-red-500 hidden" id="batchDeedsDateError"></div>
          </div>
        </div>
      </div>
      
      <!-- Selected Properties Section -->
      <div class="border-t pt-4">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-medium">Selected Instruments for Registration</h3>
          <button type="button" onclick="clearSelectedProperties()" class="text-sm text-red-600 hover:text-red-800">
            <i class="fas fa-trash-alt mr-1"></i> Clear All
          </button>
        </div>
        
        <div id="selectedPropertiesContainer">
          <div class="border rounded-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    File No
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Grantor
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Grantee
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Instrument Type
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    RegNo
                  </th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Action
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200" id="selectedPropertiesTable">
                <tr id="noSelectedPropertiesRow">
                  <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                    No instruments selected for registration. Use the table above to select instruments.
                  </td>
                </tr>
                <!-- Template for selected instruments -->
                <tr id="selectedRowTemplate" class="hidden">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">MLS-2024-001</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">John Doe</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">Jane Smith</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <input type="text" class="w-full px-3 py-1 border rounded-md bg-gray-100" readonly>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">1/1/1</td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                    <button class="text-red-500 hover:text-red-700" onclick="removeFromBatch(this)">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div> 
      </div>

      <div class="flex justify-end gap-2 mt-6">
        <button onclick="closeBatchRegisterModal()" class="px-4 py-2 border rounded-md">
          Cancel
        </button>
        <button id="batchRegisterButton" class="bg-blue-600 text-white px-4 py-2 rounded-md disabled:opacity-50 disabled:cursor-not-allowed" onclick="submitBatchRegistration()" disabled>
          Register 0 Instruments
        </button>
      </div>
    </div>
  </div>
</div>
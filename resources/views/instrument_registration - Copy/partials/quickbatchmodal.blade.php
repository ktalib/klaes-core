<div id="quickBatchModal" class="modal">
    <div class="modal-content modal-center-y lg:max-w-[900px]" style="width: 90%; max-width: 900px;">
      <div class="modal-header flex justify-between items-center p-4 border-b">
        <h2 class="text-lg font-semibold modal-title">Quick Batch Registration</h2>
        <button onclick="closeQuickBatchModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="modal-body p-6">
        <p class="text-gray-600 mb-6">Register the selected instruments from the main table.</p>
        
        <!-- Serial Number Information -->
        <div class="p-4 bg-blue-50 rounded-lg mb-6">
          <div class="flex items-center justify-between mb-2">
            <h4 class="font-medium text-blue-800">Serial Number Information</h4>
            <span class="badge bg-blue-100 text-blue-800 border-blue-200">Auto-Generated for Each Record</span>
          </div>
 
          <p class="text-sm text-gray-600 mb-4">The system will automatically assign sequential RegNo to each record following the pattern: SerialNo/PageNo/VolumeNo</p>
          
          <div class="bg-white p-3 rounded-md border border-blue-200">
            <p class="text-sm font-medium">Next available Registration number: <span id="quickBatchNextSerialNo" class="font-bold">Loading...</span></p>
            <p class="text-xs text-gray-500 mt-1">This is the starting serial number. Each entry will increment sequentially.</p>
            <div id="quickSerialNumberDebug" class="text-xs mt-2 text-red-500 hidden">
              <button onclick="retryFetchSerialNumber()" class="underline text-blue-600">Retry fetch</button>
              <span class="ml-2" id="quickSerialNumberDebugMsg"></span>
            </div>
          </div>
        </div>
        
        <!-- Common Registration Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <label for="quickBatchDeedsTime" class="block text-sm font-medium text-gray-700 mb-1">Deeds Time <span class="text-red-500">*</span></label>
            <input type="text" id="quickBatchDeedsTime" name="deeds_time" class="w-full px-3 py-2 border rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="eg. 10:30 AM" readonly required>
            <div class="text-xs text-gray-500">Format: HH:MM AM/PM</div>
          </div>
          
          <div>
            <label for="quickBatchDeedsDate" class="block text-sm font-medium text-gray-700 mb-1">Deeds Date <span class="text-red-500">*</span></label>
            <input type="date" id="quickBatchDeedsDate" name="deeds_date" class="w-full px-3 py-2 border rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" readonly required>
          </div>
</qodoArtifact>

  </div>
        
        <!-- Selected Instruments Section -->
        <div class="border-t pt-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-medium">Selected Instruments for Registration</h3>
            <span id="quickBatchInstrumentCount" class="text-sm text-gray-600">0 instruments selected</span>
          </div>
          
          <div id="quickSelectedPropertiesContainer">
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
                <tbody class="bg-white divide-y divide-gray-200" id="quickSelectedPropertiesTable">
                  <tr id="quickNoSelectedPropertiesRow">
                    <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                      No instruments selected for registration.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>  
        </div>

        <div class="flex justify-end gap-2 mt-6">
          <button onclick="closeQuickBatchModal()" class="px-4 py-2 border rounded-md">
            Cancel
          </button>
          <button id="quickBatchRegisterButton" class="bg-blue-600 text-white px-4 py-2 rounded-md disabled:opacity-50 disabled:cursor-not-allowed" onclick="submitQuickBatchRegistration()" disabled>
            Register 0 Instruments
          </button>
        </div>
      </div>
    </div>
  </div>
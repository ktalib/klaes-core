<!-- Single Register Modal -->
<div id="singleRegisterModal" class="modal">
  <div class="modal-content max-w-4xl" style="width: 90%; max-width: 800px;">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-lg font-semibold">Register Sectional Title Certificate of Occupancy</h2>
      <button onclick="closeSingleRegisterModal()" class="text-gray-500 hover:text-gray-700">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <p class="text-gray-500 mb-6">Select a verified unit from the sectional title scheme to register its Certificate of Occupancy.</p>
    
    <!-- Unit Search Section -->
    <div id="unitSearchSection">
      <div class="flex gap-2 items-center mb-4">
        <div class="flex-1 relative">
          <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
          <input type="search" placeholder="Search for a unit..." class="w-full pl-8 pr-3 py-2 border rounded-md" id="unitSearchInput">
        </div>
        <select class="border rounded-md px-3 py-2" id="unitSearchFilter">
          <option value="stmRef">ST FileNo</option>
        </select>
      </div>

      <div class="border rounded-md overflow-hidden mb-6">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                ST FIleNo
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Unit
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Block
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Owner
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Action
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200" id="unitSearchResults">
            <!-- Will be populated by JavaScript -->
            <tr>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">STM-2024-001</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">101</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">A</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">Fatima Ahmed</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <span class="badge badge-registered">Verified</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <button class="bg-blue-600 text-white px-3 py-1 rounded-md text-sm" onclick="selectUnit(0)">Select</button>
              </td>
            </tr>
            <tr>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">STM-2024-001</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">102</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">A</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">Musa Ibrahim</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <span class="badge badge-registered">Verified</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <button class="bg-blue-600 text-white px-3 py-1 rounded-md text-sm" onclick="selectUnit(1)">Select</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Unit Details Section -->
    <div id="unitDetailsSection" class="hidden">
      <!-- Property Details Overview -->
      <div class="bg-gray-50 p-4 rounded-lg space-y-4 mb-6">
        <div class="flex items-center justify-between">
          <h3 class="font-medium">Selected Unit Details</h3>
          <button class="text-gray-500 hover:text-gray-700 text-sm" onclick="backToUnitSearch()">
            Change
          </button>
        </div>

        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          <div>
            <span class="text-gray-500">ST FileNo:</span>
            <span class="ml-2 font-medium" id="selectedStmRef">STM-2024-001</span>
          </div>
          <div>
            <span class="text-gray-500">Property:</span>
            <span class="ml-2 font-medium" id="selectedProperty">Love Court Estate</span>
          </div>
          <div>
            <span class="text-gray-500">Unit Number:</span>
            <span class="ml-2 font-medium" id="selectedUnitNo">101</span>
          </div>
          <div>
            <span class="text-gray-500">Block Number:</span>
            <span class="ml-2 font-medium" id="selectedBlockNo">A</span>
          </div>
          <div>
            <span class="text-gray-500">Owner:</span>
            <span class="ml-2 font-medium" id="selectedOwner">Fatima Ahmed</span>
          </div>
          <div>
            <span class="text-gray-500">Unit Type:</span>
            <span class="ml-2 font-medium" id="selectedUnitType"></span>
          </div>
          <div style="display: none;">
            <span class="text-gray-500">Unit Size:</span>
            <span class="ml-2 font-medium" id="selectedUnitSize">75 sq.m</span>
          </div>
          <div>
            <span class="text-gray-500">Status:</span>
            <span class="ml-2">
              <span class="badge badge-pending">Pending</span>
            </span>
          </div>
        </div>
      </div>

      <form id="singleRegistrationForm" class="space-y-6">
        <input type="hidden" id="formSubApplicationId" name="sub_application_id">
        
        <!-- Certificate Details Section -->
        <div class="border-t border-b py-4">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Certificate of Occupancy Details</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label for="sectionalTitleFileNo" class="block text-sm font-medium text-gray-700 mb-1">Sectional Title File No <span class="text-red-500">*</span></label>
              <input type="text" id="sectionalTitleFileNo" name="sectional_title_file_no" class="w-full px-3 py-2 border rounded-md bg-gray-100" readonly required>
              <div class="text-xs text-red-500 hidden" id="sectionalTitleFileNoError"></div>
            </div>
            
            <div>
              <label for="applicantName" class="block text-sm font-medium text-gray-700 mb-1">Applicant Name <span class="text-red-500">*</span></label>
              <input type="text" id="applicantName" name="applicant_name" class="w-full px-3 py-2 border rounded-md bg-gray-100" readonly required>
              <div class="text-xs text-red-500 hidden" id="applicantNameError"></div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label for="tenurePeriod" class="block text-sm font-medium text-gray-700 mb-1">Tenure Period (Years) <span class="text-red-500">*</span></label>
              <input type="number" id="tenurePeriod" name="tenure_period" class="w-full px-3 py-2 border rounded-md" min="1" max="99" required>
              <div class="text-xs text-red-500 hidden" id="tenurePeriodError"></div>
            </div>
            
            <div class="hidden" >
              <label for="deedsTransfer" class="block text-sm font-medium text-gray-700 mb-1">Deeds Transfer</label>
              <input type="text" id="deedsTransfer" name="deeds_transfer" class="w-full px-3 py-2 border rounded-md">
            </div>
          </div>
        </div>
        
        <!-- Current Owner Details -->
        <div class="border-b py-4" style="display: none;">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Owner Information</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label for="currentOwner" class="block text-sm font-medium text-gray-700 mb-1">Current Owner <span class="text-red-500">*</span></label>
              <input type="text" id="currentOwner" name="current_owner" class="w-full px-3 py-2 border rounded-md" required>
              <div class="text-xs text-red-500 hidden" id="currentOwnerError"></div>
            </div>
            
            <div>
              <label for="occupation" class="block text-sm font-medium text-gray-700 mb-1">Occupation <span class="text-red-500">*</span></label>
              <input type="text" id="occupation" name="occupation" class="w-full px-3 py-2 border rounded-md" required>
              <div class="text-xs text-red-500 hidden" id="occupationError"></div>
            </div>
          </div>
        </div>
        
        <!-- Registration Details -->
        <div class="border-b py-4">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Registration Details</h3>
          
          <!-- Serial Number Section -->
          <div class="p-4 bg-blue-50 rounded-lg mb-4">
            <div class="flex items-center justify-between mb-2">
              <h4 class="font-medium text-blue-800">Registration Number Information</h4>
              <span class="badge bg-blue-100 text-blue-800 border-blue-200">Auto-Generated</span>
            </div>
            
            <div class="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Serial No</label>
                <input type="text" id="serialNo" class="w-full px-3 py-2 border rounded-md bg-gray-100" readonly>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Page No</label>
                <input type="text" id="pageNo" class="w-full px-3 py-2 border rounded-md bg-gray-100" readonly>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Volume No</label>
                <input type="text" id="volumeNo" class="w-full px-3 py-2 border rounded-md bg-gray-100" readonly>
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">RegNo</label>
              <input type="text" id="deedsSerialNo" class="w-full px-3 py-2 border rounded-md bg-gray-100" readonly>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label for="deedsTime" class="block text-sm font-medium text-gray-700 mb-1">Deeds Time <span class="text-red-500">*</span></label>
              <input type="text" id="deedsTime" name="deeds_time" class="w-full px-3 py-2 border rounded-md" placeholder="eg. 10:30 AM" required>
              <div class="text-xs text-gray-500">Format: HH:MM AM/PM</div>
              <div class="text-xs text-red-500 hidden" id="deedsTimeError"></div>
            </div>
            
            <div>
              <label for="deedsDate" class="block text-sm font-medium text-gray-700 mb-1">Deeds Date <span class="text-red-500">*</span></label>
              <input type="date" id="deedsDate" name="deeds_date" class="w-full px-3 py-2 border rounded-md" required>
              <div class="text-xs text-red-500 hidden" id="deedsDateError"></div>
            </div>
          </div>
    
        </div>
        
        <div class="flex justify-end gap-2 mt-6">
          <button type="button" onclick="closeSingleRegisterModal()" class="px-4 py-2 border rounded-md">
            Cancel
          </button>
          <button type="button" id="registerButton" class="bg-blue-600 text-white px-4 py-2 rounded-md" onclick="submitSingleRegistration()">
            Register CofO
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Toast for success/error messages -->
<div id="toast" class="hidden fixed bottom-4 right-4 bg-white rounded-lg shadow-lg max-w-xs z-50">
  <div class="flex p-4">
    <div id="toastIcon" class="flex-shrink-0">
      <i class="fas fa-check-circle text-green-500 text-xl"></i>
    </div>
    <div class="ml-3">
      <p id="toastTitle" class="text-sm font-medium text-gray-900"></p>
      <p id="toastMessage" class="mt-1 text-sm text-gray-500"></p>
    </div>
    <div class="ml-auto pl-3">
      <button onclick="hideToast()" class="text-gray-400 hover:text-gray-500">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>
</div>

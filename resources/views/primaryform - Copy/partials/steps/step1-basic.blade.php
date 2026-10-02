{{-- Step 1: Basic Information --}}
<div class="form-section active" id="step1">
  <div class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-center text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
      <button id="closeModal" class="text-gray-500 hover:text-gray-700">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <div class="mb-6">
      <div class="flex items-center mb-2">
        <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
        <h3 class="text-lg font-bold">Application for Sectional Titling - Main Application</h3>
        <div class="ml-auto flex items-center">
          <span class="text-gray-600 mr-2">Land Use:</span>
          <span id="land-use-badge" class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">
            Not Selected
          </span>
        </div>
      </div>
      <p class="text-gray-600">Complete the form below to submit a new primary application for sectional titling</p>
    </div>

    {{-- Step Navigation --}}
  <div class="flex items-center mb-8">
      <div class="flex items-center mr-4">
        <div class="step-circle active cursor-pointer" onclick="goToStep(1)">1</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive cursor-pointer" onclick="goToStep(2)">2</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive cursor-pointer" onclick="goToStep(3)">3</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive cursor-pointer" onclick="goToStep(4)">4</div>
      </div>
      <div class="flex items-center">
        <div class="step-circle inactive cursor-pointer" onclick="goToStep(5)">5</div>
      </div>
  <div class="ml-4 step-status-text" data-step-indicator data-step-total="5">Step 1 of 5</div>
    </div>

    <div class="mb-6">
      <div class="text-right text-sm text-gray-500">CODE: ST FORM - 1</div>
      <hr class="my-4">
      
      {{-- Grid Layout for File Number Selection and Application Dates (1x1 / Side-by-Side) --}}
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- File Number Selection Card (Top Priority) --}}
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-6 shadow-lg">
          <div class="flex items-center mb-4">
            <div class="bg-purple-500 p-3 rounded-lg mr-3">
              <i data-lucide="search" class="w-6 h-6 text-white"></i>
            </div>
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-900">Select Primary File Number</h3>
              <p class="text-sm text-gray-600">Choose an existing file number to auto-populate all form fields</p>
            </div>
            <div class="bg-purple-600 px-3 py-1 rounded-full shadow-sm">
              <span class="text-white text-xs font-medium">REQUIRED</span>
            </div>
          </div>
          
          <div class="space-y-4">
            <label class="block text-sm font-medium text-gray-700 mb-3">
              Primary File Number <span class="text-red-500">*</span>
            </label>
            
            {{-- Loading State --}}
            <div id="top-file-loading" class="hidden mb-4">
              <div class="flex items-center justify-center py-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-purple-600"></div>
                <span class="ml-2 text-sm text-gray-600">Loading file numbers...</span>
              </div>
            </div>
            
            {{-- Dropdown --}}
            <div class="relative">
              <select id="top-primary-file-select" 
                      name="top_primary_file_number_id" 
                      class="w-full px-4 py-4 bg-white border-2 border-purple-200 rounded-lg text-purple-900 font-mono text-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                      onchange="handleTopFileSelection(this)"
                      required>
                <option value="">🔍 Select a Primary File Number to begin...</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400"></i>
              </div>
            </div>
            
            {{-- Selection Preview --}}
            <div id="top-selection-preview" class="hidden mt-4 p-4 bg-white rounded-lg border border-purple-200 shadow-sm">
              <div class="grid grid-cols-2 gap-4 mb-3">
                <div>
                  <div class="text-lg text-purple-600 font-medium">ST File Number:</div>
                  <div id="top-selected-file-display" class="font-mono text-xl font-bold text-purple-900">-</div>
                </div>
                <div>
                  <div class="text-lg text-purple-600 font-medium">Primary File Number:</div>
                  <div id="top-fileno-display" class="font-mono text-lg font-bold text-purple-700">-</div>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4 mb-3">
                <div>
                  <div class="text-lg text-purple-600 font-medium">Land Use:</div>
                  <div id="top-land-use-display" class="text-lg font-bold text-purple-700">-</div>
                </div>
                <div>
                  <div class="text-lg text-purple-600 font-medium">Tracking ID:</div>
                  <div id="top-tracking-id-display" class="font-mono text-base font-bold text-red-600">-</div>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4 pt-3 border-t border-purple-100">
                <div>
                  <div class="text-lg text-purple-600 font-medium">Applicant Type:</div>
                  <div id="top-applicant-type-display" class="text-lg font-bold text-purple-700">-</div>
                </div>
                <div>
                  <div class="text-lg text-purple-600 font-medium">Applicant:</div>
                  <div id="top-applicant-display" class="text-lg font-bold text-purple-900">-</div>
                </div>
              </div>
            </div>
            
            <div class="mt-4 p-3 bg-purple-50 rounded-lg border border-purple-200">
              <div class="flex items-start">
                <i data-lucide="info" class="w-4 h-4 text-purple-600 mr-2 mt-0.5 flex-shrink-0"></i>
                <p class="text-xs text-purple-800">
                  <strong>Important:</strong> Select a file number first to automatically populate all form sections including applicant information, land use, and file details.
                </p>
              </div>
            </div>
          </div>
        </div>



        {{-- Date Information Card --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
          <div class="flex items-center mb-4">
            <div class="bg-green-500 p-2 rounded-lg mr-3">
              <i data-lucide="calendar" class="w-5 h-5 text-white"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">Application Dates</h3>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Application Date --}}
            <div class="relative">
              <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                <i data-lucide="calendar-check" class="w-4 h-4 mr-2 text-green-600"></i>
                Application Date
              </label>
              <div class="relative">
                <input type="date" 
                       class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" 
                       value="{{ date('Y-m-d') }}" 
                       name="application_date">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                  <i data-lucide="calendar" class="w-4 h-4 text-gray-400"></i>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Date when the application was submitted</p>
            </div>
            
            {{-- Date Captured --}}
            <div class="relative">
              <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                <i data-lucide="calendar-clock" class="w-4 h-4 mr-2 text-blue-600"></i>
                Date Captured
              </label>
              <div class="relative">
                <input type="date" 
                       class="w-full pl-10 pr-4 py-3 bg-blue-50 border border-blue-200 rounded-lg cursor-not-allowed" 
                       value="{{ date('Y-m-d') }}"  name="created_at" 
                       readonly
                       disabled>
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                  <i data-lucide="lock" class="w-4 h-4 text-blue-400"></i>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">System capture date (auto-generated)</p>
            </div>
          </div>
          
          {{-- Info Alert --}}
          <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start">
              <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0"></i>
              <p class="text-xs text-blue-800">
                The application date can be backdated if needed. The capture date is automatically set by the system.
              </p>
            </div>
          </div>
        </div>
      </div>
      {{-- End Grid Layout --}}

      {{-- File Numbers Section --}}
      <div id="file-numbers-section" class="mb-6 px-4 hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        {{-- Primary File Number Selection Card --}}
        <div class="bg-gradient-to-br from-blue-50 via-indigo-50 to-blue-100 border border-blue-200 rounded-xl p-10 shadow-lg min-h-[450px] w-full max-w-none">
          <div class="flex items-center mb-4">
            <div class="bg-blue-500 p-3 rounded-full mr-4 shadow-md">
              <i data-lucide="file-text" class="w-6 h-6 text-white"></i>
            </div>
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-900">Select Primary FileNo</h3>
              <p class="text-sm text-gray-600">Choose from existing file numbers</p>
            </div>
            <div class="bg-purple-500 px-3 py-1 rounded-full shadow-sm">
              <span class="text-white text-xs font-medium">SELECT</span>
            </div>
          </div>
          
          <div class="bg-white rounded-lg border border-blue-100 p-6 shadow-inner">
            <label class="block text-sm font-medium text-gray-700 mb-3">Primary File Number <span class="text-red-500">*</span></label>
            
            {{-- Loading State --}}
            <div id="primary-file-loading" class="hidden mb-4">
              <div class="flex items-center justify-center py-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                <span class="ml-2 text-sm text-gray-600">Loading file numbers...</span>
              </div>
            </div>
            
            {{-- Dropdown --}}
            <div class="relative">
              <select id="primary-file-select" 
                      name="primary_file_number_id" 
                      class="w-full px-4 py-4 bg-white border-2 border-blue-200 rounded-lg text-blue-900 font-mono text-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                      onchange="handlePrimaryFileSelection(this)"
                      required>
                <option value="">Select a Primary File Number...</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400"></i>
              </div>
            </div>
            
            {{-- Selected File Display --}}
            <div id="selected-file-display" class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200 hidden">
              <div class="text-sm font-medium text-blue-800 mb-2">Selected File:</div>
              <div id="selected-file-number" class="font-mono text-lg font-bold text-blue-900">-</div>
            </div>
            
            {{-- Components Display --}}
            <div id="file-components-display" class="mt-6 space-y-3 hidden">
              <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                <span class="text-gray-600">NP File No:</span>
                <span id="display-np-fileno" class="font-semibold text-blue-700">-</span>
              </div>
              <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                <span class="text-gray-600">File No:</span>
                <span id="display-fileno" class="font-semibold text-blue-700">-</span>
              </div>
              <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                <span class="text-gray-600">Land Use:</span>
                <span id="display-land-use" class="font-semibold text-blue-700">-</span>
              </div>
              <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                <span class="text-gray-600">Applicant:</span>
                <span id="display-applicant" class="font-semibold text-blue-700">-</span>
              </div>
            </div>
            
            <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
              <div class="flex items-start">
                <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0"></i>
                <p class="text-xs text-blue-800">
                  Select a primary file number to automatically populate all application fields including applicant information.
                </p>
              </div>
            </div>
          </div>
        </div>

        </div>
      </div>

    {{-- Applicant Details Section --}}
    <div id="applicant-details-section" class="hidden">
        @include('primaryform.applicant')
    </div>

    {{-- Owner Address Section --}}
    <div class="bg-gray-50 p-4 rounded-md mb-6" id="mainOwnerAddressSection">
      <div class="mb-4">
        <p class="text-sm mb-1">Owner's Address</p>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm mb-1">House No.</label>
            <input type="text" id="ownerHouseNo" class="w-full p-2 border border-gray-300 rounded-md" placeholder="HOUSE NO." name="address_house_no" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updateAddressDisplay();">
          </div>
          <div>
            <label class="block text-sm mb-1">Street Name</label>
            <input type="text" id="ownerStreetName" class="w-full p-2 border border-gray-300 rounded-md" placeholder="STREET NAME" name="owner_street_name" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updateAddressDisplay();">
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm mb-1">District</label>
            <input type="text" id="ownerDistrict" class="w-full p-2 border border-gray-300 rounded-md" placeholder="DISTRICT" name="owner_district" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updateAddressDisplay();">
          </div>
          <div>
            <label class="block text-sm mb-1">LGA <span class="text-red-500">*</span></label>
            <select id="ownerLga" class="w-full p-2 border border-gray-300 rounded-md opacity-50" name="owner_lga" onchange="updateAddressDisplay();" disabled>
              <option value="">SELECT LGA</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">State <span class="text-red-500">*</span></label>
            <select id="ownerState" class="w-full p-2 border border-gray-300 rounded-md" name="owner_state" onchange="selectOwnerLGA(this); updateAddressDisplay();">
              <option value="">SELECT STATE</option>
            </select>
          </div>
        </div>
        
        <div class="mb-4">
          <label class="block text-sm mb-1">Contact Address:</label>
          <div id="contactAddressPreview" class="p-2 bg-white border border-gray-300 rounded-md min-h-[40px]">
            <span id="fullContactAddress" style="display: block; padding: 4px; text-transform: uppercase;"></span>
          </div>
          <input type="hidden" name="address" id="contactAddressDisplay">
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm mb-1">Phone No. 1 <span class="text-red-500">*</span></label>
            <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="ENTER PHONE NUMBER" name="phone" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
          </div>
          <div>
            <label class="block text-sm mb-1">Phone No. 2</label>
            <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="ENTER ALTERNATE PHONE" name="phone_alternate" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
          </div>
        </div>
          
        <div>
          <label class="block text-sm mb-1">Email Address</label>
          <input type="email" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter email address" name="email">
        </div>
      </div>
    </div>
    </div>{{-- Close mainOwnerAddressSection --}}

    {{-- Identification Section --}}
    <div class="grid grid-cols-2 gap-6 mb-6" id="mainOwnerIdentificationSection">
      {{-- Left column --}}
      <div id="meansOfIdentificationSection">
        <label class="block mb-2 font-medium">Means of identification <span class="text-red-500">*</span></label>
        <div class="grid grid-cols-1 gap-2">
          <label class="flex items-center">
            <input type="radio" name="identification_type" class="mr-2" value="national_id" checked>
            <span>National ID</span>
          </label>
          <label class="flex items-center">
            <input type="radio" name="identification_type" class="mr-2" value="drivers_license">
            <span>Driver's License</span>
          </label>
          <label class="flex items-center">
            <input type="radio" name="identification_type" class="mr-2" value="voters_card">
            <span>Voter's Card</span>
          </label>
          <label class="flex items-center">
            <input type="radio" name="identification_type" class="mr-2" value="international_passport">
            <span>International Passport</span>
          </label>
          <label class="flex items-center">
            <input type="radio" name="identification_type" class="mr-2" value="others">
            <span>Others</span>
          </label>
        </div>
      </div>
      
      {{-- Right column - ID Document Upload (Simple File Input) --}}
      <div>
        <label class="block mb-2 font-medium" id="uploadDocumentLabel">Upload ID Document <span class="text-red-500">*</span></label>
        
        {{-- Simple File Input with Preview Card --}}
        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition-colors">
          {{-- Preview Area --}}
          <div id="passportPreviewArea" class="hidden mb-3">
            <div class="bg-white rounded-lg p-3 border border-gray-200">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0">
                    <svg class="h-10 w-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p id="passportFileName" class="text-sm font-medium text-gray-900 truncate"></p>
                    <p id="passportFileSize" class="text-xs text-gray-500"></p>
                  </div>
                </div>
                <button type="button" onclick="removePassportFile()" class="flex-shrink-0 ml-2 text-red-600 hover:text-red-800">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
          
          {{-- Upload Prompt --}}
          <div id="passportUploadPrompt" class="text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <p class="text-sm text-gray-600 mb-1">Click to upload ID document</p>
            <p class="text-xs text-gray-500">JPG, PNG, PDF (max. 5MB)</p>
          </div>
          
          {{-- Actual File Input --}}
          <input type="file" 
                 id="idDocumentInput" 
                 name="id_document" 
                 accept="image/*,.pdf" 
                 class="hidden" 
                 onchange="handleIdDocumentUpload(event)">
          
          {{-- Upload Button --}}
          <button type="button" 
                  onclick="document.getElementById('idDocumentInput').click()" 
                  class="w-full mt-3 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
            <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            Choose File
          </button>
        </div>
        
        <p class="mt-2 text-xs text-gray-500">
          <svg class="inline-block w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          Upload a clear copy of your identification document
        </p>
      </div>
    </div>

    {{-- Property Details Section --}}
    <div class="bg-gray-50 p-4 rounded-md mb-6">
      <h3 class="font-medium mb-4">Property Details</h3>
      
      @include('primaryform.types.commercial')
      @include('primaryform.types.residential')
      @include('primaryform.types.industrial')
      
      <div class="grid grid-cols-4 gap-4 mb-4">
        <div>
          <label class="block text-sm mb-1">No. of Units <span class="text-red-500">*</span></label>
          <input type="number" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter number of units" name="units_count" min="1">
        </div>
        <div>
          <label class="block text-sm mb-1">No. of Blocks <span class="text-red-500">*</span></label>
          <input type="number" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter number of blocks" name="blocks_count" min="1">
        </div>
        <div>
          <label class="block text-sm mb-1">No. of Sections (Floors) <span class="text-red-500">*</span></label>
          <input type="number" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter number of floors" name="sections_count" min="1">
        </div>
        <div>
          <label class="block text-sm mb-1">Plot Size</label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter plot size" name="plot_size" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
        </div>
      </div>
      
      <h4 class="font-medium mb-2 mt-4">Property Address</h4>
      <div class="grid grid-cols-4 gap-4 mb-4">
        <div>
          <label class="block text-sm mb-1">Scheme Number <span class="text-red-500">*</span></label>
          <input type="text" id="schemeNumber" class="w-full p-2 border border-gray-300 rounded-md" placeholder="ENTER SCHEME NUMBER" name="scheme_no" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();">
        </div>
        <div>
          <label class="block text-sm mb-1">House No.</label>
          <input type="text" id="propertyHouseNo" class="w-full p-2 border border-gray-300 rounded-md" placeholder="ENTER HOUSE NUMBER" name="property_house_no" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();">
        </div>
        <div>
          <label class="block text-sm mb-1">Plot No.</label>
          <input type="text" id="propertyPlotNo" class="w-full p-2 border border-gray-300 rounded-md" placeholder="ENTER PLOT NUMBER" name="property_plot_no" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();">
        </div>
        <div>
          <label class="block text-sm mb-1">Street Name <span class="text-red-500">*</span></label>
          <input type="text" id="propertyStreetName" class="w-full p-2 border border-gray-300 rounded-md" placeholder="ENTER STREET NAME" name="property_street_name" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();">
        </div>
      </div>
      
      <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
          <label class="block text-sm mb-1">District</label>
          <input type="text" id="propertyDistrict" class="w-full p-2 border border-gray-300 rounded-md" placeholder="ENTER DISTRICT" name="property_district" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();">
        </div>
        <div>
          <label class="block text-sm mb-1">LGA <span class="text-red-500">*</span></label>
          <select id="propertyLga" name="property_lga" class="w-full p-2 border border-gray-300 rounded-md" onchange="updatePropertyAddressDisplay();">
            <option value="">Select LGA</option>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">State <span class="text-red-500">*</span></label>
          <select id="propertyState" class="w-full p-2 border border-gray-300 rounded-md" name="property_state" onchange="selectPropertyLGA(this); updatePropertyAddressDisplay();">
            <option value="">Select State</option>
          </select>
        </div>
      </div>
      
      {{-- Property Address Preview and Hidden Input --}}
      <div class="mb-4">
        <label class="block text-sm mb-1">Property Address:</label>
        <div id="propertyAddressPreview" class="p-2 bg-white border border-gray-300 rounded-md min-h-[40px]">
          <span id="fullPropertyAddress" style="display: block; padding: 4px; text-transform: uppercase;"></span>
        </div>
        <input type="hidden" name="property_address" id="propertyAddressDisplay">
      </div>
    </div>

    @include('primaryform.types.ownership')
    
    <div class="mb-4">
      <label class="block text-sm mb-1">Write any comments that will assist in processing the application</label>
      <textarea class="w-full p-2 border border-gray-300 rounded-md" rows="4" placeholder="Enter any additional comments or information" name="comments"></textarea>
    </div>
    
    @include('primaryform.partials.initial_bill')

    {{-- Note: All hidden fields are now centralized in index.blade.php for debugging --}}
    {{-- JavaScript will update the centralized hidden fields as needed --}}

    {{-- Debug section removed --}}

    {{-- Navigation buttons --}}
    <div class="flex justify-between mt-8">
      <button type="button" onclick="window.history.back()" class="px-4 py-2 bg-white border border-gray-300 rounded-md">Cancel</button>
      <div class="flex items-center">
  <span class="text-sm text-gray-500 mr-4 step-status-text" data-step-indicator data-step-total="5">Step 1 of 5</span>
        <button type="button" onclick="goToStep(2)" class="px-4 py-2 bg-black text-white rounded-md">Next</button>
      </div>
    </div>
  </div>
</div>
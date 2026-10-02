@extends('layouts.app')

@section('page-title')
    {{ __('AI Property Record Assistant') }}
@endsection

@section('content')
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Alpine.js CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <!-- Tesseract.js for OCR -->
    <script src="https://unpkg.com/tesseract.js@4/dist/tesseract.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

@include('propertycard.ai.partials.styles')       
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <div class="container mx-auto py-6 space-y-6 max-w-6xl px-4 sm:px-6 lg:px-8">
      <!-- Page Header -->
      <div class="space-y-2">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">
          AI Property Record Assistant
        </h1>
        <p class="text-lg text-gray-600">
          Upload property documents for automated data extraction and record
          creation
        </p>
      </div>
      <div class="flex items-center justify-end mb-4">
                    <a href="{{ route('propertycard.index') }}" class="mr-3 flex items-center text-gray-600">
                        <i class="fas fa-tools mr-2"></i> Manual Assistant
                    </a>
                   
                </div>
      <!-- File Upload Card -->
      <div class="bg-white rounded-lg shadow border border-gray-200">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-900">
            Upload Property Record(s) for AI Extraction
          </h2>
          <p class="text-sm text-gray-600 mt-1">
            Upload an image (JPEG, PNG) or PDF of the property document (e.g.,
            Deed of Assignment, C of O).
          </p>
        </div>

        <div class="p-6 space-y-4">
          <!-- Error Alert -->
          <div
            id="error-alert"
            class="hidden bg-red-50 border border-red-200 rounded-md p-4"
          >
            <div class="flex">
              <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Error</h3>
                <div id="error-message" class="mt-2 text-sm text-red-700"></div>
              </div>
            </div>
          </div>

          <!-- File Upload Area -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-gray-700"
              >Document File</label
            >
            <input
              id="file-input"
              type="file"
              accept="image/jpeg,image/png,application/pdf"
              class="hidden"
            />
            <button
              id="file-upload-btn"
              class="w-full flex items-center justify-start px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-left font-normal hover:bg-gray-50 transition-colors"
            >
              <i data-lucide="file-up" class="mr-2 h-4 w-4"></i>
              <span id="file-upload-text">Click to select a file</span>
            </button>
          </div>

          <!-- Image Preview -->
          <div id="image-preview" class="hidden border p-2 rounded-md">
            <label class="text-xs text-gray-500">Image Preview</label>
            <img
              id="image-preview-img"
              class="max-w-full h-auto max-h-96 rounded-md mt-1"
            />
          </div>

          <!-- PDF Preview -->
          <div id="pdf-preview" class="hidden border p-2 rounded-md space-y-2">
            <label id="pdf-preview-label" class="text-xs text-gray-500"
              >PDF Preview</label
            >
            <div class="relative">
              <img
                id="pdf-preview-img"
                class="max-w-full h-auto max-h-[30rem] rounded-md mt-1 border mx-auto"
              />
            </div>
            <div
              id="pdf-navigation"
              class="hidden flex justify-center items-center space-x-2 mt-2"
            >
              <button
                id="pdf-prev-btn"
                class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50"
              >
                Previous
              </button>
              <span id="pdf-page-info" class="text-sm text-gray-500"
                >Page 1 / 1</span
              >
              <button
                id="pdf-next-btn"
                class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50"
              >
                Next
              </button>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="px-6 pb-6 flex flex-col sm:flex-row gap-2">
          <button
            id="start-ai-btn"
            class="w-full sm:w-auto inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            disabled
          >
            <i data-lucide="wand-2" class="mr-2 h-4 w-4"></i>
            Extract Data with AI
          </button>
          <button
            id="reset-btn"
            class="hidden w-full sm:w-auto inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50"
          >
            Reset
          </button>
        </div>
      </div>

      <!-- AI Processing Visualizer -->
      <div
        id="ai-processing"
        class="hidden bg-white rounded-lg shadow border border-gray-200"
      >
        <div class="p-6">
          <div class="flex justify-between mb-2">
            <span class="text-sm font-medium"
              >Property Document AI Analysis</span
            >
            <span id="ai-progress-text" class="text-sm">0% Complete</span>
          </div>
          <div class="relative">
            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div
                id="ai-progress-bar"
                class="h-full bg-blue-500 rounded-full transition-all duration-500 ease-in-out"
                style="width: 0%"
              ></div>
            </div>
            <div class="flex justify-between mt-2">
              <div
                class="flex flex-col items-center stage-indicator"
                data-stage="0"
              >
                <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                <span class="text-xs text-gray-500">Init</span>
              </div>
              <div
                class="flex flex-col items-center stage-indicator"
                data-stage="1"
              >
                <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                <span class="text-xs text-gray-500">OCR</span>
              </div>
              <div
                class="flex flex-col items-center stage-indicator"
                data-stage="2"
              >
                <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                <span class="text-xs text-gray-500">Layout</span>
              </div>
              <div
                class="flex flex-col items-center stage-indicator"
                data-stage="3"
              >
                <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                <span class="text-xs text-gray-500">Extract</span>
              </div>
              <div
                class="flex flex-col items-center stage-indicator"
                data-stage="4"
              >
                <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                <span class="text-xs text-gray-500">Assemble</span>
              </div>
              <div
                class="flex flex-col items-center stage-indicator"
                data-stage="5"
              >
                <div class="w-4 h-4 rounded-full bg-gray-300 mb-1"></div>
                <span class="text-xs text-gray-500">Done</span>
              </div>
            </div>
          </div>
          <div class="mt-4 flex items-start gap-3">
            <div class="p-2 rounded-full bg-blue-100">
              <i
                id="ai-stage-icon"
                data-lucide="brain"
                class="h-5 w-5 text-blue-600"
              ></i>
            </div>
            <div>
              <p id="ai-stage-title" class="text-sm font-medium mb-1">
                Current Stage: Initializing
              </p>
              <p id="ai-stage-description" class="text-xs text-gray-600">
                Preparing for AI analysis...
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Keyword Findings Display -->
      <div
        id="keyword-findings"
        class="hidden bg-white rounded-lg shadow border border-gray-200"
      >
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center space-x-2">
            <i data-lucide="file-key-2" class="h-6 w-6 text-blue-600"></i>
            <h3 class="text-xl font-semibold text-gray-900">
              Key Document Types Found
            </h3>
          </div>
          <p
            id="keyword-findings-description"
            class="text-sm text-gray-600 mt-1"
          ></p>
        </div>
        <div class="p-6">
          <ul id="keyword-findings-list" class="space-y-2">
            <!-- Keyword findings will be inserted here -->
          </ul>
        </div>
      </div>

      <!-- Raw Extracted Text -->
      <div
        id="raw-text-card"
        class="hidden bg-white rounded-lg shadow border border-gray-200"
      >
        <div class="p-6 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-xl font-semibold text-gray-900">
              Raw Extracted Text
            </h3>
            <button
              id="toggle-raw-text"
              class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent text-gray-700 hover:bg-gray-100"
            >
              <i data-lucide="chevron-down" class="h-4 w-4"></i>
              Show
            </button>
          </div>
        </div>
        <div id="raw-text-content" class="collapsible-content">
          <div class="p-6">
            <textarea
              id="raw-text-textarea"
              readonly
              rows="10"
              class="w-full text-xs bg-gray-50 font-mono border border-gray-300 rounded-md p-3"
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Extracted Property Details -->
      <div
        id="extracted-details"
        class="hidden bg-white rounded-lg shadow border-l-4 border-l-green-500"
        x-data="{
            // Form fields synchronized with manual form
            fileNumber: '',
            houseNo: '',
            plotNo: '',
            streetName: '',
            district: '',
            lga: '',
            transactionType: '',
            transactionDate: '',
            serialNo: '',
            pageNo: '',
            volumeNo: '',
            regDate: new Date().toISOString().split('T')[0],
            regTime: '09:00',
            landUse: '',
            period: '',
            periodUnit: 'Years',
            firstParty: '',
            secondParty: '',
            propertyDescription: '',
            showManualEntry: false,
            
            // Track current values from components
            currentStreetName: '',
            currentDistrict: '',
            
            // Party labels for different transaction types
            partyLabels: {
                'Power of Attorney': { first: 'Grantor', second: 'Grantee' },
                'Deed of Assignment': { first: 'Assignor', second: 'Assignee' },
                'ST Assignment': { first: 'Assignor', second: 'Assignee' },
                'Deed of Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
                'Tripartite Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
                'Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'ST Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'SLTR Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'Customary Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'Deed of Transfer': { first: 'Transferor', second: 'Transferee' },
                'Deed of Gift': { first: 'Donor', second: 'Donee' },
                'Deed of Lease': { first: 'Lessor', second: 'Lessee' },
                'Deed of Sub Lease': { first: 'Lessor', second: 'Lessee' },
                'Deed of Sub Under Lease': { first: 'Lessor', second: 'Lessee' },
                'Indenture of Lease': { first: 'Lessor', second: 'Lessee' },
                'Quarry Lease': { first: 'Lessor', second: 'Lessee' },
                'Private Lease': { first: 'Lessor', second: 'Lessee' },
                'Building Lease': { first: 'Lessor', second: 'Lessee' },
                'Tenancy Agreement': { first: 'Landlord', second: 'Tenant' },
                'Deed of Release': { first: 'Releasor', second: 'Releasee' },
                'Deed of Surrender': { first: 'Surrenderor', second: 'Surrenderee' },
                'Letter of Administration': { first: 'Administrator', second: 'Beneficiary' },
                'Certificate of Purchase': { first: 'Vendor', second: 'Purchaser' }
            },
            
            // Computed properties
            get showTransactionDetails() {
                return this.transactionType !== '';
            },
            
            get currentPartyLabels() {
                return this.partyLabels[this.transactionType] || { first: 'Grantor', second: 'Grantee' };
            },
            
            get isGovernmentTransaction() {
                const govTypes = ['Certificate of Occupancy', 'ST Certificate of Occupancy', 'SLTR Certificate of Occupancy', 'Customary Right of Occupancy', 'Occupation Permit'];
                return govTypes.includes(this.transactionType);
            },
            
            get regNumberPreview() {
                const parts = [this.serialNo, this.pageNo, this.volumeNo].filter(Boolean);
                return parts.length > 0 ? parts.join('/') : '';
            },
            
            // Method to update property description with auto-population
            updatePropertyDescription() {
                const parts = [];
                
                // Add house number if available
                if (this.houseNo && this.houseNo.trim()) {
                    parts.push(this.houseNo.trim());
                }
                
                // Add street name if available
                if (this.currentStreetName && this.currentStreetName.trim()) {
                    parts.push(this.currentStreetName.trim());
                }
                
                // Add district if available
                if (this.currentDistrict && this.currentDistrict.trim()) {
                    parts.push(this.currentDistrict.trim());
                }
                
                // Add LGA if available
                if (this.lga && this.lga.trim()) {
                    parts.push(this.lga.trim());
                }
                
                // Add state (prefer field value, default is Kano State)
                const stateElement = document.querySelector('#state');
                const stateValue = stateElement ? stateElement.value : '';
                if (stateValue && stateValue.trim()) {
                    parts.push(stateValue.trim());
                } else {
                    parts.push('Kano State');
                }
                
                // Update the property description
                this.propertyDescription = parts.length > 0 ? parts.join(', ') : '';
            },
            
            // Methods
            getFirstPartyFieldName() {
                const typeMap = {
                    'Deed of Assignment': 'Assignor',
                    'ST Assignment': 'Assignor',
                    'Deed of Mortgage': 'Mortgagor',
                    'Tripartite Mortgage': 'Mortgagor',
                    'Deed of Surrender': 'Surrenderor',
                    'Deed of Sub Lease': 'Lessor',
                    'Deed of Sub Under Lease': 'Lessor',
                    'Indenture of Lease': 'Lessor',
                    'Quarry Lease': 'Lessor',
                    'Private Lease': 'Lessor',
                    'Building Lease': 'Lessor',
                    'Tenancy Agreement': 'Landlord',
                    'Deed of Release': 'Releasor',
                    'Deed of Transfer': 'Transferor',
                    'Deed of Gift': 'Donor',
                    'Letter of Administration': 'Administrator',
                    'Certificate of Purchase': 'Vendor'
                };
                return typeMap[this.transactionType] || 'Grantor';
            },
            
            getSecondPartyFieldName() {
                const typeMap = {
                    'Deed of Assignment': 'Assignee',
                    'ST Assignment': 'Assignee',
                    'Deed of Mortgage': 'Mortgagee',
                    'Tripartite Mortgage': 'Mortgagee',
                    'Deed of Surrender': 'Surrenderee',
                    'Deed of Sub Lease': 'Lessee',
                    'Deed of Sub Under Lease': 'Lessee',
                    'Indenture of Lease': 'Lessee',
                    'Quarry Lease': 'Lessee',
                    'Private Lease': 'Lessee',
                    'Building Lease': 'Lessee',
                    'Tenancy Agreement': 'Tenant',
                    'Deed of Release': 'Releasee',
                    'Deed of Transfer': 'Transferee',
                    'Deed of Gift': 'Donee',
                    'Letter of Administration': 'Beneficiary',
                    'Certificate of Purchase': 'Purchaser'
                };
                return typeMap[this.transactionType] || 'Grantee';
            },
            
            // Initialize
            init() {
                // Watch for transaction type changes
                this.$watch('transactionType', (value, oldValue) => {
                    // Auto-fill for government transactions
                    const govTypes = ['Certificate of Occupancy', 'ST Certificate of Occupancy', 'SLTR Certificate of Occupancy', 'Customary Right of Occupancy', 'Occupation Permit'];
                    if (govTypes.includes(value)) {
                        this.firstParty = 'KANO STATE GOVERNMENT';
                    } else {
                        this.firstParty = '';
                    }
                });
                
                // Auto-sync page number with serial number
                this.$watch('serialNo', (value) => {
                    this.pageNo = value;
                });
                
                // Enable only the active file-number mode (smart vs manual)
                const syncFileNoMode = () => {
                    const smartEl = document.getElementById('smart-fileno-container');
                    const manualEl = document.getElementById('manual-fileno-container');
                    const setDisabled = (root, disabled) => {
                        if (!root) return;
                        root.querySelectorAll('input[name], select[name], textarea[name]').forEach(el => {
                            el.disabled = disabled;
                        });
                    };
                    // If manual is active, disable smart inputs; otherwise disable manual inputs
                    setDisabled(smartEl, this.showManualEntry);
                    setDisabled(manualEl, !this.showManualEntry);
                };
                this.$watch('showManualEntry', () => syncFileNoMode());
                // Initial sync
                setTimeout(syncFileNoMode, 0);
                
                // Watch for property details changes and update description
                this.$watch('houseNo', () => {
                    this.updatePropertyDescription();
                });
                
                this.$watch('lga', () => {
                    this.updatePropertyDescription();
                });
                
                // Set up comprehensive monitoring for component fields
                const setupFieldMonitoring = () => {
                    // Monitor street name dropdown
                    const streetNameSelect = document.querySelector('#streetName');
                    if (streetNameSelect) {
                        streetNameSelect.addEventListener('change', () => {
                            setTimeout(() => this.updatePropertyDescription(), 50);
                        });
                    }
                    
                    // Monitor custom street name input
                    const streetNameCustom = document.querySelector('#otherStreetName');
                    if (streetNameCustom) {
                        streetNameCustom.addEventListener('input', () => {
                            setTimeout(() => this.updatePropertyDescription(), 50);
                        });
                    }
                    
                    // Monitor district dropdown
                    const districtSelect = document.querySelector('#district');
                    if (districtSelect) {
                        districtSelect.addEventListener('change', () => {
                            setTimeout(() => this.updatePropertyDescription(), 50);
                        });
                    }
                    
                    // Monitor custom district input
                    const districtCustom = document.querySelector('#otherDistrict');
                    if (districtCustom) {
                        districtCustom.addEventListener('input', () => {
                            setTimeout(() => this.updatePropertyDescription(), 50);
                        });
                    }
                    
                    // Monitor state input
                    const stateInput = document.querySelector('#state');
                    if (stateInput) {
                        stateInput.addEventListener('input', () => {
                            setTimeout(() => this.updatePropertyDescription(), 50);
                        });
                    }
                };
                
                // Initial setup and periodic re-setup
                setTimeout(() => {
                    setupFieldMonitoring();
                    this.updatePropertyDescription();
                }, 200);
            }
        }"
        x-init="init()"
      >
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center space-x-2">
            <i data-lucide="check-circle" class="h-6 w-6 text-green-600"></i>
            <h3 class="text-xl font-semibold text-gray-900">
              AI Extracted Property Details
            </h3>
          </div>
          <p id="extraction-confidence" class="text-sm text-gray-600 mt-1">
            Review the details extracted by the AI. Add or modify instruments as
            needed, then save the record.
          </p>
        </div>

        <form id="property-form" action="{{ route('property-records.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="property_id" id="property_id" value="">
          <input type="hidden" name="form_action" id="form_action" value="add">
          
          <div class="p-6 space-y-6">
            <!-- Updated Property Information Form to match the manual layout design -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Left Panel - File Number Information -->
              <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                <div class="flex items-center space-x-2 mb-4">
                  <i data-lucide="file-text" class="h-5 w-5 text-green-600"></i>
                  <h4 class="text-lg font-medium text-gray-900">
                    File Number Information
                  </h4>
                </div>
                <p class="text-sm text-gray-600 mb-6">
                  AI detected file numbers from document or manually select/enter
                </p>

                <!-- Enhanced AI-detected file numbers container -->
                <div id="detected-file-numbers" class="space-y-4 mb-6">
                  <!-- File numbers will be dynamically populated here by AI -->
                  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center space-x-2 mb-2">
                      <i data-lucide="brain" class="h-4 w-4 text-blue-600"></i>
                      <span class="text-sm font-medium text-blue-800">AI Detected File Numbers</span>
                    </div>
                    <div id="ai-detected-list" class="space-y-2">
                      <p class="text-xs text-gray-500 italic">No file numbers detected yet. Upload and process a document to see AI detections.</p>
                    </div>
                  </div>
                </div>

                <!-- File Number Selection with Smart Selector -->
                <div class="space-y-1">
                  <div class="flex items-center justify-between mb-3">
                    <label for="fileno-select" class="block text-sm font-medium text-gray-700">Select File Number</label>
                    <button type="button" @click="showManualEntry = !showManualEntry" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                      <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                      </svg>
                      <span x-text="showManualEntry ? 'Use Smart Selector' : 'Enter Fileno manually'"></span>
                    </button>
                  </div>
                  
                  <!-- Smart File Number Selector (Default) -->
                  <div x-show="!showManualEntry" x-transition id="smart-fileno-container">
                    @include('propertycard.partials.smart_fileno_selector')
                  </div>
                  
                  <!-- Manual File Number Entry -->
                  <div x-show="showManualEntry" x-transition id="manual-fileno-container">
                    @include('propertycard.partials.manual_fileno')
                  </div>
                </div>
              </div>

              <!-- Right Panel - Property Details -->
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">House No</label>
                    <input
                      name="house_no"
                      id="houseNo"
                      x-model="houseNo"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Plot No.</label>
                    <input
                      name="plot_no"
                      id="plotNo"
                      x-model="plotNo"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="Enter plot number"
                    />
                  </div>
                </div>

                <!-- Street Name and District using enhanced components -->
                <div class="grid grid-cols-2 gap-4" 
                     @street-changed="currentStreetName = $event.detail.isOther ? $event.detail.value : ($event.detail.value !== 'other' ? $event.detail.value : ''); updatePropertyDescription();"
                     @district-changed="currentDistrict = $event.detail.isOther ? $event.detail.value : ($event.detail.value !== 'other' ? $event.detail.value : ''); updatePropertyDescription();">
                  @include('components.StreetName2')
                  @include('components.District')
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">LGA</label>
                    @include('propertycard.partials.lga')
                  </div>
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">State</label>
                    <input
                      name="state"
                      id="state"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      value="Kano"
                      readonly
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Instrument Type Section (synchronized with manual form) -->
            <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
              <h4 class="text-lg font-medium text-gray-900 mb-4">Instrument Type</h4>
              <div class="space-y-4">
                <!-- Transaction Type and Date -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-1">
                    <label for="transactionType" class="text-sm font-medium text-gray-700">Transaction Type</label>
                    <select id="transactionType" name="transactionType" x-model="transactionType" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                      <option value="">Select type</option>
                      <option value="Deed of Transfer">Deed of Transfer</option>
                      <option value="Certificate of Occupancy">Certificate of Occupancy</option>
                      <option value="ST Certificate of Occupancy">ST Certificate of Occupancy</option>
                      <option value="SLTR Certificate of Occupancy">SLTR Certificate of Occupancy</option>
                      <option value="Irrevocable Power of Attorney">Irrevocable Power of Attorney</option>
                      <option value="Deed of Release">Deed of Release</option>
                      <option value="Deed of Assignment">Deed of Assignment</option>
                      <option value="ST Assignment">ST Assignment</option>
                      <option value="Deed of Mortgage">Deed of Mortgage</option>
                      <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                      <option value="Deed of Sub Lease">Deed of Sub Lease</option>
                      <option value="Deed of Sub Under Lease">Deed of Sub Under Lease</option>
                      <option value="Power of Attorney">Power of Attorney</option>
                      <option value="Deed of Surrender">Deed of Surrender</option>
                      <option value="Indenture of Lease">Indenture of Lease</option>
                      <option value="Deed of Variation">Deed of Variation</option>
                      <option value="Customary Right of Occupancy">Customary Right of Occupancy</option>
                      <option value="Vesting Assent">Vesting Assent</option>
                      <option value="Court Judgement">Court Judgement</option>
                      <option value="Exchange of Letters">Exchange of Letters</option>
                      <option value="Tenancy Agreement">Tenancy Agreement</option>
                      <option value="Revocation of Power of Attorney">Revocation of Power of Attorney</option>
                      <option value="Deed of Convenyence">Deed of Convenyence</option>
                      <option value="Memorandom of Agreement">Memorandom of Agreement</option>
                      <option value="Quarry Lease">Quarry Lease</option>
                      <option value="Private Lease">Private Lease</option>
                      <option value="Deed of Gift">Deed of Gift</option>
                      <option value="Deed of Partition">Deed of Partition</option>
                      <option value="Non-European Occupational Lease">Non-European Occupational Lease</option>
                      <option value="Deed of Revocation">Deed of Revocation</option>
                      <option value="Deed of lease">Deed of lease</option>
                      <option value="Deed of Reconveyance">Deed of Reconveyance</option>
                      <option value="Letter of Administration">Letter of Administration</option>
                      <option value="Customary Inhertitance">Customary Inhertitance</option>
                      <option value="Certificate of Purchase">Certificate of Purchase</option>
                      <option value="Deed of Rectification">Deed of Rectification</option>
                      <option value="Building Lease">Building Lease</option>
                      <option value="Memorandum of Loss">Memorandum of Loss</option>
                      <option value="Vesting Deed">Vesting Deed</option>
                      <option value="ST Fragmentation">ST Fragmentation</option>
                      <option value="Occupation Permit">Occupancy Permit</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                  <div class="space-y-1">
                    <label for="transactionDate" class="text-sm font-medium text-gray-700">Transaction/Certificate Date</label>
                    <input type="date" id="transactionDate" name="transactionDate" x-model="transactionDate" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                  </div>
                </div>

                <!-- Registration Number -->
                <div class="space-y-1">
                  <label class="text-sm font-medium text-gray-700">Registration Number</label>
                  <div class="grid grid-cols-5 gap-2">
                    <div>
                      <label for="serialNo" class="text-xs text-gray-600">Serial No.</label>
                      <input id="serialNo" name="serialNo" x-model="serialNo" class="w-full px-3 py-1 border border-gray-300 rounded-md text-xs focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" placeholder="e.g. 1">
                    </div>
                    <div>
                      <label for="pageNo" class="text-xs text-gray-500">Page No. (Auto-filled)</label>
                      <input id="pageNo" name="pageNo" x-model="pageNo" readonly class="w-full px-3 py-1 border border-gray-300 rounded-md text-xs bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="Same as Serial No.">
                    </div>
                    <div>
                      <label for="volumeNo" class="text-xs text-gray-600">Volume No.</label>
                      <input id="volumeNo" name="volumeNo" x-model="volumeNo" class="w-full px-3 py-1 border border-gray-300 rounded-md text-xs focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" placeholder="e.g. 2">
                    </div>
                    <div>
                      <label for="regDate" class="text-xs text-gray-600">Reg Date</label>
                      <input id="regDate" name="regDate" type="date" x-model="regDate" class="w-full px-3 py-1 border border-gray-300 rounded-md text-xs focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                    </div>
                    <div>
                      <label for="regTime" class="text-xs text-gray-600">Reg Time</label>
                      <input id="regTime" name="regTime" type="time" x-model="regTime" class="w-full px-3 py-1 border border-gray-300 rounded-md text-xs focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                    </div>
                  </div>
                  
                  <!-- Registration Number Preview -->
                  <div x-show="regNumberPreview" x-transition class="mt-2 p-3 bg-blue-50 border-2 border-blue-200 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm font-semibold text-blue-700">Registration Number:</span>
                      </div>
                      <span class="text-lg font-bold text-blue-800 tracking-wider" x-text="regNumberPreview"></span>
                    </div>
                  </div>
                </div>

                <!-- Land Use and Period -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-1">
                    <label for="landUse" class="text-sm font-medium text-gray-700">Land Use</label>
                    <select id="landUse" name="landUse" x-model="landUse" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                      <option value="">Select land use</option>
                      <option value="RESIDENTIAL">RESIDENTIAL</option>
                      <option value="AGRICULTURAL">AGRICULTURAL</option>
                      <option value="COMMERCIAL">COMMERCIAL</option>
                      <option value="COMMERCIAL ( WARE HOUSE)">COMMERCIAL ( WARE HOUSE)</option>
                      <option value="COMMERCIAL (OFFICES)">COMMERCIAL (OFFICES)</option>
                      <option value="COMMERCIAL (PETROL FILLING STATION)">COMMERCIAL (PETROL FILLING STATION)</option>
                      <option value="COMMERCIAL (RICE PROCESSING)">COMMERCIAL (RICE PROCESSING)</option>
                      <option value="COMMERCIAL (SCHOOL)">COMMERCIAL (SCHOOL)</option>
                      <option value="COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)">COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)</option>
                      <option value="COMMERCIAL (SHOPS AND OFFICES)">COMMERCIAL (SHOPS AND OFFICES)</option>
                      <option value="COMMERCIAL (SHOPS)">COMMERCIAL (SHOPS)</option>
                      <option value="COMMERCIAL (WAREHOUSE)">COMMERCIAL (WAREHOUSE)</option>
                      <option value="COMMERCIAL (WORKSHOP AND OFFICES)">COMMERCIAL (WORKSHOP AND OFFICES)</option>
                      <option value="COMMERCIAL AND RESIDENTIAL">COMMERCIAL AND RESIDENTIAL</option>
                      <option value="INDUSTRIAL">INDUSTRIAL</option>
                      <option value="INDUSTRIAL (SMALL SCALE)">INDUSTRIAL (SMALL SCALE)</option>
                      <option value="RESIDENTIAL AND COMMERCIAL">RESIDENTIAL AND COMMERCIAL</option>
                      <option value="RESIDENTIAL/COMMERCIAL">RESIDENTIAL/COMMERCIAL</option>
                      <option value="RESIDENTIAL/COMMERCIAL LAYOUT">RESIDENTIAL/COMMERCIAL LAYOUT</option>
                    </select>
                  </div>

                  <div class="space-y-1">
                    <label for="period" class="text-sm font-medium text-gray-700">Period/Tenancy</label>
                    <div class="flex space-x-1">
                      <input id="period" name="period" type="number" x-model="period" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10" placeholder="Period">
                      <select id="periodUnit" name="periodUnit" x-model="periodUnit" class="w-[90px] px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                        <option value="Days">Days</option>
                        <option value="Months">Months</option>
                        <option value="Years" selected>Years</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Transaction Details Section -->
            <div x-show="showTransactionDetails" 
                 x-transition
                 class="bg-gray-50 rounded-lg p-6 border border-gray-200">
              <h4 class="text-lg font-medium text-gray-900 mb-4">Transaction Details</h4>
              
              <!-- Party Fields -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                  <label class="text-sm font-medium text-gray-700" x-text="currentPartyLabels.first"></label>
                  <input type="text" 
                         :name="getFirstPartyFieldName()" 
                         x-model="firstParty"
                         class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                         :class="isGovernmentTransaction ? 'bg-gray-100 text-gray-800' : ''"
                         :readonly="isGovernmentTransaction"
                         :placeholder="isGovernmentTransaction ? 'KANO STATE GOVERNMENT' : 'Enter ' + currentPartyLabels.first.toLowerCase() + ' name'">
                </div>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-gray-700" x-text="currentPartyLabels.second"></label>
                  <input type="text" 
                         :name="getSecondPartyFieldName()" 
                         x-model="secondParty" 
                         class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                         :placeholder="'Enter ' + currentPartyLabels.second.toLowerCase() + ' name'">
                </div>
              </div>
            </div>

            <!-- Property Description with Auto-Population -->
            <div class="space-y-1">
              <div class="flex justify-between items-center">
                <label class="text-sm font-medium text-gray-700">Property Description</label>
                <button type="button" @click="updatePropertyDescription()" class="text-xs text-blue-600 hover:text-blue-800">
                  🔄 Refresh Description
                </button>
              </div>
              <textarea id="property-description" 
                        name="property_description" 
                        rows="4" 
                        x-model="propertyDescription"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 bg-gray-50" 
                        readonly
                        placeholder="This field will be auto-populated based on property details above"></textarea>
              <div class="text-xs text-gray-500 italic">This field auto-populates from House No, Street Name, District, LGA, and State.</div>
            </div>

            <!-- Instruments Manager (keeping existing functionality) -->
            <div class="border-t pt-6">
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <h4 class="text-lg font-medium text-gray-900">
                    Document Instruments
                  </h4>
                  <button
                    type="button"
                    id="add-instrument-btn"
                    class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2"
                  >
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Add Instrument
                  </button>
                </div>

                <!-- No Instruments State -->
                <div
                  id="no-instruments"
                  class="text-center py-8 text-gray-500 border-2 border-dashed border-gray-200 rounded-lg"
                >
                  <i
                    data-lucide="file-key-2"
                    class="h-8 w-8 mx-auto mb-2 text-gray-400"
                  ></i>
                  <p class="text-sm">No instruments added yet</p>
                  <p class="text-xs text-gray-400">
                    Click "Add Instrument" to get started
                  </p>
                </div>

                <!-- Instruments List -->
                <div id="instruments-list" class="space-y-3">
                  <!-- Instruments will be inserted here -->
                </div>
              </div>
            </div>

            <!-- Hidden fields to maintain compatibility -->
            <input type="hidden" name="originalAllottee" id="original-allottee" />
            <input type="hidden" name="currentAllottee" id="current-allottee" />
            <input type="hidden" name="addressOfOriginalAllottee" id="address-original-allottee" />
            <input type="hidden" name="addressOfCurrentAllottee" id="address-current-allottee" />
            <input type="hidden" name="oldTitleSerialNo" id="old-title-serial-no" />
            <input type="hidden" name="oldTitlePageNo" id="old-title-page-no" />
            <input type="hidden" name="oldTitleVolumeNo" id="old-title-volume-no" />
            <input type="hidden" name="titleIssuedYear" id="title-issued-year" />
            <input type="hidden" name="currentYearTitleOwned" id="current-year-title-owned" />
            <input type="hidden" name="phoneNo" id="phone-no" />
            <input type="hidden" name="specifically" id="specifically" />
            <input type="hidden" name="areaInHectares" id="area-in-hectares" />
            <input type="hidden" name="titleStatus" id="title-status" />
            <input type="hidden" name="instruments" id="instruments-data" />
            
            <!-- Additional hidden fields for file number handling (keep IDs for JS, avoid name collisions) -->
            <!-- Removed duplicate fileno field to prevent overriding smart selector value -->
            <input type="hidden" id="file-prefix" />
            <input type="hidden" id="file-serial-no" />
            <input type="hidden" id="complete-file-no" />
            <input type="hidden" id="file-number-type" />
            
            <!-- Additional hidden fields for location data -->
            <input type="hidden" name="otherStreetName" id="other-street-name" />
            <input type="hidden" name="otherDistrict" id="other-district" />

            <!-- Save Button -->
            <div class="flex justify-end pt-4 border-t">
              <button
                type="submit"
                id="save-record-btn"
                class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-green-600 text-white hover:bg-green-700 gap-2"
              >
                <i data-lucide="check-circle" class="h-4 w-4"></i>
                Save Property Record
              </button>
            </div>
          </div>
        </form>
      </div>
    

    <!-- Toast Notifications -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
      <!-- Toast messages will be inserted here -->
    </div>
       
        <!-- Footer -->
        @include('admin.footer')
    </div>
        
      @include('propertycard.ai.partials.scripts')

      <script>
        // Enhanced function to toggle "Other" input fields for street and district names
        function toggleOtherInput(selectId, inputId) {
          const selectElement = document.getElementById(selectId);
          const inputElement = document.getElementById(inputId);
          
          if (selectElement && inputElement) {
            if (selectElement.value === 'Other') {
              inputElement.classList.remove('hidden');
              inputElement.focus();
            } else {
              inputElement.classList.add('hidden');
              inputElement.value = '';
            }
          }
        }

        // Enhanced AI file number display function
        function displayDetectedFileNumbers(fileNumbers) {
          const container = document.getElementById('ai-detected-list');
          if (!container) return;

          if (!fileNumbers || fileNumbers.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-500 italic">No file numbers detected yet. Upload and process a document to see AI detections.</p>';
            return;
          }

          container.innerHTML = fileNumbers.map((fileNum, index) => 
            `<div class="flex items-center justify-between bg-white border border-blue-200 rounded-md p-2">
              <div class="flex-1">
                <div class="text-sm font-medium text-blue-800">${fileNum.number || fileNum.original || 'Unknown'}</div>
                <div class="text-xs text-gray-600">Type: ${fileNum.type || 'Unknown'}</div>
                ${fileNum.confidence ? `<div class="text-xs text-gray-500">Confidence: ${fileNum.confidence}%</div>` : ''}
              </div>
              <button onclick="selectDetectedFileNumber(${index})" class="px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">
                Use This
              </button>
            </div>`
          ).join('');
        }

        // Function to select a detected file number
        function selectDetectedFileNumber(index) {
          if (window.detectedFileNumbers && window.detectedFileNumbers[index]) {
            const fileNum = window.detectedFileNumbers[index];
            // Trigger the file number selection logic
            console.log('Selected file number:', fileNum);
            
            // You can add logic here to populate the smart selector or manual entry
            // based on the selected file number
            
            // Show success message
            if (typeof showToast === 'function') {
              showToast('File number selected: ' + (fileNum.number || fileNum.original), 'success');
            }
          }
        }
      </script>
@endsection
<div class="form-section" id="step4">
  <div class="p-6">
    @php
      $landUseParamRaw = request()->query('landuse');
      $normalizedQuery = is_string($landUseParamRaw) ? strtolower($landUseParamRaw) : '';
      $landUseLookup = [
          'commercial' => 'Commercial',
          'residential' => 'Residential',
          'industrial' => 'Industrial',
          'mixed' => 'Mixed',
      ];
      $normalizedLandUse = $landUseLookup[$normalizedQuery] ?? 'Mixed';
      $isMixedBuyerLandUse = $normalizedLandUse === 'Mixed';
      $buyerLandUseOptions = $isMixedBuyerLandUse
          ? ['Residential', 'Commercial', 'Warehouse']
          : [$normalizedLandUse];
      $fixedBuyerLandUse = $isMixedBuyerLandUse ? null : ($buyerLandUseOptions[0] ?? null);
    @endphp
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-center text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
      <button id="closeModal3" class="text-gray-500 hover:text-gray-700">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <div class="mb-6">
      <div class="flex items-center mb-2">
        <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
        <h3 class="text-lg font-bold">Application for Sectional Titling - Main Application</h3>
        <div class="ml-auto flex items-center">
          <span class="text-gray-600 mr-2">Land Use:</span>
          <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
            {{ $isMixedBuyerLandUse ? 'Mixed Use' : $normalizedLandUse }}
          </span>
        </div>
      </div>
      <p class="text-gray-600">Complete the form below to submit a new primary application for sectional titling
      </p>
    </div>

  <div class="flex items-center mb-8">
      <div class="flex items-center mr-4">
        <div class="step-circle inactive cursor-pointer" onclick="goToStep(1)">1</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive cursor-pointer" onclick="goToStep(2)">2</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive cursor-pointer" onclick="goToStep(3)">3</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle active cursor-pointer" onclick="goToStep(4)">4</div>
      </div>
      <div class="flex items-center">
        <div class="step-circle inactive cursor-pointer" onclick="goToStep(5)">5</div>
      </div>
  <div class="ml-4 step-status-text" data-step-indicator data-step-label="Buyers List">Step 4 - Buyers List</div>
    </div>

    <div class="mb-6">
      <div class="flex items-start mb-4">
        <i data-lucide="layout" class="w-5 h-5 mr-2 text-green-600"></i>
        <span class="font-medium">Buyers List</span>
      </div>

      <!-- Unit Details Form Section -->
      <div class="bg-gray-50 p-4 rounded-md mb-6">
        <h3 class="font-medium mb-4">Add list of buyer</h3>

        <!-- CSV Upload Section - Simplified -->
        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-medium text-gray-700 flex items-center">
              <i data-lucide="upload" class="w-4 h-4 mr-2 text-blue-500"></i>
              Bulk Import Buyers (CSV)
            </h4>
            <a href="{{ route('primaryform.template.download') }}"
               class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded-md hover:bg-blue-100 flex items-center"
               download>
              <i data-lucide="download" class="w-3 h-3 mr-1"></i>
              Download Template
            </a>
          </div>
          
          <!-- Important Notice -->
          <div class="bg-amber-50 border border-amber-200 rounded-md p-3 mb-4">
            <div class="flex items-start">
              <i data-lucide="info" class="w-4 h-4 text-amber-600 mr-2 mt-0.5 flex-shrink-0"></i>
              <div class="text-sm text-amber-800">
                <strong>Important:</strong> CSV import will add buyers to the list below. Your buyer data will be saved when you submit the complete application.
              </div>
            </div>
          </div>
          
          <!-- File Upload Section -->
          <div class="border-2 border-dashed border-gray-300 rounded-md p-4 text-center hover:border-blue-400 transition-colors">
            <div class="flex justify-center mb-2">
              <i data-lucide="file-text" class="w-8 h-8 text-gray-400"></i>
            </div>
            
            <input type="file" 
                   id="csvFileInput"
                   name="csv_file" 
                   accept=".csv" 
                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                   onchange="handleCsvImport()">
            
            <p class="text-xs text-gray-500 mt-2">CSV file with buyer information (max 5MB)</p>
          </div>
          
          <!-- Result Area -->
          <div id="csv-result" class="mt-3"></div>
        </div>

        <!-- Simplified Buyers List -->
        <div id="buyers-container" style="display: none;">
          <div class="border border-gray-200 rounded-lg p-4 mb-4 bg-white buyer-row" data-index="0">
            <div class="flex justify-between items-start mb-4">
              <h4 class="text-sm font-medium text-gray-700">Buyer <span class="buyer-number">1</span></h4>
              <button type="button" 
                      class="bg-red-500 text-white p-1.5 rounded-md hover:bg-red-600 flex items-center justify-center remove-buyer" 
                      onclick="removeBuyer(this)" 
                      style="display: none;">
                <i data-lucide="x" class="w-4 h-4"></i>
              </button>
            </div>
            
            <!-- Buyer Name Fields -->
            <div class="grid grid-cols-4 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Title <span class="text-red-500">*</span>
                </label>
                <select name="records[0][buyerTitle]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" data-buyer-title-select>
                  <option value="">Select title</option>
                  <option value="Mr.">Mr.</option>
                  <option value="Mrs.">Mrs.</option>
                  <option value="Chief">Chief</option>
                  <option value="Master">Master</option>
                  <option value="Capt">Capt</option>
                  <option value="Coln">Coln</option>
                  <option value="HRH">HRH</option>
                  <option value="Mallam">Mallam</option>
                  <option value="Prof">Prof</option>
                  <option value="Dr.">Dr.</option>
                  <option value="Alhaji">Alhaji</option>
                  <option value="Hajia">Hajia</option>
                  <option value="High Chief">High Chief</option>
                  <option value="Senator">Senator</option>
                  <option value="Messr">Messr</option>
                  <option value="Honorable">Honorable</option>
                  <option value="Miss">Miss</option>
                  <option value="Barr.">Barr.</option>
                  <option value="Arc.">Arc.</option>
                  <option value="Other">Other</option>
                </select>
                <div data-custom-title-container class="mt-2 hidden">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Enter the exact title to use:</label>
                  <p class="text-xs text-gray-500 mb-2">Type the honorific exactly as it should appear on the certificate (for example: <span class="font-semibold text-gray-700">Engr.</span>)</p>
                  <input type="text" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter custom title" data-custom-title-input oninput="this.value = this.value.toUpperCase()">
                  <div data-custom-title-hidden></div>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  First Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="records[0][firstName]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter First Name" oninput="this.value = this.value.toUpperCase()">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Middle Name (Optional)
                </label>
                <input type="text" name="records[0][middleName]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter Middle Name" oninput="this.value = this.value.toUpperCase()">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Surname <span class="text-red-500">*</span>
                </label>
                <input type="text" name="records[0][surname]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter Surname" oninput="this.value = this.value.toUpperCase()">
              </div>
            </div>

            <!-- Unit Information -->
            <div class="grid grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Land Use <span class="text-red-500">*</span>
                </label>
                @if (!$isMixedBuyerLandUse)
                  <input type="hidden" name="records[0][landUse]" value="{{ $fixedBuyerLandUse }}" data-land-use-hidden="true">
                @endif
                <select
                  @if ($isMixedBuyerLandUse)
                    name="records[0][landUse]"
                  @endif
                  class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm {{ $isMixedBuyerLandUse ? '' : 'bg-gray-100 text-gray-500 cursor-not-allowed' }}"
                  data-land-use-select
                  @unless ($isMixedBuyerLandUse)
                    disabled
                  @endunless
                >
                  @if ($isMixedBuyerLandUse)
                    <option value="">Select Land Use</option>
                  @endif
                  @foreach ($buyerLandUseOptions as $option)
                    <option value="{{ $option }}" {{ (!$isMixedBuyerLandUse && $loop->first) ? 'selected' : '' }}>{{ $option }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Unit Number <span class="text-red-500">*</span>
                </label>
                <input type="text" name="records[0][unit_no]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" placeholder="Enter Unit Number" oninput="this.value = this.value.toUpperCase()">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Unit Measurement (Optional)
                </label>
                <input type="text" name="records[0][unitMeasurement]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm" placeholder="e.g. 50sqm">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Section Number <span class="text-red-500">*</span>
                </label>
                <input type="text" name="records[0][sectionNumber]" class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm" placeholder="Enter Section Number">
              </div>
            </div>
          </div>
        </div>

        <!-- Add Another Buyer Button -->
        <div class="flex justify-center mt-4">
          <button type="button" onclick="showBuyersAndAddBuyer()" class="bg-green-600 text-white px-4 py-2 rounded-md text-sm hover:bg-green-700 flex items-center">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
            Add Buyer
          </button>
        </div>
      </div>
    </div>

    <!-- Navigation buttons -->
    <div class="flex justify-between mt-8">
      <button type="button" onclick="goToStep(3)" class="px-4 py-2 bg-white border border-gray-300 rounded-md">Previous</button>
      <div class="flex items-center">
  <span class="text-sm text-gray-500 mr-4 step-status-text" data-step-indicator data-step-total="5">Step 4 of 5</span>
        <button type="button" onclick="goToStep(5)" class="px-4 py-2 bg-black text-white rounded-md">Next</button>
      </div>
    </div>
  </div>

  <script>
    window.primaryFormLandUseConfig = {
      isMixed: {{ $isMixedBuyerLandUse ? 'true' : 'false' }},
      fixedValue: @json($fixedBuyerLandUse),
      allowedOptions: @json($buyerLandUseOptions),
    };

    if (typeof window.addBuyer !== 'function') {
      window.__addBuyerQueue = window.__addBuyerQueue || [];
      window.__queuedAddBuyerStub = function() {
        var args = Array.prototype.slice.call(arguments || []);
        window.__addBuyerQueue.push(args);
        if (window.console && typeof window.console.warn === 'function') {
          console.warn('addBuyer is waiting for buyers.js to finish loading. Call queued.');
        }
      };
      window.addBuyer = window.__queuedAddBuyerStub;
    }

    // Show buyers container and add first buyer
    window.showBuyersAndAddBuyer = function() {
      var container = document.getElementById('buyers-container');
      if (container) {
        container.style.display = 'block';
      }
      
      // Change button text and function
      var button = document.querySelector('button[onclick="showBuyersAndAddBuyer()"]');
      if (button) {
        button.innerHTML = '<i data-lucide="plus" class="w-4 h-4 mr-2"></i>Add Another Buyer';
        button.setAttribute('onclick', 'addBuyer()');
        
        // Reinitialize icons
        if (typeof lucide !== 'undefined') {
          lucide.createIcons();
        }
      }
      
      // Don't call addBuyer since the first buyer row already exists in the container
    };

    (function ensureBuyersScriptLoaded() {
      if (typeof window.addBuyer === 'function' && window.addBuyer !== window.__queuedAddBuyerStub) {
        return;
      }

      var existing = document.querySelector('script[data-primaryform-buyers="true"]');
      if (existing) {
        return;
      }

      var script = document.createElement('script');
      script.src = '{{ asset('js/primaryform/buyers.js') }}?fallback={{ time() }}';
      script.type = 'text/javascript';
      script.async = false;
      script.defer = false;
      script.dataset.primaryformBuyers = 'true';
      script.onload = function() {
        if (typeof window.initPrimaryBuyerFormFields === 'function') {
          window.initPrimaryBuyerFormFields();
        }
      };
      document.head.appendChild(script);
    })();

    // All buyer functions are now loaded from external buyers.js file
    // Only initialization code remains here to avoid duplicates
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🎯 Buyers step initialized');
      
      // Use functions from external buyers.js
      if (typeof updateRemoveButtons === 'function') {
        updateRemoveButtons();
      }
      
      // Initialize Lucide icons
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
</div>
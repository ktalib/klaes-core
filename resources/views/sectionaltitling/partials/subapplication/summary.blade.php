<div class="form-section" id="step4">
    <div class="p-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-center text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
        <button id="closeModal3" class="text-gray-500 hover:text-gray-700">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      
      <div class="mb-6">
        <div class="flex items-center mb-2">
          <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
          @if($isSUA ?? false)
            <h3 class="text-lg font-bold">Application for Standalone Unit Application (SUA)</h3>
          @else
            <h3 class="text-lg font-bold">Application for Sectional Titlle - Unit Application (Secondary)</h3>
          @endif
          <div class="ml-auto flex items-center">
            <span class="text-gray-600 mr-2">Land Use:</span>
            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
              @if($isSUA ?? false)
                {{ $selectedLandUse ?? 'N/A' }}
              @else
                {{ $motherApplication->land_use ?? 'N/A' }}
              @endif
            </span>
          </div>
        </div>
        <p class="text-gray-600">
          @if($isSUA ?? false)
            Complete the form below to submit a new standalone unit application
          @else
            Complete the form below to submit a new unit application for sectional titling
          @endif
        </p>
      </div>

      <div class="flex items-center mb-8">
        <div class="flex items-center mr-4">
          <div class="step-circle inactive-tab cursor-pointer" onclick="goToStep(1)">1</div>
        </div>
        <div class="flex items-center mr-4">
          <div class="step-circle inactive-tab cursor-pointer" onclick="goToStep(2)">2</div>
        </div>
         <div class="flex items-center mr-4">
          <div class="step-circle inactive-tab cursor-pointer" onclick="goToStep(3)">3</div>
        </div>
        <div class="flex items-center mr-4">
          <div class="step-circle active-tab cursor-pointer" onclick="goToStep(4)">4</div>
        </div>
        <div class="ml-4">Step 4</div>
      </div>

      <div class="mb-6">
        <div class="flex items-start mb-4">
          <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
          <span class="font-medium">Application Summary</span>
        </div>
        
        <!-- File Information Section -->
        <div class="border border-gray-200 rounded-md p-6 mb-6 bg-blue-50">
          <h4 class="font-medium mb-4 text-blue-800">File Information</h4>
          <div class="grid grid-cols-2 gap-6">
            <table class="w-full text-sm">
              @if($isSUA ?? false)
                <tr>
                  <td class="py-1 text-gray-600">Primary FileNo:</td>
                  <td class="py-1 font-medium text-blue-800" id="summary-primary-file-number">
                    <span id="primaryFileNumberDisplay">{{ $primaryFileNo ?? '' }}</span>
                  </td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">SUA FileNo:</td>
                  <td class="py-1 font-medium text-green-800" id="summary-sua-file-number">
                    <span id="suaFileNumberDisplay">{{ $suaFileNo ?? '' }}</span>
                  </td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">MLPP FileNo:</td>
                  <td class="py-1 font-medium" id="summary-mls-file-number">
                    <span id="mlsFileNumberDisplay">{{ $mlsFileNo ?? $primaryFileNo ?? '' }}</span>
                  </td>
                </tr>
              @else
                <tr>
                  <td class="py-1 text-gray-600">STFileNo:</td>
                  <td class="py-1 font-medium text-blue-800" id="summary-st-file-number">
                    <span id="stFileNumberDisplay"></span>
                  </td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Main Application ID:</td>
                  <td class="py-1 font-medium" id="summary-main-id">
                    <span id="mainIdDisplay"></span>
                  </td>
                </tr>
              @endif
              <tr>
                <td class="py-1 text-gray-600">Scheme No:</td>
                <td class="py-1 font-medium" id="summary-scheme-no">
                  <span id="schemeNoDisplay"></span>
                </td>
              </tr>
            </table>
            <table class="w-full text-sm">
              @if($isSUA ?? false)
                <tr>
                  <td class="py-1 text-gray-600">Allocation Source:</td>
                  <td class="py-1 font-medium" id="summary-allocation-source">
                    <span id="allocationSourceDisplay"></span>
                  </td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Allocation Entity:</td>
                  <td class="py-1 font-medium" id="summary-allocation-entity">
                    <span id="allocationEntityDisplay"></span>
                  </td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Property Location:</td>
                  <td class="py-1 font-medium" id="summary-property-location">
                    <span id="propertyLocationDisplay" class="text-sm">N/A</span>
                  </td>
                </tr>
              @endif
              <tr>
                <td class="py-1 text-gray-600">Land Use:</td>
                <td class="py-1 font-medium" id="summary-land-use">
                  @if($isSUA ?? false)
                    <span id="landUseDisplay">{{ $selectedLandUse ?? 'N/A' }}</span>
                  @else
                    <span id="landUseDisplay">{{ $motherApplication->land_use ?? 'N/A' }}</span>
                  @endif
                </td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Applicant Information Section -->
        <div class="border border-gray-200 rounded-md p-6 mb-6" id="applicant-info-section">
          <h4 class="font-medium mb-4">Applicant Information</h4>
          <div class="grid grid-cols-2 gap-6">
            <table class="w-full text-sm">
              <tr>
                <td class="py-1 text-gray-600">Applicant Type:</td>
                <td class="py-1 font-medium" id="summary-applicant-type">
                  <span id="applicantTypeDisplay">Individual</span>
                </td>
              </tr>
              <tr id="individual-name-row">
                <td class="py-1 text-gray-600">Name:</td>
                <td class="py-1 font-medium" id="summary-applicant-name">
                  <span id="applicantNameDisplay"></span>
                </td>
              </tr>
              <tr id="corporate-name-row" style="display: none;">
                <td class="py-1 text-gray-600">Corporate Name:</td>
                <td class="py-1 font-medium" id="summary-corporate-name">
                  <span id="corporateNameDisplay"></span>
                </td>
              </tr>
              <tr id="corporate-rc-row" style="display: none;">
                <td class="py-1 text-gray-600">RC Number:</td>
                <td class="py-1 font-medium" id="summary-rc-number">
                  <span id="rcNumberDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">Email:</td>
                <td class="py-1 font-medium" id="summary-applicant-email">
                  <span id="emailDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">Phone:</td>
                <td class="py-1 font-medium" id="summary-applicant-phone">
                  <span id="phoneDisplay"></span>
                </td>
              </tr>
            </table>
            <div>
              <h5 class="font-medium mb-2">Means of Identification</h5>
              <table class="w-full text-sm">
                <tr id="main-identification-row">
                  <td class="py-1 text-gray-600">ID Type:</td>
                  <td class="py-1 font-medium" id="summary-identification-type">
                    <span id="identificationTypeDisplay"></span>
                  </td>
                </tr>
                <tr id="main-identification-status-row">
                  <td class="py-1 text-gray-600">ID Document:</td>
                  <td class="py-1 font-medium" id="summary-identification-status">
                    <span id="identificationStatusDisplay"></span>
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </div>

        <!-- Multiple Owners Section (shown only for multiple owners) -->
        <div class="border border-gray-200 rounded-md p-6 mb-6" id="multiple-owners-section" style="display: none;">
          <h4 class="font-medium mb-4">Multiple Owners Information</h4>
          <div id="multiple-owners-summary" class="space-y-4">
            <!-- Dynamic content will be inserted here -->
          </div>
        </div>

        <!-- Unit Information Section -->
        <div class="border border-gray-200 rounded-md p-6 mb-6">
          <h4 class="font-medium mb-4">Unit Information</h4>
          <div class="grid grid-cols-2 gap-6">
            <table class="w-full text-sm">
              <tr>
                <td class="py-1 text-gray-600">Unit Type:</td>
                <td class="py-1 font-medium" id="summary-unit-type">
                  <span id="unitTypeDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600 hidden">Ownership Type:</td>
                <td class="py-1 font-medium" id="summary-ownership-type">
                  <span id="ownershipTypeDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">Block No:</td>
                <td class="py-1 font-medium" id="summary-block-no">
                  <span id="blockNumberDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">Unit Size:</td>
                <td class="py-1 font-medium" id="summary-unit-size">
                  <span id="unitSizeDisplay"></span>
                </td>
              </tr>
            </table>
            <table class="w-full text-sm">
              <tr>
                <td class="py-1 text-gray-600">Section (Floor) No:</td>
                <td class="py-1 font-medium" id="summary-floor-no">
                  <span id="floorNumberDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">Unit No:</td>
                <td class="py-1 font-medium" id="summary-unit-no">
                  <span id="unitNumberDisplay"></span>
                </td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Address Information Section -->
        <div class="border border-gray-200 rounded-md p-6 mb-6" id="address-section">
          <h4 class="font-medium mb-4">Unit Owner's Address</h4>
          <div class="grid grid-cols-2 gap-6">
            <table class="w-full text-sm">
              <tr>
                <td class="py-1 text-gray-600">House No:</td>
                <td class="py-1 font-medium" id="summary-house-no">
                  <span id="houseNoDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">Street Name:</td>
                <td class="py-1 font-medium" id="summary-street-name">
                  <span id="streetNameDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">District:</td>
                <td class="py-1 font-medium" id="summary-district">
                  <span id="districtDisplay"></span>
                </td>
              </tr>
            </table>
            <table class="w-full text-sm">
              <tr>
                <td class="py-1 text-gray-600">LGA:</td>
                <td class="py-1 font-medium" id="summary-lga">
                  <span id="lgaDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">State:</td>
                <td class="py-1 font-medium" id="summary-state">
                  <span id="stateDisplay"></span>
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-600">Complete Address:</td>
                <td class="py-1 font-medium" id="summary-complete-address">
                  <span id="completeAddressDisplay"></span>
                </td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Shared Areas Section -->
        <div class="border border-gray-200 rounded-md p-6 mb-6">
          <h4 class="font-medium mb-4">Shared Areas</h4>
          <div id="shared-areas-summary" class="grid grid-cols-2 gap-4">
            <!-- Dynamic content will be inserted here -->
          </div>
        </div>

        <!-- Payment Information Section -->
        <div class="border border-gray-200 rounded-md p-6 mb-6">
          <h4 class="font-medium mb-4">Payment Information</h4>
          
          <!-- Processing Fee -->
          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
              <h5 class="font-medium text-gray-700">Processing Fee</h5>
              <span class="font-bold text-green-600" id="processingFeeDisplay">₦0</span>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-600">Payment Date:</span>
                <span class="ml-2 font-medium" id="processingFeePaymentDateDisplay">-</span>
              </div>
              <div>
                <span class="text-gray-600">Receipt No:</span>
                <span class="ml-2 font-medium" id="processingFeeReceiptNoDisplay">-</span>
              </div>
            </div>
          </div>

          <!-- Application Fee -->
          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
              <h5 class="font-medium text-gray-700">Application Fee</h5>
              <span class="font-bold text-green-600" id="applicationFeeDisplay">₦0</span>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-600">Payment Date:</span>
                <span class="ml-2 font-medium" id="applicationFeePaymentDateDisplay">-</span>
              </div>
              <div>
                <span class="text-gray-600">Receipt No:</span>
                <span class="ml-2 font-medium" id="applicationFeeReceiptNoDisplay">-</span>
              </div>
            </div>
          </div>

          <!-- Survey Fee -->
          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
              <h5 class="font-medium text-gray-700">Survey Fee</h5>
              <span class="font-bold text-green-600" id="surveyFeeDisplay">₦0</span>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-600">Payment Date:</span>
                <span class="ml-2 font-medium" id="surveyFeePaymentDateDisplay">-</span>
              </div>
              <div>
                <span class="text-gray-600">Receipt No:</span>
                <span class="ml-2 font-medium" id="surveyFeeReceiptNoDisplay">-</span>
              </div>
            </div>
          </div>

          <!-- Total Amount -->
          <div class="border-t border-gray-300 pt-4">
            <div class="flex items-center justify-between">
              <h5 class="font-bold text-gray-800">Total Amount:</h5>
              <span class="font-bold text-xl text-green-600" id="totalFeeDisplay">₦0</span>
            </div>
          </div>
        </div>

        <!-- Comments Section -->
        <div class="border border-gray-200 rounded-md p-6 mb-6" id="comments-section" style="display: none;">
          <h4 class="font-medium mb-4">Application Comments</h4>
          <div class="bg-gray-50 p-4 rounded-md">
            <p id="commentsDisplay" class="text-sm text-gray-700"></p>
          </div>
        </div>
        
        <!-- Documents Section -->
        <div class="border border-gray-200 rounded-md p-6 mb-6" id="uploaded-documents-section">
          <h4 class="font-medium mb-4">Uploaded Documents</h4>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <span id="applicationLetterIndicator" class="inline-block w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                <span>Application Letter</span>
              </div>
              <span id="applicationLetterStatus" class="text-sm text-red-600 font-medium">Not Uploaded</span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <span id="buildingPlanIndicator" class="inline-block w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                <span>Building Plan</span>
              </div>
              <span id="buildingPlanStatus" class="text-sm text-red-600 font-medium">Not Uploaded</span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <span id="architecturalDesignIndicator" class="inline-block w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                <span>Architectural Design</span>
              </div>
              <span id="architecturalDesignStatus" class="text-sm text-red-600 font-medium">Not Uploaded</span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <span id="ownershipDocumentIndicator" class="inline-block w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                <span>Ownership Document</span>
              </div>
              <span id="ownershipDocumentStatus" class="text-sm text-red-600 font-medium">Not Uploaded</span>
            </div>
          </div>
        </div>
        
        <div class="flex justify-between mt-8">
          <div class="flex space-x-4">
            <button class="px-4 py-2 bg-white border border-gray-300 rounded-md" id="backStep4">Back</button>
            {{-- <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-md flex items-center" id="printApplicationSlip">
              <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
              Print Application Slip
            </button> --}}
          </div>
          <div class="flex items-center">
            <span class="text-sm text-gray-500 mr-4">Step 4 of 4</span>
            <button type="submit" id="submitApplication" class="px-4 py-2 bg-black text-white rounded-md">Submit Application</button>
          </div>
        </div>
      </div>
    </div>
  </div>

<!-- Print Application Slip Template (hidden by default) -->
<div id="printTemplate" class="hidden">
  <div class="print-container">
    <div class="print-header">
      <div class="header-with-logos">
        <div class="logo-left">
          <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Nigeria Coat of Arms" class="logo-image">
        </div>
        <div class="header-text">
          <h1 class="text-xl font-bold mb-1">MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
          <h2 class="text-lg font-semibold mb-4">
            @if($isSUA ?? false)
              APPLICATION FOR STANDALONE UNIT APPLICATION (SUA)
            @else
              APPLICATION FOR SECTIONAL TITLING - UNIT APPLICATION
            @endif
          </h2>
        </div>
        <div class="logo-right">
          <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Ministry Logo" class="logo-image">
        </div>
      </div>
      <div class="border-b-2 border-black mb-6"></div>
    </div>

    <div class="print-body">
      <div class="mb-4">
        <h3 class="text-lg font-bold mb-2">Application Receipt</h3>
        <div class="flex justify-between mb-2">
          <span>Application ID: <span id="print-app-id"></span></span>
          <span>Date: <span id="print-date"></span></span>
        </div>
        <div class="flex justify-between">
          <span>
            @if($isSUA ?? false)
              SUA FileNo: <span id="print-file-number"></span>
            @else
              STFileNo: <span id="print-file-number"></span>
            @endif
          </span>
          <span>Land Use: 
            @if($isSUA ?? false)
              <span id="print-land-use">{{ $selectedLandUse ?? 'N/A' }}</span>
            @else
              <span id="print-land-use">{{ $motherApplication->land_use ?? 'N/A' }}</span>
            @endif
          </span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-6 mb-4">
        <div>
          <h4 class="font-medium mb-2 border-b border-gray-300 pb-1">Applicant Information</h4>
          <table class="w-full text-sm">
            <tr>
              <td class="py-1 text-gray-600 w-1/3">Applicant Type:</td>
              <td class="py-1 font-medium" id="print-applicant-type"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Name:</td>
              <td class="py-1 font-medium" id="print-name"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Email:</td>
              <td class="py-1 font-medium" id="print-email"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Phone:</td>
              <td class="py-1 font-medium" id="print-phone"></td>
            </tr>
          </table>
        </div>
        
        <div>
          <h4 class="font-medium mb-2 border-b border-gray-300 pb-1">Unit Information</h4>
          <table class="w-full text-sm">
            <tr>
              <td class="py-1 text-gray-600 w-1/2">Unit Type:</td>
              <td class="py-1 font-medium" id="print-unit-type"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Block No:</td>
              <td class="py-1 font-medium" id="print-blocks"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Section (Floor) No:</td>
              <td class="py-1 font-medium" id="print-sections"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Unit No:</td>
              <td class="py-1 font-medium" id="print-units"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Unit Size:</td>
              <td class="py-1 font-medium" id="print-unit-size"></td>
            </tr>
          </table>
        </div>
      </div>

      <div class="mb-4">
        <h4 class="font-medium mb-2 border-b border-gray-300 pb-1">Contact Address</h4>
        <table class="w-full text-sm">
          <tr>
            <td class="py-1 text-gray-600 w-1/4">Complete Address:</td>
            <td class="py-1 font-medium" id="print-address"></td>
          </tr>
        </table>
      </div>

      <div class="mb-4">
        <h4 class="font-medium mb-2 border-b border-gray-300 pb-1">Property Information</h4>
        <table class="w-full text-sm">
          @if($isSUA ?? false)
            <tr>
              <td class="py-1 text-gray-600 w-1/4">Primary FileNo:</td>
              <td class="py-1 font-medium" id="print-primary-file-no"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">MLPP FileNo:</td>
              <td class="py-1 font-medium" id="print-mls-file-no"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Property Location:</td>
              <td class="py-1 font-medium" id="print-property-location"></td>
            </tr>
          @else
            <tr>
              <td class="py-1 text-gray-600 w-1/4">Main Application ID:</td>
              <td class="py-1 font-medium" id="print-main-application-id"></td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Scheme No:</td>
              <td class="py-1 font-medium" id="print-scheme-no"></td>
            </tr>
          @endif
        </table>
      </div>
      
      <div class="mb-6">
        <h4 class="font-medium mb-2 border-b border-gray-300 pb-1">Payment Information</h4>
        <table class="w-full text-sm">
          <tr>
            <td class="py-1 text-gray-600 w-1/4">Application Fee:</td>
            <td class="py-1 font-medium" id="print-application-fee"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">Processing Fee:</td>
            <td class="py-1 font-medium" id="print-processing-fee"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">Survey Fee:</td>
            <td class="py-1 font-medium" id="print-survey-fee"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600 font-medium">Total:</td>
            <td class="py-1 font-bold" id="print-total-fee"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">Receipt Number:</td>
            <td class="py-1 font-medium" id="print-receipt-number"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">Payment Date:</td>
            <td class="py-1 font-medium" id="print-payment-date"></td>
          </tr>
        </table>
      </div>

      <div class="mb-6 grid grid-cols-2 gap-4">
        <div>
          <h4 class="font-medium mb-2 border-b border-gray-300 pb-1">Required Documents</h4>
          <div id="print-documents" class="text-sm">
            <!-- Documents will be populated dynamically -->
          </div>
        </div>
        <div>
          <h4 class="font-medium mb-2 border-b border-gray-300 pb-1">For Official Use Only</h4>
          <div class="mt-4">
            <div class="border-t border-gray-300 pt-4 mt-4">
              <div class="text-center">
                <p>Signature & Stamp</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="print-footer mt-6 text-center text-sm">
      <p>This is an official application receipt. Please keep for your records.</p>
      <p>Application submitted on: <span id="print-submission-date"></span></p>
    </div>

    
  </div>
</div>

<style>
@page {
    size: A4 landscape;
    margin: 8mm;
}

.header-with-logos {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.logo-left, .logo-right {
    flex: 0 0 60px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.header-text {
    flex: 1;
    text-align: center;
    padding: 0 15px;
}

/* Remove any text-shadow from logo-image and header-text */
.logo-image {
    width: 50px;
    height: 50px;
    object-fit: contain;
    /* text-shadow: none; */ /* Not needed */
}

.header-text, .header-text h1, .header-text h2 {
    text-shadow: none !important;
}

@media print {
    html, body {
        margin: 0;
        padding: 0;
        width: 297mm;
        height: 210mm;
        overflow: hidden;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Hide everything first using display to avoid layered ghost rendering */
    body > * { display: none !important; }

    /* Only show the print template */
    #printTemplate { 
        display: block !important; 
        position: static !important; 
        width: 100%;
        height: auto; 
        text-shadow: none !important;
    }
    #printTemplate * { 
        text-shadow: none !important; 
        -webkit-font-smoothing: antialiased !important; 
        -moz-osx-font-smoothing: grayscale !important; 
    }

    /* Container layout */
    .print-container {
      padding: 6mm;
      width: 100%;
      min-height: 100%;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      page-break-inside: avoid;
    }
    
    /* Ensure all text elements have clean rendering */
    .print-container * {
        text-shadow: none !important;
        -webkit-text-shadow: none !important;
        -moz-text-shadow: none !important;
        -ms-text-shadow: none !important;
        -o-text-shadow: none !important;
        -webkit-font-smoothing: antialiased !important;
        -moz-osx-font-smoothing: grayscale !important;
        text-rendering: optimizeLegibility !important;
    }
    .print-header {
        flex-shrink: 0;
        margin-bottom: 6px;
    }
  .print-body {
    flex: 1;
    font-size: 9px;
    line-height: 1.15;
    overflow: hidden;
  }
    .print-footer {
        flex-shrink: 0;
        margin-top: 6px;
        font-size: 8px;
    }
    .header-with-logos {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 6px;
        width: 100%;
    }
    .logo-left, .logo-right {
        flex: 0 0 60px;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .header-text {
        flex: 1;
        text-align: center;
        padding: 0 15px;
        text-shadow: none !important;
    }
    .header-text h1 {
        font-size: 14px;
        margin-bottom: 2px;
        font-weight: bold;
        text-shadow: none !important;
        -webkit-text-shadow: none !important;
        -moz-text-shadow: none !important;
    }
    .header-text h2 {
        font-size: 12px;
        margin-bottom: 0;
        font-weight: 600;
        text-shadow: none !important;
        -webkit-text-shadow: none !important;
        -moz-text-shadow: none !important;
    }
  .logo-image {
    width: 50px;
    height: 50px;
    object-fit: contain;
    display: block;
  }
    .no-print {
      display: none;
    }
    
    /* Compact layout for single page */
    .grid.grid-cols-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 4px;
    }
    
    .print-body h3 {
        font-size: 11px;
        margin-bottom: 4px;
        font-weight: bold;
        text-shadow: none !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    .print-body h4 {
        font-size: 10px;
        margin-bottom: 3px;
        border-bottom: 1px solid #ccc;
        padding-bottom: 1px;
        font-weight: 600;
        text-shadow: none !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    .print-body table {
        font-size: 9px;
        line-height: 1.0;
        width: 100%;
        text-shadow: none !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
  .print-body table td {
    padding: 1px 0;
    vertical-align: top;
    text-shadow: none !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
    
    .print-body .mb-4 {
        margin-bottom: 4px;
    }
    
    .print-body .mb-6 {
        margin-bottom: 6px;
    }
    
    .border-b-2 {
        border-bottom: 1px solid #000;
        margin-bottom: 4px;
    }
    
    /* Reduce spacing for compact layout */
    .print-body > div {
        margin-bottom: 3px;
    }
    
    /* Flex layout for better space utilization */
    .print-body .mb-6.grid.grid-cols-2 {
        display: flex;
        gap: 10px;
    }
    
    .print-body .mb-6.grid.grid-cols-2 > div {
        flex: 1;
    }
    
    /* Compact the main content sections */
    .print-body > .mb-4:first-child {
        margin-bottom: 3px;
    }
    
    .print-body > .grid.grid-cols-2.gap-6.mb-4 {
        margin-bottom: 3px;
        gap: 8px;
    }
    
    .print-body > .mb-4:nth-child(3) {
        margin-bottom: 3px;
    }
    
    .print-body > .mb-6:last-of-type {
        margin-bottom: 3px;
    }
    
    /* Ensure content fits in available space */
    .print-body .flex.justify-between {
        font-size: 9px;
    }
    
  .print-body span {
    font-size: inherit;
    text-shadow: none !important;
  }
    
    /* Compact document list */
    #print-documents {
        font-size: 8px;
        line-height: 1.0;
        text-shadow: none !important;
    }
    
  #print-documents > div { margin-bottom: 2px; }
}

/* Screen-only helper to ensure print template stays hidden normally */
@media screen {
  #printTemplate { display: none; }
}
</style>

<script>
// Function to update the summary
function updateApplicationSummary() {
  // Check if this is a SUA application
  const isSUA = document.querySelector('input[name="is_sua"]')?.value === '1' || 
               window.location.href.includes('/sua/create') ||
               document.querySelector('.sua-indicator');
  
  // File Information
  if (isSUA) {
    // SUA file information
    const primaryFileNo = document.getElementById('hidden-sua-np-fileno')?.value ||
                         document.querySelector('input[name="np_fileno"]')?.value || 'N/A';
    const suaFileNo = document.getElementById('hidden-sua-fileno')?.value ||
                     document.getElementById('unitFileNo')?.value ||
                     document.querySelector('input[name="fileno"]')?.value || 'N/A';
    const mlsFileNo = document.getElementById('hidden-sua-mls-fileno')?.value ||
                      document.querySelector('input[name="mls_fileno"]')?.value ||
                      primaryFileNo;
    const allocationSource = document.querySelector('select[name="allocation_source"]')?.value || 'N/A';
    const allocationEntity = document.querySelector('select[name="allocation_entity"]')?.value || 'N/A';
    const propertyLocation = document.querySelector('textarea[name="property_location"]')?.value || 'N/A';
    const suaLandUse = document.getElementById('sua_land_use_hidden')?.value ||
                       document.querySelector('input[name="land_use"]')?.value || 'N/A';
    
    // Update SUA-specific fields if they exist
    if (document.getElementById('primaryFileNumberDisplay')) {
      document.getElementById('primaryFileNumberDisplay').textContent = primaryFileNo;
    }
    if (document.getElementById('suaFileNumberDisplay')) {
      document.getElementById('suaFileNumberDisplay').textContent = suaFileNo;
    }
    if (document.getElementById('mlsFileNumberDisplay')) {
      document.getElementById('mlsFileNumberDisplay').textContent = mlsFileNo;
    }
    if (document.getElementById('allocationSourceDisplay')) {
      document.getElementById('allocationSourceDisplay').textContent = allocationSource;
    }
    if (document.getElementById('allocationEntityDisplay')) {
      document.getElementById('allocationEntityDisplay').textContent = allocationEntity;
    }
    if (document.getElementById('propertyLocationDisplay')) {
      document.getElementById('propertyLocationDisplay').textContent = propertyLocation;
    }
    if (document.getElementById('landUseDisplay')) {
      document.getElementById('landUseDisplay').textContent = suaLandUse;
    }
  } else {
    // Regular sectional titling file information
    const stFileNo = document.querySelector('input[name="fileno"]')?.value || 'N/A';
    const mainId = document.getElementById('mainIdHidden')?.value || 'N/A';
    const unitLandUse = document.querySelector('select[name="land_use"]')?.value ||
                        document.querySelector('input[name="land_use"]')?.value ||
                        document.getElementById('landUseDisplay')?.textContent || 'N/A';
    
    if (document.getElementById('stFileNumberDisplay')) {
      document.getElementById('stFileNumberDisplay').textContent = stFileNo;
    }
    if (document.getElementById('mainIdDisplay')) {
      document.getElementById('mainIdDisplay').textContent = mainId;
    }
    if (document.getElementById('landUseDisplay')) {
      document.getElementById('landUseDisplay').textContent = unitLandUse;
    }
  }
  
  // Common fields
  const schemeNo = document.querySelector('input[name="scheme_no"]')?.value || 'N/A';
  if (document.getElementById('schemeNoDisplay')) {
    document.getElementById('schemeNoDisplay').textContent = schemeNo;
  }
  
  // Applicant Information
  let applicantType = document.getElementById('mainApplicantTypeInput')?.value ||
                      document.querySelector('input[name="applicant_type"]')?.value ||
                      document.querySelector('input[name="applicantType"]:checked')?.value ||
                      document.getElementById('applicantType')?.value ||
                      'individual';
  applicantType = (applicantType || 'individual').toLowerCase();
  if (document.getElementById('applicantTypeDisplay')) {
    document.getElementById('applicantTypeDisplay').textContent = applicantType.charAt(0).toUpperCase() + applicantType.slice(1);
  }
  
  // Show/hide sections based on applicant type
  const individualRows = document.querySelectorAll('#individual-name-row');
  const corporateRows = document.querySelectorAll('#corporate-name-row, #corporate-rc-row');
  const multipleOwnersSection = document.getElementById('multiple-owners-section');
  const addressSection = document.getElementById('address-section');
  const mainIdentificationRows = document.querySelectorAll('#main-identification-row, #main-identification-status-row');
  const applicantInfoSection = document.getElementById('applicant-info-section');
  const uploadedDocumentsSection = document.getElementById('uploaded-documents-section');
  
  if (applicantType === 'individual') {
    individualRows.forEach(row => row.style.display = '');
    corporateRows.forEach(row => row.style.display = 'none');
    multipleOwnersSection.style.display = 'none';
    addressSection.style.display = 'block';
    mainIdentificationRows.forEach(row => row.style.display = '');
    applicantInfoSection.style.display = 'block';
    uploadedDocumentsSection.style.display = 'block';
    
    // Individual name - handle both SUA and regular field names
    const title = document.getElementById('applicantTitle')?.value || 
                 document.querySelector('input[name="applicant_title"]')?.value || '';
    const firstName = document.getElementById('applicantName')?.value || 
                     document.querySelector('input[name="first_name"]')?.value || '';
    const middleName = document.getElementById('applicantMiddleName')?.value || 
                      document.querySelector('input[name="middle_name"]')?.value || '';
    const surname = document.getElementById('applicantSurname')?.value || 
                   document.querySelector('input[name="surname"]')?.value || '';
    
    let fullName = '';
    if (title) fullName += title + ' ';
    if (firstName) fullName += firstName + ' ';
    if (middleName) fullName += middleName + ' ';
    if (surname) fullName += surname;
    
    if (document.getElementById('applicantNameDisplay')) {
      document.getElementById('applicantNameDisplay').textContent = fullName.trim() || 'N/A';
    }
    
  } else if (applicantType === 'corporate') {
    individualRows.forEach(row => row.style.display = 'none');
    corporateRows.forEach(row => row.style.display = '');
    multipleOwnersSection.style.display = 'none';
    addressSection.style.display = 'block';
    mainIdentificationRows.forEach(row => row.style.display = '');
    applicantInfoSection.style.display = 'block';
    uploadedDocumentsSection.style.display = 'block';
    
    // Corporate information
    document.getElementById('corporateNameDisplay').textContent = document.getElementById('corporateName')?.value || 'N/A';
    document.getElementById('rcNumberDisplay').textContent = document.getElementById('rcNumber')?.value || 'N/A';
    
  } else if (applicantType === 'multiple') {
    individualRows.forEach(row => row.style.display = 'none');
    corporateRows.forEach(row => row.style.display = 'none');
    multipleOwnersSection.style.display = 'block';
    addressSection.style.display = 'none';
    mainIdentificationRows.forEach(row => row.style.display = 'none');
    applicantInfoSection.style.display = 'none';
    uploadedDocumentsSection.style.display = 'none';
    
    // Multiple owners information
    updateMultipleOwnersSummary();
  }
  
  // Main identification information (for individual and corporate)
  if (applicantType !== 'multiple') {
    const identificationType = document.querySelector('input[name="identification_type"]:checked')?.value || 'N/A';
    document.getElementById('identificationTypeDisplay').textContent = identificationType.charAt(0).toUpperCase() + identificationType.slice(1);
    
    const identificationFile = document.getElementById('identification_image');
    const hasIdentificationFile = identificationFile && identificationFile.files && identificationFile.files.length > 0;
    document.getElementById('identificationStatusDisplay').innerHTML = hasIdentificationFile ? 
      '<span class="text-green-600">Uploaded</span>' : '<span class="text-red-600">Not Uploaded</span>';
  }
  
  // Contact Information
  // Handle email field - SUA uses 'email', regular uses 'owner_email'
  const emailValue = document.querySelector('input[name="email"]')?.value || 
                    document.querySelector('input[name="owner_email"]')?.value || 'N/A';
  if (document.getElementById('emailDisplay')) {
    document.getElementById('emailDisplay').textContent = emailValue;
  }
  
  // Handle phone numbers
  const phoneInputs = document.querySelectorAll('input[name="phone_number[]"]');
  const phoneNumbers = Array.from(phoneInputs).map(input => input.value).filter(value => value);
  if (document.getElementById('phoneDisplay')) {
    document.getElementById('phoneDisplay').textContent = phoneNumbers.join(', ') || 'N/A';
  }
  
  // Unit Information
  updateUnitInformation();
  
  // Address Information (only for individual and corporate)
  if (applicantType !== 'multiple') {
    updateAddressInformation();
  }
  
  // Shared Areas
  updateSharedAreasSummary();
  
  // Payment Information
  updatePaymentInformation();
  
  // Comments
  updateComments();
  
  // Documents
  updateDocumentIndicators();
}

function updateMultipleOwnersSummary() {
  const ownersContainer = document.getElementById('multiple-owners-summary');
  const ownerNameInputs = document.querySelectorAll('input[name="multiple_owners_names[]"]');
  const ownerAddressInputs = document.querySelectorAll('textarea[name="multiple_owners_address[]"]');
  const ownerEmailInputs = document.querySelectorAll('input[name="multiple_owners_email[]"]');
  const ownerPhoneInputs = document.querySelectorAll('input[name="multiple_owners_phone[]"]');
  
  let summaryHTML = '';
  
  ownerNameInputs.forEach((nameInput, index) => {
    const name = nameInput.value || `Owner ${index + 1}`;
    const address = ownerAddressInputs[index]?.value || 'N/A';
    const email = ownerEmailInputs[index]?.value || 'N/A';
    const phone = ownerPhoneInputs[index]?.value || 'N/A';
    
    // Get identification type for this owner
    const identificationTypeInput = document.querySelector(`input[name="multiple_owners_identification_type_${index}"]:checked`);
    const identificationType = identificationTypeInput?.value || 'N/A';
    
    // Check if identification file is uploaded
    const identificationFileInputs = document.querySelectorAll('input[name="multiple_owners_identification_image[]"]');
    const hasIdentificationFile = identificationFileInputs[index] && identificationFileInputs[index].files && identificationFileInputs[index].files.length > 0;
    
    summaryHTML += `
      <div class="border border-gray-100 rounded-md p-4 bg-gray-50">
        <h6 class="font-medium text-sm mb-2">${name}</h6>
        <div class="grid grid-cols-2 gap-4 text-xs">
          <div>
            <span class="text-gray-600">Address:</span>
            <p class="font-medium mb-2">${address}</p>
            <span class="text-gray-600">Email:</span>
            <p class="font-medium mb-2">${email}</p>
            <span class="text-gray-600">Phone:</span>
            <p class="font-medium">${phone}</p>
          </div>
          <div>
            <span class="text-gray-600">ID Type:</span>
            <p class="font-medium">${identificationType.charAt(0).toUpperCase() + identificationType.slice(1)}</p>
            <span class="text-gray-600">ID Document:</span>
            <p class="font-medium ${hasIdentificationFile ? 'text-green-600' : 'text-red-600'}">
              ${hasIdentificationFile ? 'Uploaded' : 'Not Uploaded'}
            </p>
          </div>
        </div>
      </div>
    `;
  });
  
  ownersContainer.innerHTML = summaryHTML || '<p class="text-gray-500">No owners added yet</p>';
}

function updateUnitInformation() {
  // Determine unit type based on land use and selected options
  let unitType = 'N/A';
  
  // For SUA, check for the hidden unit_type field first
  const hiddenUnitType = document.querySelector('input[name="unit_type"]')?.value;
  if (hiddenUnitType) {
    unitType = hiddenUnitType;
  } else {
    // For regular sectional titling, check based on land use
    const landUse = document.getElementById('landUseDisplay').textContent;
    if (landUse.includes('Residential') || landUse.includes('Mixed')) {
      unitType = document.querySelector('input[name="residence_type"]:checked')?.value || 'N/A';
    } else if (landUse.includes('Commercial')) {
      unitType = document.querySelector('input[name="commercial_type"]:checked')?.value || 'N/A';
    } else if (landUse.includes('Industrial')) {
      unitType = document.querySelector('input[name="industrial_type"]:checked')?.value || 'N/A';
    }
  }
  
  document.getElementById('unitTypeDisplay').textContent = unitType;
  
  // Ownership type - check if ownership section exists
  let finalOwnershipType = 'N/A';
  const ownershipType = document.querySelector('input[name="ownershipType"]:checked')?.value;
  if (ownershipType) {
    const otherOwnership = document.querySelector('input[name="otherOwnership"]')?.value;
    finalOwnershipType = ownershipType === 'others' && otherOwnership ? otherOwnership : ownershipType;
  } else {
    // For SUA or when ownership section is not shown, use a default
    finalOwnershipType = 'Not Applicable';
  }
  document.getElementById('ownershipTypeDisplay').textContent = finalOwnershipType;
  
  // Unit details
  document.getElementById('blockNumberDisplay').textContent = document.querySelector('input[name="block_number"]')?.value || 'N/A';
  document.getElementById('floorNumberDisplay').textContent = document.querySelector('input[name="floor_number"]')?.value || 'N/A';
  document.getElementById('unitNumberDisplay').textContent = document.querySelector('input[name="unit_number"]')?.value || 'N/A';
  document.getElementById('unitSizeDisplay').textContent = document.querySelector('input[name="unit_size"]')?.value || 'N/A';
}

function updateAddressInformation() {
  const houseNo = document.querySelector('input[name="address_house_no"]')?.value || '';
  const streetName = document.querySelector('input[name="address_street_name"]')?.value || '';
  const district = document.querySelector('input[name="address_district"]')?.value || '';
  const lga = document.querySelector('select[name="address_lga"]')?.value || '';
  const state = document.querySelector('select[name="address_state"]')?.value || '';
  
  document.getElementById('houseNoDisplay').textContent = houseNo || 'N/A';
  document.getElementById('streetNameDisplay').textContent = streetName || 'N/A';
  document.getElementById('districtDisplay').textContent = district || 'N/A';
  document.getElementById('lgaDisplay').textContent = lga || 'N/A';
  document.getElementById('stateDisplay').textContent = state || 'N/A';
  
  // Construct complete address
  const addressParts = [houseNo, streetName, district, lga, state].filter(part => part);
  const completeAddress = addressParts.join(', ');
  document.getElementById('completeAddressDisplay').textContent = completeAddress || 'N/A';
}

function updateSharedAreasSummary() {
  const sharedAreasContainer = document.getElementById('shared-areas-summary');
  const sharedAreaCheckboxes = document.querySelectorAll('input[name="shared_areas[]"]:checked');
  const otherAreasTextarea = document.getElementById('other_areas_detail');
  
  let areasHTML = '';
  
  sharedAreaCheckboxes.forEach(checkbox => {
    areasHTML += `<div class="flex items-center"><span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span><span class="text-sm">${checkbox.value}</span></div>`;
  });
  
  if (otherAreasTextarea && otherAreasTextarea.value.trim()) {
    areasHTML += `<div class="flex items-center"><span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span><span class="text-sm">Other: ${otherAreasTextarea.value}</span></div>`;
  }
  
  sharedAreasContainer.innerHTML = areasHTML || '<p class="text-gray-500 text-sm">No shared areas selected</p>';
}

function updatePaymentInformation() {
  // Get values from form inputs - handle both text and select fields for survey fee
  let applicationFee = 0;
  let processingFee = 0;
  let surveyFee = 0;
  
  const applicationFeeInput = document.querySelector('input[name="application_fee"]');
  if (applicationFeeInput) {
    const value = applicationFeeInput.value.replace(/,/g, ''); // Remove commas from formatted numbers
    applicationFee = parseFloat(value) || 0;
  }
  
  const processingFeeInput = document.querySelector('input[name="processing_fee"]');
  if (processingFeeInput) {
    const value = processingFeeInput.value.replace(/,/g, ''); // Remove commas from formatted numbers
    processingFee = parseFloat(value) || 0;
  }
  
  // Handle survey fee which could be either input or select field with name "site_plan_fee"
  const surveyFeeInput = document.querySelector('input[name="site_plan_fee"]');
  const surveyFeeSelect = document.querySelector('select[name="site_plan_fee"]');
  
  if (surveyFeeInput) {
    const value = surveyFeeInput.value.replace(/,/g, ''); // Remove commas from formatted numbers
    surveyFee = parseFloat(value) || 0;
  } else if (surveyFeeSelect) {
    surveyFee = parseFloat(surveyFeeSelect.value) || 0;
  }
  
  // Update fee amounts
  document.getElementById('applicationFeeDisplay').textContent = '₦' + applicationFee.toLocaleString();
  document.getElementById('processingFeeDisplay').textContent = '₦' + processingFee.toLocaleString();
  document.getElementById('surveyFeeDisplay').textContent = '₦' + surveyFee.toLocaleString();
  
  const totalFee = applicationFee + processingFee + surveyFee;
  document.getElementById('totalFeeDisplay').textContent = '₦' + totalFee.toLocaleString();
  
  // Update individual payment information
  // Processing Fee payment details
  const processingFeePaymentDate = document.querySelector('input[name="processing_fee_payment_date"]')?.value;
  const processingFeeReceiptNo = document.querySelector('input[name="processing_fee_receipt_no"]')?.value;
  
  if (document.getElementById('processingFeePaymentDateDisplay')) {
    document.getElementById('processingFeePaymentDateDisplay').textContent = 
      processingFeePaymentDate ? formatDate(processingFeePaymentDate) : '-';
  }
  if (document.getElementById('processingFeeReceiptNoDisplay')) {
    document.getElementById('processingFeeReceiptNoDisplay').textContent = processingFeeReceiptNo || '-';
  }
  
  // Application Fee payment details
  const applicationFeePaymentDate = document.querySelector('input[name="application_fee_payment_date"]')?.value;
  const applicationFeeReceiptNo = document.querySelector('input[name="application_fee_receipt_no"]')?.value;
  
  if (document.getElementById('applicationFeePaymentDateDisplay')) {
    document.getElementById('applicationFeePaymentDateDisplay').textContent = 
      applicationFeePaymentDate ? formatDate(applicationFeePaymentDate) : '-';
  }
  if (document.getElementById('applicationFeeReceiptNoDisplay')) {
    document.getElementById('applicationFeeReceiptNoDisplay').textContent = applicationFeeReceiptNo || '-';
  }
  
  // Survey Fee payment details
  const surveyFeePaymentDate = document.querySelector('input[name="survey_fee_payment_date"]')?.value;
  const surveyFeeReceiptNo = document.querySelector('input[name="survey_fee_receipt_no"]')?.value;
  
  if (document.getElementById('surveyFeePaymentDateDisplay')) {
    document.getElementById('surveyFeePaymentDateDisplay').textContent = 
      surveyFeePaymentDate ? formatDate(surveyFeePaymentDate) : '-';
  }
  if (document.getElementById('surveyFeeReceiptNoDisplay')) {
    document.getElementById('surveyFeeReceiptNoDisplay').textContent = surveyFeeReceiptNo || '-';
  }
}

// Helper function to format dates consistently
function formatDate(dateString) {
  if (!dateString) return '-';
  try {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
      month: 'numeric',
      day: 'numeric',
      year: 'numeric'
    }).format(date);
  } catch (error) {
    return dateString; // Return original string if formatting fails
  }
}

function updateComments() {
  const comments = document.querySelector('textarea[name="application_comment"]')?.value;
  const commentsSection = document.getElementById('comments-section');
  
  if (comments && comments.trim()) {
    document.getElementById('commentsDisplay').textContent = comments;
    commentsSection.style.display = 'block';
  } else {
    commentsSection.style.display = 'none';
  }
}

// Update document indicators based on file uploads
function updateDocumentIndicators() {
  const documents = [
    { id: 'application_letter', indicator: 'applicationLetterIndicator', status: 'applicationLetterStatus' },
    { id: 'building_plan', indicator: 'buildingPlanIndicator', status: 'buildingPlanStatus' },
    { id: 'architectural_design', indicator: 'architecturalDesignIndicator', status: 'architecturalDesignStatus' },
    { id: 'ownership_document', indicator: 'ownershipDocumentIndicator', status: 'ownershipDocumentStatus' }
  ];
  
  documents.forEach(doc => {
    const fileInput = document.getElementById(doc.id);
    const indicator = document.getElementById(doc.indicator);
    const statusElement = document.getElementById(doc.status);
    
    if (fileInput && fileInput.files && fileInput.files.length > 0) {
      indicator.classList.remove('bg-red-500');
      indicator.classList.add('bg-green-500');
      if (statusElement) {
        statusElement.textContent = 'Uploaded';
        statusElement.classList.remove('text-red-600');
        statusElement.classList.add('text-green-600');
      }
    } else {
      indicator.classList.remove('bg-green-500');
      indicator.classList.add('bg-red-500');
      if (statusElement) {
        statusElement.textContent = 'Not Uploaded';
        statusElement.classList.remove('text-green-600');
        statusElement.classList.add('text-red-600');
      }
    }
  });
}

// Initialize form event listeners to update summary
document.addEventListener('DOMContentLoaded', function() {
  // Make functions globally available
  window.updateApplicationSummary = updateApplicationSummary;
  window.updatePaymentInformation = updatePaymentInformation;
  
  // Update summary immediately if step 4 is visible
  const step4 = document.getElementById('step4');
  if (step4 && step4.classList.contains('active-tab')) {
    updateApplicationSummary();
  }
  
  // Update summary when the "Next" button on step 3 is clicked
  const nextStep3Button = document.getElementById('nextStep3');
  if (nextStep3Button) {
    nextStep3Button.addEventListener('click', updateApplicationSummary);
  }
  
  // Add event listeners for payment fields to update summary in real-time
  const paymentFields = ['application_fee', 'processing_fee', 'site_plan_fee', 'receipt_number', 'payment_date'];
  paymentFields.forEach(fieldName => {
    const inputField = document.querySelector(`input[name="${fieldName}"]`);
    const selectField = document.querySelector(`select[name="${fieldName}"]`);
    
    if (inputField) {
      inputField.addEventListener('input', updatePaymentInformation);
      inputField.addEventListener('change', updatePaymentInformation);
    }
    
    if (selectField) {
      selectField.addEventListener('change', updatePaymentInformation);
    }
  });
  
  // Add event listeners for file uploads to update document indicators in real-time
  const fileInputs = [
    'application_letter',
    'building_plan', 
    'architectural_design',
    'ownership_document'
  ];
  
  fileInputs.forEach(inputId => {
    const fileInput = document.getElementById(inputId);
    if (fileInput) {
      fileInput.addEventListener('change', updateDocumentIndicators);
    }
  });
  
  // Initialize document indicators on page load
  updateDocumentIndicators();
  
  // Set up event listeners for form fields to update address preview
  const addressFields = ['ownerHouseNo', 'ownerStreetName', 'ownerDistrict', 'ownerLga', 'ownerState'];
  addressFields.forEach(fieldId => {
    const field = document.getElementById(fieldId);
    if (field) {
      field.addEventListener('input', function() {
        const houseNo = document.getElementById('ownerHouseNo')?.value || '';
        const streetName = document.getElementById('ownerStreetName')?.value || '';
        const district = document.getElementById('ownerDistrict')?.value || '';
        const lga = document.getElementById('ownerLga')?.value || '';
        const state = document.getElementById('ownerState')?.value || '';
        
        const parts = [houseNo, streetName, district, lga, state].filter(part => part);
        const fullAddress = parts.join(', ');
        
        const fullContactAddressEl = document.getElementById('fullContactAddress');
        const contactAddressHiddenEl = document.getElementById('contactAddressHidden');
        
        if (fullContactAddressEl) fullContactAddressEl.textContent = fullAddress;
        if (contactAddressHiddenEl) contactAddressHiddenEl.value = fullAddress;
      });
    }
  });
  
  // Add print functionality
  const printButton = document.getElementById('printApplicationSlip');
  if (printButton) {
    printButton.addEventListener('click', function() {
      // Collect all application data from the summary
      const isSUA = document.querySelector('input[name="is_sua"]')?.value === '1' || 
                   window.location.href.includes('/sua/create');
      
      const data = {
        applicationId: 'SUB-' + Math.floor(Math.random() * 100000),
        isSUA: isSUA,
        landUse: isSUA ? (document.getElementById('landUseDisplay')?.textContent || 'N/A') : 
                        ('{{ $motherApplication->land_use ?? "N/A" }}'),
        applicantType: document.getElementById('applicantTypeDisplay')?.textContent || 'N/A',
        applicantName: document.getElementById('applicantNameDisplay')?.textContent || 'N/A',
        applicantEmail: document.getElementById('emailDisplay')?.textContent || 'N/A',
        applicantPhone: document.getElementById('phoneDisplay')?.textContent || 'N/A',
        applicantAddress: document.getElementById('completeAddressDisplay')?.textContent || 'N/A',
        unitType: document.getElementById('unitTypeDisplay')?.textContent || 'N/A',
        units: document.getElementById('unitNumberDisplay')?.textContent || 'N/A',
        blocks: document.getElementById('blockNumberDisplay')?.textContent || 'N/A',
        sections: document.getElementById('floorNumberDisplay')?.textContent || 'N/A',
        unitSize: document.getElementById('unitSizeDisplay')?.textContent || 'N/A',
        fileNumber: isSUA ? 
          (document.getElementById('suaFileNumberDisplay')?.textContent || 'N/A') :
          (document.getElementById('stFileNumberDisplay')?.textContent || 'N/A'),
        primaryFileNo: document.getElementById('primaryFileNumberDisplay')?.textContent || 'N/A',
        mlsFileNo: document.getElementById('mlsFileNumberDisplay')?.textContent || 'N/A',
        propertyLocation: document.getElementById('propertyLocationDisplay')?.textContent || 'N/A',
        mainApplicationId: document.getElementById('mainIdDisplay')?.textContent || 'N/A',
        schemeNo: document.getElementById('schemeNoDisplay')?.textContent || 'N/A',
        applicationFee: document.getElementById('applicationFeeDisplay')?.textContent || 'N/A',
        processingFee: document.getElementById('processingFeeDisplay')?.textContent || 'N/A',
        surveyFee: document.getElementById('surveyFeeDisplay')?.textContent || 'N/A',
        totalFee: document.getElementById('totalFeeDisplay')?.textContent || 'N/A',
        receiptNumber: document.getElementById('receiptNumberDisplay')?.textContent || 'N/A',
        paymentDate: document.getElementById('paymentDateDisplay')?.textContent || 'N/A'
      };

      // Collect documents
      const documents = [];
      const applicationLetterStatus = document.getElementById('applicationLetterStatus')?.textContent;
      const buildingPlanStatus = document.getElementById('buildingPlanStatus')?.textContent;
      const architecturalDesignStatus = document.getElementById('architecturalDesignStatus')?.textContent;
      const ownershipDocumentStatus = document.getElementById('ownershipDocumentStatus')?.textContent;
      
      if (applicationLetterStatus === 'Uploaded') documents.push('Application Letter');
      if (buildingPlanStatus === 'Uploaded') documents.push('Building Plan');
      if (architecturalDesignStatus === 'Uploaded') documents.push('Architectural Design');
      if (ownershipDocumentStatus === 'Uploaded') documents.push('Ownership Document');
      
      data.documents = documents;

      // Build URL with parameters
      const params = new URLSearchParams();
      Object.keys(data).forEach(key => {
        if (Array.isArray(data[key])) {
          data[key].forEach(item => params.append(key + '[]', item));
        } else {
          params.append(key, data[key]);
        }
      });

      // Open print page in new window
      const printUrl = '{{ route("secondaryform.print") }}?' + params.toString();
      window.open(printUrl, '_blank', 'width=1024,height=768');
    });
  }
});
</script>
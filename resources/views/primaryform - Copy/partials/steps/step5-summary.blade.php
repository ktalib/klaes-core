{{-- Step 5: Application Summary --}}
<div class="form-section" id="step5" style="display: none;">
    <div class="mb-6">
        <div class="flex items-center mb-4">
            <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center text-sm font-bold mr-3">5</div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">Application Summary</h2>
                <p class="text-gray-600">Review your application details before submission</p>
            </div>
        </div>
    </div>

    {{-- Step Navigation --}}
    <div class="flex items-center mb-8">
        <div class="flex items-center mr-4">
            <div class="step-circle completed cursor-pointer" onclick="goToStep(1)">1</div>
        </div>
        <div class="flex items-center mr-4">
            <div class="step-circle completed cursor-pointer" onclick="goToStep(2)">2</div>
        </div>
        <div class="flex items-center mr-4">
            <div class="step-circle completed cursor-pointer" onclick="goToStep(3)">3</div>
        </div>
        <div class="flex items-center mr-4">
            <div class="step-circle completed cursor-pointer" onclick="goToStep(4)">4</div>
        </div>
        <div class="flex items-center">
            <div class="step-circle active cursor-pointer" onclick="goToStep(5)">5</div>
        </div>
        <div class="ml-4 step-status-text" data-step-indicator data-step-total="5">Step 5 of 5</div>
    </div>

    {{-- File Number Information --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6 mb-6 shadow-sm hidden">
        <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            File Number Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 ">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ST File Number</label>
                <div id="summary-np-fileno" class="p-2 bg-white border border-blue-200 rounded font-mono text-blue-900">-</div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                <div id="summary-land-use" class="p-2 bg-white border border-blue-200 rounded text-blue-900">-</div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Application Date</label>
                <div id="summary-application-date" class="p-2 bg-white border border-blue-200 rounded text-blue-900">{{ date('M d, Y') }}</div>
            </div>
        </div>
    </div>

      <div class="mb-6" id="application-summary">
        <div class="flex items-start mb-4">
          <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
          <span class="font-medium">Application Summary</span>
        </div>
        
        <div class="border border-gray-200 rounded-md p-6 mb-6">
          <div class="grid grid-cols-2 gap-6">
            <div>
              <h4 class="font-medium mb-4">Applicant Information</h4>
              <table class="w-full text-sm" id="main-owner-summary-table">
                <tr>
                  <td class="py-1 text-gray-600">Applicant Type:</td>
                  <td class="py-1 font-medium" id="summary-applicant-type">-</td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Name:</td>
                  <td class="py-1 font-medium" id="summary-name">-</td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Email:</td>
                  <td class="py-1 font-medium" id="summary-email">-</td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Phone:</td>
                  <td class="py-1 font-medium" id="summary-phone">-</td>
                </tr>
              </table>
              <div id="multiple-owners-summary" class="hidden">
                <h5 class="font-medium mt-4 mb-2">Multiple Owners</h5>
                <div id="multiple-owners-list" class="space-y-2"></div>
              </div>
            </div>
            
            <div>
              <h4 class="font-medium mb-4">Unit Information</h4>
              <table class="w-full text-sm">
                <tr>
                  <td class="py-1 text-gray-600">Type of Residence:</td>
                  <td class="py-1 font-medium" id="summary-residence-type">-</td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Block No:</td>
                  <td class="py-1 font-medium" id="summary-blocks">-</td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Section (Floor) No:</td>
                  <td class="py-1 font-medium" id="summary-sections">-</td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Unit No:</td>
                  <td class="py-1 font-medium" id="summary-units">-</td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">File Number:</td>
                  <td class="py-1 font-medium" id="summary-file-number">-</td>
                </tr>
                <tr>
                  <td class="py-1 text-gray-600">Land Use:</td>
                  <td class="py-1 font-medium">
                    @if (request()->query('landuse') === 'Commercial')
                      Commercial
                    @elseif (request()->query('landuse') === 'Residential')
                      Residential
                    @elseif (request()->query('landuse') === 'Industrial')
                      Industrial
                    @else
                      Mixed Use
                    @endif
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </div>
        
        <div class="mb-6">
          <h4 class="font-medium mb-4">Address Information</h4>
          <table class="w-full text-sm">
            <tr>
              <td class="py-1 text-gray-600 w-1/4">Scheme Number:</td>
              <td class="py-1 font-medium" id="summary-scheme-no">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600 w-1/4">House No:</td>
              <td class="py-1 font-medium" id="summary-house-no">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Street Name:</td>
              <td class="py-1 font-medium" id="summary-street-name">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">District:</td>
              <td class="py-1 font-medium" id="summary-district">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">LGA:</td>
              <td class="py-1 font-medium" id="summary-lga">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">State:</td>
              <td class="py-1 font-medium" id="summary-state">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Complete Address:</td>
              <td class="py-1 font-medium" id="summary-full-address">-</td>
            </tr>
          </table>
        </div>
        
        <div class="mb-6">
          <div class="flex items-start mb-4">
            <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
            <span class="font-medium">Payment Information</span>
          </div>
          <table class="w-full text-sm">
            <tr>
              <td class="py-1 text-gray-600 w-1/4">Application Fee:</td>
              <td class="py-1 font-medium" id="summary-application-fee">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Processing Fee:</td>
              <td class="py-1 font-medium" id="summary-processing-fee">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Site Plan Fee:</td>
              <td class="py-1 font-medium" id="summary-site-plan-fee">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600 font-medium">Total:</td>
              <td class="py-1 font-bold" id="summary-total-fee">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Receipt Number:</td>
              <td class="py-1 font-medium" id="summary-receipt-number">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Payment Date:</td>
              <td class="py-1 font-medium" id="summary-payment-date">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Date Captured:</td>
              <td class="py-1 font-medium" id="summary-date-captured">-</td>
            </tr>
          </table>
        </div>
        
        <div class="mb-6">
          <h4 class="font-medium mb-4">Property Address</h4>
          <table class="w-full text-sm">
            <tr>
              <td class="py-1 text-gray-600">House No:</td>
              <td class="py-1 font-medium" id="summary-property-house-no">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Plot No:</td>
              <td class="py-1 font-medium" id="summary-property-plot-no">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Street Name:</td>
              <td class="py-1 font-medium" id="summary-property-street-name">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">District:</td>
              <td class="py-1 font-medium" id="summary-property-district">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">LGA:</td>
              <td class="py-1 font-medium" id="summary-property-lga">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">State:</td>
              <td class="py-1 font-medium" id="summary-property-state">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">Complete Address:</td>
              <td class="py-1 font-medium" id="summary-property-full-address">-</td>
            </tr>
          </table>
        </div>
        
        <div class="mb-6">
          <h4 class="font-medium mb-4">Identification</h4>
          <table class="w-full text-sm mb-4">
            <tr>
              <td class="py-1 text-gray-600 w-1/4">ID Type:</td>
              <td class="py-1 font-medium" id="summary-id-type">-</td>
            </tr>
            <tr>
              <td class="py-1 text-gray-600">ID Document:</td>
              <td class="py-1 font-medium" id="summary-id-document">-</td>
            </tr>
          </table>
        </div>
        
        <div class="mb-6">
          <h4 class="font-medium mb-4">Uploaded Documents</h4>
          <div class="grid grid-cols-2 gap-4" id="summary-documents">
            <!-- Documents will be populated dynamically -->
          </div>
        </div>
        
        <div class="flex justify-between mt-8">
          <div class="flex space-x-4">
            <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-md" id="backStep5">Back</button>
            <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-md flex items-center" id="printApplicationSlip">
              <i data-lucide="save" class="w-4 h-4 mr-2"></i>
               Save as Draft
            </button>
          </div>
          <div class="flex items-center">
            <span class="text-sm text-gray-500 mr-4 step-status-text" data-step-indicator data-step-total="5">Step 5 of 5</span>
            <button type="button" class="px-4 py-2 bg-black text-white rounded-md" onclick="confirmSubmission()">Submit Application</button>
          </div>
        </div>
      </div>
    </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Add event listener for the back button
      const backStep5Button = document.getElementById('backStep5');
      if (backStep5Button) {
        backStep5Button.addEventListener('click', function() {
          if (typeof window.goToStep === 'function') {
            window.goToStep(4);
            return;
          }

          // Fallback in unlikely event goToStep is unavailable
          const step5 = document.getElementById('step5');
          const step4 = document.getElementById('step4');
          if (step5) {
            step5.classList.remove('active');
            step5.style.display = 'none';
            step5.style.visibility = 'hidden';
            step5.style.opacity = '0';
            step5.style.height = '0';
            step5.style.overflow = 'hidden';
          }
          if (step4) {
            step4.classList.add('active');
            step4.style.display = 'block';
            step4.style.visibility = 'visible';
            step4.style.opacity = '1';
            step4.style.height = 'auto';
            step4.style.overflow = 'visible';
          }

          if (typeof updateStepCircles === 'function') {
            updateStepCircles(4);
          }
          if (typeof updateStepText === 'function') {
            updateStepText(4);
          }
        });
      }
      
      // Initialize Print Application Slip functionality
      function initializePrintFunctionality() {
        const printButton = document.getElementById('printApplicationSlip');
        
        if (printButton) {
          printButton.addEventListener('click', function() {
            // Collect all application data from the summary
            const data = {
              applicationId: 'APP-' + Math.floor(Math.random() * 100000),
              landUse: '{{ request()->query("landuse", "Residential") }}',
              applicantType: document.getElementById('summary-applicant-type').textContent,
              applicantName: document.getElementById('summary-name').textContent,
              applicantEmail: document.getElementById('summary-email').textContent,
              applicantPhone: document.getElementById('summary-phone').textContent,
              applicantAddress: document.getElementById('summary-full-address').textContent,
              residenceType: document.getElementById('summary-residence-type').textContent,
              units: document.getElementById('summary-units').textContent,
              blocks: document.getElementById('summary-blocks').textContent,
              sections: document.getElementById('summary-sections').textContent,
              fileNumber: document.getElementById('summary-file-number').textContent,
              propertyHouseNo: document.getElementById('summary-property-house-no').textContent,
              propertyPlotNo: document.getElementById('summary-property-plot-no').textContent,
              propertyStreet: document.getElementById('summary-property-street-name').textContent,
              propertyDistrict: document.getElementById('summary-property-district').textContent,
              propertyLGA: document.getElementById('summary-property-lga').textContent,
              propertyState: document.getElementById('summary-property-state').textContent,
              propertyFullAddress: document.getElementById('summary-property-full-address').textContent,
              applicationFee: document.getElementById('summary-application-fee').textContent,
              processingFee: document.getElementById('summary-processing-fee').textContent,
              sitePlanFee: document.getElementById('summary-site-plan-fee').textContent,
              totalFee: document.getElementById('summary-total-fee').textContent,
              receiptNumber: document.getElementById('summary-receipt-number').textContent,
              paymentDate: document.getElementById('summary-payment-date').textContent
            };

            // Collect documents
            const uploadedDocs = document.getElementById('summary-documents').children;
            const documents = [];
            for (let i = 0; i < uploadedDocs.length; i++) {
              const docName = uploadedDocs[i].querySelector('span:last-child')?.textContent;
              if (docName) {
                documents.push(docName);
              }
            }
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
            const printUrl = '{{ route("primaryform.print") }}?' + params.toString();
            window.open(printUrl, '_blank', 'width=1024,height=768');
          });
        }
      }
      
      // Initialize print functionality
      initializePrintFunctionality();
    });

    // AJAX Confirmation function for final submission
    function confirmSubmission() {
      console.log('🚀 Confirm submission called');
      
      Swal.fire({
        title: 'Submit Application?',
        html: '<div style="text-align: left;"><strong>Please confirm that:</strong><br><br>' +
              '• All information provided is accurate<br>' +
              '• All required documents have been uploaded<br>' +
              '• You have reviewed the application summary<br><br>' +
              '<strong>The application will be submitted via AJAX without page reload.</strong></div>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Submit Application',
        cancelButtonText: 'Cancel',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          // Force AJAX submission ONLY - no fallback
          console.log('✅ User confirmed submission - using AJAX only');
          
          // Wait a bit for FormSubmission to be available
          setTimeout(() => {
            if (typeof window.FormSubmission !== 'undefined' && window.FormSubmission.submitForm) {
              console.log('🎯 Using AJAX form submission');
              window.FormSubmission.submitForm();
            } else if (typeof FormSubmission !== 'undefined' && FormSubmission.submitForm) {
              console.log('🎯 Using global FormSubmission object');
              FormSubmission.submitForm();
            } else {
              console.error('❌ AJAX handler not loaded');
              Swal.fire({
                title: 'Error',
                text: 'AJAX form submission handler is not loaded. Please refresh the page and try again.',
                icon: 'error'
              });
            }
          }, 100); // Small delay to ensure scripts are loaded
        }
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      // Patch: update summary for multiple owners
      function updateMultipleOwnersSummary() {
        const applicantTypeEl = document.querySelector('input[name="applicantType"]:checked');
        const applicantType = applicantTypeEl ? applicantTypeEl.value : (document.getElementById('applicantType')?.value || '');
        const mainOwnerTable = document.getElementById('main-owner-summary-table');
        const multipleOwnersDiv = document.getElementById('multiple-owners-summary');
        const multipleOwnersList = document.getElementById('multiple-owners-list');

        if (applicantType === 'multiple') {
          // Hide main owner email/phone, show multiple owners
          if (mainOwnerTable) mainOwnerTable.style.display = 'none';
          if (multipleOwnersDiv) multipleOwnersDiv.classList.remove('hidden');
          if (multipleOwnersList) {
            multipleOwnersList.innerHTML = '';
            // Collect all multiple owners from the form
            const names = document.querySelectorAll('input[name="multiple_owners_names[]"]');
            const addresses = document.querySelectorAll('textarea[name="multiple_owners_address[]"]');
            const emails = document.querySelectorAll('input[name="multiple_owners_email[]"]');
            const phones = document.querySelectorAll('input[name="multiple_owners_phone[]"]');
            const idTypes = document.querySelectorAll('input[type="radio"][name^="multiple_owners_identification_type"]');
            const idImages = document.querySelectorAll('input[name="multiple_owners_identification_image[]"]');
            // Group by index
            for (let i = 0; i < names.length; i++) {
              const ownerName = names[i]?.value || '-';
              const ownerAddress = addresses[i]?.value || '-';
              const ownerEmail = emails[i]?.value || '-';
              const ownerPhone = phones[i]?.value || '-';
              // Find checked idType for this owner
              let ownerIdType = '-';
              const idTypeRadios = document.getElementsByName(`multiple_owners_identification_type[${i}]`);
              if (idTypeRadios && idTypeRadios.length) {
                for (let r = 0; r < idTypeRadios.length; r++) {
                  if (idTypeRadios[r].checked) {
                    ownerIdType = idTypeRadios[r].value.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    break;
                  }
                }
              }
              // Get file name for ID image
              let ownerIdImage = '-';
              if (idImages[i] && idImages[i].files && idImages[i].files.length > 0) {
                ownerIdImage = idImages[i].files[0].name;
              }
              // Render owner summary
              const ownerDiv = document.createElement('div');
              ownerDiv.className = 'border border-gray-100 rounded p-2 bg-gray-50';
              ownerDiv.innerHTML = `
                <div class="font-semibold text-gray-700 mb-1">Owner ${i + 1}</div>
                <div class="text-xs"><span class="font-medium">Name:</span> ${ownerName}</div>
                <div class="text-xs"><span class="font-medium">Address:</span> ${ownerAddress}</div>
                <div class="text-xs"><span class="font-medium">Email:</span> ${ownerEmail}</div>
                <div class="text-xs"><span class="font-medium">Phone:</span> ${ownerPhone}</div>
                <div class="text-xs"><span class="font-medium">ID Type:</span> ${ownerIdType}</div>
                <div class="text-xs"><span class="font-medium">ID Document:</span> ${ownerIdImage}</div>
              `;
              multipleOwnersList.appendChild(ownerDiv);
            }
          }
        } else {
          // Show main owner, hide multiple owners
          if (mainOwnerTable) mainOwnerTable.style.display = '';
          if (multipleOwnersDiv) multipleOwnersDiv.classList.add('hidden');
        }
      }

      // Patch into summary update
      if (window.updateApplicationSummary) {
        const origUpdate = window.updateApplicationSummary;
        window.updateApplicationSummary = function() {
          origUpdate();
          updateMultipleOwnersSummary();
        }
      } else {
        updateMultipleOwnersSummary();
      }
    });
  </script>

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
          <h2 class="text-lg font-semibold mb-4">APPLICATION FOR SECTIONAL TITLING - PRIMARY APPLICATION</h2>
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
          <span>File Number: <span id="print-file-number"></span></span>
          <span>Land Use: 
            @if (request()->query('landuse') === 'Commercial')
              Commercial
            @elseif (request()->query('landuse') === 'Residential')
              Residential
            @elseif (request()->query('landuse') === 'Industrial')
              Industrial
            @else
              Mixed Use
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
              <td class="py-1 text-gray-600 w-1/2">Residence Type:</td>
              <td class="py-1 font-medium" id="print-residence-type"></td>
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
        <h4 class="font-medium mb-2 border-b border-gray-300 pb-1">Property Address</h4>
        <table class="w-full text-sm">
          <tr>
            <td class="py-1 text-gray-600 w-1/4">House No:</td>
            <td class="py-1 font-medium" id="print-property-house-no"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">Plot No:</td>
            <td class="py-1 font-medium" id="print-property-plot-no"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">Street Name:</td>
            <td class="py-1 font-medium" id="print-property-street-name"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">District:</td>
            <td class="py-1 font-medium" id="print-property-district"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">LGA:</td>
            <td class="py-1 font-medium" id="print-property-lga"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">State:</td>
            <td class="py-1 font-medium" id="print-property-state"></td>
          </tr>
          <tr>
            <td class="py-1 text-gray-600">Complete Address:</td>
            <td class="py-1 font-medium" id="print-property-full-address"></td>
          </tr>
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
            <td class="py-1 text-gray-600">Site Plan Fee:</td>
            <td class="py-1 font-medium" id="print-site-plan-fee"></td>
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

{{-- JavaScript for Summary Updates --}}
<script>
/**
 * Update the summary step with current form data
 */
function updateSummary() {
    console.log('📋 Updating application summary from inline function...');
    
    // First, try to use the main updateApplicationSummary function if it exists
    if (typeof window.updateApplicationSummary === 'function') {
        console.log('✅ Using main updateApplicationSummary function');
        window.updateApplicationSummary();
        return;
    }
    
    console.log('⚠️ Main summary function not found, using fallback...');
    
    // Get form reference
    const form = document.getElementById('primaryApplicationForm');
    if (!form) {
        console.error('❌ Form not found for summary update');
        return;
    }
    
    // Update file number information
    const npFileno = form.querySelector('[name="np_fileno"]')?.value;
    const landUse = form.querySelector('[name="land_use"]')?.value;
    
    // Try multiple field name variants for applicant type
    const applicantTypeRadio = form.querySelector('input[name="applicant_type"]:checked') ||
                               form.querySelector('input[name="applicantType"]:checked');
    const applicantType = applicantTypeRadio?.value || 
                         form.querySelector('[name="applicant_type"]')?.value || '';
    
    const summaryNpFileno = document.getElementById('summary-np-fileno');
    const summaryLandUse = document.getElementById('summary-land-use');
    
    if (summaryNpFileno) summaryNpFileno.textContent = npFileno || '-';
    if (summaryLandUse) summaryLandUse.textContent = landUse || '-';
    
    // Update legacy summary fields (existing IDs)
    const legacyApplicantType = document.getElementById('summary-applicant-type');
    const legacyName = document.getElementById('summary-name');
    const legacyEmail = document.getElementById('summary-email');
    const legacyPhone = document.getElementById('summary-phone');
    
    if (legacyApplicantType) legacyApplicantType.textContent = applicantType || '-';
    
    // Update based on applicant type
    if (applicantType === 'individual' || applicantType === 'Individual') {
        // Update individual fields - try multiple field names
        const title = form.querySelector('[name="title"]')?.value || 
                     form.querySelector('[name="applicant_title"]')?.value || '';
        const fname = form.querySelector('[name="fname"]')?.value || 
                     form.querySelector('[name="first_name"]')?.value || '';
        const mname = form.querySelector('[name="mname"]')?.value || 
                     form.querySelector('[name="middle_name"]')?.value || '';
        const lname = form.querySelector('[name="lname"]')?.value || 
                     form.querySelector('[name="surname"]')?.value || '';
        
        const fullName = [title, fname, mname, lname].filter(Boolean).join(' ');
        if (legacyName) legacyName.textContent = fullName || '-';
        
    } else if (applicantType === 'corporate' || applicantType === 'Corporate') {
        // Update corporate fields
        const corporateName = form.querySelector('[name="corporate_name"]')?.value;
        const rcNumber = form.querySelector('[name="rc_number"]')?.value;
        
        if (legacyName) legacyName.textContent = `${corporateName || '-'} (RC: ${rcNumber || '-'})`;
        
    } else if (applicantType === 'multiple' || applicantType === 'Multiple') {
        // Update multiple owners
        if (legacyName) legacyName.textContent = 'Multiple owners - see details';
    }
    
    // Update contact information - try multiple field names
    const phone = form.querySelector('[name="phone"]')?.value || 
                 form.querySelector('[name="phone_number"]')?.value || '';
    const email = form.querySelector('[name="email"]')?.value || 
                 form.querySelector('[name="owner_email"]')?.value || '';
    
    if (legacyPhone) legacyPhone.textContent = phone || '-';
    if (legacyEmail) legacyEmail.textContent = email || '-';
    
    // Update unit information fields
    const fileNumber = document.getElementById('summary-file-number');
    const blocks = document.getElementById('summary-blocks');
    const sections = document.getElementById('summary-sections');
    const units = document.getElementById('summary-units');
    const residenceType = document.getElementById('summary-residence-type');
    
    if (fileNumber) fileNumber.textContent = npFileno || '-';
    
    const blocksCount = form.querySelector('[name="blocks_count"]')?.value;
    const sectionsCount = form.querySelector('[name="sections_count"]')?.value;
    const unitsCount = form.querySelector('[name="units_count"]')?.value;
    
    if (blocks) blocks.textContent = blocksCount || '-';
    if (sections) sections.textContent = sectionsCount || '-';
    if (units) units.textContent = unitsCount || '-';
    if (residenceType) residenceType.textContent = landUse || '-';
    
    // Update property address fields
    const propertyHouseNo = form.querySelector('[name="property_house_no"]')?.value || '';
    const propertyPlotNo = form.querySelector('[name="property_plot_no"]')?.value || '';
    const propertyStreet = form.querySelector('[name="property_street_name"]')?.value || '';
    const propertyDistrict = form.querySelector('[name="property_district"]')?.value || '';
    const propertyLga = form.querySelector('[name="property_lga"]')?.value || '';
    const propertyState = form.querySelector('[name="property_state"]')?.value || '';
    
    const summaryPropertyHouseNo = document.getElementById('summary-property-house-no');
    const summaryPropertyPlotNo = document.getElementById('summary-property-plot-no');
    const summaryPropertyStreet = document.getElementById('summary-property-street-name');
    const summaryPropertyDistrict = document.getElementById('summary-property-district');
    const summaryPropertyLga = document.getElementById('summary-property-lga');
    const summaryPropertyState = document.getElementById('summary-property-state');
    const summaryPropertyFullAddress = document.getElementById('summary-property-full-address');
    
    if (summaryPropertyHouseNo) summaryPropertyHouseNo.textContent = propertyHouseNo || '-';
    if (summaryPropertyPlotNo) summaryPropertyPlotNo.textContent = propertyPlotNo || '-';
    if (summaryPropertyStreet) summaryPropertyStreet.textContent = propertyStreet || '-';
    if (summaryPropertyDistrict) summaryPropertyDistrict.textContent = propertyDistrict || '-';
    if (summaryPropertyLga) summaryPropertyLga.textContent = propertyLga || '-';
    if (summaryPropertyState) summaryPropertyState.textContent = propertyState || '-';
    
    if (summaryPropertyFullAddress) {
        const fullAddress = [propertyHouseNo, propertyPlotNo, propertyStreet, propertyDistrict, propertyLga, propertyState]
            .filter(part => part && part.trim() !== '')
            .join(', ');
        summaryPropertyFullAddress.textContent = fullAddress || '-';
    }
    
    console.log('✅ Summary updated successfully', {
        npFileno,
        landUse,
        applicantType,
        blocksCount,
        sectionsCount,
        unitsCount,
        propertyHouseNo,
        propertyPlotNo,
        phone,
        email
    });
}

// Update summary when step 5 is shown
document.addEventListener('DOMContentLoaded', function() {
    // Hook into step navigation to update summary
    const originalShowStep = window.showStep;
    if (originalShowStep) {
        window.showStep = function(stepNumber) {
            originalShowStep(stepNumber);
            if (stepNumber === 5) {
                setTimeout(updateSummary, 100); // Small delay to ensure DOM is ready
            }
        };
    }
});
</script>
<!-- Step 4: EDMS Workflow -->
<div class="form-section" id="step4">
  <div class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-gray-800">EDMS Workflow</h2>
      <button type="button" onclick="window.history.back()" class="text-gray-500 hover:text-gray-700">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    
    <div class="mb-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
          <h3 class="text-lg font-bold">Electronic Document Management System</h3>
        </div>
        <div class="flex items-center">
          <span class="text-gray-600 mr-2">Status:</span>
          <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">
            Pending Setup
          </span>
        </div>
      </div>
      <p class="text-gray-600 mt-1">EDMS workflow will be available after the application is submitted</p>
    </div>

    <div class="flex items-center mb-6">
      <div class="flex items-center mr-4">
        <div class="step-circle inactive flex items-center justify-center">1</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive flex items-center justify-center">2</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive flex items-center justify-center">3</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle active flex items-center justify-center">4</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive flex items-center justify-center">5</div>
      </div>
      <div class="flex items-center mr-4">
        <div class="step-circle inactive flex items-center justify-center">6</div>
      </div>
      
      <div class="ml-4">Step 4</div>
    </div>

    <div class="mb-6">
      <div class="text-right text-sm text-gray-500">EDMS WORKFLOW</div>
      <hr class="my-4">
      
      <!-- EDMS Information Card -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
        <div class="flex items-start">
          <div class="bg-blue-100 p-3 rounded-full mr-4">
            <i data-lucide="database" class="w-6 h-6 text-blue-600"></i>
          </div>
          <div class="flex-1">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Electronic Document Management System</h3>
            <p class="text-gray-700 mb-4">
              The EDMS workflow will be automatically initiated after your application is successfully submitted. 
              This system will guide you through three essential steps:
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Step 1: File Indexing -->
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center mb-3">
                  <div class="bg-gray-100 p-2 rounded-full mr-3">
                    <i data-lucide="folder-plus" class="w-5 h-5 text-gray-600"></i>
                  </div>
                  <h4 class="font-semibold text-gray-900">File Indexing</h4>
                </div>
                <p class="text-sm text-gray-600">
                  Create digital file index with metadata including file number, title, land use type, and property details.
                </p>
              </div>
              
              <!-- Step 2: Document Scanning -->
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center mb-3">
                  <div class="bg-gray-100 p-2 rounded-full mr-3">
                    <i data-lucide="upload" class="w-5 h-5 text-gray-600"></i>
                  </div>
                  <h4 class="font-semibold text-gray-900">Document Scanning</h4>
                </div>
                <p class="text-sm text-gray-600">
                  Upload scanned documents including supporting documents, plans, and certificates.
                </p>
              </div>
              
              <!-- Step 3: Page Typing -->
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center mb-3">
                  <div class="bg-gray-100 p-2 rounded-full mr-3">
                    <i data-lucide="type" class="w-5 h-5 text-gray-600"></i>
                  </div>
                  <h4 class="font-semibold text-gray-900">Page Typing</h4>
                </div>
                <p class="text-sm text-gray-600">
                  Classify and label each document page for proper organization and retrieval.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Next Steps Information -->
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-center">
          <i data-lucide="info" class="w-5 h-5 text-yellow-600 mr-3"></i>
          <div>
            <h4 class="font-medium text-yellow-800">What happens next?</h4>
            <p class="text-sm text-yellow-700 mt-1">
              After submitting this application, you will be redirected to the EDMS workflow where you can complete 
              the file indexing, document scanning, and page typing processes.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="flex justify-between mt-8">
      <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-md" id="prevStep4">Previous</button>
      <div class="flex items-center">
        <span class="text-sm text-gray-500 mr-4">Step 4 of 6</span>
        <button type="button" class="px-4 py-2 bg-black text-white rounded-md" id="nextStep4">Next</button>
      </div>
    </div>
  </div>
</div>
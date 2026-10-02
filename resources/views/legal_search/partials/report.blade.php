    <!-- Legal Search Report View (initially hidden) -->
    <div id="legal-search-report-view" class="hidden">
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-2xl font-bold">Legal Search Report</h2>
            <p class="text-gray-500" id="report-subtitle">Official search report for file <span id="report-file-reference"></span></p>
          </div>
            <div class="watermark">{{ $moduleConfig['watermarkText'] ?? 'FOR OFFICE USE ONLY' }}</div>   
          <div class="flex items-center gap-2 hidden-print">
            <button id="back-to-file-details-btn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back to File Details
            </button>
            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-black hover:bg-black/90">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              Print Report
            </button>
          </div>
        </div>

        <div class="space-y-6 print-div">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6">
              <!-- Report Header -->
                <div class="mb-6 border-b pb-4">
  <div class="flex flex-wrap items-center justify-between mb-4">
    <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Kano State Logo" class="h-16 w-16">
    <div class="text-center md:mx-4 flex-1">
      <h3 class="text-xl font-bold text-blue-700">KANO STATE GEOGRAPHIC INFORMATION SYSTEM</h3>
      <h4 class="text-lg font-semibold">MINISTRY OF LAND AND PHYSICAL PLANNING</h4>
      <h5 class="text-md font-medium mt-1">LEGAL SEARCH REPORT</h5>
    </div>
    <img src="{{ asset('assets/logo/logo2.jpg') }}" alt="GIS Logo" class="h-16 w-16">
  </div>
  
  <!-- Timestamp section -->
  <div class="flex flex-col sm:flex-row justify-end text-right mt-4">
    <div class="mb-2 sm:mb-0">
      <p class="text-sm font-medium" id="report-timestamp">These details are as at: </p>
    </div>
    <div class="sm:ml-4">
      <p class="text-sm text-gray-600" id="report-date">Date: </p>
      <p class="text-sm text-gray-600" id="report-time">Time: </p>
    </div>
  </div>
</div>
              <!-- Property Details Section -->
              <div class="mb-6">
                <div class="inline-block border border-black px-2 py-1 bg-gray-100 text-sm font-bold mb-2">
                  Property Details
                </div>
                <div class="border-t border-black pt-3 space-y-2 text-sm">
                  <div class="flex">
                    <div class="w-36 font-bold">File Number:</div>
                    <div id="report-file-numbers"></div>
                  </div>
                  <div class="flex">
                    <div class="w-36 font-bold">File Title:</div>
                    <div id="report-file-title"></div>
                  </div>
                  <div class="flex">
                    <div class="w-36 font-bold">Schedule:</div>
                    <div>Kano</div>
                  </div>
                  <div class="flex">
                    <div class="w-36 font-bold">Plot Number:</div>
                    <div id="report-plot-number"></div>
                  </div>
                  <div class="flex">
                    <div class="w-36 font-bold">Plan Number:</div>
                    <div id="report-plan-number"></div>
                  </div>
                  <div class="flex">
                    <div class="w-36 font-bold">Plot Description:</div>
                    <div id="report-plot-description"></div>
                  </div>
                </div>
              </div>

              <!-- File History Section -->
              <div class="mb-6">
                <div class="inline-block border border-black px-2 py-1 bg-gray-100 text-sm font-bold mb-2">
                  File History
                </div>
                <div class="border-t border-black pt-3">
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                      <thead>
                        <tr>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">S/N</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Party 1</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Party 2</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Party 3</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Party 4</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Instrument/Transaction Type</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Date/Time</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Reg Particulars</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Size</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Caveat</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Comments</th>
                        </tr>
                      </thead>
                      <tbody id="report-transactions-table">
                        <!-- Will be populated dynamically -->
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Report Footer -->
              <div class="mt-8 text-sm">
                <p class="font-bold" id="report-timestamp"></p>

                <div class="mt-6 flex justify-between items-start">
                  <div>
                    <p class="font-bold">Director Deeds</p>
                    <p class="mt-2 text-sm" style="border-bottom: 1px dotted #000; width: 300px; display: inline-block;">Name: {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</p>
                  </div>

                  <div class="text-right">
                    <!-- QR Code -->
                    <img id="report-qr-code" src="/placeholder.svg" alt="QR Code with File Details" width="80" height="80">
                    <div class="text-center text-xs mt-1">Scan for file verification</div>
                  </div>
                </div>

                <div class="mt-8 border-t pt-4 text-center">
                  <p class="text-xs text-muted-foreground">
                    Disclaimer: This Search Report does not represent consent to any transaction and is without prejudice to subsequent disclosures.
                  </p>
                  <p class="text-xs text-muted-foreground mt-2">For enquiries, please call +234 (0) 8023456789</p>
                  <p class="text-xs text-muted-foreground mt-1">
                    KANO STATE GEOGRAPHIC INFORMATION SYSTEM, Plot P/123, Secretariat Kano, Kano State
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    <!-- Reports View (initially hidden) -->
    <div id="reports-view" class="hidden space-y-6">
      <!-- Will be populated dynamically -->
    </div>

@extends('layouts.app')
@section('page-title')
    {{ __('On-Premise - Pay-per-Search') }}
@endsection

 
@section('content')
@include('onpremise.style') 
  <!-- Include Print.js library for enhanced printing -->
  <script src="https://printjs-4de6.kxcdn.com/print.min.js"></script>
  <link rel="stylesheet" type="text/css" href="https://printjs-4de6.kxcdn.com/print.min.css">
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <div class="p-6">
             
  
  



  <!-- Main Content -->
  <main class="flex-1 p-6">
    <div id="dashboard-view" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Pay-per-Search -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-6">
            <div class="flex items-center gap-2 mb-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <h3 class="text-lg font-semibold">Pay-per-Search</h3>
            </div>
            <p class="text-sm text-gray-500 mb-4">Find legal records for pay-per-search purposes</p>
            <p class="text-sm text-gray-500 mb-4">
              Conduct legal searches for pay-per-search purposes with extended access to records.
            </p>
            <button id="search-records-btn" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-black hover:bg-black/90">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z" />
              </svg>
              Search Records
            </button>
          </div>
        </div>

         <!-- Card 2: Recent Searches -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-6">
            <div class="flex items-center gap-2 mb-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <h3 class="text-lg font-semibold">Recent Searches</h3>
            </div>
            <p class="text-sm text-gray-500 mb-4">View your recent legal searches</p>
            <p class="text-sm text-gray-500 mb-4">
              Access your recent legal searches to quickly return to previously viewed records.
            </p>
            <a href="{{route('onpremise.reports')}}" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              View Reports
            </a>
          </div>
        </div>

        <!-- Card 3: Online Portal -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-6">
            <div class="flex items-center gap-2 mb-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
              </svg>
              <h3 class="text-lg font-semibold">Online Portal</h3>
            </div>
            <p class="text-sm text-gray-500 mb-4">Access the online legal search portal</p>
            <p class="text-sm text-gray-500 mb-4">
              Switch to the online portal for remote access to legal search services.
            </p>
            <a href="http://search.klas.com.ng/" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" target="_blank">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
              Go to Online Portal
            </a>
          </div>
        </div>
      </div>

      <!-- Monthly Trends Chart -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
          <div class="flex items-center gap-2 mb-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            <h3 class="text-lg font-semibold">Search Trends</h3>
          </div>
          <p class="text-sm text-gray-500 mb-4">Pay-per-Search volume over the past 12 months</p>
          <div class="h-[350px]">
            <canvas id="searchTrendsChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Additional statistics for Pay-per-Search -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Search Statistics -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-1">Search Statistics</h3>
            <p class="text-sm text-gray-500 mb-4">Key metrics for Pay-per-Searches</p>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Total Searches (This Month)</span>
                <span class="font-bold">24</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Average Search Time</span>
                <span class="font-bold">3.2 minutes</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Success Rate</span>
                <span class="font-bold">92%</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Most Common Search Type</span>
                <span class="font-bold">File Number</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-1">Recent Activity</h3>
            <p class="text-sm text-gray-500 mb-4">Latest Pay-per-Search activities</p>
            <div class="space-y-4">
              <div class="flex justify-between items-center border-b pb-2">
                <div>
                  <p class="font-medium">File Search #2024</p>
                  <p class="text-sm text-gray-500">Just now</p>
                </div>
                <span class="px-2 py-1 text-xs rounded-full bg-gray-100">Completed</span>
              </div>
              <div class="flex justify-between items-center border-b pb-2">
                <div>
                  <p class="font-medium">File Search #2025</p>
                  <p class="text-sm text-gray-500">2 hours ago</p>
                </div>
                <span class="px-2 py-1 text-xs rounded-full bg-gray-100">Completed</span>
              </div>
              <div class="flex justify-between items-center border-b pb-2">
                <div>
                  <p class="font-medium">File Search #2026</p>
                  <p class="text-sm text-gray-500">Yesterday</p>
                </div>
                <span class="px-2 py-1 text-xs rounded-full bg-gray-100">Completed</span>
              </div>
              <div class="flex justify-between items-center">
                <div>
                  <p class="font-medium">File Search #2027</p>
                  <p class="text-sm text-gray-500">3 days ago</p>
                </div>
                <span class="px-2 py-1 text-xs rounded-full bg-gray-100">Completed</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- File History View (initially hidden) -->
    <div id="file-history-view" class="hidden">
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-2xl font-bold">File Details</h2>
            <p class="text-gray-500" id="file-details-subtitle">Viewing legal search results for file <span id="file-reference"></span></p>
          </div>
          <div class="flex items-center gap-2">
            <button id="back-to-dashboard-btn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back to Dashboard
            </button>
            <button id="new-search-from-details-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-black hover:bg-black/90">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              New Search
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - File Information -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-6">
          <h3 class="text-lg font-semibold mb-4">File Information</h3>
          <p class="text-sm text-gray-500 mb-4">Details about the selected file</p>
          
          <div class="space-y-4">
            <div class="form-row">
              <span class="form-label">File Number (MLSF):</span>
              <span class="form-value" id="file-number-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">KANGIS File Number:</span>
              <span class="form-value" id="kangis-file-number-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">New KANGIS File Number:</span>
              <span class="form-value" id="new-kangis-file-number-value"></span>
            </div>
            
            <div class="form-row">
              <span class="form-label">Current Guarantor:</span>
              <span class="form-value" id="current-guarantor-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">Current Guarantee:</span>
              <span class="form-value" id="current-guarantee-value"></span>
            </div>
            
            <div class="form-row">
              <span class="form-label">LGA:</span>
              <span class="form-value" id="lga-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">District:</span>
              <span class="form-value" id="district-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">Property Type:</span>
              <span class="form-value" id="property-type-value"></span>
            </div>
            
            <div class="form-row">
              <span class="form-label">Last Transaction:</span>
              <span class="form-value" id="last-transaction-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">Status:</span>
              <span class="form-value flex justify-end items-center" id="status-value">
                <span class="status-indicator bg-green-500"></span>
                Active
              </span>
            </div>
          </div>
        </div>

        <!-- Right Column - Transaction History -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden lg:col-span-2">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-2">Transaction History</h3>
            <p class="text-sm text-gray-500 mb-4">Historical transactions associated with this file</p>
            <div class="mt-6 flex justify-end">
              <button id="view-detailed-records-btn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                View Detailed Records
              </button>
            </div>
            <div class="tabs border-b mb-6">
              <button class="tab active" data-tab="property-history">Property History</button>
              <button class="tab" data-tab="instrument-registration">Instrument Registration</button>
              <button class="tab" data-tab="cofo">CofO</button>
            </div>

            <div id="property-history-tab" class="tab-content active">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Transaction Type</th>
                      <th>Grantor/Authority</th>
                      <th>Grantee/Recipient</th>
                      <th>Registration Particulars</th>
                      <th>Size</th>
                      <th>Caveat</th>
                      <th>Comments</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="property-history-table">
                    <!-- Will be populated dynamically -->
                  </tbody>
                </table>
              </div>
             
            </div>

            <div id="instrument-registration-tab" class="tab-content">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr>
                      <th>Registration Date</th>
                      <th>Instrument Type</th>
                      <th>Registration Particulars.</th>
                      <th>Parties</th>
                      <th>Registered By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="instrument-registration-table">
                    <!-- Will be populated dynamically -->
                  </tbody>
                </table>
              </div>
            </div>

            <div id="cofo-tab" class="tab-content">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr>
                      <th>Registration Particulars</th>
                      <th>Issue Date</th>
                      <th>Holder Name</th>
                      <th>Land Use</th>
                      <th>Term</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="cofo-table">
                    <!-- Will be populated dynamically -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Legal Search Report View (initially hidden) -->
    <div id="legal-search-report-view" class="hidden">
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-2xl font-bold">Legal Search Report</h2>
            <p class="text-gray-500" id="report-subtitle">Pay-per-Search report for file <span id="report-file-reference"></span></p>
          </div>
            <div class="watermark">FOR OFFICE USE ONLY</div>   
          <div class="flex items-center gap-2 hidden-print">
            <button id="back-to-file-details-btn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back to File Details
            </button>
            <button id="print-report-btn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
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
  
  <!-- Timestamp section - now properly responsive -->
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

              <!-- Transaction History Section -->
              <div class="mb-6">
                <div class="inline-block border border-black px-2 py-1 bg-gray-100 text-sm font-bold mb-2">
                  Transaction History
                </div>
                <div class="border-t border-black pt-3">
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                      <thead>
                        <tr>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">S/N</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Grantor</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Grantee</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Transaction Type</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Date/Time</th>
                          <th class="border border-gray-300 px-3 py-2 text-left font-bold bg-gray-200">Registration Particulars</th>
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
                    {{-- <p class="font-medium">Yours Faithfully,</p> --}}
                    {{-- <div class="h-12 mt-2 border-2 border-blue-900 p-1 w-48 transform -rotate-12">
                      <svg width="200" height="45" viewBox="0 0 200 45" xmlns="http://www.w3.org/2000/svg">
                        <path
                          d="M10,35 C30,5 50,40 70,15 C90,30 110,10 130,25 C150,15 170,30 190,20"
                          stroke="#000080"
                          fill="none"
                          stroke-width="2"
                        />
                      </svg>
                    </div> --}}
                     {{-- {{ auth()->user()->first_name }} {{ auth()->user()->first_name }} --}}
                    <p class="mt-2">Generated by: {{ auth()->user()->first_name }}</p>
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
  </main>

  <!-- Search Modal (initially hidden) -->
  <div id="search-modal" class="hidden modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Search Land Records</h2>
      </div>

      <div class="flex flex-col h-full overflow-hidden">
        <!-- Search Section -->
        <div class="search-section">
          <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-medium">Search Filters</h3>
            <button id="toggle-filters-btn" class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md">
              Collapse Filters
            </button>
          </div>

          <div id="filters-container" class="flex flex-wrap items-center gap-2 mb-4">
            <!-- File Number Filter -->
            <div class="flex items-center gap-2 mb-2">
              <span class="badge badge-outline">File Number</span>
              <input type="text" id="fileNumber" placeholder="Enter file number" class="flex-grow px-3 py-2 border border-gray-300 rounded-md">
              <button class="h-8 w-8 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Guarantor Name Filter -->
            <div class="flex items-center gap-2 mb-2">
              <span class="badge badge-outline">Guarantor Name</span>
              <input type="text" id="guarantorName" placeholder="Enter guarantor name" class="flex-grow px-3 py-2 border border-gray-300 rounded-md">
              <button class="h-8 w-8 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Guarantee Name Filter -->
            <div class="flex items-center gap-2 mb-2">
              <span class="badge badge-outline">Guarantee Name</span>
              <input type="text" id="guaranteeName" placeholder="Enter guarantee name" class="flex-grow px-3 py-2 border border-gray-300 rounded-md">
              <button class="h-8 w-8 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Add Filter Button -->
            <div class="relative" id="filter-dropdown-container">
              <button id="add-filter-btn" class="inline-flex items-center gap-1 px-3 py-1 text-sm bg-white border border-gray-300 rounded-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Filter
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Filter Dropdown (initially hidden) -->
              <div id="filter-dropdown" class="hidden absolute top-full left-0 mt-1 z-50 w-64 bg-white rounded-md shadow-lg border border-gray-200">
                <div class="p-2 max-h-[200px] overflow-y-auto">
                  <button class="w-full text-left px-3 py-1 text-sm hover:bg-gray-100 rounded-md" data-filter="lga">LGA</button>
                  <button class="w-full text-left px-3 py-1 text-sm hover:bg-gray-100 rounded-md" data-filter="district">District</button>
                  <button class="w-full text-left px-3 py-1 text-sm hover:bg-gray-100 rounded-md" data-filter="location">Location</button>
                  <button class="w-full text-left px-3 py-1 text-sm hover:bg-gray-100 rounded-md" data-filter="plotNumber">Plot Number</button>
                  <button class="w-full text-left px-3 py-1 text-sm hover:bg-gray-100 rounded-md" data-filter="planNumber">Plan Number</button>
                  <button class="w-full text-left px-3 py-1 text-sm hover:bg-gray-100 rounded-md" data-filter="size">Size</button>
                  <button class="w-full text-left px-3 py-1 text-sm hover:bg-gray-100 rounded-md" data-filter="caveat">Caveat</button>
                </div>
              </div>
            </div>

            <!-- Reset Button -->
            <button id="reset-search-btn" class="ml-auto inline-flex items-center gap-1 px-3 py-1 text-sm bg-white border border-gray-300 rounded-md">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Reset
            </button>
          </div>

          <!-- Collapsed Filters (initially hidden) -->
          <div id="collapsed-filters" class="hidden flex items-center gap-2 mb-2">
            <div class="text-sm text-gray-500">
              Filtered by: <span id="active-filters-summary">File Number</span>
            </div>
            <button id="reset-search-collapsed-btn" class="ml-auto inline-flex items-center gap-1 px-3 py-1 text-sm bg-white border border-gray-300 rounded-md">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Reset
            </button>
          </div>

          <!-- View mode toggle -->
          <div class="flex justify-between items-center">
            <div class="text-sm text-gray-500"><span id="results-count">0</span> results found</div>
            <div class="tabs">
              <button class="tab active" data-view="table">Table View</button>
              <button class="tab" data-view="cards">Card View</button>
            </div>
          </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
          <!-- Loading Spinner (initially hidden) -->
          <div id="search-loading" class="hidden flex justify-center items-center py-8">
            <div class="spinner"></div>
          </div>

          <!-- No Results Message (initially hidden) -->
          <div id="no-results-message" class="hidden text-center py-8">
            <p class="text-gray-500">No results found. Please try a different search.</p>
          </div>

          <!-- Table View Results (initially hidden) -->
          <div id="table-results" class="hidden border rounded-md overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full min-w-[1000px]">
                <thead class="bg-gray-100">
                  <tr>
                    <th>ST Unit FileNo</th>
                    <th>New FileNo</th>
                    <th>MLPP File No</th>
                    <th>KANGIS File No</th>
                    <th>New KANGIS</th>
                    <th>Guarantor</th>
                    <th>Guarantee</th>
                    <th>LGA</th>
                    <th>Location</th>
                    <th>Plot No</th>
                    <th>Transaction Type</th>
                    <th>Size</th>
                    <th>Caveat</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="table-results-body">
                  <!-- Will be populated dynamically -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Card View Results (initially hidden) -->
          <div id="card-results" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Will be populated dynamically -->
          </div>

          <!-- File Details View (initially hidden) -->
          <div id="file-details-view" class="hidden space-y-6">
            <!-- Will be populated dynamically -->
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog (initially hidden) -->
  <div id="delete-confirm-dialog" class="hidden modal-overlay">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full">
      <h3 class="text-lg font-semibold mb-2">Confirm Deletion</h3>
      <p class="text-gray-500 mb-4">Are you sure you want to delete this record? This action cannot be undone.</p>
      <div class="flex justify-end gap-2">
        <button id="cancel-delete-btn" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium">Cancel</button>
        <button id="confirm-delete-btn" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">Delete</button>
      </div>
    </div>
  </div>

  <!-- JavaScript -->



        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>
   
@include('onpremise.js')
@endsection



<script>
// Enhanced landscape printing function
function printLandscapeReport() {
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=1200,height=800');
    
    // Get the print content
    const printContent = document.querySelector('.print-div').innerHTML;
    
    // Landscape-specific styles
    const landscapeStyles = 
        <style>
            @media print {
                @page {
                    size: A4 landscape !important;
                    margin: 8mm 12mm !important;
                    orientation: landscape !important;
                }
                
                html, body {
                    width: 297mm !important;
                    height: 210mm !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                
                .print-div {
                    width: 100% !important;
                    max-width: 297mm !important;
                    min-height: 210mm !important;
                }
                
                .print-div table {
                    width: 100% !important;
                    font-size: 8pt !important;
                }
                
                .print-div th,
                .print-div td {
                    padding: 2px 3px !important;
                    font-size: 7pt !important;
                    line-height: 1.1 !important;
                }
            }
        </style>
    ;
    
    // Write the complete HTML with landscape styles
    printWindow.document.write(
        <!DOCTYPE html>
        <html>
        <head>
            <title>Legal Search Report - Landscape</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
             + landscapeStyles + 
        </head>
        <body>
            <div class="print-div">
                 + printContent + 
            </div>
        </body>
        </html>
    );
    
    printWindow.document.close();
    
    // Wait for content to load then print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    };
}

// Override the existing print button click
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.getElementById('print-report-btn');
    if (printBtn) {
        printBtn.onclick = function(e) {
            e.preventDefault();
            printLandscapeReport();
        };
    }
});
</script>





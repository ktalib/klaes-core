<div id="dashboard-view" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Official Search -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-6">
            <div class="flex items-center gap-2 mb-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <h3 class="text-lg font-semibold">Official Search</h3>
            </div>
            <p class="text-sm text-gray-500 mb-4">Find legal records for Official purposes</p>
            <p class="text-sm text-gray-500 mb-4">
              Conduct legal searches for Official purposes with extended access to records.
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
            <a href="{{route('legalsearchreports.index')}}" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
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
          <p class="text-sm text-gray-500 mb-4">Official search volume over the past 12 weeks</p>
          <div class="h-[350px]">
            <canvas id="searchTrendsChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Additional statistics for Official Search -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Search Statistics -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-1">Search Statistics</h3>
            <p class="text-sm text-gray-500 mb-4">Key metrics for official searches</p>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Total Searches (This Month)</span>
                <span class="font-bold" id="stat-total-searches">-</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Printed Reports (This Month)</span>
                <span class="font-bold" id="stat-printed-reports">-</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Success Rate</span>
                <span class="font-bold" id="stat-success-rate">-</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium">Most Common Search Type</span>
                <span class="font-bold" id="stat-common-type">-</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-1">Recent Activity</h3>
            <p class="text-sm text-gray-500 mb-4">Latest official search activities</p>
            <div class="space-y-4" id="recent-activity-list">
              <p class="text-sm text-gray-500">Loading...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

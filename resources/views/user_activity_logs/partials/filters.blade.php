<!-- Filters Card -->
<div class="bg-white shadow-lg rounded-2xl p-6 mb-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
    <!-- Card Header -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-lg bg-blue-100">
                <i class="fas fa-filter text-blue-600 text-lg"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900">Search & Filter</h3>
                <p class="text-xs text-gray-500 mt-0.5">Narrow down results with advanced filters</p>
            </div>
        </div>
    </div>

    <!-- Filters Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
        <!-- User Filter -->
        <div>
            <label for="user_filter" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">User</label>
            <select id="user_filter" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all duration-200 hover:border-gray-400">
                <option value="">All Users</option>
            </select>
        </div>

        <!-- Status Filter -->
        <div>
            <label for="status_filter" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Status</label>
            <select id="status_filter" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all duration-200 hover:border-gray-400">
                <option value="">All Status</option>
                <option value="online">🟢 Online</option>
                <option value="offline">⚫ Offline</option>
            </select>
        </div>

        <!-- Device Filter -->
        <div>
            <label for="device_filter" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Device</label>
            <select id="device_filter" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all duration-200 hover:border-gray-400">
                <option value="">All Devices</option>
                <option value="desktop">💻 Desktop</option>
                <option value="mobile">📱 Mobile</option>
                <option value="tablet">📱 Tablet</option>
            </select>
        </div>

        <!-- Browser Filter -->
        <div>
            <label for="browser_filter" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Browser</label>
            <select id="browser_filter" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all duration-200 hover:border-gray-400">
                <option value="">All Browsers</option>
                <option value="Chrome">Chrome</option>
                <option value="Firefox">Firefox</option>
                <option value="Safari">Safari</option>
                <option value="Edge">Edge</option>
            </select>
        </div>

        <!-- From Date -->
        <div>
            <label for="date_from" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">From Date</label>
            <input type="date" id="date_from" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all duration-200 hover:border-gray-400">
        </div>

        <!-- To Date -->
        <div>
            <label for="date_to" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">To Date</label>
            <input type="date" id="date_to" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition-all duration-200 hover:border-gray-400">
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="mt-5 flex flex-wrap gap-3">
        <button onclick="applyFilters()" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg shadow-md hover:shadow-lg font-semibold text-sm transition-all duration-200 transform hover:scale-105 active:scale-95">
            <i class="fas fa-search mr-2"></i>
            Apply Filters
        </button>
        <button onclick="clearFilters()" class="inline-flex items-center px-5 py-2.5 bg-gray-100 text-gray-700 border border-gray-300 rounded-lg shadow-sm hover:shadow-md hover:bg-gray-50 font-semibold text-sm transition-all duration-200">
            <i class="fas fa-redo mr-2"></i>
            Reset
        </button>
    </div>
</div>

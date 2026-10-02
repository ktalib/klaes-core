<div class="file-tracker-dashboard">
    <!-- Page Header -->
    <header class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $PageTitle ?? 'File Tracker Dashboard' }}</h1>
                <p class="text-gray-600 mt-1">Monitor all files in transit across the organization</p>
            </div>
            <div class="flex items-center gap-2">
                <button id="createFileBtn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i data-lucide="plus" class="h-4 w-4 mr-2"></i>
                    Create File Tracker
                </button>
                <button id="refreshBtn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                    Refresh
                </button>
            </div>
        </div>
    </header>

    <!-- Dashboard Content -->
    <div class="p-6">
        <!-- Dashboard Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i data-lucide="file-text" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Files</p>
                        <h3 class="text-2xl font-bold text-gray-900" id="total-files">0</h3>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-gray-500">
                    <i data-lucide="trending-up" class="h-4 w-4 text-green-500 mr-1"></i>
                    <span>12% increase from last week</span>
                </div>
            </div>

            <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i data-lucide="activity" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active Files</p>
                        <h3 class="text-2xl font-bold text-gray-900" id="active-files">0</h3>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-gray-500">
                    <i data-lucide="clock" class="h-4 w-4 text-blue-500 mr-1"></i>
                    <span>Currently being processed</span>
                </div>
            </div>

            <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                        <i data-lucide="alert-triangle" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">High Priority</p>
                        <h3 class="text-2xl font-bold text-gray-900" id="high-priority">0</h3>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-gray-500">
                    <i data-lucide="alert-circle" class="h-4 w-4 text-red-500 mr-1"></i>
                    <span>Requires immediate attention</span>
                </div>
            </div>

            <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                        <i data-lucide="building" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Offices Involved</p>
                        <h3 class="text-2xl font-bold text-gray-900" id="offices-involved">0</h3>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center text-sm text-gray-500">
                        <i data-lucide="map-pin" class="h-4 w-4 text-purple-500 mr-1"></i>
                        <span>Across all departments</span>
                    </div>
                    <button id="toggleChartsBtn" class="toggle-charts-btn inline-flex items-center px-3 py-1.5 border border-purple-300 rounded-md text-sm font-medium text-purple-700 bg-purple-50 hover:bg-purple-100">
                        <i data-lucide="bar-chart-3" class="h-4 w-4 mr-1"></i>
                        <span>Show Charts</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Charts Section (Hidden by Default) -->
        <div id="charts-section" class="mb-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Priority Distribution Chart -->
                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Priority Distribution</h3>
                        <div class="flex items-center space-x-2">
                            <button class="priority-filter-btn px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700" data-priority="ALL">All</button>
                            <button class="priority-filter-btn px-3 py-1 text-xs font-medium rounded-full text-gray-500 hover:bg-gray-100" data-priority="HIGH">High</button>
                            <button class="priority-filter-btn px-3 py-1 text-xs font-medium rounded-full text-gray-500 hover:bg-gray-100" data-priority="MEDIUM">Medium</button>
                            <button class="priority-filter-btn px-3 py-1 text-xs font-medium rounded-full text-gray-500 hover:bg-gray-100" data-priority="LOW">Low</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="priority-chart"></canvas>
                    </div>
                </div>

                <!-- Office Distribution Chart -->
                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Office Distribution</h3>
                        <div class="flex items-center space-x-2">
                            <button class="office-filter-btn px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700" data-office="ALL">All</button>
                            <button class="office-filter-btn px-3 py-1 text-xs font-medium rounded-full text-gray-500 hover:bg-gray-100" data-office="TOP5">Top 5</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="office-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
                        </div>
                        <input type="text" id="search-input" placeholder="Search files by name, number, or tracking ID..." class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <select id="priority-filter" class="block w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="ALL">All Priorities</option>
                        <option value="HIGH">High Priority</option>
                        <option value="MEDIUM">Medium Priority</option>
                        <option value="LOW">Low Priority</option>
                    </select>
                    <select id="office-filter" class="block w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="ALL">All Offices</option>
                        <option value="OFF-001">Reception (RCP)</option>
                        <option value="OFF-002">Customer Care (CCU)</option>
                        <option value="OFF-003">Document Verification (DVF)</option>
                        <option value="OFF-004">Survey Department (SUR)</option>
                        <option value="OFF-005">Legal Department (LEG)</option>
                        <option value="OFF-006">Planning Department (PLN)</option>
                        <option value="OFF-007">Director's Office (DIR)</option>
                        <option value="OFF-008">Certificate Issuance (CRT)</option>
                        <option value="OFF-009">Archive (ARC)</option>
                        <option value="OFF-010">Finance Department (FIN)</option>
                        <option value="OFF-011">IT Department (ITD)</option>
                        <option value="OFF-012">Registry (REG)</option>
                    </select>
                    <button id="clear-filters" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i data-lucide="x" class="h-4 w-4 mr-1"></i>
                        Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Files Table -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <i data-lucide="files" class="h-5 w-5"></i>
                    Files in Transit
                </h3>
                <p class="text-sm text-gray-600 mt-1">All files currently being tracked across offices</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Office</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time in Office</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="files-table-body" class="bg-white divide-y divide-gray-200">
                        <!-- Files will be dynamically added here -->
                    </tbody>
                </table>
            </div>
            <div id="no-results" class="hidden p-12 text-center">
                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i data-lucide="file-x" class="h-12 w-12 text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No files found</h3>
                <p class="text-gray-500">Try adjusting your search or filter to find what you're looking for.</p>
            </div>
        </div>
    </div>

    <!-- View Details Dialog -->
    <div id="details-dialog" class="dialog-overlay">
        <div class="dialog-content details-dialog">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">File Tracker Details</h2>
                <button id="close-details" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>
            <div id="details-content">
                <!-- Details content will be dynamically generated -->
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button id="close-details-btn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Close
                </button>
                <button id="print-details-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                    Print Details
                </button>
            </div>
        </div>
    </div>

</div>

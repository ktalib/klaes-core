<!-- Activity Logs Card -->
<div class="bg-white shadow-lg rounded-2xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
    <!-- Card Header with Gradient -->
    <div class="px-6 py-5 bg-gradient-to-r from-blue-600 to-blue-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-lg bg-white bg-opacity-20">
                    <i class="fas fa-table text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white flex items-center">Activity History</h3>
                    <p class="text-blue-100 text-sm mt-0.5">Complete record of all user login sessions</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <button onclick="bulkDelete()" id="bulk-delete-btn" class="hidden inline-flex items-center px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg shadow-md hover:shadow-lg font-semibold text-sm transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-trash mr-2"></i>
                    Delete Selected
                </button>
                <span class="text-sm font-bold text-white bg-white bg-opacity-20 px-3 py-1 rounded-full" id="selected-count" style="display: none;"></span>
            </div>
        </div>
    </div>

    <!-- Table Container with Horizontal Scroll -->
    <div class="overflow-x-auto">
        <table id="activity-logs-table" class="w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 sticky top-0 z-10 border-b-2 border-gray-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-user text-blue-600"></i>
                            User
                        </div>
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-globe text-green-600"></i>
                            IP Address
                        </div>
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-mobile-alt text-purple-600"></i>
                            Device Info
                        </div>
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-sign-in-alt text-emerald-600"></i>
                            Login Time
                        </div>
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-sign-out-alt text-rose-600"></i>
                            Logout Time
                        </div>
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-hourglass-half text-orange-600"></i>
                            Duration
                        </div>
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-circle text-cyan-600"></i>
                            Status
                        </div>
                    </th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-900 uppercase tracking-wider">
                        <i class="fas fa-cog"></i> Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <!-- Data will be populated by DataTables -->
            </tbody>
        </table>
    </div>

    <!-- Empty State Message -->
    <div id="empty-state" class="hidden px-6 py-16 text-center bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="mb-4">
            <i class="fas fa-inbox text-gray-300 text-6xl mb-4 block"></i>
        </div>
        <p class="text-gray-600 font-semibold text-lg">No activity logs found</p>
        <p class="text-gray-400 text-sm mt-2 max-w-md mx-auto">Activity logs will appear here as users interact with the system. Apply filters or refresh to see more data.</p>
        <button onclick="updateStatistics(); activityTable.ajax.reload();" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 text-sm font-medium">
            <i class="fas fa-sync-alt mr-2"></i>
            Refresh Data
        </button>
    </div>
</div>

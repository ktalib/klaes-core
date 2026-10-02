<!-- Activity Details Modal -->
<div id="activity-details-modal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">Activity Details</h3>
            <button onclick="closeActivityModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="activity-details-content">
            <!-- Content will be populated via AJAX -->
        </div>
    </div>
</div>

<!-- Cleanup Modal -->
<div id="cleanup-modal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">Cleanup Old Logs</h3>
            <button onclick="closeCleanupModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-4">
            <label for="cleanup-days" class="block text-sm font-medium text-gray-700 mb-2">Delete logs older than:</label>
            <select id="cleanup-days" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="30">30 days</option>
                <option value="60">60 days</option>
                <option value="90">90 days</option>
                <option value="180">180 days</option>
                <option value="365">1 year</option>
            </select>
        </div>
        <div class="flex justify-end space-x-3">
            <button onclick="closeCleanupModal()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button onclick="performCleanup()" class="px-4 py-2 bg-red-600 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-red-700">
                Delete Logs
            </button>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div id="settings-modal" class="modal-overlay fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">Activity Logs Settings</h3>
            <button onclick="closeSettingsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="space-y-6">
            <div>
                <h4 class="text-md font-medium text-gray-900 mb-3">Data Retention</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-gray-700">Automatic cleanup interval</label>
                        <select id="cleanup-interval" class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-gray-700">Keep logs for</label>
                        <select id="retention-days" class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                            <option value="90">90 days</option>
                            <option value="180">180 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-md font-medium text-gray-900 mb-3">Display Options</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-gray-700">Auto-refresh interval (seconds)</label>
                        <input type="number" id="refresh-interval" value="30" min="10" max="300" class="w-20 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-gray-700">Records per page</label>
                        <select id="records-per-page" class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
            <button onclick="closeSettingsModal()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button onclick="saveSettings()" class="px-4 py-2 bg-blue-600 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-blue-700">
                Save Settings
            </button>
        </div>
    </div>
</div>

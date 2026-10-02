<!-- Final Conveyance Modal -->
<div id="finalConveyanceModal" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Generate Final Conveyance</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeFinalConveyanceModal()">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6" id="modalContent">
            <!-- Loading State -->
            <div id="loadingState" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <p class="mt-2 text-gray-600">Loading application information...</p>
            </div>

            <!-- Tabs -->
            <div id="tabsContainer" class="hidden">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="-mb-px flex space-x-8">
                        <button onclick="switchTab('generate')" id="generateTab" class="py-2 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600 whitespace-nowrap">
                            Generate
                        </button>
                        <button onclick="switchTab('buyers')" id="buyersTab" class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            Buyers List
                        </button>
                    </nav>
                </div>

                <!-- Generate Tab Content -->
                <div id="generateTabContent" class="tab-content">
                    <!-- Application Information -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <h4 class="text-sm font-medium text-blue-900 mb-3">Application Information</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">ST File No:</span>
                                <span id="stFileNo" class="text-gray-900"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">MLPP File No:</span>
                                <span id="mlsFileNo" class="text-gray-900"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Applicant:</span>
                                <span id="applicantName" class="text-gray-900"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Land Use:</span>
                                <span id="landUse" class="text-gray-900"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Property Location:</span>
                                <span id="propertyLocation" class="text-gray-900"></span>
                            </div>
                            <div class="">
                                <span class="font-medium text-gray-700">Units:</span>
                                <span id="unitsCount" class="text-gray-900"></span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="text-gray-700 mb-4">Are you ready to generate the Final Conveyance for this application?</p>
                        <p class="text-sm text-gray-500 mb-6">This action will create the final conveyance document and mark the application's Final Conveyance as completed.</p>
                    </div>
                </div>

                <!-- Buyers List Tab Content -->
                <div id="buyersTabContent" class="tab-content hidden">
                    <!-- Buyers List -->
                    <div class="border border-gray-200 rounded-lg">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900">Buyers</h4>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buyer</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit No</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dimension</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Measurement</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="buyersTableBody" class="bg-white divide-y divide-gray-200">
                                        <!-- Buyers will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                            <div id="noBuyersMessage" class="text-center py-8 text-gray-500 text-sm hidden">
                                No buyers found for this application.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-end space-x-3 p-6 border-t border-gray-200">
            <button type="button" 
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                    onclick="closeFinalConveyanceModal()">
                Cancel
            </button>
            <!-- Generate Tab Button -->
            <button type="button" 
                    id="generateButton"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed hidden"
                    onclick="generateFinalConveyance()">
                <span id="generateButtonText">Generate</span>
                <div id="generateButtonSpinner" class="ml-2 animate-spin rounded-full h-4 w-4 border-b-2 border-white" style="display: none;"></div>
            </button>
        </div>
        </div>
    </div>
</div>

<!-- Edit Buyer Modal -->
<div id="editBuyerModal" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Edit Buyer</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeEditBuyerModal()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <form id="editBuyerForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Buyer Title</label>
                        <select id="editBuyerTitle" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Title</option>
                            <option value="Mr">Mr</option>
                            <option value="Mrs">Mrs</option>
                            <option value="Miss">Miss</option>
                            <option value="Dr">Dr</option>
                            <option value="Prof">Prof</option>
                            <option value="Engr">Engr</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Buyer Name *</label>
                        <input type="text" id="editBuyerName" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit No *</label>
                        <input type="text" id="editUnitNo" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Measurement (sqm)</label>
                        <input type="number" id="editMeasurement" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Cubic Measurement (cbm)</label>
                        <input type="number" id="editCubicMeasurement" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dimension</label>
                        <input type="text" id="editDimension" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end space-x-3 p-6 border-t border-gray-200">
                <button type="button" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                        onclick="closeEditBuyerModal()">
                    Cancel
                </button>
                <button type="button" 
                        id="updateBuyerButton"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        onclick="updateBuyer()">
                    <span id="updateBuyerButtonText">Update</span>
                    <div id="updateBuyerButtonSpinner" class="ml-2 animate-spin rounded-full h-4 w-4 border-b-2 border-white" style="display: none;"></div>
                </button>
            </div>
        </div>
    </div>
</div>
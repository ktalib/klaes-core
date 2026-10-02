<!-- Batch Details Modal -->
<div id="batchDetailsModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title"
    role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
            onclick="closeBatchDetailsModal()"></div>

        <!-- Modal panel -->
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex items-center justify-between mb-6 pb-4 border-b">
                    <div class="flex items-center space-x-3">
                        <div class="p-2 bg-blue-100 rounded-lg text-blue-600">
                            <i data-lucide="layers" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900" id="batchModalTitle">Batch Details</h3>
                            <p class="text-sm text-gray-500" id="batchModalSubtitle">Viewing all records for batch</p>
                        </div>
                    </div>
                    <button onclick="closeBatchDetailsModal()"
                        class="text-gray-400 hover:text-gray-500 transition-colors bg-gray-100 p-2 rounded-full">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <div class="overflow-x-auto border rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    MLPP File No</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    File Name</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Land Use</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Plot No</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Location</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    LGA</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody id="batchDetailsTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Rows will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse space-x-reverse space-x-3">
                <button type="button" onclick="closeBatchDetailsModal()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
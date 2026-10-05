{{-- Export Preview Modal for the Land Registry register.
     Cloned from instrument_registration.modals.export_preview rather than shared,
     so the Deeds export can change without moving this one. The rows come from
     window.landRegisterExportRows, set by the register page. --}}
<div id="landExportPreviewModal" class="fixed inset-0 hidden overflow-y-auto" style="z-index: 60;" aria-labelledby="land-export-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeLandExportModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full border border-gray-200">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-orange-600 to-orange-700 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-lg">
                        <i class="fas fa-file-export text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white" id="land-export-modal-title">Export {{ config('land_registration.instrument_type') }} Register</h3>
                        <p class="text-orange-100 text-sm opacity-90">Filter the register, preview, then download as PDF or CSV</p>
                    </div>
                </div>
                <button type="button" onclick="closeLandExportModal()" class="text-white hover:text-orange-100 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="bg-white px-6 py-6">
                <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end">
                        <!-- Status -->
                        <div class="space-y-1">
                            <label for="landExportStatusFilter" class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Status</label>
                            <select id="landExportStatusFilter" onchange="loadLandExportPreview()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all outline-none">
                                <option value="">All</option>
                                <option value="registered">Registered</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>

                        <!-- Volume (filled from the register's own volumes) -->
                        <div class="space-y-1">
                            <label for="landExportVolumeFilter" class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Volume</label>
                            <select id="landExportVolumeFilter" onchange="loadLandExportPreview()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all outline-none">
                                <option value="">All Volumes</option>
                            </select>
                        </div>

                        <!-- Reg Date From -->
                        <div class="space-y-1">
                            <label for="landExportStartDate" class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Reg Date From</label>
                            <input type="date" id="landExportStartDate" onchange="loadLandExportPreview()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all outline-none">
                        </div>

                        <!-- Reg Date To -->
                        <div class="space-y-1">
                            <label for="landExportEndDate" class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Reg Date To</label>
                            <input type="date" id="landExportEndDate" onchange="loadLandExportPreview()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all outline-none">
                        </div>

                        <!-- Count -->
                        <div class="flex items-center justify-end gap-4 h-full">
                            <div class="text-right">
                                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider">Total Records</span>
                                <span id="landExportRecordCount" class="text-lg font-black text-orange-700 font-mono">0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Table -->
                <div class="overflow-hidden border border-gray-200 rounded-xl shadow-sm">
                    <div class="overflow-x-auto" style="max-height: 500px;">
                        <table class="min-w-full divide-y divide-gray-200" id="landExportPreviewTable">
                            <thead class="bg-gray-50">
                                <tr></tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="landExportPreviewBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3 border-t border-gray-200">
                <button type="button" onclick="downloadLandExportPdf()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-red-600 text-base font-bold text-white hover:bg-red-700 focus:outline-none sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i class="fas fa-file-pdf"></i>
                    <span>Download PDF</span>
                </button>
                <button type="button" onclick="downloadLandExportCsv()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-green-600 text-base font-bold text-white hover:bg-green-700 focus:outline-none sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i class="fas fa-file-csv"></i>
                    <span>Download CSV</span>
                </button>
                <button type="button" onclick="closeLandExportModal()" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-100 focus:outline-none sm:w-auto sm:text-sm transition-all">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #landExportPreviewTable thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
</style>

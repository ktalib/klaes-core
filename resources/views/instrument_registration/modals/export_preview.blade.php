<!-- Export Preview Modal -->
<div id="exportPreviewModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeExportModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full border border-gray-200">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i class="fas fa-file-export text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white" id="modal-title">Export Instruments Preview</h3>
                        <p class="text-green-100 text-sm opacity-90">Consolidated report generation & export filter</p>
                    </div>
                </div>
                <button type="button" onclick="closeExportModal()" class="text-white hover:text-green-100 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="bg-white px-6 py-6">
                <!-- Consolidated Filter Summary & Controls -->
                <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end">
                        <!-- Instrument Type -->
                        <div class="space-y-1">
                            <label for="modalInstrumentTypeFilter" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Instrument Type</label>
                            <select id="modalInstrumentTypeFilter" onchange="loadExportPreviewData()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all outline-none">
                                <option value="">All Types</option>
                                @foreach(($instrumentTypes ?? []) as $type)
                                    @php
                                        $typeLabel = is_object($type) ? ($type->name ?? $type) : $type;
                                        $typeValue = in_array(strtolower(trim((string) $typeLabel)), ['sltr cofo pagination', 'sltr pagination'], true)
                                            ? 'SLTR Certificate of Occupancy'
                                            : $typeLabel;
                                    @endphp
                                    <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Volume -->
                        <div class="space-y-1">
                            <label for="modalVolumeFilter" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Volume</label>
                            <select id="modalVolumeFilter" onchange="loadExportPreviewData()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all outline-none">
                                <option value="">All Volumes</option>
                                @for ($i = 1; $i <= 999; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Start Date -->
                        <div class="space-y-1">
                            <label for="modalStartDateFilter" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Start Date</label>
                            <input type="date" id="modalStartDateFilter" onchange="loadExportPreviewData()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all outline-none">
                        </div>

                        <!-- End Date -->
                        <div class="space-y-1">
                            <label for="modalEndDateFilter" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">End Date</label>
                            <input type="date" id="modalEndDateFilter" onchange="loadExportPreviewData()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all outline-none">
                        </div>

                        <!-- Refresh & Count -->
                        <div class="flex items-center justify-between md:justify-end gap-4 h-full md:pb-0.5">
                            <div class="text-right md:mr-2">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Records</span>
                                <span id="previewRecordCount" class="text-lg font-black text-green-700 font-mono">0</span>
                            </div>
                            <button type="button" onclick="loadExportPreviewData()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2 text-xs shadow-sm transition-all hover:shadow-md h-[36px]">
                                <i class="fas fa-sync fa-sm"></i>
                                <span>Refresh</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preview Table Wrapper -->
                <div class="overflow-hidden border border-gray-200 rounded-xl shadow-sm">
                    <div class="overflow-x-auto max-h-[500px]">
                        <table class="min-w-full divide-y divide-gray-200" id="exportPreviewTable">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">SN</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Op Serial No</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Registration Particulars</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Allottee</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Deed Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Location</th>
                                </tr>
                            </thead>
 
                            <tbody class="bg-white divide-y divide-gray-200" id="exportPreviewBody">
                                <!-- Data will be loaded here via JS -->
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 italic">
                                        <div class="flex flex-col items-center gap-2">
                                            <i class="fas fa-spinner fa-spin text-3xl text-green-500"></i>
                                            <span>Fetching data for preview...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3 border-t border-gray-200">
                <button type="button" id="confirmDownloadPdfBtn" onclick="downloadExportPdf()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-red-600 text-base font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i class="fas fa-file-pdf"></i>
                    <span>Download PDF</span>
                </button>
                <button type="button" id="confirmDownloadBtn" onclick="downloadExportCsv()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-green-600 text-base font-bold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i class="fas fa-file-csv"></i>
                    <span>Download CSV</span>
                </button>
                <button type="button" onclick="closeExportModal()" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 sm:w-auto sm:text-sm transition-all">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #exportPreviewTable thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
</style>

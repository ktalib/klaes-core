{{-- ── Allocation List Register: export preview ────────────────────────────
     Filters narrow the register, "Load Preview" compiles it, and both download
     buttons build from the very array shown here. The PDF carries the ministry
     letterhead and the coat-of-arms watermark on every page.              --}}
<div id="aleExportModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="ale-export-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="aleCloseExportModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-7xl sm:w-full border border-gray-200">

            {{-- Header --}}
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i data-lucide="file-text" class="h-6 w-6 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white" id="ale-export-title">Allocation List Register</h3>
                        <p class="text-blue-100 text-sm opacity-90">Official report export &mdash; PDF and CSV</p>
                    </div>
                </div>
                <button type="button" onclick="aleCloseExportModal()" class="text-white hover:text-blue-100 transition-colors">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="bg-white px-6 py-6">

                {{-- Filters --}}
                <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4 items-end">

                        <div class="space-y-1">
                            <label for="aleExportYear" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Year</label>
                            <select id="aleExportYear" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none">
                                <option value="">All Years</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label for="aleExportLga" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">LGA</label>
                            <select id="aleExportLga" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none">
                                <option value="">All LGAs</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label for="aleExportDistrict" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">District</label>
                            <select id="aleExportDistrict" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none">
                                <option value="">All Districts</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label for="aleExportStartDate" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Captured From</label>
                            <input type="date" id="aleExportStartDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none">
                        </div>

                        <div class="space-y-1">
                            <label for="aleExportEndDate" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Captured To</label>
                            <input type="date" id="aleExportEndDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none">
                        </div>

                        {{-- Each group starts a fresh page, the way the paper
                             register is bound in sections. --}}
                        <div class="space-y-1">
                            <label for="aleExportGroupBy" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Group Pages By</label>
                            <select id="aleExportGroupBy" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none">
                                <option value="">No Grouping</option>
                                <option value="allocation_year">Year</option>
                                <option value="lga">LGA</option>
                                <option value="district">District</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between lg:justify-end gap-4 h-full lg:pb-0.5">
                            <div class="text-right lg:mr-2">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Records</span>
                                <span id="aleExportCount" class="text-lg font-black text-blue-700 font-mono">0</span>
                            </div>
                            <button type="button" onclick="aleLoadExportPreview()"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2 text-xs shadow-sm transition-all hover:shadow-md h-[36px] whitespace-nowrap">
                                <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                                <span>Load Preview</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Preview --}}
                <div class="overflow-hidden border border-gray-200 rounded-xl shadow-sm">
                    <div class="overflow-x-auto max-h-[500px]">
                        <table class="min-w-full divide-y divide-gray-200" id="aleExportPreviewTable">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    {{-- Must stay in step with the preview columns in
                                         allocation_list_export.js, or rows render shifted
                                         under the wrong headers. --}}
                                    @foreach (['S/N', 'File No', 'File Title', 'Allottee Name', 'Plot No', 'Location', 'Year', 'Captured On', 'Created By'] as $heading)
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="aleExportPreviewBody">
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-500 italic">
                                        Choose your filters, then load the preview.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="mt-3 text-[11px] text-gray-400">
                    The CSV additionally carries District, LGA and State.
                </p>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3 border-t border-gray-200">
                <button type="button" onclick="aleDownloadExportPdf()"
                    class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-red-600 text-base font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i data-lucide="file-down" class="h-4 w-4"></i>
                    <span>Download PDF</span>
                </button>
                <button type="button" onclick="aleDownloadExportCsv()"
                    class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-green-600 text-base font-bold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i data-lucide="table" class="h-4 w-4"></i>
                    <span>Download CSV</span>
                </button>
                <button type="button" onclick="aleCloseExportModal()"
                    class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 sm:w-auto sm:text-sm transition-all">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #aleExportPreviewTable thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
</style>

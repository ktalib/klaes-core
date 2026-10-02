<!-- Project Records Modal -->
<div id="records-modal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4">
    <!-- Overlay -->
    <div id="records-modal-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0"></div>

    <!-- Modal Content -->
    <div class="relative w-full max-w-6xl max-h-[90vh] flex flex-col transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all duration-300 scale-95 opacity-0" id="records-modal-container">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-8 py-6">
                <div>
                    <h2 class="text-xl font-bold text-slate-800" id="records-modal-title">Project Valuation Records</h2>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider mt-1" id="records-modal-subtitle"></p>
                </div>
                <button type="button" onclick="VFC.closeRecordsModal()" class="close-records-modal p-2 rounded-xl hover:bg-white hover:shadow-sm text-slate-400 hover:text-slate-600 transition-all">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="px-8 py-6 flex-1 overflow-y-auto">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-slate-400 text-[10px] uppercase tracking-widest font-bold border-b border-slate-100">
                                <th class="pb-4 px-2">S/N</th>
                                <th class="pb-4 px-2">Name of Owner</th>
                                <th class="pb-4 px-2">Type of Building</th>
                                <th class="pb-4 px-2 text-center">No. of Building</th>
                                <th class="pb-4 px-2 text-center">Area (M²)</th>
                                <th class="pb-4 px-2 text-right">Rate (₦)</th>
                                <th class="pb-4 px-2 text-right">Amount (₦)</th>
                                <th class="pb-4 px-2">Account Details</th>
                                <th class="pb-4 px-2">Phone</th>
                                <th class="pb-4 px-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="project-records-body" class="text-slate-600 text-sm divide-y divide-slate-50">
                            <!-- Dynamically populated -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-8 py-4">
                <div class="flex items-center gap-2 text-slate-500 text-xs font-medium">
                    <i data-lucide="info" class="h-4 w-4"></i>
                    <span>Select "Print Batch" to generate a single report for all records.</span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" id="generate-batch-btn" class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 shadow-sm transition-all">
                        <i data-lucide="calculator" class="h-4 w-4"></i>
                        Generate
                    </button>
                    <button type="button" id="print-batch-btn" onclick="VFC.printBatch(this)" class="hidden print-batch-btn items-center gap-2 px-6 py-2.5 rounded-xl bg-teal-600 text-white font-semibold hover:bg-teal-700 shadow-sm transition-all">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                        Print Records
                    </button>
                    <button type="button" onclick="VFC.closeRecordsModal()" class="close-records-modal px-6 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-white hover:shadow-sm transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
</div>

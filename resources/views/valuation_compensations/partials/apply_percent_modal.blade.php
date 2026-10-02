<!-- Apply Percentage Modal -->
<div id="apply-percent-modal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4">
    <!-- Overlay -->
    <div id="apply-percent-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0"></div>

    <!-- Modal Content -->
    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all duration-300 scale-95 opacity-0" id="apply-percent-container">
        <div class="p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="h-12 w-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <i data-lucide="calculator" class="h-6 w-6"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-800">Apply Percentage</h3>
                    <p class="text-sm text-slate-500">Adjust the final valuation totals.</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Sub Total Info -->
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sub Total</span>
                        <span class="text-lg font-black text-slate-700" id="modal-sub-total">₦0.00</span>
                    </div>
                </div>

                <!-- Input -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Percentage to Apply (%)</label>
                    <div class="relative">
                        <input type="number" id="apply-percentage-input" 
                               class="w-full pl-4 pr-12 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-bold text-slate-700 transition-all"
                               placeholder="0" min="0" max="200" step="0.1">
                        <div class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">%</div>
                    </div>
                </div>

                <!-- Final Total Preview -->
                <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Final Adjusted Total</span>
                        <span class="text-xl font-black text-emerald-700" id="modal-final-total">₦0.00</span>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center gap-3 mt-8">
                <button type="button" id="confirm-apply-percent" class="flex-1 py-3 px-6 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                    Apply & Continue
                </button>
                <button type="button" id="print-batch-btn-modal" class="hidden print-batch-btn flex-1 py-3 px-6 bg-teal-600 text-white font-bold rounded-xl hover:bg-teal-700 shadow-lg shadow-teal-200 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="printer" class="h-5 w-5"></i>
                    Print Batch Now
                </button>
                <button type="button" id="cancel-apply-percent" onclick="VFC.closeApplyPercentModal()" class="px-6 py-3 border border-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-50 transition-all">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

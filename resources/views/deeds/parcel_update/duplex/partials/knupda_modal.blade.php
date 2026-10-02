{{-- KAMMA clearance for the whole duplex — one evaluation, not one per stage. --}}
<div id="dx-knupda-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4">
    {{-- Typed fields: closes by Cancel or the X, never by clicking the backdrop. --}}
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg border border-slate-100">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-black text-slate-800">KAMMA / Physical Planning Evaluation</h3>
            <button onclick="closeKnupda()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="knupda-duplex-id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Land Value</label>
                    <input type="number" step="0.01" id="knupda-land-value" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">KAMMA / Physical Planning Fee</label>
                    <input type="number" step="0.01" id="knupda-fee" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Status</label>
                <select id="knupda-status" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                    <option value="">— Select —</option>
                    <option value="Approved">Approved</option>
                    <option value="Declined">Declined</option>
                    <option value="Pending">Pending</option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">
                    The memo cannot be generated until this reads Approved, and the duplex cannot be approved until the memo and conveyance exist.
                </p>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Remarks</label>
                <textarea id="knupda-remarks" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm"></textarea>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50/60">
            <button onclick="closeKnupda()" class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600">Cancel</button>
            <button onclick="saveKnupda()" class="px-5 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">Save</button>
        </div>
    </div>
</div>

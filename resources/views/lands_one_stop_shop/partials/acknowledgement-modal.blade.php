{{-- ═══════════════════════════════════════════════════════════════════════
     Acknowledgement Modal – FORM LN-03C
     Auto-fills from table row data; editable fields left for user input.
     ═══════════════════════════════════════════════════════════════════════ --}}
<div id="acknowledgementModal"
     class="fixed inset-0 z-[9999] hidden"
     aria-modal="true" role="dialog">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAcknowledgementModal()"></div>

    {{-- Dialog --}}
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">

            {{-- ── Header ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-green-600 to-green-700">
                <div>
                    <h2 class="text-lg font-bold text-white">Acknowledgement Form</h2>
                    <p class="text-green-100 text-xs mt-0.5">FORM LN-03C &mdash; Occupancy Permit (OP) Acknowledgment</p>
                </div>
                <button type="button" onclick="closeAcknowledgementModal()"
                        class="text-white/80 hover:text-white transition p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- ── Body (scrollable) ── --}}
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">

                {{-- Ministry header --}}
                <div class="text-center space-y-1 pb-4 border-b border-slate-100">
                    <h3 class="text-base font-extrabold text-green-700 uppercase tracking-wide">Ministry of Land and Physical Planning</h3>
                    <p class="text-[11px] text-slate-500">No. 2 Dr. Bala Mohd. Road, Nassarawa GRA, Kano P.M.B. 3083, Kano-Nigeria</p>
                    <span class="inline-block mt-2 px-4 py-1 bg-red-500 text-white text-xs font-bold rounded uppercase tracking-wider">
                        Occupancy Permit (OP) Acknowledgment Form
                    </span>
                    <p class="text-sm font-bold text-slate-500 mt-2">ONE STOP SHOP</p>
                </div>

                <form id="acknowledgementForm">
                    {{-- Date / Time row --}}
                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Date</label>
                            <input type="text" id="ack_date" readonly
                                   class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Time</label>
                            <input type="text" id="ack_time" readonly
                                   class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                        </div>
                    </div>

                    {{-- Applicant name --}}
                    <div class="mb-5">
                        <label class="block text-xs font-semibold text-slate-500 mb-1">
                            Receipt of Original OP for <span class="text-slate-400">(Applicant Name)</span>
                        </label>
                        <input type="text" id="ack_applicant_name" readonly
                               class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                    </div>

                    {{-- Plot / Plan --}}
                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Plot No.</label>
                            <input type="text" id="ack_plot_no" readonly
                                   class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Plan No.</label>
                            <input type="text" id="ack_plan_no" readonly
                                   class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                        </div>
                    </div>

                    {{-- Location --}}
                    <div class="mb-5">
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Located at</label>
                        <input type="text" id="ack_location" readonly
                               class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                    </div>

                    {{-- Confirmation text --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-6 text-xs text-amber-800 leading-relaxed hidden">
                        This acknowledgement is to confirm the receipt of your original OP. The documents will be subjected
                        to verification to ascertain its authenticity.
                    </div>

                    {{-- ── Editable Signature Section ── --}}
                    <div class="border-t border-slate-200 pt-5 space-y-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Signatures</p>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Name of the Applicant <span class="text-red-400">*</span></label>
                                <input type="text" id="ack_sig_applicant_name" placeholder="Enter applicant name"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                            </div>
                            <div Class="hidden>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Applicant Signature</label>
                                <input type="text" id="ack_sig_applicant" placeholder="Signature"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Chairman One Stop Shop <span class="text-red-400">*</span></label>
                                <input type="text" id="ack_sig_chairman_name" placeholder="Enter chairman name"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Chairman Signature</label>
                                <input type="text" id="ack_sig_chairman" placeholder="Signature"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                            </div>
                        </div>
                    </div>

                    {{-- Disclaimer --}}
                    <div class="mt-6 border border-red-200 rounded-lg p-4 bg-red-50/50">
                        <p class="text-center text-xs font-bold text-red-600 uppercase mb-2">Disclaimer</p>
                        <p class="text-[11px] text-red-800 leading-relaxed">
                            This acknowledgement does not in anyway validate the authenticity of the documents described above.
                            All documents are subject to further verification for Authenticity.
                        </p>
                        <p class="text-[11px] text-red-800 leading-relaxed mt-1">
                            This acknowledgement <strong>must</strong> be presented at the time of collection of the Letter of Grant.
                        </p>
                    </div>

                    {{-- Hidden record ID --}}
                    <input type="hidden" id="ack_record_id" value="">
                    <input type="hidden" id="ack_file_no" value="">
                </form>
            </div>

            {{-- ── Footer ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50">
                <button type="button" onclick="closeAcknowledgementModal()"
                        class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                    Cancel
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="saveAcknowledgement()"
                            class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm transition">
                        <i data-lucide="save" class="w-4 h-4"></i> Save
                    </button>
                    <button type="button" id="ackPrintBtn" onclick="printAcknowledgement()" disabled
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-green-50">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

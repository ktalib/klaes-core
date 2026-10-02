{{-- ═══════════════════════════════════════════════════════════════════════
     Recommendation Modal – Grant of Statutory Right of Occupancy
     Auto-fills from table row data; editable fields left for user input.
     ═══════════════════════════════════════════════════════════════════════ --}}
<div id="recommendationModal"
     class="fixed inset-0 z-[9999] hidden"
     aria-modal="true" role="dialog">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeRecommendationModal()"></div>

    {{-- Dialog --}}
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden">

            {{-- ── Header ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-slate-700 to-slate-800">
                <div>
                    <h2 class="text-lg font-bold text-white">Recommendation Form</h2>
                    <p class="text-slate-300 text-xs mt-0.5">Recommendation for Grant of Statutory Right of Occupancy</p>
                </div>
                <button type="button" onclick="closeRecommendationModal()"
                        class="text-white/80 hover:text-white transition p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- ── Body (scrollable) ── --}}
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">

                {{-- Ministry header --}}
                <div class="text-center space-y-1 pb-4 border-b border-slate-100">
                    <h3 class="text-base font-extrabold text-slate-600 uppercase tracking-wide">
                        Recommendation for Grant of Statutory Right of Occupancy
                    </h3>
                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mt-1">Conditions for Application</p>
                </div>

                <form id="recommendationForm">                        <input type="hidden" id="rec_application_date" value="">
                        <input type="hidden" id="rec_applicant_address" value="">
                    {{-- ═══ Section 1 – Auto-filled Fields ═══ --}}
                    <div class="space-y-4 mb-6">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-blue-400 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400 inline-block"></span>
                            Auto-filled from Record
                        </p>

                        {{-- 1. Name of Applicant --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">
                                <span class="text-slate-400 mr-1">1.</span> Name of Applicant
                            </label>
                            <input type="text" id="rec_applicant_name" readonly
                                   class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                        </div>

                        {{-- 2a. File Ref No + 2b. Purpose of Clause --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">
                                    <span class="text-slate-400 mr-1">2a.</span> File Ref No.
                                </label>
                                <input type="text" id="rec_file_ref" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 font-mono cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">
                                    <span class="text-slate-400 mr-1">2b.</span> Purpose of Clause
                                </label>
                                <input type="text" id="rec_purpose" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                        </div>

                        {{-- 2c. Location + 2d. Plot No + 2e. Layout Plan No --}}
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">
                                    <span class="text-slate-400 mr-1">2c.</span> Location
                                </label>
                                <input type="text" id="rec_location" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">
                                    <span class="text-slate-400 mr-1">2d.</span> Plot No.
                                </label>
                                <input type="text" id="rec_plot_no" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">
                                    <span class="text-slate-400 mr-1">2e.</span> Layout Plan No.
                                </label>
                                <input type="text" id="rec_plan_no" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                        </div>
                    </div>

                    {{-- ═══ Section 2 – Editable Fields ═══ --}}
                    <div class="space-y-4 mb-6 border-t border-slate-200 pt-5">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-500 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                            Fill in the Details Below
                        </p>

                        {{-- 3. Term --}}
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">
                                    <span class="text-slate-400 mr-1">3.</span> Term <span class="text-slate-400 font-normal">(years)</span>
                                </label>
                                <input type="number" id="rec_term" placeholder="e.g. 99" min="1"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">
                                    <span class="text-slate-400 mr-1">4.</span> Value for Proposed Development
                                </label>
                                <input type="text" id="rec_dev_value" placeholder="&#8358; 0.00"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">
                                    <span class="text-slate-400 mr-1">5.</span> Time for Completion
                                </label>
                                <input type="text" id="rec_completion_time" placeholder="e.g. 24 months"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                        </div>

                        {{-- 6, 7, 8 – Financial --}}
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">
                                    <span class="text-slate-400 mr-1">6.</span> Annual Ground Rent <span class="text-slate-400 font-normal">(phpa)</span>
                                </label>
                                <input type="text" id="rec_ground_rent" placeholder="&#8358; 0.00"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">
                                    <span class="text-slate-400 mr-1">7.</span> Development Charge
                                </label>
                                <input type="text" id="rec_dev_charge"  
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" value="To Follow">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">
                                    <span class="text-slate-400 mr-1">8.</span> Survey &amp; Processing Charges
                                </label>
                                <input type="text" id="rec_survey_charges" placeholder="&#8358; 0.00"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    {{-- ═══ Section 3 – Director Recommendation ═══ --}}
                    <div class="border-t border-slate-200 pt-5 mb-6">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-amber-500 flex items-center gap-2 mb-4">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span>
                            Director of Land Recommendation
                        </p>

                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">
                                <span class="text-slate-400 mr-1">9.</span> The Director of Land recommends / does not recommend the application for the following reasons:
                            </label>
                            <textarea id="rec_director_reasons" rows="3" placeholder="Enter reasons for recommendation or non-recommendation..."
                                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4 hidden">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Director Land – Sign</label>
                                <input type="text" id="rec_director_sign" placeholder="Enter name"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Date</label>
                                <input type="date" id="rec_director_date"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    {{-- ═══ Section 4 – Permanent Secretary ═══ --}}
                    <div class="border-t border-slate-200 pt-5 mb-6">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-purple-500 flex items-center gap-2 mb-4">
                            <span class="w-1.5 h-1.5 rounded-full bg-purple-500 inline-block"></span>
                            Permanent Secretary Recommendation
                        </p>

                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">
                                <span class="text-slate-400 mr-1">10.</span> I recommend / do not recommend the Application for a Grant of Right of Occupancy over:
                            </label>
                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div>
                                    <label class="block text-[11px] text-slate-400 mb-0.5">Plot No.</label>
                                    <input type="text" id="rec_ps_plot" readonly
                                           class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-slate-400 mb-0.5">Location</label>
                                    <input type="text" id="rec_ps_location" readonly
                                           class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                                </div>
                            </div>
                        </div> 

                        <div class="grid grid-cols-2 gap-4 hidden">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Permanent Secretary – Sign</label>
                                <input type="text" id="rec_ps_sign" placeholder="Enter name"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Date</label>
                                <input type="date" id="rec_ps_date"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    {{-- ═══ Section 5 – Commissioner Approval ═══ --}}
                    <div class="border-t border-slate-200 pt-5 hidden">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-red-500 flex items-center gap-2 mb-4">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>
                            Commissioner Approval
                        </p>

                        <div class="border border-slate-200 rounded-lg p-4 bg-slate-50/50">
                            <label class="block text-xs font-semibold text-slate-600 mb-2">
                                <span class="text-slate-400 mr-1">11.</span> The Grant of Right of Occupancy is hereby:
                            </label>
                            <div class="flex items-center gap-6 mb-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="rec_approval_status" value="approved"
                                           class="text-green-600 focus:ring-green-500">
                                    <span class="text-sm font-semibold text-green-700">APPROVED</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="rec_approval_status" value="not_approved"
                                           class="text-red-600 focus:ring-red-500">
                                    <span class="text-sm font-semibold text-red-700">NOT APPROVED</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-4 hidden">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">The Honourable Commissioner of Land</label>
                                    <input type="text" id="rec_commissioner_name" placeholder="Enter name"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Date</label>
                                    <input type="date" id="rec_commissioner_date"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Footer tag --}}
                    <p class="text-center text-[11px] text-slate-400 font-semibold mt-4">
                        Kano State Ministry of Land and Physical Planning
                    </p>

                    {{-- Hidden record ID --}}
                    <input type="hidden" id="rec_record_id" value="">
                </form>
            </div>

            {{-- ── Footer ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50">
                <button type="button" onclick="closeRecommendationModal()"
                        class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                    Cancel
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" id="recSaveBtn" onclick="saveRecommendation()"
                            class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-slate-700 rounded-lg hover:bg-slate-800 shadow-sm transition">
                        <i data-lucide="save" class="w-4 h-4"></i> Save
                    </button>
                    {{-- One button, two jobs, in the order the work is done.

                         Until the proof has been run it prints a WHITE COPY: what
                         is in the fields right now — typed, possibly unsaved, and
                         not yet read by anyone. Named for what it does, or an
                         officer presses "Print" expecting the document and gets a
                         draft.

                         Once the proof has been run it becomes the official print,
                         because that is the next step and this is the card the
                         officer is already on. It is the same document, the same
                         serial and the same paper as the Print Manager's, so it
                         stands behind the same "have you read it?" question.

                         This partial is shared by four pages, only one of which
                         loads the state machine, so the markup keeps the plain
                         white-copy handler and that page's
                         _ossApplyRecButtonState() takes the button over — label,
                         icon, styling and handler — when it hydrates. The other
                         three are left exactly as they were. --}}
                    <button type="button" id="recPrintBtn" onclick="printRecommendation()" disabled
                            data-rec-print-mode="white-copy"
                            title="Black &amp; white proof of this form for vetting — not an official copy"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white">
                        {{-- The icon is swapped with the mode, so it lives in a
                             holder of its own: lucide replaces an <i> with an <svg>
                             on first render, and there would be no <i> left to
                             change. --}}
                        <span data-rec-print-icon class="inline-flex"><i data-lucide="file-search" class="w-4 h-4"></i></span>
                        <span data-rec-print-label>Print White Copy</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

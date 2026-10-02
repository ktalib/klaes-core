{{-- ═══════════════════════════════════════════════════════════════════
     OSS Application Bill Modal
     Shows bill items based on application type with auto-filled costs.
     ═══════════════════════════════════════════════════════════════════ --}}

<div id="ossBillModal"
     class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 backdrop-blur-sm hidden"
     onclick="if(event.target===this) ossCloseBillModal()">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden transform transition-all"
         onclick="event.stopPropagation()">

        {{-- Header --}}
        <div id="ossBillModalHeader"
             class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-emerald-600 to-emerald-700">
            <div>
                <h2 class="text-lg font-bold text-white">Bill</h2>
                <p id="ossBillSubtitle" class="text-xs text-white/80 mt-0.5"></p>
            </div>
            <button type="button" onclick="ossCloseBillModal()"
                    class="p-1.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 space-y-4">

            {{-- Hidden fields --}}
            <input type="hidden" id="ossBill_application_id">
            <input type="hidden" id="ossBill_application_type">

            {{-- Land Use Selector (shown when land_use is missing) --}}
            <div id="ossBill_landuse_row" class="hidden">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Purpose / Land Use <span class="text-red-500">*</span></label>
                <select id="ossBill_landuse_select" onchange="ossBillLandUseChanged(this.value)"
                        class="w-full bg-white border border-amber-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-amber-400/30 focus:border-amber-400">
                    <option value="">— Select Land Use —</option>
                    <option value="residential">Residential</option>
                    <option value="commercial">Commercial</option>
                    <option value="industrial">Industrial</option>
                    <option value="agricultural">Agricultural</option>
                </select>
                <p class="text-[11px] text-amber-600 mt-1">Land use not set — please select to generate the bill.</p>
            </div>

            {{-- Bill Items Table --}}
            <table class="w-full text-sm border border-slate-200 rounded-xl overflow-hidden">
                <thead>
                    <tr class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-2.5 text-left">#</th>
                        <th class="px-4 py-2.5 text-left">Description</th>
                        <th class="px-4 py-2.5 text-right">Amount (₦)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2.5 text-slate-400 font-mono">1</td>
                        <td class="px-4 py-2.5 font-medium text-slate-700">Change of Name</td>
                        <td class="px-4 py-2.5 text-right">
                            <input type="text" id="ossBill_change_of_name" readonly
                                   class="w-28 text-right bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700">
                        </td>
                    </tr>
                    {{-- Purpose sub-row: shown only for Residential --}}
                    <tr id="ossBill_density_row" class="border-t border-green-100 bg-green-50/40 hidden">
                        <td class="px-4 py-2 text-green-300 font-mono text-xs pl-6">↳</td>
                        <td class="px-4 py-2" colspan="2">
                            <label class="text-[10px] font-bold text-green-600 uppercase tracking-wide block mb-1">Purpose <span class="text-red-500">*</span></label>
                            <select id="ossBill_density_select" onchange="ossOnDensityChange(this.value)"
                                    class="w-full bg-white border border-green-300 rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-green-400/30 focus:border-green-400">
                                <option value="">— Select Purpose —</option>
                                <option value="3000">HIGH DENSITY — ₦3,000</option>
                                <option value="5000">MEDIUM DENSITY — ₦5,000</option>
                                <option value="10000">LOW DENSITY — ₦10,000</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2.5 text-slate-400 font-mono">2</td>
                        <td class="px-4 py-2.5 font-medium text-slate-700">Processing Fee</td>
                        <td class="px-4 py-2.5 text-right">
                            <input type="text" id="ossBill_processing_fee" readonly
                                   class="w-28 text-right bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700">
                        </td>
                    </tr>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2.5 text-slate-400 font-mono">3</td>
                        <td class="px-4 py-2.5 font-medium text-slate-700">Survey Deposit</td>
                        <td class="px-4 py-2.5 text-right">
                            <input type="text" id="ossBill_survey_deposit" readonly
                                   class="w-28 text-right bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700">
                        </td>
                    </tr>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2.5 text-slate-400 font-mono">4</td>
                        <td class="px-4 py-2.5 font-medium text-slate-700">Application Form</td>
                        <td class="px-4 py-2.5 text-right">
                            <input type="text" id="ossBill_application_form" readonly
                                   class="w-28 text-right bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700">
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-300 bg-slate-50">
                        <td colspan="2" class="px-4 py-3 text-right font-bold text-slate-800 uppercase text-xs tracking-wide">Total</td>
                        <td class="px-4 py-3 text-right">
                            <span id="ossBill_total" class="text-lg font-extrabold text-emerald-700">₦0</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3">
            <button type="button" onclick="ossCloseBillModal()"
                    class="px-5 py-2 rounded-xl text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition">
                Cancel
            </button>
            <button type="button" onclick="ossSaveBill()"
                    id="ossBillSaveBtn"
                    class="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition">
                <i data-lucide="printer" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>
                Print Bill
            </button>
        </div>
    </div>
</div>

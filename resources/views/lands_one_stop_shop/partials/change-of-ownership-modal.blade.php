@include('lands_one_stop_shop.partials.option-lists')
{{-- ═══════════════════════════════════════════════════════════════════════
     Change of Ownership Modal – Occupancy Permit
     Fields mirror docs/templates/landsoss/one-stop3.html
     (Signature of Applicant & Date excluded from modal form)
     ═══════════════════════════════════════════════════════════════════════ --}}
<div id="changeOfOwnershipModal"
     class="fixed inset-0 z-[9999] hidden"
     aria-modal="true" role="dialog">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeChangeOfOwnershipModal()"></div>

    {{-- Dialog --}}
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">

            {{-- ── Header ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-amber-600 to-amber-700">
                <div>
                    <h2 class="text-lg font-bold text-white">Change of Ownership Form</h2>
                    <p class="text-amber-100 text-xs mt-0.5">Occupancy Permit &mdash; Change of Ownership</p>
                </div>
                <button type="button" onclick="closeChangeOfOwnershipModal()"
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
                    <div class="mt-2">
                        <span class="text-green-700 font-bold text-sm">OCCUPANCY PERMIT</span><br>
                        <span class="inline-block mt-1 px-4 py-1 bg-red-400 text-white text-xs font-bold rounded uppercase tracking-wider">
                            CHANGE OF OWNERSHIP FORM
                        </span>
                    </div>
                </div>

                <form id="changeOfOwnershipForm">
                    {{-- ═══ Section 1 – OP Details ═══ --}}
                    <div class="mb-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">1. OP Details</p>
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">OP Number</label>
                                <input type="text" id="coo_op_number" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Property Location</label>
                                <input type="text" id="coo_location" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Plot No</label>
                                <input type="text" id="coo_plot_no" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Plan No</label>
                                <input type="text" id="coo_plan_no" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Date of Issuance</label>
                                <input type="date" id="coo_date_of_issuance"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                            </div>
                        </div>
                    </div>

                    {{-- ═══ Section 2 – Original Allottee ═══ --}}
                    <div class="mb-5 border-t border-slate-100 pt-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">2. Original Allottee</p>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Name</label>
                                <input type="text" id="coo_original_name"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                       placeholder="Name of original allottee">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Address</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-amber-50/60 border border-amber-200 mt-1">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                        <input type="text" id="coo_orig_addr_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" placeholder="e.g., 12A">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                        <input type="text" id="coo_orig_addr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" placeholder="e.g., 45">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                        <select id="coo_orig_addr_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">SELECT STREET</option>
                                            <option data-options-slot="streets" hidden></option>
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="coo-orig-addr-street-other-wrapper" class="md:col-span-2 hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                        <input type="text" id="coo_orig_addr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" placeholder="Type street name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                        <select id="coo_orig_addr_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">SELECT DISTRICT</option>
                                            <option data-options-slot="districts" hidden></option>
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="coo-orig-addr-district-other-wrapper" class="hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                        <input type="text" id="coo_orig_addr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" placeholder="Type district name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                        <select id="coo_orig_addr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">SELECT LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                        <select id="coo_orig_addr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">SELECT STATE</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <textarea id="coo_original_address" rows="2" readonly
                                          placeholder="Address will be built automatically..."
                                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Phone No.</label>
                                <input type="text" id="coo_original_phone"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                       placeholder="Phone number">
                            </div>
                        </div>
                    </div>

                    {{-- ═══ Section 3 – Current Owner ═══ --}}
                    <div class="mb-5 border-t border-slate-100 pt-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">3. Current Owner</p>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Name</label>
                                <input type="text" id="coo_current_name"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                       placeholder="Name of current owner">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Address</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-amber-50/60 border border-amber-200 mt-1">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                        <input type="text" id="coo_curr_addr_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" placeholder="e.g., 12A">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                        <input type="text" id="coo_curr_addr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" placeholder="e.g., 45">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                        <select id="coo_curr_addr_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">SELECT STREET</option>
                                            <option data-options-slot="streets" hidden></option>
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="coo-curr-addr-street-other-wrapper" class="md:col-span-2 hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                        <input type="text" id="coo_curr_addr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" placeholder="Type street name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                        <select id="coo_curr_addr_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">SELECT DISTRICT</option>
                                            <option data-options-slot="districts" hidden></option>
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="coo-curr-addr-district-other-wrapper" class="hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                        <input type="text" id="coo_curr_addr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" placeholder="Type district name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                        <select id="coo_curr_addr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">SELECT LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                        <select id="coo_curr_addr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">SELECT STATE</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <textarea id="coo_current_address" rows="2" readonly
                                          placeholder="Address will be built automatically..."
                                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Phone No.</label>
                                <input type="text" id="coo_current_phone"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                       placeholder="Phone number">
                            </div>
                        </div>
                    </div>

                    {{-- ═══ Section 4 – How ownership was obtained ═══ --}}
                    <div class="mb-5 border-t border-slate-100 pt-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">4. How Ownership Was Obtained</p>
                        <div class="grid grid-cols-3 gap-3">
                            <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                <input type="radio" name="coo_ownership_method" value="Direct" class="text-amber-600 focus:ring-amber-500">
                                <span class="text-sm text-slate-700">Direct</span>
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                <input type="radio" name="coo_ownership_method" value="Resettlement" class="text-amber-600 focus:ring-amber-500">
                                <span class="text-sm text-slate-700">Resettlement</span>
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                <input type="radio" name="coo_ownership_method" value="Gift" class="text-amber-600 focus:ring-amber-500">
                                <span class="text-sm text-slate-700">Gift</span>
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                <input type="radio" name="coo_ownership_method" value="Purchase" class="text-amber-600 focus:ring-amber-500">
                                <span class="text-sm text-slate-700">Purchase</span>
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                <input type="radio" name="coo_ownership_method" value="Inheritance" class="text-amber-600 focus:ring-amber-500">
                                <span class="text-sm text-slate-700">Inheritance</span>
                            </label>
                        </div>
                    </div>

                    {{-- ═══ Declaration ═══ --}}
                    <div class="mt-4 border border-slate-200 rounded-lg p-4 bg-slate-50/50">
                        <p class="text-[11px] text-slate-600 leading-relaxed">
                            I, the undersigned declares that all the information given above is true and correct and I also understand
                            that if any part of the information is false, it may / shall jeopardize the validity of the application or
                            document or any registration resulting there-from.
                        </p>
                    </div>

                    {{-- Hidden record ID --}}
                    <input type="hidden" id="coo_record_id" value="">
                </form>
            </div>

            {{-- ── Footer ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50">
                <button type="button" onclick="closeChangeOfOwnershipModal()"
                        class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                    Cancel
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="saveChangeOfOwnership()"
                            class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700 shadow-sm transition">
                        <i data-lucide="save" class="w-4 h-4"></i> Save
                    </button>
                    <button type="button" id="cooPrintBtn" onclick="printChangeOfOwnership()" disabled
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-amber-700 bg-white border border-amber-300 rounded-lg hover:bg-amber-50 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

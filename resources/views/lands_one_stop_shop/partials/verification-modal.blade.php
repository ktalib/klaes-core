@include('lands_one_stop_shop.partials.option-lists')
{{-- ═══════════════════════════════════════════════════════════════════════
     Verification Modal – FORM LN-03A
     Fields mirror docs/templates/landsoss/one-stop2.html
     ═══════════════════════════════════════════════════════════════════════ --}}
<div id="verificationModal"
     class="fixed inset-0 z-[9999] hidden"
     aria-modal="true" role="dialog">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeVerificationModal()"></div>

    {{-- Dialog --}}
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">

            {{-- ── Header ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-teal-600 to-teal-700">
                <div>
                    <h2 class="text-lg font-bold text-white">Verification Form</h2>
                    <p class="text-teal-100 text-xs mt-0.5">FORM LN-03A &mdash; Occupancy Permit (OP) Verification</p>
                </div>
                <button type="button" onclick="closeVerificationModal()"
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
                    <p class="text-sm font-bold text-slate-500 mt-2">ONE STOP SHOP</p>
                    <span class="inline-block mt-1 px-4 py-1 bg-red-400 text-white text-xs font-bold rounded uppercase tracking-wider">
                        Occupancy Permit (OP) Verification Form
                    </span>
                </div>

                <form id="verificationForm" enctype="multipart/form-data">
                    {{-- ═══ Passport Photograph ═══ --}}
                    <div class="mb-5 flex justify-end">
                        <div class="text-center">
                            <div id="ver_passport_preview"
                                 class="w-[110px] h-[130px] border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50 overflow-hidden cursor-pointer hover:border-teal-400 transition"
                                 onclick="document.getElementById('ver_passport_input').click()">
                                <div id="ver_passport_placeholder" class="text-center px-2">
                                    <i data-lucide="camera" class="w-6 h-6 text-slate-400 mx-auto mb-1"></i>
                                    <p class="text-[10px] text-slate-400 leading-tight">Click to upload<br>Passport Photo</p>
                                </div>
                                <img id="ver_passport_img" src="" alt="Passport" class="hidden w-full h-full object-cover">
                            </div>
                            <input type="file" id="ver_passport_input" accept="image/*" class="hidden"
                                   onchange="verPreviewPassport(this)">
                            <button type="button" id="ver_passport_remove" class="hidden mt-1 text-[10px] text-red-500 hover:text-red-700"
                                    onclick="verRemovePassport()">Remove</button>
                        </div>
                    </div>

                    {{-- ═══ PART A – Applicant Details ═══ --}}
                    <div class="mb-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-teal-600 mb-3">Part A</p>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">1. Name of Applicant</label>
                                <input type="text" id="ver_applicant_name" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">2. Address</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-teal-50/60 border border-teal-200 mt-1">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                        <input type="text" id="ver_addr_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500" placeholder="e.g., 12A">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                        <input type="text" id="ver_addr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500" placeholder="e.g., 45">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                        <select id="ver_addr_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500">
                                            <option value="">SELECT STREET</option>
                                            <option data-options-slot="streets" hidden></option>
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="ver-addr-street-other-wrapper" class="md:col-span-2 hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                        <input type="text" id="ver_addr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500" placeholder="Type street name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                        <select id="ver_addr_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500">
                                            <option value="">SELECT DISTRICT</option>
                                            <option data-options-slot="districts" hidden></option>
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="ver-addr-district-other-wrapper" class="hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                        <input type="text" id="ver_addr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500" placeholder="Type district name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                        <select id="ver_addr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500">
                                            <option value="">SELECT LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                        <select id="ver_addr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500">
                                            <option value="">SELECT STATE</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <textarea id="ver_address" rows="2" readonly
                                          placeholder="Address will be built automatically..."
                                          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">3. Phone Number(s)</label>
                                    <input type="text" id="ver_phone"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                                           placeholder="Phone number">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">4. E-mail Address</label>
                                    <input type="text" id="ver_email"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                                           placeholder="Email address">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">5. Means of Identification <span class="text-slate-400">(Photocopy to be attached)</span></label>
                                <div class="grid grid-cols-3 gap-3 mt-2">
                                    <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                        <input type="radio" name="ver_id_type" value="National I.D." class="text-teal-600 focus:ring-teal-500">
                                        <span class="text-sm text-slate-700">National I.D.</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                        <input type="radio" name="ver_id_type" value="Voters Card" class="text-teal-600 focus:ring-teal-500">
                                        <span class="text-sm text-slate-700">Voters Card</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                        <input type="radio" name="ver_id_type" value="Driving Licence" class="text-teal-600 focus:ring-teal-500">
                                        <span class="text-sm text-slate-700">Driving Licence</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                        <input type="radio" name="ver_id_type" value="International Passport" class="text-teal-600 focus:ring-teal-500">
                                        <span class="text-sm text-slate-700">Int'l Passport</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                                        <input type="radio" name="ver_id_type" value="Others" class="text-teal-600 focus:ring-teal-500">
                                        <span class="text-sm text-slate-700">Others</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ PART B – OP Details ═══ --}}
                    <div class="mb-5 border-t border-slate-100 pt-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-teal-600 mb-3">Part B</p>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">1. Name of Original Allottee</label>
                                <input type="text" id="ver_original_allottee" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">2. OP No.</label>
                                    <input type="text" id="ver_op_no" readonly
                                           class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">3. Plot No. / Plan No.</label>
                                    <input type="text" id="ver_plot_plan" readonly
                                           class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">4. Property Location</label>
                                <input type="text" id="ver_location" readonly
                                       class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 cursor-not-allowed">
                            </div>
                        </div>
                    </div>

                    {{-- ═══ FOR OFFICIAL USE ONLY ═══ --}}
                    <div class="mb-4 border-t border-slate-100 pt-5">
                        <div class="border border-slate-300 rounded-lg p-4 bg-slate-50/50">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 text-center mb-3 underline">For Official Use Only</p>
                            <div class="flex items-center gap-6 mb-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="ver_recommendation" value="Recommended" class="text-teal-600 focus:ring-teal-500">
                                    <span class="text-sm font-semibold text-slate-700">Recommended</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="ver_recommendation" value="Not Recommended" class="text-red-600 focus:ring-red-500">
                                    <span class="text-sm font-semibold text-slate-700">Not Recommended</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Chairman One Stop Shop</label>
                                <input type="text" id="ver_chairman_name"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                                       placeholder="Name of Chairman">
                            </div>
                        </div>
                    </div>

                    {{-- Hidden record ID --}}
                    <input type="hidden" id="ver_record_id" value="">
                </form>
            </div>

            {{-- ── Footer ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50">
                <button type="button" onclick="closeVerificationModal()"
                        class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                    Cancel
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="saveVerification()"
                            class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 shadow-sm transition">
                        <i data-lucide="save" class="w-4 h-4"></i> Save
                    </button>
                    <button type="button" id="verPrintBtn" onclick="printVerification()" disabled
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-teal-700 bg-white border border-teal-300 rounded-lg hover:bg-teal-50 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

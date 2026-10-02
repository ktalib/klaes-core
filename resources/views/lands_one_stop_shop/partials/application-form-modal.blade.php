@include('lands_one_stop_shop.partials.option-lists')
{{-- ═══════════════════════════════════════════════════════════════════════
     OSS Application Form Modal
     3 Types: Residential, Commercial, Industrial
     Each type has its OWN form section matching the official paper forms.
     ═══════════════════════════════════════════════════════════════════════ --}}
{{-- Select2 look-and-feel for the OP TP No. / Location dropdowns.
     Kept scoped to .oss-searchable-select so it never affects other pages. --}}
<style>
    .oss-searchable-select + .select2-container .select2-selection--single {
        height: 42px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 0.5rem !important;
        padding: 6px 8px !important;
        font-size: 0.875rem !important;
        background: #fff;
    }
    .oss-searchable-select + .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 28px !important;
        color: #0f172a;
        padding-left: 2px;
    }
    .oss-searchable-select + .select2-container .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    /* Dropdown must sit above the z-[9999] modal overlay. */
    .select2-container { z-index: 10050 !important; }
    .select2-dropdown { border: 1px solid #cbd5e1 !important; }
    .select2-results__option { font-size: 0.8rem; padding: 8px 12px; }
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: #d97706;
    }
</style>

<div id="ossApplicationModal"
     class="fixed inset-0 z-[9999] hidden"
     aria-modal="true" role="dialog">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="ossCloseModal()"></div>

    {{-- Dialog --}}
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden">

            {{-- ── Header ── --}}
            <div id="ossModalHeader" class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-yellow-500 to-yellow-600">
                <div>
                    <h2 id="ossModalTitle" class="text-lg font-bold text-white">New Application</h2>
                    <p id="ossModalSubtitle" class="text-yellow-100 text-xs mt-0.5">Application for Statutory Right of Occupancy</p>
                </div>
                <button type="button" onclick="ossCloseModal()"
                        class="text-white/80 hover:text-white transition p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- ── Tab Selector (Application Type) ── --}}
            <div class="px-6 pt-4 pb-2 border-b border-slate-100 bg-slate-50/60">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Select Land Type</p>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="ossSelectType('residential')" id="ossTab_residential"
                            class="oss-tab-btn px-5 py-2 rounded-lg text-sm font-semibold transition
                                   bg-yellow-500 text-white shadow-sm">
                        <i data-lucide="home" class="w-4 h-4 inline -mt-0.5 mr-1"></i> Residential Land
                    </button>
                    <button type="button" onclick="ossSelectType('commercial')" id="ossTab_commercial"
                            class="oss-tab-btn px-5 py-2 rounded-lg text-sm font-semibold transition
                                   bg-white text-slate-500 border border-slate-200 hover:bg-slate-50">
                        <i data-lucide="building-2" class="w-4 h-4 inline -mt-0.5 mr-1"></i> Commercial Land
                    </button>
                    <button type="button" onclick="ossSelectType('industrial')" id="ossTab_industrial"
                            class="oss-tab-btn px-5 py-2 rounded-lg text-sm font-semibold transition
                                   bg-white text-slate-500 border border-slate-200 hover:bg-slate-50">
                        <i data-lucide="factory" class="w-4 h-4 inline -mt-0.5 mr-1"></i> Industrial Land
                    </button>
                    <button type="button" onclick="ossSelectType('agricultural')" id="ossTab_agricultural"
                            class="oss-tab-btn px-5 py-2 rounded-lg text-sm font-semibold transition
                                   bg-white text-slate-500 border border-slate-200 hover:bg-slate-50">
                        <i data-lucide="wheat" class="w-4 h-4 inline -mt-0.5 mr-1"></i> Agricultural Land
                    </button>
                </div>
            </div>

            {{-- ── Body (scrollable form) ── --}}
            <div class="flex-1 overflow-y-auto px-6 py-5">
                <form id="ossApplicationForm" novalidate>
                    <input type="hidden" id="oss_id" name="id" value="">
                    <input type="hidden" id="oss_application_type" name="application_type" value="residential">

                    {{-- File Number Lookup (via Global File Number Selector → PRA) --}}
                    <div class="mb-5">
                        <label class="block text-xs font-bold text-slate-700 mb-1">File Number Lookup</label>
                        <div class="flex items-center gap-2">
                            <input type="text" id="oss_file_no_display" readonly
                                   placeholder="Click 'Select File Number' to choose"
                                   class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-50 cursor-pointer focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                   onclick="ossOpenFileNumberSelector()">
                            <input type="hidden" id="oss_file_no" name="file_no" value="">
                            <input type="hidden" id="oss_file_indexing_id" name="file_indexing_id" value="">
                            <button type="button" onclick="ossOpenFileNumberSelector()"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition shadow-sm whitespace-nowrap">
                                <i data-lucide="file-search" class="w-4 h-4"></i>
                                Select File Number
                            </button>
                            <button type="button" onclick="ossClearFileNumberSelection()" id="oss_file_no_clear_btn"
                                    class="hidden px-2 py-2 text-red-500 hover:text-red-700 transition" title="Clear selection">
                                <i data-lucide="x-circle" class="w-5 h-5"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Use the file number selector to look up PRA records and prefill this form.</p>
                        <div id="oss_file_indexing_card" class="hidden mt-2 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-blue-700 uppercase tracking-wider">Linked File Record</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1 text-[11px]" id="oss_file_indexing_details"></div>
                        </div>
                        <div id="oss_file_indexing_empty" class="text-xs text-slate-700 mt-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                            No file selected.
                        </div>
                    </div>

                    {{-- ── Occupancy Permit Details (synced back onto the PRA OP row on save) ── --}}
                    <div class="mb-5 border border-slate-200 rounded-xl overflow-hidden hidden" id="oss_opd_section">
                        <div class="flex items-center justify-between px-4 py-3 bg-slate-50 border-b border-slate-200">
                            <div class="flex items-center gap-2">
                                <i data-lucide="stamp" class="w-4 h-4 text-amber-600"></i>
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Occupancy Permit Details</span>
                                <span id="oss_opd_source_badge" class="hidden text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">From record</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" id="oss_opd_edit_btn" onclick="ossToggleOpdEdit()"
                                        class="hidden inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                    <span id="oss_opd_edit_btn_text">Edit</span>
                                </button>
                            </div>
                        </div>

                        <div id="oss_opd_body" class="hidden px-4 py-4 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Instrument Type</label>
                                    <select id="oss_opd_instrument_type" disabled
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-100 text-slate-500">
                                        <option value="Occupancy Permit" selected>Occupancy Permit</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                                    <select id="oss_opd_status"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                        <option value="Normal" selected>Normal</option>
                                        <option value="Normal Cancellation">Normal Cancellation</option>
                                        <option value="Total Cancellation">Total Cancellation</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Occupancy Permit (OP) Type</label>
                                    <select id="oss_opd_op_type"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                        <option value="">Select OP Type</option>
                                        <option value="Resettlement">Resettlement</option>
                                        <option value="Direct Allocation">Direct Allocation</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">OP Serial Number <span class="text-red-400">*</span></label>
                                    <input type="text" id="oss_opd_op_serial_number" placeholder="Enter OP serial number"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Transaction Date</label>
                                    <input type="date" id="oss_opd_date"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">File Number</label>
                                    <input type="text" id="oss_opd_file_number" readonly
                                           placeholder="Auto-populated from file number"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-600">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">TP No.</label>
                                    <select id="oss_opd_tp_no"
                                            class="oss-searchable-select w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                        <option value="">Select TP No.</option>
                                        <option data-options-slot="tp" hidden></option>
                                        <option value="Other">Other (specify)</option>
                                    </select>
                                </div>
                                <div id="oss-opd-tp-no-other-wrapper" class="hidden">
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Specify TP No.</label>
                                    <input type="text" id="oss_opd_tp_no_other" placeholder="Enter TP number"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Land Use</label>
                                    <select id="oss_opd_land_use"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                        <option value="">Select Land Use</option>
                                        <option value="RESIDENTIAL">Residential</option>
                                        <option value="COMMERCIAL">Commercial</option>
                                        <option value="INDUSTRIAL">Industrial</option>
                                        <option value="AGRICULTURAL">Agricultural</option>
                                        <option value="MIXED USE">Mixed Use</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Grantor</label>
                                    <input type="text" id="oss_opd_grantor" readonly value="KANO STATE GOVERNMENT"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-100 text-slate-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Grantee</label>
                                    <input type="text" id="oss_opd_grantee" placeholder="Enter grantee name"
                                           style="text-transform: uppercase;"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Serial No</label>
                                    <input type="text" id="oss_opd_serial_no" inputmode="numeric" pattern="[0-9]*"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, ''); ossSyncOpdPageNo();"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                                <div>
                                    {{-- Page No always mirrors Serial No. --}}
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Page No</label>
                                    <input type="text" id="oss_opd_page_no" readonly
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Vol No</label>
                                    <input type="text" id="oss_opd_vol_no" inputmode="numeric" pattern="[0-9]*"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Deeds Time</label>
                                    <input type="time" id="oss_opd_deeds_time"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Deeds Date</label>
                                    <input type="date" id="oss_opd_deeds_date"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                            </div>

                            {{-- ── Location (moved from the standalone Location Builder — belongs to the OP) ── --}}
                            <div class="border-t border-slate-200 pt-4">
                                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">Location</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">Plot No</label>
                                        <input type="text" id="oss_lb_plot_no" placeholder="e.g. 486A"
                                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">District</label>
                                        <select id="oss_lb_district" class="oss-searchable-select w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">Select district</option>
                                            <option data-options-slot="districts" hidden></option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">LGA</label>
                                        <select id="oss_lb_lga" class="oss-searchable-select w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <option value="">Select LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Full Location</label>
                                    <input type="text" id="oss_lb_full_location" placeholder="Composed location"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                    <p class="text-[11px] text-slate-500 mt-1">Built from the fields above; you can also edit it directly.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ╔══════════════════════════════════════════════════════════════════════╗
                         ║  RESIDENTIAL LAND FORM                                              ║
                         ╚══════════════════════════════════════════════════════════════════════╝ --}}
                    <div id="ossResidentialForm">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-2 mb-5">
                            <p class="text-xs font-bold text-yellow-700 uppercase tracking-wider">Application for Statutory Right of Occupancy — Residential Land</p>
                        </div>

                        <div class="mb-5 grid grid-cols-1 lg:grid-cols-4 gap-4 items-start">
                            {{-- 1. Applicant's Full Name --}}
                            <div class="lg:col-span-3">
                                <label class="block text-xs font-bold text-slate-700 mb-1">1. Applicant's Full Name <span class="text-red-400">*</span></label>
                                <input type="text" id="oss_applicant_name" name="applicant_name" required
                                       placeholder="Enter full name"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">(A) Age</label>
                                        <input type="text" id="oss_age" name="age" placeholder="e.g. 35"
                                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">(B) Sex</label>
                                        <select id="oss_sex" name="sex"
                                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                            <option value="">-- Select --</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">(C) Marital Status</label>
                                        <select id="oss_marital_status" name="marital_status"
                                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                            <option value="">-- Select --</option>
                                            <option value="Single">Single</option>
                                            <option value="Married">Married</option>
                                            <option value="Divorced">Divorced</option>
                                            <option value="Widowed">Widowed</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(D) If Married woman, give name and address of husband</label>
                                    <input type="text" id="oss_husband_name_address" name="husband_name_address"
                                           placeholder="Husband's name and address"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                </div>
                            </div>

                            {{-- Passport Photograph --}}
                            <div class="lg:col-span-1 flex lg:justify-end justify-start">
                                <div class="text-center">
                                    <div id="oss_res_passport_preview"
                                         class="w-[110px] h-[130px] border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50 overflow-hidden cursor-pointer hover:border-yellow-400 transition"
                                         onclick="document.getElementById('oss_res_passport_input').click()">
                                        <div id="oss_res_passport_placeholder" class="text-center px-2">
                                            <i data-lucide="camera" class="w-6 h-6 text-slate-400 mx-auto mb-1"></i>
                                            <p class="text-[10px] text-slate-400 leading-tight">Click to upload<br>Passport Photo</p>
                                        </div>
                                        <img id="oss_res_passport_img" src="" alt="Passport" class="hidden w-full h-full object-cover">
                                    </div>
                                    <input type="file" id="oss_res_passport_input" name="passport_photo" accept="image/*" class="hidden" required
                                           onchange="ossResPreviewPassport(this)">
                                    <p class="text-[10px] text-slate-500 mt-1">* Required</p>
                                    <button type="button" id="oss_res_passport_remove" class="hidden mt-1 text-[10px] text-red-500 hover:text-red-700"
                                            onclick="ossResRemovePassport()">Remove</button>
                                    <p id="oss_res_passport_error" class="hidden text-[10px] text-red-500 mt-1">Passport photo is required</p>
                                </div>
                            </div>
                        </div>

                        {{-- 2. Residential Address --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">2. Residential Address <span class="text-slate-400 text-[10px] font-normal">(P.O. Box must not be given)</span></label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-green-50/60 border border-green-200 mt-1">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                    <input type="text" id="oss_res_addr_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g., 12A">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                    <input type="text" id="oss_res_addr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g., 45">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                    <select id="oss_res_addr_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT STREET</option>
                                        <option data-options-slot="streets" hidden></option>
                                        <option value="Other">OTHER</option>
                                    </select>
                                </div>
                                <div id="oss-res-addr-street-other-wrapper" class="md:col-span-2 hidden">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                    <input type="text" id="oss_res_addr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type street name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                    <select id="oss_res_addr_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT DISTRICT</option>
                                        <option data-options-slot="districts" hidden></option>
                                        <option value="Other">OTHER</option>
                                    </select>
                                </div>
                                <div id="oss-res-addr-district-other-wrapper" class="hidden">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                    <input type="text" id="oss_res_addr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type district name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                    <select id="oss_res_addr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT LGA</option>
                                        @foreach($lgas as $lga)
                                            <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                    <select id="oss_res_addr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT STATE</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <textarea id="oss_residential_address" name="residential_address" rows="2" readonly
                                      placeholder="Address will be built automatically..."
                                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                        </div>

                        {{-- 3. Correspondence Address --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">3. Correspondence Address</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-green-50/60 border border-green-200 mt-1">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                    <input type="text" id="oss_res_corr_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g., 12A">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                    <input type="text" id="oss_res_corr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g., 45">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                    <select id="oss_res_corr_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT STREET</option>
                                        <option data-options-slot="streets" hidden></option>
                                        <option value="Other">OTHER</option>
                                    </select>
                                </div>
                                <div id="oss-res-corr-street-other-wrapper" class="md:col-span-2 hidden">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                    <input type="text" id="oss_res_corr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type street name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                    <select id="oss_res_corr_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT DISTRICT</option>
                                        <option data-options-slot="districts" hidden></option>
                                        <option value="Other">OTHER</option>
                                    </select>
                                </div>
                                <div id="oss-res-corr-district-other-wrapper" class="hidden">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                    <input type="text" id="oss_res_corr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type district name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                    <select id="oss_res_corr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT LGA</option>
                                        @foreach($lgas as $lga)
                                            <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                    <select id="oss_res_corr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT STATE</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <textarea id="oss_res_correspondence_address" name="correspondence_address" rows="2" readonly
                                      placeholder="Address will be built automatically..."
                                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                        </div>

                        {{-- 4. Email & Phone --}}
                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">4. Email <span class="text-slate-400 text-[10px] font-normal">(If any)</span></label>
                                <input type="email" id="oss_res_email" name="email" placeholder="email@example.com"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Phone No.</label>
                                <input type="text" id="oss_res_phone" name="phone" placeholder="e.g. 08012345678" data-phone-ng
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                            </div>
                        </div>

                        {{-- 5. Business Address --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">5. Business Address <span class="text-slate-400 text-[10px] font-normal">(If different from 3 above)</span></label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-green-50/60 border border-green-200 mt-1">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                    <input type="text" id="oss_res_biz_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g., 12A">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                    <input type="text" id="oss_res_biz_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g., 45">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                    <select id="oss_res_biz_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT STREET</option>
                                        <option data-options-slot="streets" hidden></option>
                                        <option value="Other">OTHER</option>
                                    </select>
                                </div>
                                <div id="oss-res-biz-street-other-wrapper" class="md:col-span-2 hidden">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                    <input type="text" id="oss_res_biz_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type street name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                    <select id="oss_res_biz_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT DISTRICT</option>
                                        <option data-options-slot="districts" hidden></option>
                                        <option value="Other">OTHER</option>
                                    </select>
                                </div>
                                <div id="oss-res-biz-district-other-wrapper" class="hidden">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                    <input type="text" id="oss_res_biz_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type district name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                    <select id="oss_res_biz_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT LGA</option>
                                        @foreach($lgas as $lga)
                                            <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                    <select id="oss_res_biz_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">SELECT STATE</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <input type="text" id="oss_business_address" name="business_address" readonly
                                   placeholder="Address will be built automatically..."
                                   class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2">

                            <div class="mt-3">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Nationality <span class="text-slate-400 text-[10px]">(Indicate if Naturalized)</span></label>
                                <input type="text" id="oss_res_nationality" name="nationality" placeholder="e.g. Nigerian"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                            </div>
                        </div>

                        {{-- 6. If Nigerian --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">6. If Nigerian</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(A) State of Origin</label>
                                    <select id="oss_res_state_of_origin" name="state_of_origin"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">-- Select State --</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}">{{ $state->StateName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(B) L.G. of Origin</label>
                                    <select id="oss_res_lga" name="lga"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                        <option value="">-- Select LGA --</option>
                                        @foreach($lgas as $lga)
                                            <option value="{{ $lga->name }}">{{ $lga->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- 7. Occupation --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">7. Occupation</label>
                            <select id="oss_occupation" name="occupation"
                                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                <option value="">-- Select Occupation --</option>
                                <option value="Civil Servant">Civil Servant</option>
                                <option value="Trader">Trader</option>
                                <option value="Farmer">Farmer</option>
                                <option value="House Wife">House Wife</option>
                                <option value="Unemployed">Unemployed</option>
                                <option value="Student">Student</option>
                                <option value="Teacher">Teacher</option>
                                <option value="Lecturer">Lecturer</option>
                                <option value="Business Man">Business Man</option>
                                <option value="Banker">Banker</option>
                                <option value="Accountant">Accountant</option>
                                <option value="Medical Practitioner">Medical Practitioner</option>
                                <option value="Military Personnel">Military Personnel</option>
                                <option value="Law Enforcement Officer">Law Enforcement Officer</option>
                                <option value="Lawyer">Lawyer</option>
                                <option value="Judge">Judge</option>
                                <option value="Priest">Priest</option>
                                <option value="Sportsman">Sportsman</option>
                                <option value="Musician">Musician</option>
                                <option value="Architect">Architect</option>
                                <option value="Engineer">Engineer</option>
                                <option value="IT Professional">IT Professional</option>
                                <option value="Pharmacist">Pharmacist</option>
                                <option value="Financial Analyst">Financial Analyst</option>
                                <option value="Real Estate Agent">Real Estate Agent</option>
                                <option value="Real Estate Developer">Real Estate Developer</option>
                                <option value="Contractor">Contractor</option>
                                <option value="Journalist">Journalist</option>
                                <option value="Public Office Holder">Public Office Holder</option>
                                <option value="Secretary">Secretary</option>
                                <option value="Computer Operator">Computer Operator</option>
                                <option value="Town Planner">Town Planner</option>
                                <option value="Surveyor">Surveyor</option>
                                <option value="Hair Dresser">Hair Dresser</option>
                                <option value="Barber">Barber</option>
                                <option value="Consultant">Consultant</option>
                                <option value="Other">Other</option>
                            </select>

                            <div class="mt-3">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">(a) Annual Income</label>
                                <input type="text" id="oss_res_annual_income" name="annual_income" placeholder="e.g. ₦1,500,000"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                            </div>
                        </div>

                        {{-- 8. Previous Allocation --}}
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-slate-700 mb-1">8. Have you been allocated any Residential Plot before?</label>
                            <select id="oss_res_prev_allocated" name="prev_allocated"
                                    onchange="document.getElementById('ossResPrevDetails').classList.toggle('hidden', this.value !== 'Yes')"
                                    class="w-40 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                <option value="">-- Select --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>

                            <div id="ossResPrevDetails" class="mt-3 hidden">
                                <p class="text-[11px] font-semibold text-slate-500 mb-2">If yes, state:</p>
                                @for($i = 1; $i <= 3; $i++)
                                <div class="grid grid-cols-3 gap-3 mb-2 pl-4">
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-0.5">({{ $i }}) a. Plot No.</label>
                                        <input type="text" id="oss_prev_plot_{{ $i }}" placeholder="Plot No."
                                               class="oss-prev-field w-full border border-slate-200 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-green-500/20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-0.5">b. Location</label>
                                        <input type="text" id="oss_prev_location_{{ $i }}" placeholder="Location"
                                               class="oss-prev-field w-full border border-slate-200 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-green-500/20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-0.5">c. Cert of Occupancy No.</label>
                                        <input type="text" id="oss_prev_cert_{{ $i }}" placeholder="C of O No."
                                               class="oss-prev-field w-full border border-slate-200 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-green-500/20">
                                    </div>
                                </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                    {{-- END RESIDENTIAL --}}


                    {{-- ╔══════════════════════════════════════════════════════════════════════╗
                         ║  COMMERCIAL LAND FORM                                               ║
                         ╚══════════════════════════════════════════════════════════════════════╝ --}}
                    <div id="ossCommercialForm" class="hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-2 mb-5">
                            <p class="text-xs font-bold text-blue-700 uppercase tracking-wider">Application for Statutory Right of Occupancy — Commercial Land</p>
                        </div>

                        {{-- 1. Applicant's Full Name --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">1. Applicant's Full Name <span class="text-red-400">*</span></label>
                            <input type="text" id="oss_com_applicant_name" placeholder="Enter full name"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>

                        {{-- 2. Nationality --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">2. Nationality <span class="text-slate-400 text-[10px] font-normal">(Indicate if Naturalized)</span></label>
                            <input type="text" id="oss_com_nationality" placeholder="e.g. Nigerian"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">

                            <p class="text-[11px] font-semibold text-slate-500 mt-3 mb-2">If Nigerian:</p>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(a) State of Origin</label>
                                    <select id="oss_com_state_of_origin"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                        <option value="">-- Select State --</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}">{{ $state->StateName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(b) Home Domicile</label>
                                    <input type="text" id="oss_com_home_domicile" placeholder="Home domicile"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(c) Occupation or Business</label>
                                    <input type="text" id="oss_com_occupation_or_business" placeholder="Occupation or business"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(d) Nature of Commerce Activity</label>
                                    <input type="text" id="oss_com_nature_of_commerce" placeholder="Nature of commerce activity"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">(e) Annual Income</label>
                                <input type="text" id="oss_com_annual_income" placeholder="e.g. ₦5,000,000"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                        </div>

                        {{-- 3. Company Details --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">3. Company Details</label>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(a) Company is Registered Under</label>
                                    <input type="text" id="oss_com_company_registered_under" placeholder="e.g. CAC"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(b) Registration Particulars</label>
                                    <input type="text" id="oss_com_registration_particulars" placeholder="Registration details"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(c) Business Location</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-blue-50/60 border border-blue-200 mt-1">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">House No</label>
                                            <input type="text" id="oss_com_biz_house" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="e.g., 12A">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Plot No</label>
                                            <input type="text" id="oss_com_biz_plot" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="e.g., 45">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Street Name</label>
                                            <select id="oss_com_biz_street" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="">SELECT STREET</option>
                                                <option data-options-slot="streets" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-com-biz-street-other-wrapper" class="md:col-span-2 hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                            <input type="text" id="oss_com_biz_street_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Type street name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">District</label>
                                            <select id="oss_com_biz_district" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="">SELECT DISTRICT</option>
                                                <option data-options-slot="districts" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-com-biz-district-other-wrapper" class="hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify District</label>
                                            <input type="text" id="oss_com_biz_district_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Type district name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">LGA</label>
                                            <select id="oss_com_biz_lga" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="">SELECT LGA</option>
                                                @foreach($lgas as $lga)
                                                    <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">State</label>
                                            <select id="oss_com_biz_state" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="">SELECT STATE</option>
                                                @foreach($states as $state)
                                                    <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <input type="text" id="oss_com_business_location" readonly
                                           placeholder="Address will be built automatically..."
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs bg-gray-50 text-slate-600 mt-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(d) Correspondence Address</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-blue-50/60 border border-blue-200 mt-1">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">House No</label>
                                            <input type="text" id="oss_com_corr_house" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="e.g., 12A">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Plot No</label>
                                            <input type="text" id="oss_com_corr_plot" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="e.g., 45">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Street Name</label>
                                            <select id="oss_com_corr_street" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="">SELECT STREET</option>
                                                <option data-options-slot="streets" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-com-corr-street-other-wrapper" class="md:col-span-2 hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                            <input type="text" id="oss_com_corr_street_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Type street name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">District</label>
                                            <select id="oss_com_corr_district" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="">SELECT DISTRICT</option>
                                                <option data-options-slot="districts" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-com-corr-district-other-wrapper" class="hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify District</label>
                                            <input type="text" id="oss_com_corr_district_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Type district name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">LGA</label>
                                            <select id="oss_com_corr_lga" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="">SELECT LGA</option>
                                                @foreach($lgas as $lga)
                                                    <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">State</label>
                                            <select id="oss_com_corr_state" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                                <option value="">SELECT STATE</option>
                                                @foreach($states as $state)
                                                    <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <input type="text" id="oss_com_correspondence_address" readonly
                                           placeholder="Address will be built automatically..."
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs bg-gray-50 text-slate-600 mt-2">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">(e) Annual Income or Anticipation Income</label>
                                <input type="text" id="oss_com_annual_income_anticipation" placeholder="Annual income or anticipated income"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            </div>
                        </div>

                        {{-- 4. Previous Allocation --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">4. Have you ever been allocated any Commercial Land before?</label>
                            <select id="oss_com_prev_allocated"
                                    onchange="document.getElementById('ossComPrevDetails').classList.toggle('hidden', this.value !== 'Yes')"
                                    class="w-40 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                <option value="">-- Select --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>

                            <div id="ossComPrevDetails" class="mt-3 hidden">
                                <div class="mb-3">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(a) If yes, state when and where, and Certificate of Occupancy number</label>
                                    <textarea id="oss_com_prev_allocation_details" rows="2" placeholder="When, where, and C of O number..."
                                              class="w-full border border-slate-200 rounded px-3 py-2 text-xs focus:ring-1 focus:ring-blue-500/20 resize-none"></textarea>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(b) Purpose for which land above is being used</label>
                                    <input type="text" id="oss_com_prev_land_purpose" placeholder="Current use of previously allocated land"
                                           class="w-full border border-slate-200 rounded px-3 py-2 text-xs focus:ring-1 focus:ring-blue-500/20">
                                </div>
                            </div>
                        </div>

                        {{-- 5. Purpose --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">5. Purpose for which land applied for is required</label>
                            <textarea id="oss_com_purpose" rows="2" placeholder="State the purpose..."
                                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none"></textarea>
                        </div>

                        {{-- 6. Activities --}}
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-slate-700 mb-1">6. What type of activities are you intend to undertake?</label>
                            <textarea id="oss_com_intended_activities" rows="2" placeholder="Describe the intended activities..."
                                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none"></textarea>
                        </div>
                    </div>
                    {{-- END COMMERCIAL --}}


                    {{-- ╔══════════════════════════════════════════════════════════════════════╗
                         ║  INDUSTRIAL LAND FORM                                               ║
                         ╚══════════════════════════════════════════════════════════════════════╝ --}}
                    <div id="ossIndustrialForm" class="hidden">
                        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-2 mb-5">
                            <p class="text-xs font-bold text-red-700 uppercase tracking-wider">Application for Statutory Right of Occupancy — Industrial Land Only</p>
                        </div>

                        {{-- 1. Name of Applicant or Company --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">1. Name of Applicant or Company <span class="text-red-400">*</span></label>
                            <input type="text" id="oss_ind_applicant_name" placeholder="Enter applicant or company name"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                        </div>

                        {{-- 2. Nationality --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">2. Nationality <span class="text-slate-400 text-[10px] font-normal">(Indicate if Naturalized)</span></label>
                            <input type="text" id="oss_ind_nationality" placeholder="e.g. Nigerian"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">

                            <p class="text-[11px] font-semibold text-slate-500 mt-3 mb-2">If Nigerian:</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(a) State of Origin</label>
                                    <select id="oss_ind_state_of_origin"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                        <option value="">-- Select State --</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}">{{ $state->StateName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(b) Home Domicile</label>
                                    <input type="text" id="oss_ind_home_domicile" placeholder="Home domicile"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                </div>
                            </div>
                        </div>

                        {{-- 3. Occupation & Nature --}}
                        <div class="mb-5">
                            <div class="mb-3">
                                <label class="block text-xs font-bold text-slate-700 mb-1">3. Occupation or Business</label>
                                <input type="text" id="oss_ind_occupation_or_business" placeholder="Occupation or business"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">4. Nature of Occupation or Business</label>
                                <input type="text" id="oss_ind_nature_of_occupation" placeholder="Describe the nature..."
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            </div>
                        </div>

                        {{-- 5. Company Registration --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-2">5. Registration Details</label>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(A) Applicant/Company is registered under</label>
                                    <input type="text" id="oss_ind_company_registered_under" placeholder="e.g. CAC"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(B) Registration Particulars</label>
                                    <input type="text" id="oss_ind_registration_particulars" placeholder="Registration details"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(C) Business Location</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-red-50/60 border border-red-200 mt-1">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">House No</label>
                                            <input type="text" id="oss_ind_biz_house" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500" placeholder="e.g., 12A">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Plot No</label>
                                            <input type="text" id="oss_ind_biz_plot" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500" placeholder="e.g., 45">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Street Name</label>
                                            <select id="oss_ind_biz_street" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                                <option value="">SELECT STREET</option>
                                                <option data-options-slot="streets" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-ind-biz-street-other-wrapper" class="md:col-span-2 hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                            <input type="text" id="oss_ind_biz_street_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500" placeholder="Type street name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">District</label>
                                            <select id="oss_ind_biz_district" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                                <option value="">SELECT DISTRICT</option>
                                                <option data-options-slot="districts" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-ind-biz-district-other-wrapper" class="hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify District</label>
                                            <input type="text" id="oss_ind_biz_district_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500" placeholder="Type district name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">LGA</label>
                                            <select id="oss_ind_biz_lga" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                                <option value="">SELECT LGA</option>
                                                @foreach($lgas as $lga)
                                                    <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">State</label>
                                            <select id="oss_ind_biz_state" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                                <option value="">SELECT STATE</option>
                                                @foreach($states as $state)
                                                    <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <input type="text" id="oss_ind_business_location" readonly
                                           placeholder="Address will be built automatically..."
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs bg-gray-50 text-slate-600 mt-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(D) Correspondence Address</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-red-50/60 border border-red-200 mt-1">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">House No</label>
                                            <input type="text" id="oss_ind_corr_house" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500" placeholder="e.g., 12A">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Plot No</label>
                                            <input type="text" id="oss_ind_corr_plot" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500" placeholder="e.g., 45">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Street Name</label>
                                            <select id="oss_ind_corr_street" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                                <option value="">SELECT STREET</option>
                                                <option data-options-slot="streets" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-ind-corr-street-other-wrapper" class="md:col-span-2 hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                            <input type="text" id="oss_ind_corr_street_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500" placeholder="Type street name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">District</label>
                                            <select id="oss_ind_corr_district" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                                <option value="">SELECT DISTRICT</option>
                                                <option data-options-slot="districts" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-ind-corr-district-other-wrapper" class="hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify District</label>
                                            <input type="text" id="oss_ind_corr_district_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500" placeholder="Type district name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">LGA</label>
                                            <select id="oss_ind_corr_lga" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                                <option value="">SELECT LGA</option>
                                                @foreach($lgas as $lga)
                                                    <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">State</label>
                                            <select id="oss_ind_corr_state" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                                <option value="">SELECT STATE</option>
                                                @foreach($states as $state)
                                                    <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <input type="text" id="oss_ind_correspondence_address" readonly
                                           placeholder="Address will be built automatically..."
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs bg-gray-50 text-slate-600 mt-2">
                                </div>
                            </div>
                        </div>

                        {{-- 6. Annual Income / Turnover --}}
                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">6. Annual Income or Turnover</label>
                                <input type="text" id="oss_ind_annual_income_turnover" placeholder="e.g. ₦10,000,000"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">7. Number of Employees</label>
                                <input type="text" id="oss_ind_number_of_employees" placeholder="e.g. 50"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            </div>
                        </div>

                        {{-- 8. Previous Allocation --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">8. Have you ever been allocated Industrial Land before?</label>
                            <select id="oss_ind_prev_allocated"
                                    onchange="document.getElementById('ossIndPrevDetails').classList.toggle('hidden', this.value !== 'Yes')"
                                    class="w-40 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                                <option value="">-- Select --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>

                            <div id="ossIndPrevDetails" class="mt-3 hidden">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">If yes, indicate the date of the allocation and Certificate of Occupancy Number</label>
                                <textarea id="oss_ind_prev_allocation_details" rows="2" placeholder="Date, location, and C of O number..."
                                          class="w-full border border-slate-200 rounded px-3 py-2 text-xs focus:ring-1 focus:ring-red-500/20 resize-none"></textarea>
                            </div>
                        </div>

                        {{-- 9. Purpose --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">9. Purpose for which land applied for is acquired</label>
                            <textarea id="oss_ind_purpose" rows="2" placeholder="State the purpose..."
                                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 resize-none"></textarea>

                            <div class="mt-3">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Nature of the Industrial</label>
                                <input type="text" id="oss_ind_nature_of_industrial" placeholder="Describe the nature of industrial activity"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            </div>

                            <div class="mt-3">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Give any Special requirement needed for waste disposal</label>
                                <textarea id="oss_ind_waste_disposal" rows="2" placeholder="Special waste disposal requirements..."
                                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                    {{-- END INDUSTRIAL --}}


                    {{-- ╔══════════════════════════════════════════════════════════════════════╗
                         ║  AGRICULTURAL LAND FORM                                             ║
                         ╚══════════════════════════════════════════════════════════════════════╝ --}}
                    <div id="ossAgriculturalForm" class="hidden">
                        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-2 mb-5">
                            <p class="text-xs font-bold text-green-700 uppercase tracking-wider">Application for Statutory Right of Occupancy — Agricultural Land Only</p>
                        </div>

                        {{-- 1. Name of Applicant or Company --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">1. Name of Applicant or Company <span class="text-red-400">*</span></label>
                            <input type="text" id="oss_agr_applicant_name" placeholder="Enter applicant or company name"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        </div>

                        {{-- 2. Nationality --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">2. Nationality <span class="text-slate-400 text-[10px] font-normal">(Indicate if Naturalized)</span></label>
                            <input type="text" id="oss_agr_nationality" placeholder="e.g. Nigerian"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">

                            <p class="text-[11px] font-semibold text-slate-500 mt-3 mb-2">If Nigerian:</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(a) State of Origin</label>
                                    <select id="oss_agr_state_of_origin"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                        <option value="">-- Select State --</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}">{{ $state->StateName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(b) Home Domicile</label>
                                    <input type="text" id="oss_agr_home_domicile" placeholder="Home domicile"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                </div>
                            </div>
                        </div>

                        {{-- 3. Occupation & Nature --}}
                        <div class="mb-5">
                            <div class="mb-3">
                                <label class="block text-xs font-bold text-slate-700 mb-1">3. Occupation or Business</label>
                                <input type="text" id="oss_agr_occupation_or_business" placeholder="Occupation or business"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">4. Nature of Occupation or Business</label>
                                <input type="text" id="oss_agr_nature_of_occupation" placeholder="Describe the nature..."
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                            </div>
                        </div>

                        {{-- 5. Company Registration --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-2">5. Registration Details</label>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(A) Applicant/Company is registered under</label>
                                    <input type="text" id="oss_agr_company_registered_under" placeholder="e.g. CAC"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(B) Registration Particulars</label>
                                    <input type="text" id="oss_agr_registration_particulars" placeholder="Registration details"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(C) Business Location</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-emerald-50/60 border border-emerald-200 mt-1">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">House No</label>
                                            <input type="text" id="oss_agr_biz_house" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="e.g., 12A">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Plot No</label>
                                            <input type="text" id="oss_agr_biz_plot" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="e.g., 45">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Street Name</label>
                                            <select id="oss_agr_biz_street" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                                <option value="">SELECT STREET</option>
                                                <option data-options-slot="streets" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-agr-biz-street-other-wrapper" class="md:col-span-2 hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                            <input type="text" id="oss_agr_biz_street_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Type street name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">District</label>
                                            <select id="oss_agr_biz_district" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                                <option value="">SELECT DISTRICT</option>
                                                <option data-options-slot="districts" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-agr-biz-district-other-wrapper" class="hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify District</label>
                                            <input type="text" id="oss_agr_biz_district_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Type district name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">LGA</label>
                                            <select id="oss_agr_biz_lga" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                                <option value="">SELECT LGA</option>
                                                @foreach($lgas as $lga)
                                                    <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">State</label>
                                            <select id="oss_agr_biz_state" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                                <option value="">SELECT STATE</option>
                                                @foreach($states as $state)
                                                    <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <input type="text" id="oss_agr_business_location" readonly
                                           placeholder="Address will be built automatically..."
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs bg-gray-50 text-slate-600 mt-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">(D) Correspondence Address</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-emerald-50/60 border border-emerald-200 mt-1">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">House No</label>
                                            <input type="text" id="oss_agr_corr_house" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="e.g., 12A">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Plot No</label>
                                            <input type="text" id="oss_agr_corr_plot" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="e.g., 45">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Street Name</label>
                                            <select id="oss_agr_corr_street" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                                <option value="">SELECT STREET</option>
                                                <option data-options-slot="streets" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-agr-corr-street-other-wrapper" class="md:col-span-2 hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                            <input type="text" id="oss_agr_corr_street_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Type street name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">District</label>
                                            <select id="oss_agr_corr_district" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                                <option value="">SELECT DISTRICT</option>
                                                <option data-options-slot="districts" hidden></option>
                                                <option value="Other">OTHER</option>
                                            </select>
                                        </div>
                                        <div id="oss-agr-corr-district-other-wrapper" class="hidden">
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Specify District</label>
                                            <input type="text" id="oss_agr_corr_district_other" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Type district name">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">LGA</label>
                                            <select id="oss_agr_corr_lga" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                                <option value="">SELECT LGA</option>
                                                @foreach($lgas as $lga)
                                                    <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 mb-1">State</label>
                                            <select id="oss_agr_corr_state" class="w-full border border-slate-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                                <option value="">SELECT STATE</option>
                                                @foreach($states as $state)
                                                    <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <input type="text" id="oss_agr_correspondence_address" readonly
                                           placeholder="Address will be built automatically..."
                                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-xs bg-gray-50 text-slate-600 mt-2">
                                </div>
                            </div>
                        </div>

                        {{-- 6. Annual Income / Turnover --}}
                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">6. Annual Income or Turnover</label>
                                <input type="text" id="oss_agr_annual_income_turnover" placeholder="e.g. ₦10,000,000"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">7. Number of Employees</label>
                                <input type="text" id="oss_agr_number_of_employees" placeholder="e.g. 50"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                            </div>
                        </div>

                        {{-- 8. Previous Allocation --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">8. Have you ever been allocated Agricultural Land before?</label>
                            <select id="oss_agr_prev_allocated"
                                    onchange="document.getElementById('ossAgrPrevDetails').classList.toggle('hidden', this.value !== 'Yes')"
                                    class="w-40 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                <option value="">-- Select --</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>

                            <div id="ossAgrPrevDetails" class="mt-3 hidden">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">If yes, indicate the date of the allocation and Certificate of Occupancy Number</label>
                                <textarea id="oss_agr_prev_allocation_details" rows="2" placeholder="Date, location, and C of O number..."
                                          class="w-full border border-slate-200 rounded px-3 py-2 text-xs focus:ring-1 focus:ring-emerald-500/20 resize-none"></textarea>
                            </div>
                        </div>

                        {{-- 9. Purpose --}}
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">9. Purpose for which land applied for is acquired</label>
                            <textarea id="oss_agr_purpose" rows="2" placeholder="State the purpose..."
                                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 resize-none"></textarea>

                            <div class="mt-3">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Nature of the Agricultural Activity</label>
                                <input type="text" id="oss_agr_nature_of_agricultural" placeholder="Describe the nature of agricultural activity"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                            </div>

                            <div class="mt-3">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Give any Special requirement needed for waste disposal</label>
                                <textarea id="oss_agr_waste_disposal" rows="2" placeholder="Special waste disposal requirements..."
                                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                    {{-- END AGRICULTURAL --}}


                    {{-- ═══ Remarks (shared) ═══ --}}
                    <div class="border-t border-slate-200 pt-4 mt-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Remarks / Notes</label>
                        <textarea id="oss_remarks" name="remarks" rows="2"
                                  placeholder="Any additional notes..."
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none"></textarea>
                    </div>
                </form>
            </div>

            {{-- ── Footer ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50">
                <button type="button" onclick="ossCloseModal()"
                        class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="button" id="ossSubmitBtn" onclick="ossSaveApplication()"
                        class="inline-flex items-center gap-1.5 px-6 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow-sm transition">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span id="ossSubmitText">Save Application</span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ═══ View-only Detail Modal ═══ --}}
<div id="ossViewModal"
     class="fixed inset-0 z-[9999] hidden"
     aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="ossCloseViewModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-slate-600 to-slate-700">
                <div>
                    <h2 class="text-lg font-bold text-white">Application Details</h2>
                    <p id="ossViewSubtitle" class="text-slate-300 text-xs mt-0.5"></p>
                </div>
                <button type="button" onclick="ossCloseViewModal()"
                        class="text-white/80 hover:text-white transition p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="ossViewBody" class="flex-1 overflow-y-auto px-6 py-5 text-sm text-slate-700">
                {{-- Populated by JS --}}
            </div>
            <div class="flex items-center justify-end px-6 py-3 border-t border-slate-200 bg-slate-50">
                <button type="button" onclick="ossCloseViewModal()"
                        class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@extends('layouts.app')

@section('page-title')
    Mortgage Records
@endsection
@section('content')
<div class="flex-1 overflow-auto bg-slate-50">
    @include('admin.header', ['PageTitle' => 'Mortgage Records', 'PageDescription' => 'Comprehensive list of Deed of Mortgage and Tripartite Mortgage records from PRA, Instrument Capture, and File History Staging.'])
    
    <div class="p-6 space-y-6">
        {{-- ① Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">


               {{-- Daily Combined --}}
            <div class="premium-card group border-l-4 border-l-rose-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Captured Today</p>
                        <h3 class="text-2xl font-black text-rose-700 mt-0.5">{{ number_format($dailyRecords) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center group-hover:bg-rose-500 transition-colors duration-300">
                        <i data-lucide="calendar" class="w-5 h-5 text-rose-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

          

            {{-- Instrument Capture --}}
            <div class="premium-card group border-l-4 border-l-indigo-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Instrument Capture</p>
                        <h3 class="text-2xl font-black text-indigo-700 mt-0.5">{{ number_format($icTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center group-hover:bg-indigo-500 transition-colors duration-300">
                        <i data-lucide="scan-line" class="w-5 h-5 text-indigo-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- Property Records (PRA) --}}
            <div class="premium-card group border-l-4 border-l-emerald-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Property Records (PRA)</p>
                        <h3 class="text-2xl font-black text-emerald-700 mt-0.5">{{ number_format($praTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-500 transition-colors duration-300">
                        <i data-lucide="database" class="w-5 h-5 text-emerald-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- File History Staging --}}
            <div class="premium-card group border-l-4 border-l-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">File History Staging</p>
                        <h3 class="text-2xl font-black text-amber-700 mt-0.5">{{ number_format($fhsTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center group-hover:bg-amber-500 transition-colors duration-300">
                        <i data-lucide="archive" class="w-5 h-5 text-amber-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

   
              {{-- Total Records (All) --}}
            <div class="premium-card group border-l-4 border-l-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Records</p>
                        <h3 class="text-2xl font-black text-slate-800 mt-0.5">{{ number_format($totalRecords) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center group-hover:bg-slate-800 transition-colors duration-300">
                        <i data-lucide="layers" class="w-5 h-5 text-slate-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

        </div>

        {{-- ② Data Table Card --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-white flex items-center justify-between rounded-t-3xl">
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="table-2" class="w-5 h-5 text-indigo-500"></i>
                    Mortgage Records
                </h2>
                <div class="flex items-center gap-2">
                    <button id="refresh_btn" class="p-2 hover:bg-slate-100 rounded-lg transition text-slate-400 hover:text-indigo-600" title="Refresh Table">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto w-full">
                    <table id="mortgage_table" class="w-full text-sm min-w-[1400px]">
                        <thead>
                            <tr class="text-left text-xs font-bold text-slate-500 uppercase tracking-wider bg-slate-50">
                                <th class="px-4 py-4 rounded-tl-xl">S/N</th>
                                <th class="px-4 py-4">File Number</th>
                                <th class="px-4 py-4">Registration Particulars</th>
                                <th class="px-4 py-4">Associated S&amp;R</th>
                                <th class="px-4 py-4">Instrument Type</th>
                                <th class="px-4 py-4">Party 1 </th>
                                <th class="px-4 py-4">Party 2 </th>
                                <th class="px-4 py-4">Party 3</th>
                                <th class="px-4 py-4">Party 4</th>
                                <th class="px-4 py-4">Location</th>
                                <th class="px-4 py-4">Registration Date</th>
                                <th class="px-4 py-4">Created By</th>
                                <th class="px-4 py-4">Date Captured</th>
                                <th class="px-4 py-4 text-center">Source</th>
                                <th class="px-4 py-4 text-center rounded-tr-xl">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL 1: Instrument Capture (IC) — mirrors IC form layout
     ══════════════════════════════════════════════════════════ --}}
<div id="mortgage-modal-ic" class="mort-modal fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm mort-backdrop"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Edit Instrument Capture Record</h2>
                <p class="text-sm text-gray-500 mt-0.5" id="ic-modal-filelabel"></p>
            </div>
            <button class="mort-close p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <form id="ic-edit-form" class="flex-1 overflow-y-auto p-6 space-y-6">
            <input type="hidden" id="ic-record-id">

            {{-- File Number + Instrument Type + Entry Date --}}
            <div class="grid grid-cols-3 gap-6">
                <div class="border-l-4 border-blue-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">File Number</h3>
                    <input id="ic-file-number" type="text" readonly
                        class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 cursor-not-allowed font-mono">
                </div>
                <div class="border-l-4 border-purple-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Instrument Type <span class="text-red-500">*</span></h3>
                    <select id="ic-instrument-type" class="ic-input w-full">
                        <option value="Deed of Mortgage">Deed of Mortgage</option>
                        <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                    </select>
                </div>
                <div class="border-l-4 border-gray-400 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Entry Date</h3>
                    <input id="ic-entry-date" type="date" class="ic-input w-full">
                </div>
            </div>

            <hr class="border-gray-200">

            {{-- First Party (Mortgagor) | Second Party (Mortgagee) | Sidebar --}}
            <div class="grid grid-cols-3 gap-6">
                <div class="border-l-4 border-green-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Party 1 – Mortgagor</h3>
                    <div class="space-y-3">
                        <input id="ic-party-1-name" type="text" placeholder="Name" class="ic-input w-full">
                        <input id="ic-party-1-address" type="text" placeholder="Street Address" class="ic-input w-full">
                        <div class="grid grid-cols-2 gap-2">
                            <input id="ic-party-1-city" type="text" placeholder="City" class="ic-input">
                            <input id="ic-party-1-state" type="text" placeholder="State" class="ic-input">
                        </div>
                        <input id="ic-party-1-phone" type="text" placeholder="Phone" class="ic-input w-full">
                    </div>
                </div>
                <div class="border-l-4 border-amber-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Party 2 – Mortgagee</h3>
                    <div class="space-y-3">
                        <input id="ic-party-2-name" type="text" placeholder="Name" class="ic-input w-full">
                        <input id="ic-party-2-address" type="text" placeholder="Street Address" class="ic-input w-full">
                        <div class="grid grid-cols-2 gap-2">
                            <input id="ic-party-2-city" type="text" placeholder="City" class="ic-input">
                            <input id="ic-party-2-state" type="text" placeholder="State" class="ic-input">
                        </div>
                        <input id="ic-party-2-phone" type="text" placeholder="Phone" class="ic-input w-full">
                    </div>
                </div>
                <div class="border-l-4 border-rose-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Mortgage Details</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="ic-label">Bank Name</label>
                            <input id="ic-bank-name" type="text" placeholder="Bank name" class="ic-input w-full">
                        </div>
                        <div>
                            <label class="ic-label">Mortgage Date</label>
                            <input id="ic-mortgage-date" type="date" class="ic-input w-full">
                        </div>
                        <div>
                            <label class="ic-label">Governor Sign Date</label>
                            <input id="ic-governor-date" type="date" class="ic-input w-full">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tripartite extra parties (shown when Tripartite Mortgage) --}}
            <div id="ic-tripartite-section" class="hidden border border-blue-100 rounded-lg p-4 bg-blue-50/30 space-y-4">
                <h4 class="text-sm font-semibold text-blue-800">Additional Parties (Tripartite)</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="ic-label">Co-Mortgagor (Party 3)</label>
                        <input id="ic-party-3-name" type="text" placeholder="Name" class="ic-input w-full mb-2">
                        <input id="ic-party-3-address" type="text" placeholder="Address" class="ic-input w-full mb-2">
                        <input id="ic-party-3-phone" type="text" placeholder="Phone" class="ic-input w-full">
                    </div>
                    <div>
                        <label class="ic-label">Third Party (Party 4)</label>
                        <input id="ic-party-4-name" type="text" placeholder="Name" class="ic-input w-full mb-2">
                        <input id="ic-party-4-address" type="text" placeholder="Address" class="ic-input w-full mb-2">
                        <input id="ic-party-4-phone" type="text" placeholder="Phone" class="ic-input w-full">
                    </div>
                </div>
            </div>

            <hr class="border-gray-200">

            {{-- Property Details --}}
            <div class="border-l-4 border-teal-500 pl-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Property Details</h3>

                {{-- Location Builder --}}
                <div class="border border-blue-100 rounded-lg p-3 bg-blue-50/30 mb-3">
                    <p class="text-xs font-semibold text-blue-700 mb-2 flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3 h-3"></i> Location Builder
                    </p>
                    <div class="grid grid-cols-3 gap-3 mb-3">
                        <div>
                            <label class="ic-label">District</label>
                            <select id="ic-district-select" class="ic-input w-full">
                                <option value="">Select District</option>
                                <option value="Other">Other</option>
                            </select>
                            <div id="ic-district-custom-wrap" class="hidden mt-1">
                                <input id="ic-district-custom" type="text" placeholder="Specify district"
                                    class="ic-input w-full">
                            </div>
                        </div>
                        <div>
                            <label class="ic-label">LGA</label>
                            <select id="ic-lga-select" class="ic-input w-full">
                                <option value="">Select LGA</option>
                            </select>
                        </div>
                        <div>
                            <label class="ic-label">State</label>
                            <input type="text" value="KANO" readonly
                                class="ic-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                    </div>
                    <div>
                        <label class="ic-label">Built Location Description</label>
                        <input id="ic-location-built" type="text" readonly placeholder="Auto-built from District + LGA"
                            class="ic-input w-full bg-blue-50 text-blue-800 font-semibold uppercase cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <textarea id="ic-property-description" rows="2" placeholder="Property Description / Address"
                        class="ic-input w-full resize-none"></textarea>
                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="ic-label">Plot No.</label>
                                <input id="ic-plot-number" type="text" placeholder="Plot Number" class="ic-input w-full">
                            </div>
                            <div>
                                <label class="ic-label">TP No.</label>
                                <input id="ic-tp-no" type="text" placeholder="TP No" class="ic-input w-full">
                            </div>
                        </div>
                        <div>
                            <label class="ic-label">Size</label>
                            <input id="ic-plot-size" type="text" placeholder="Plot size" class="ic-input w-full">
                        </div>
                    </div>
                </div>
            </div>

            <hr class="border-gray-200">

            {{-- Registration + Solicitor --}}
            <div class="grid grid-cols-2 gap-6">
                <div class="border-l-4 border-sky-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Registration</h3>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div>
                            <label class="ic-label">Serial No.</label>
                            <input id="ic-serial-no" type="text" placeholder="e.g. 5" class="ic-input w-full">
                        </div>
                        <div>
                            <label class="ic-label">Page No.</label>
                            <input id="ic-page-no" type="text" placeholder="e.g. 5" class="ic-input w-full">
                        </div>
                        <div>
                            <label class="ic-label">Volume No.</label>
                            <input id="ic-volume-no" type="text" placeholder="e.g. 1" class="ic-input w-full">
                        </div>
                    </div>
                    <div>
                        <label class="ic-label">Registration Date</label>
                        <input id="ic-reg-date" type="date" class="ic-input w-full">
                    </div>
                </div>
                <div class="border-l-4 border-indigo-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Solicitor</h3>
                    <div class="space-y-2">
                        <input id="ic-solicitor-name" type="text" placeholder="Name" class="ic-input w-full">
                        <input id="ic-solicitor-address" type="text" placeholder="Address" class="ic-input w-full">
                    </div>
                </div>
            </div>

            <div id="ic-error-box" class="hidden bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700"></div>
        </form>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button class="mort-cancel px-5 py-2.5 text-gray-700 font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">Cancel</button>
            <button id="ic-save-btn" class="px-6 py-2.5 text-white font-medium bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2 text-sm">
                <i data-lucide="save" class="h-4 w-4"></i>
                <span id="ic-save-text">Save Changes</span>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL 2: PRA — mirrors Property Transaction Details card
     ══════════════════════════════════════════════════════════ --}}
<div id="mortgage-modal-pra" class="mort-modal fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm mort-backdrop"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Edit Property Record (PRA)</h2>
                <p class="text-sm text-gray-500 mt-0.5" id="pra-modal-filelabel"></p>
            </div>
            <button class="mort-close p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <form id="pra-edit-form" class="flex-1 overflow-y-auto p-6 space-y-4">
            <input type="hidden" id="pra-record-id">

            {{-- Blue info box --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">File Information</h4>
                <div class="grid grid-cols-2 gap-2 text-sm text-blue-700">
                    <div><strong>File Number:</strong> <span id="pra-info-fileno"></span></div>
                    <div><strong>Source:</strong> Property Records (PRA)</div>
                </div>
            </div>

            {{-- Transaction Type + Date --}}
            <div class="border border-gray-300 rounded-lg p-4 bg-white shadow-sm space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instrument Type <span class="text-red-500">*</span></label>
                        <select id="pra-instrument-type" class="pra-input w-full">
                            <option value="Deed of Mortgage">Deed of Mortgage</option>
                            <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date (Deeds Date)</label>
                        <input id="pra-deeds-date" type="date" class="pra-input w-full">
                    </div>
                </div>

                {{-- Registration Number --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration Number</label>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Serial No.</label>
                            <input id="pra-serial-no" type="text" placeholder="e.g. 5" class="pra-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Page No. <span class="text-xs text-gray-400">(auto)</span></label>
                            <input id="pra-page-no" type="text" readonly class="pra-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Volume No.</label>
                            <input id="pra-volume-no" type="text" placeholder="e.g. 2" class="pra-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Reg Date</label>
                            <input id="pra-reg-date" type="date" class="pra-input w-full">
                        </div>
                    </div>
                    <div id="pra-reg-preview" class="hidden mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-sm">
                        <span class="font-semibold text-blue-700">Reg No:</span>
                        <span id="pra-reg-preview-val" class="font-bold text-blue-800 ml-2"></span>
                    </div>
                </div>

                {{-- Land Use --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                    <select id="pra-land-use" class="pra-input w-full">
                        <option value="">Select land use</option>
                        <option>RESIDENTIAL</option>
                        <option>AGRICULTURAL</option>
                        <option>COMMERCIAL</option>
                        <option>INDUSTRIAL</option>
                        <option>RESIDENTIAL/COMMERCIAL</option>
                    </select>
                </div>

                {{-- Parties --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 1 / Mortgagor</label>
                        <input id="pra-party-1" type="text" placeholder="Mortgagor name" class="pra-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 2 / Mortgagee</label>
                        <input id="pra-party-2" type="text" placeholder="Mortgagee / Bank name" class="pra-input w-full">
                    </div>
                </div>

                {{-- Co-Mortgagor (Mortgagor_2) --}}
                <div class="border-l-2 border-blue-100 pl-4 space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Co-Mortgagor (Mortgagor 2)</label>
                    <input id="pra-mortgagor-2" type="text" placeholder="Second mortgagor name (optional)" class="pra-input w-full">
                </div>

                {{-- Location Builder --}}
                <div class="border border-emerald-100 rounded-lg p-3 bg-emerald-50/30">
                    <p class="text-xs font-semibold text-emerald-700 mb-2 flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3 h-3"></i> Location Builder
                    </p>
                    <div class="grid grid-cols-3 gap-2 mb-2">
                        <div>
                            <label class="pra-label">District</label>
                            <select id="pra-district-select" class="pra-input w-full">
                                <option value="">Select District</option>
                                <option value="Other">Other</option>
                            </select>
                            <div id="pra-district-custom-wrap" class="hidden mt-1">
                                <input id="pra-district-custom" type="text" placeholder="Specify district" class="pra-input w-full">
                            </div>
                        </div>
                        <div>
                            <label class="pra-label">LGA</label>
                            <select id="pra-lga-select" class="pra-input w-full">
                                <option value="">Select LGA</option>
                            </select>
                        </div>
                        <div>
                            <label class="pra-label">State</label>
                            <input type="text" value="KANO" readonly class="pra-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                    </div>
                    <div>
                        <label class="pra-label">Built Location Description</label>
                        <input id="pra-location-built" type="text" readonly placeholder="Auto-built from District + LGA"
                            class="pra-input w-full bg-emerald-50 text-emerald-800 font-semibold uppercase cursor-not-allowed">
                    </div>
                </div>

                {{-- Comments --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remarks / Comments</label>
                    <textarea id="pra-comments" rows="2" placeholder="Additional remarks..." class="pra-input w-full resize-none"></textarea>
                </div>
            </div>

            <div id="pra-error-box" class="hidden bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700"></div>
        </form>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button class="mort-cancel px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">Cancel</button>
            <button id="pra-save-btn" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                <i data-lucide="save" class="h-4 w-4"></i>
                <span id="pra-save-text">Save Transaction Details</span>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL 3: FHS — same layout as PRA + Party 3 & 4
     ══════════════════════════════════════════════════════════ --}}
<div id="mortgage-modal-fhs" class="mort-modal fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm mort-backdrop"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Edit File History Staging Record</h2>
                <p class="text-sm text-gray-500 mt-0.5" id="fhs-modal-filelabel"></p>
            </div>
            <button class="mort-close p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <form id="fhs-edit-form" class="flex-1 overflow-y-auto p-6 space-y-4">
            <input type="hidden" id="fhs-record-id">

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-amber-800 mb-2">File Information</h4>
                <div class="grid grid-cols-2 gap-2 text-sm text-amber-700">
                    <div><strong>File Number:</strong> <span id="fhs-info-fileno"></span></div>
                    <div><strong>Source:</strong> File History Staging</div>
                </div>
            </div>

            <div class="border border-gray-300 rounded-lg p-4 bg-white shadow-sm space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instrument Type <span class="text-red-500">*</span></label>
                        <select id="fhs-instrument-type" class="fhs-input w-full">
                            <option value="Deed of Mortgage">Deed of Mortgage</option>
                            <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date (Deeds Date)</label>
                        <input id="fhs-deeds-date" type="date" class="fhs-input w-full">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration Number</label>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Serial No.</label>
                            <input id="fhs-serial-no" type="text" placeholder="e.g. 5" class="fhs-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Page No. <span class="text-xs text-gray-400">(auto)</span></label>
                            <input id="fhs-page-no" type="text" readonly class="fhs-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Volume No.</label>
                            <input id="fhs-volume-no" type="text" placeholder="e.g. 2" class="fhs-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Reg Date</label>
                            <input id="fhs-reg-date" type="date" class="fhs-input w-full">
                        </div>
                    </div>
                    <div id="fhs-reg-preview" class="hidden mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-sm">
                        <span class="font-semibold text-blue-700">Reg No:</span>
                        <span id="fhs-reg-preview-val" class="font-bold text-blue-800 ml-2"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                    <select id="fhs-land-use" class="fhs-input w-full">
                        <option value="">Select land use</option>
                        <option>RESIDENTIAL</option>
                        <option>AGRICULTURAL</option>
                        <option>COMMERCIAL</option>
                        <option>INDUSTRIAL</option>
                        <option>RESIDENTIAL/COMMERCIAL</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 1 / Mortgagor</label>
                        <input id="fhs-party-1" type="text" placeholder="Mortgagor name" class="fhs-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 2 / Mortgagee</label>
                        <input id="fhs-party-2" type="text" placeholder="Mortgagee / Bank name" class="fhs-input w-full">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 3 (Co-Mortgagor)</label>
                        <input id="fhs-party-3" type="text" placeholder="Co-mortgagor (optional)" class="fhs-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 4 (Third Party)</label>
                        <input id="fhs-party-4" type="text" placeholder="Third party (optional)" class="fhs-input w-full">
                    </div>
                </div>

                {{-- Location Builder --}}
                <div class="border border-amber-100 rounded-lg p-3 bg-amber-50/30">
                    <p class="text-xs font-semibold text-amber-700 mb-2 flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3 h-3"></i> Location Builder
                    </p>
                    <div class="grid grid-cols-3 gap-2 mb-2">
                        <div>
                            <label class="fhs-label">District</label>
                            <select id="fhs-district-select" class="fhs-input w-full">
                                <option value="">Select District</option>
                                <option value="Other">Other</option>
                            </select>
                            <div id="fhs-district-custom-wrap" class="hidden mt-1">
                                <input id="fhs-district-custom" type="text" placeholder="Specify district" class="fhs-input w-full">
                            </div>
                        </div>
                        <div>
                            <label class="fhs-label">LGA</label>
                            <select id="fhs-lga-select" class="fhs-input w-full">
                                <option value="">Select LGA</option>
                            </select>
                        </div>
                        <div>
                            <label class="fhs-label">State</label>
                            <input type="text" value="KANO" readonly class="fhs-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                    </div>
                    <div>
                        <label class="fhs-label">Built Location Description</label>
                        <input id="fhs-location-built" type="text" readonly placeholder="Auto-built from District + LGA"
                            class="fhs-input w-full bg-amber-50 text-amber-800 font-semibold uppercase cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remarks / Comments</label>
                    <textarea id="fhs-comments" rows="2" placeholder="Additional remarks..." class="fhs-input w-full resize-none"></textarea>
                </div>
            </div>

            <div id="fhs-error-box" class="hidden bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700"></div>
        </form>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button class="mort-cancel px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">Cancel</button>
            <button id="fhs-save-btn" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition shadow-sm flex items-center gap-2 text-sm">
                <i data-lucide="save" class="h-4 w-4"></i>
                <span id="fhs-save-text">Save Transaction Details</span>
            </button>
        </div>
    </div>
</div>

{{-- S&R Details Modal --}}
<div id="sr_details_modal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closeSrDetailsModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-slate-100">
            <div class="bg-gradient-to-r from-orange-500 to-amber-600 px-6 py-5 flex items-center justify-between">
                <h3 class="text-lg font-black text-white flex items-center gap-2" id="sr-modal-title">
                    <i class="fas fa-file-contract text-xl"></i>
                    Associated Surrender &amp; Release
                </h3>
                <button type="button" class="text-white/80 hover:text-white transition cursor-pointer" onclick="closeSrDetailsModal()">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="px-6 py-6 space-y-4 max-h-[70vh] overflow-y-auto bg-slate-50" id="sr_details_modal_body">
                {{-- Dynamically populated --}}
            </div>
            <div class="bg-white px-6 py-4 border-t border-slate-100 flex justify-end">
                <button type="button" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-sm transition shadow-md hover:shadow-lg cursor-pointer" onclick="closeSrDetailsModal()">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

{{-- DUMMY: keep old id so nothing else breaks --}}
<div id="mortgage-edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" id="mortgage-modal-backdrop"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col z-10">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
                    <i data-lucide="file-edit" class="w-5 h-5 text-indigo-600"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-800">Edit Mortgage Record</h3>
                    <p id="modal-source-label" class="text-xs text-slate-400 font-medium mt-0.5"></p>
                </div>
            </div>
            <button id="mortgage-modal-close" class="p-2 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Body --}}
        <form id="mortgage-edit-form" class="overflow-y-auto flex-1 px-6 py-5 space-y-6">
            <input type="hidden" id="edit-record-id">

            {{-- ── Section: Core ── --}}
            <div>
                <h4 class="modal-section-title">Core Details</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="modal-label">File Number</label>
                        <input id="edit-file-number" type="text" readonly
                            class="modal-input bg-slate-50 text-slate-400 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="modal-label">Instrument Type <span class="text-rose-500">*</span></label>
                        <select id="edit-instrument-type" class="modal-input">
                            <option value="Deed of Mortgage">Deed of Mortgage</option>
                            <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- ── Section: Registration ── --}}
            <div>
                <h4 class="modal-section-title">Registration</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="modal-label">Registration Particulars</label>
                        <input id="edit-reg-particulars" type="text" placeholder="e.g. 5/5/2025" class="modal-input">
                    </div>
                    <div>
                        <label id="edit-date-label" class="modal-label">Date</label>
                        <input id="edit-transaction-date" type="date" class="modal-input">
                    </div>
                </div>
            </div>

            {{-- ── Section: Party 1 – Mortgagor ── --}}
            <div>
                <h4 class="modal-section-title">Party 1 – Mortgagor</h4>
                <div class="space-y-3">
                    <input id="edit-party-1" type="text" placeholder="Full name" class="modal-input w-full">
                    {{-- IC-only extended fields --}}
                    <div id="edit-p1-extended" class="hidden grid grid-cols-2 gap-3">
                        <input id="edit-party-1-address" type="text" placeholder="Street Address" class="modal-input">
                        <input id="edit-party-1-phone" type="text" placeholder="Phone Number" class="modal-input">
                        <input id="edit-party-1-city" type="text" placeholder="City" class="modal-input">
                        <input id="edit-party-1-state" type="text" placeholder="State" class="modal-input">
                    </div>
                    {{-- PRA-only: second mortgagor --}}
                    <div id="edit-mortgagor-2-row" class="hidden">
                        <label class="modal-label">Mortgagor 2</label>
                        <input id="edit-mortgagor-2" type="text" placeholder="Second mortgagor name" class="modal-input w-full">
                    </div>
                </div>
            </div>

            {{-- ── Section: Party 2 – Mortgagee ── --}}
            <div>
                <h4 class="modal-section-title">Party 2 – Mortgagee / Bank</h4>
                <div class="space-y-3">
                    <input id="edit-party-2" type="text" placeholder="Full name or bank name" class="modal-input w-full">
                    {{-- IC-only extended fields --}}
                    <div id="edit-p2-extended" class="hidden grid grid-cols-2 gap-3">
                        <input id="edit-party-2-address" type="text" placeholder="Street Address" class="modal-input">
                        <input id="edit-party-2-phone" type="text" placeholder="Phone Number" class="modal-input">
                        <input id="edit-party-2-city" type="text" placeholder="City" class="modal-input">
                        <input id="edit-party-2-state" type="text" placeholder="State" class="modal-input">
                    </div>
                </div>
            </div>

            {{-- ── Section: Co-Mortgagor / Third Party (IC & FHS, shown for Tripartite) ── --}}
            <div id="edit-extra-parties-section" class="hidden space-y-4">
                {{-- Party 3 --}}
                <div>
                    <h4 class="modal-section-title">Party 3 – Co-Mortgagor</h4>
                    <div class="space-y-3">
                        <input id="edit-party-3" type="text" placeholder="Co-mortgagor name" class="modal-input w-full">
                        <div id="edit-p3-extended" class="hidden grid grid-cols-2 gap-3">
                            <input id="edit-party-3-address" type="text" placeholder="Address" class="modal-input">
                            <input id="edit-party-3-phone" type="text" placeholder="Phone" class="modal-input">
                        </div>
                    </div>
                </div>
                {{-- Party 4 --}}
                <div>
                    <h4 class="modal-section-title">Party 4 – Third Party</h4>
                    <div class="space-y-3">
                        <input id="edit-party-4" type="text" placeholder="Third party name" class="modal-input w-full">
                        <div id="edit-p4-extended" class="hidden grid grid-cols-2 gap-3">
                            <input id="edit-party-4-address" type="text" placeholder="Address" class="modal-input">
                            <input id="edit-party-4-phone" type="text" placeholder="Phone" class="modal-input">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Section: Mortgage Details (IC only) ── --}}
            <div id="edit-mortgage-details-section" class="hidden">
                <h4 class="modal-section-title">Mortgage Details</h4>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="modal-label">Bank Name</label>
                        <input id="edit-bank-name" type="text" placeholder="Bank name" class="modal-input">
                    </div>
                    <div>
                        <label class="modal-label">Mortgage Date</label>
                        <input id="edit-mortgage-date" type="date" class="modal-input">
                    </div>
                    <div>
                        <label class="modal-label">Governor Sign Date</label>
                        <input id="edit-governor-date" type="date" class="modal-input">
                    </div>
                </div>
            </div>

            {{-- ── Section: Property Details ── --}}
            <div>
                <h4 class="modal-section-title">Property Details</h4>
                <div class="space-y-3">
                    {{-- IC: full property details --}}
                    <div id="edit-property-full" class="hidden space-y-3">
                        <textarea id="edit-property-description" rows="2" placeholder="Property Description / Address"
                            class="modal-input w-full resize-none"></textarea>
                        <div class="grid grid-cols-3 gap-3">
                            <input id="edit-plot-number" type="text" placeholder="Plot Number" class="modal-input">
                            <input id="edit-plot-size" type="text" placeholder="Size" class="modal-input">
                            <input id="edit-location" type="text" placeholder="Location / Area" class="modal-input">
                        </div>
                    </div>
                    {{-- PRA / FHS: location only --}}
                    <div id="edit-property-simple">
                        <label class="modal-label">Location</label>
                        <input id="edit-location-simple" type="text" placeholder="Location" class="modal-input w-full">
                    </div>
                </div>
            </div>

            {{-- ── Section: Solicitor (IC only) ── --}}
            <div id="edit-solicitor-section" class="hidden">
                <h4 class="modal-section-title">Solicitor</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="modal-label">Solicitor Name</label>
                        <input id="edit-solicitor-name" type="text" placeholder="Name" class="modal-input">
                    </div>
                    <div>
                        <label class="modal-label">Solicitor Address</label>
                        <input id="edit-solicitor-address" type="text" placeholder="Address" class="modal-input">
                    </div>
                </div>
            </div>

            {{-- ── Section: Comments (PRA & FHS only) ── --}}
            <div id="edit-comments-section" class="hidden">
                <h4 class="modal-section-title">Remarks / Comments</h4>
                <textarea id="edit-comments" rows="2" placeholder="Additional notes or remarks..."
                    class="modal-input w-full resize-none"></textarea>
            </div>

            {{-- Validation error --}}
            <div id="edit-error-box" class="hidden bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 text-sm text-rose-700"></div>
        </form>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 flex-shrink-0">
            <button id="mortgage-modal-cancel"
                class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                Cancel
            </button>
            <button id="mortgage-modal-save"
                class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span id="save-btn-text">Save Changes</span>
            </button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Premium Design System */
    .premium-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .premium-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: #cbd5e1;
    }

    /* Horizontal scroll for the DataTable wrapper */
    #mortgage_table_wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* DataTable Overrides */
    #mortgage_table_wrapper .dataTables_length select {
        border-radius: 12px;
        padding: 6px 32px 6px 12px;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        color: #475569;
    }
    #mortgage_table_wrapper .dataTables_filter input {
        border-radius: 12px;
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        width: 300px;
        transition: all 0.2s;
    }
    #mortgage_table_wrapper .dataTables_filter input:focus {
        border-color: #6366f1;
        outline: none;
    }
    
    table.dataTable thead th {
        background-color: #f8fafc !important;
        border-bottom: 2px solid #e2e8f0 !important;
        color: #64748b !important;
        padding: 16px 12px !important;
    }
    
    table.dataTable tbody td {
        padding: 12px 12px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        color: #334155;
    }
    
    table.dataTable tbody tr:hover {
        background-color: #f8fafc !important;
    }

    .source-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 99px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        white-space: nowrap;
    }
    .source-ic { background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; }
    .source-pra { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .source-fhs { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

    .file-no-link {
        color: #4f46e5;
        font-weight: 700;
        text-decoration: none;
        transition: color 0.2s;
    }
    .file-no-link:hover {
        color: #312e81;
        text-decoration: underline;
    }

    /* Timeline Badge Styles */
    .timeline-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        border: 1px solid #bfdbfe;
        background-color: #eff6ff;
        color: #1e40af;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 4px;
        white-space: nowrap;
    }
    .timeline-badge:hover {
        background-color: #dbeafe;
        transform: scale(1.05);
    }

    /* Mortgage Edit Modals */
    .mort-modal.flex { display: flex !important; }

    .ic-label, .pra-label, .fhs-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 4px;
    }
    .ic-input, .pra-input, .fhs-input {
        padding: 7px 10px;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        color: #374151;
        font-size: 13px;
        background: #fff;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .ic-input:focus, .pra-input:focus, .fhs-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
    }
    select.ic-input, select.pra-input, select.fhs-input {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 28px;
    }
    /* Legacy selectors kept so nothing breaks */
    .modal-label { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 6px; }
    .modal-input { width: 100%; padding: 9px 12px; border-radius: 10px; border: 1px solid #e2e8f0; color: #334155; font-size: 13px; background: #fff; }
    .modal-section-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; padding-bottom: 8px; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .p-6 > * {
        animation: fadeIn 0.4s ease-out forwards;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/property-timeline-modal.js') }}"></script>
<script>
$(function() {
    const table = $('#mortgage_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('mortgages.data') }}",
        columns: [
            { 
                data: null, 
                orderable: false, 
                searchable: false,
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { 
                data: 'file_number', 
                name: 'file_number',
                className: 'whitespace-nowrap',
                render: function(data, type, row) {
                    const fileNo = data || '—';
                    const propId = row.prop_id || '';
                    const count = row.timeline_count || 1;
                    
                    let html = `<div class="flex flex-col whitespace-nowrap">
                                    <span class="file-no-link whitespace-nowrap" style="white-space: nowrap;">${fileNo}</span>`;
                    
                    if (fileNo !== '—' || propId) {
                        html += `<div class="mt-1">
                                    <button type="button" class="timeline-badge" 
                                            onclick="openPropertyTimeline('${propId}', '${fileNo}')"
                                            title="View full property timeline">
                                        <i class="fas fa-history mr-1 opacity-70"></i> Timeline (${count})
                                    </button>
                                 </div>`;
                    }
                    
                    html += `</div>`;
                    return html;
                }
            },
            { data: 'registration_particulars', name: 'registration_particulars', defaultContent: '0/0/0' },
            {
                data: 'associated_surrenders',
                name: 'associated_surrenders',
                orderable: false,
                searchable: false,
                render: function(data) {
                    const records = data || [];
                    if (records.length === 0) {
                        return `<span class="text-slate-400 text-xs italic">None</span>`;
                    }
                    return `<div class="py-1">
                                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-orange-50 hover:bg-orange-100 text-orange-800 border border-orange-200 transition duration-150 uppercase tracking-wide cursor-pointer sr-trigger shadow-sm hover:shadow" title="View Surrender &amp; Release Details">
                                    <i class="fas fa-file-contract text-[11px] text-orange-600"></i> S&amp;R (${records.length})
                                </button>
                            </div>`;
                }
            },
            {
                data: 'instrument_type', 
                name: 'instrument_type',
                render: function(data) {
                    return `<span class="font-semibold text-slate-700">${data}</span>`;
                }
            },
            { data: 'party_1', name: 'party_1', defaultContent: '—' },
            { data: 'party_2', name: 'party_2', defaultContent: '—' },
            { data: 'party_3', name: 'party_3', defaultContent: '—' },
            { data: 'party_4', name: 'party_4', defaultContent: '—' },
            { data: 'location', name: 'location', defaultContent: '—' },
            {
                data: 'transaction_date',
                name: 'transaction_date',
                render: function(data) {
                    return data && data !== '—'
                        ? `<span class="font-mono text-slate-700 text-xs font-bold">${data}</span>`
                        : '<span class="text-slate-300">—</span>';
                }
            },
            {
                data: 'created_by',
                name: 'created_by',
                render: function(data) {
                    return data ? `<span class="text-slate-600 text-xs">${data}</span>` : '<span class="text-slate-300">—</span>';
                }
            },
            {
                data: 'date_captured',
                name: 'date_captured',
                render: function(data) {
                    return data && data !== '—'
                        ? `<span class="font-mono text-slate-700 text-xs font-bold">${data}</span>`
                        : '<span class="text-slate-300">—</span>';
                }
            },
            {
                data: 'source_table',
                name: 'source_table',
                className: 'text-center',
                render: function(data) {
                    let cls = '';
                    if (data === 'Instrument Capture') cls = 'source-ic';
                    else if (data === 'Property Records') cls = 'source-pra';
                    else if (data === 'File History Staging') cls = 'source-fhs';
                    return `<span class="source-badge ${cls}">${data}</span>`;
                }
            },
            {
                data: 'id',
                name: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data) {
                    return `<button type="button"
                                class="edit-mortgage-btn inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition"
                                data-id="${data}">
                                <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                            </button>`;
                }
            }
        ],
        pageLength: 25,
        order: [[10, 'desc']],
        language: {
            search: "",
            searchPlaceholder: "Search records...",
            lengthMenu: "_MENU_ records per page",
            processing: '<div class="flex items-center justify-center p-4"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>'
        },
        drawCallback: function() {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });

    $('#refresh_btn').on('click', function() {
        table.ajax.reload();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 3-Modal Edit System
    // ─────────────────────────────────────────────────────────────────────────

    function setVal(id, v) { $(id).val(v || ''); }
    function setDt(id, v)  { $(id).val(v ? String(v).substring(0, 10) : ''); }

    // ─── Reference Data + Location Builder ───────────────────────────────────
    let _refDistricts = null, _refLgas = null;

    function loadRefData(callback) {
        if (_refDistricts && _refLgas) { callback(); return; }
        $.when(
            $.getJSON('/api/reference/districts'),
            $.getJSON('/api/reference/lgas')
        ).done(function(dRes, lRes) {
            _refDistricts = dRes[0].data || [];
            _refLgas      = lRes[0].data || [];
            callback();
        });
    }

    function populateLocationDropdowns(prefix, districtVal, lgaVal) {
        const $distSel = $('#' + prefix + '-district-select');
        const $lgaSel  = $('#' + prefix + '-lga-select');

        // Rebuild district options (keep placeholder + Other)
        $distSel.find('option:not([value=""]):not([value="Other"])').remove();
        (_refDistricts || []).forEach(function(d) {
            $distSel.append($('<option>', { value: d.name, text: d.name }));
        });
        // Rebuild LGA options
        $lgaSel.find('option:not([value=""])').remove();
        (_refLgas || []).forEach(function(l) {
            $lgaSel.append($('<option>', { value: l.name, text: l.name }));
        });

        // Select saved district
        if (districtVal) {
            const exists = $distSel.find('option').filter(function() {
                return $(this).val().toUpperCase() === districtVal.toUpperCase();
            }).length > 0;
            if (exists) {
                $distSel.val(districtVal);
                $('#' + prefix + '-district-custom-wrap').addClass('hidden');
            } else {
                $distSel.val('Other');
                $('#' + prefix + '-district-custom-wrap').removeClass('hidden');
                $('#' + prefix + '-district-custom').val(districtVal);
            }
        } else {
            $distSel.val('');
            $('#' + prefix + '-district-custom-wrap').addClass('hidden');
        }

        // Select saved LGA
        if (lgaVal) {
            $lgaSel.val(lgaVal);
        } else {
            $lgaSel.val('');
        }

        buildLocationString(prefix);
    }

    function buildLocationString(prefix) {
        let district = '';
        const $distSel = $('#' + prefix + '-district-select');
        if ($distSel.val() === 'Other') {
            district = ($('#' + prefix + '-district-custom').val() || '').trim().toUpperCase();
        } else {
            district = ($distSel.val() || '').trim().toUpperCase();
        }
        const lga   = ($('#' + prefix + '-lga-select').val() || '').trim().toUpperCase();
        const parts = [district, lga, 'KANO'].filter(Boolean);
        $('#' + prefix + '-location-built').val(parts.join(', '));
    }

    // Wire up change handlers for all 3 modal prefixes
    ['ic', 'pra', 'fhs'].forEach(function(p) {
        $(document).on('change', '#' + p + '-district-select', function() {
            $('#' + p + '-district-custom-wrap').toggleClass('hidden', $(this).val() !== 'Other');
            buildLocationString(p);
        });
        $(document).on('input',  '#' + p + '-district-custom', function() { buildLocationString(p); });
        $(document).on('change', '#' + p + '-lga-select',      function() { buildLocationString(p); });
    });
    // ─────────────────────────────────────────────────────────────────────────

    // Split "S/P/V" regNo into parts
    function splitRegNo(regNo) {
        const parts = String(regNo || '').split('/');
        return { s: parts[0] || '', p: parts[1] || '', v: parts[2] || '' };
    }

    // Close all modals
    function closeAllMortgageModals() {
        $('.mort-modal').addClass('hidden').removeClass('flex');
    }

    // Generic save dispatcher
    function mortgageSave(compositeId, payload, errorBox, saveBtn, saveText) {
        $(errorBox).addClass('hidden').text('');
        $(saveText).text('Saving…');
        $(saveBtn).prop('disabled', true);

        payload['_method'] = 'PUT';

        $.ajax({
            url: '/mortgages/' + compositeId,
            method: 'POST',
            data: payload,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function() {
                closeAllMortgageModals();
                table.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: 'Updated', text: 'Record updated successfully.', timer: 2500, showConfirmButton: false });
            },
            error: function(xhr) {
                let msg = 'An error occurred. Please try again.';
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON?.errors || {};
                    msg = Object.values(errors).flat().join(' ');
                } else if (xhr.responseJSON?.message) {
                    msg = xhr.responseJSON.message;
                }
                $(errorBox).removeClass('hidden').text(msg);
                $(saveText).text('Save Changes');
                $(saveBtn).prop('disabled', false);
            }
        });
    }

    // ── Open the right modal ──
    $('#mortgage_table').on('click', '.edit-mortgage-btn', function() {
        const compositeId = $(this).data('id');
        const prefix = compositeId.split('_')[0]; // ic | pra | fhs

        $.ajax({
            url: '/mortgages/' + compositeId + '/edit',
            method: 'GET',
            success: function(d) {
                closeAllMortgageModals();

                if (prefix === 'ic') {
                    openIcModal(compositeId, d);
                } else if (prefix === 'pra') {
                    openPraModal(compositeId, d);
                } else {
                    openFhsModal(compositeId, d);
                }

                if (typeof lucide !== 'undefined') lucide.createIcons();
            },
            error: function() {
                alert('Failed to load record. Please try again.');
            }
        });
    });

    // ── IC Modal ──
    function openIcModal(id, d) {
        // Parse reg no from registration_particulars ("S/P/V")
        const reg = splitRegNo(d.registration_particulars);

        setVal('#ic-record-id', id);
        $('#ic-modal-filelabel').text('File: ' + (d.file_number || '—'));
        setVal('#ic-file-number', d.file_number);
        setVal('#ic-instrument-type', d.instrument_type || 'Deed of Mortgage');
        setDt('#ic-entry-date', d.entry_date);
        // Party 1
        setVal('#ic-party-1-name',    d.party_1);
        setVal('#ic-party-1-address', d.party_1_address);
        setVal('#ic-party-1-phone',   d.party_1_phone);
        setVal('#ic-party-1-city',    d.party_1_city);
        setVal('#ic-party-1-state',   d.party_1_state);
        // Party 2
        setVal('#ic-party-2-name',    d.party_2);
        setVal('#ic-party-2-address', d.party_2_address);
        setVal('#ic-party-2-phone',   d.party_2_phone);
        setVal('#ic-party-2-city',    d.party_2_city);
        setVal('#ic-party-2-state',   d.party_2_state);
        // Mortgage details
        setVal('#ic-bank-name',   d.bank_name);
        setDt('#ic-mortgage-date', d.mortgage_date);
        setDt('#ic-governor-date', d.governor_consent_date);
        // Tripartite section
        const isTripartite = (d.instrument_type === 'Tripartite Mortgage');
        $('#ic-tripartite-section').toggleClass('hidden', !isTripartite);
        setVal('#ic-party-3-name',    d.party_3);
        setVal('#ic-party-3-address', d.party_3_address);
        setVal('#ic-party-3-phone',   d.party_3_phone);
        setVal('#ic-party-4-name',    d.party_4);
        setVal('#ic-party-4-address', d.party_4_address);
        setVal('#ic-party-4-phone',   d.party_4_phone);
        // Property
        setVal('#ic-property-description', d.property_description);
        setVal('#ic-plot-number', d.plot_number);
        setVal('#ic-plot-size',   d.plot_size);
        // Location builder
        loadRefData(function() { populateLocationDropdowns('ic', d.district, d.lga); });
        // Registration
        setVal('#ic-serial-no', reg.s);
        setVal('#ic-page-no',   reg.p);
        setVal('#ic-volume-no', reg.v);
        setDt('#ic-reg-date', d.transaction_date);
        // Solicitor
        setVal('#ic-solicitor-name',    d.solicitor_name);
        setVal('#ic-solicitor-address', d.solicitor_address);
        // Show
        $('#ic-error-box').addClass('hidden');
        $('#ic-save-text').text('Save Changes');
        $('#ic-save-btn').prop('disabled', false);
        $('#mortgage-modal-ic').removeClass('hidden').addClass('flex');
    }

    $('#ic-save-btn').on('click', function() {
        const id = $('#ic-record-id').val();
        const s = $('#ic-serial-no').val(), p = $('#ic-page-no').val(), v = $('#ic-volume-no').val();
        const regParticulars = [s, p, v].filter(Boolean).join('/');
        mortgageSave(id, {
            _method:                  'PUT',
            instrument_type:          $('#ic-instrument-type').val(),
            registration_particulars: regParticulars,
            transaction_date:         $('#ic-reg-date').val(),
            party_1:                  $('#ic-party-1-name').val(),
            party_1_address:          $('#ic-party-1-address').val(),
            party_1_phone:            $('#ic-party-1-phone').val(),
            party_1_city:             $('#ic-party-1-city').val(),
            party_1_state:            $('#ic-party-1-state').val(),
            party_2:                  $('#ic-party-2-name').val(),
            party_2_address:          $('#ic-party-2-address').val(),
            party_2_phone:            $('#ic-party-2-phone').val(),
            party_2_city:             $('#ic-party-2-city').val(),
            party_2_state:            $('#ic-party-2-state').val(),
            party_3:                  $('#ic-party-3-name').val(),
            party_3_address:          $('#ic-party-3-address').val(),
            party_3_phone:            $('#ic-party-3-phone').val(),
            party_4:                  $('#ic-party-4-name').val(),
            party_4_address:          $('#ic-party-4-address').val(),
            party_4_phone:            $('#ic-party-4-phone').val(),
            bank_name:                $('#ic-bank-name').val(),
            mortgage_date:            $('#ic-mortgage-date').val(),
            governor_consent_date:    $('#ic-governor-date').val(),
            property_description:     $('#ic-property-description').val(),
            plot_number:              $('#ic-plot-number').val(),
            plot_size:                $('#ic-plot-size').val(),
            district:                 $('#ic-district-select').val() === 'Other' ? $('#ic-district-custom').val() : $('#ic-district-select').val(),
            lga:                      $('#ic-lga-select').val(),
            location:                 $('#ic-location-built').val(),
            solicitor_name:           $('#ic-solicitor-name').val(),
            solicitor_address:        $('#ic-solicitor-address').val(),
        }, '#ic-error-box', '#ic-save-btn', '#ic-save-text');
    });

    // Sync serial → page (like the IC form)
    $('#ic-serial-no').on('input', function() { setVal('#ic-page-no', $(this).val()); });

    // ── PRA Modal ──
    function openPraModal(id, d) {
        const reg = splitRegNo(d.registration_particulars);
        setVal('#pra-record-id', id);
        $('#pra-modal-filelabel').text('File: ' + (d.file_number || '—'));
        $('#pra-info-fileno').text(d.file_number || '—');
        setVal('#pra-instrument-type', d.instrument_type);
        setDt('#pra-deeds-date', d.transaction_date);
        setVal('#pra-serial-no', reg.s);
        setVal('#pra-page-no',   reg.p);
        setVal('#pra-volume-no', reg.v);
        setDt('#pra-reg-date',   d.transaction_date);
        updatePraPreview();
        setVal('#pra-land-use', d.land_use);
        setVal('#pra-party-1',     d.party_1);
        setVal('#pra-party-2',     d.party_2);
        setVal('#pra-mortgagor-2', d.mortgagor_2);
        loadRefData(function() { populateLocationDropdowns('pra', d.district, d.lga); });
        setVal('#pra-comments',    d.comments);
        $('#pra-error-box').addClass('hidden');
        $('#pra-save-text').text('Save Transaction Details');
        $('#pra-save-btn').prop('disabled', false);
        $('#mortgage-modal-pra').removeClass('hidden').addClass('flex');
    }

    function updatePraPreview() {
        const s = $('#pra-serial-no').val(), p = $('#pra-page-no').val(), v = $('#pra-volume-no').val();
        const preview = [s, p, v].filter(Boolean).join('/');
        $('#pra-reg-preview').toggleClass('hidden', !preview);
        $('#pra-reg-preview-val').text(preview);
    }
    $('#pra-serial-no').on('input', function() { setVal('#pra-page-no', $(this).val()); updatePraPreview(); });
    $('#pra-volume-no').on('input', updatePraPreview);

    $('#pra-save-btn').on('click', function() {
        const id = $('#pra-record-id').val();
        const s = $('#pra-serial-no').val(), p = $('#pra-page-no').val(), v = $('#pra-volume-no').val();
        mortgageSave(id, {
            _method:                  'PUT',
            instrument_type:          $('#pra-instrument-type').val(),
            registration_particulars: [s, p, v].filter(Boolean).join('/'),
            transaction_date:         $('#pra-deeds-date').val(),
            party_1:                  $('#pra-party-1').val(),
            party_2:                  $('#pra-party-2').val(),
            mortgagor_2:              $('#pra-mortgagor-2').val(),
            district:                 $('#pra-district-select').val() === 'Other' ? $('#pra-district-custom').val() : $('#pra-district-select').val(),
            lga:                      $('#pra-lga-select').val(),
            location:                 $('#pra-location-built').val(),
            comments:                 $('#pra-comments').val(),
        }, '#pra-error-box', '#pra-save-btn', '#pra-save-text');
    });

    // ── FHS Modal ──
    function openFhsModal(id, d) {
        const reg = splitRegNo(d.registration_particulars);
        setVal('#fhs-record-id', id);
        $('#fhs-modal-filelabel').text('File: ' + (d.file_number || '—'));
        $('#fhs-info-fileno').text(d.file_number || '—');
        setVal('#fhs-instrument-type', d.instrument_type);
        setDt('#fhs-deeds-date', d.transaction_date);
        setVal('#fhs-serial-no', reg.s);
        setVal('#fhs-page-no',   reg.p);
        setVal('#fhs-volume-no', reg.v);
        setDt('#fhs-reg-date',   d.transaction_date);
        updateFhsPreview();
        setVal('#fhs-land-use', d.land_use);
        setVal('#fhs-party-1', d.party_1);
        setVal('#fhs-party-2', d.party_2);
        setVal('#fhs-party-3', d.party_3);
        setVal('#fhs-party-4', d.party_4);
        loadRefData(function() { populateLocationDropdowns('fhs', d.district, d.lga); });
        setVal('#fhs-comments', d.comments);
        $('#fhs-error-box').addClass('hidden');
        $('#fhs-save-text').text('Save Transaction Details');
        $('#fhs-save-btn').prop('disabled', false);
        $('#mortgage-modal-fhs').removeClass('hidden').addClass('flex');
    }

    function updateFhsPreview() {
        const s = $('#fhs-serial-no').val(), p = $('#fhs-page-no').val(), v = $('#fhs-volume-no').val();
        const preview = [s, p, v].filter(Boolean).join('/');
        $('#fhs-reg-preview').toggleClass('hidden', !preview);
        $('#fhs-reg-preview-val').text(preview);
    }
    $('#fhs-serial-no').on('input', function() { setVal('#fhs-page-no', $(this).val()); updateFhsPreview(); });
    $('#fhs-volume-no').on('input', updateFhsPreview);

    $('#fhs-save-btn').on('click', function() {
        const id = $('#fhs-record-id').val();
        const s = $('#fhs-serial-no').val(), p = $('#fhs-page-no').val(), v = $('#fhs-volume-no').val();
        mortgageSave(id, {
            _method:                  'PUT',
            instrument_type:          $('#fhs-instrument-type').val(),
            registration_particulars: [s, p, v].filter(Boolean).join('/'),
            transaction_date:         $('#fhs-deeds-date').val(),
            party_1:                  $('#fhs-party-1').val(),
            party_2:                  $('#fhs-party-2').val(),
            party_3:                  $('#fhs-party-3').val(),
            party_4:                  $('#fhs-party-4').val(),
            district:                 $('#fhs-district-select').val() === 'Other' ? $('#fhs-district-custom').val() : $('#fhs-district-select').val(),
            lga:                      $('#fhs-lga-select').val(),
            location:                 $('#fhs-location-built').val(),
            comments:                 $('#fhs-comments').val(),
        }, '#fhs-error-box', '#fhs-save-btn', '#fhs-save-text');
    });

    // ── Close handlers (backdrop, X button, Cancel, Escape) ──
    $(document).on('click', '.mort-backdrop, .mort-close, .mort-cancel', closeAllMortgageModals);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') closeAllMortgageModals(); });

    // ── Associated S&R click handler ──
    $('#mortgage_table').on('click', '.sr-trigger', function() {
        const tr = $(this).closest('tr');
        const rowData = table.row(tr).data();
        if (rowData && rowData.associated_surrenders) {
            openSrDetailsModal(rowData.associated_surrenders, rowData.file_number || '—');
        }
    });
});

function openSrDetailsModal(records, fileNo) {
    const body = $('#sr_details_modal_body');
    body.empty();
    $('#sr-modal-title').html(`<i class="fas fa-file-contract text-xl"></i> S&amp;R for ${fileNo}`);

    records.forEach(function(r) {
        const reg  = r.registration_particulars && r.registration_particulars.trim() ? r.registration_particulars : 'Unregistered';
        const p1   = r.party_1 || '—';
        const p2   = r.party_2 || '—';
        const p3Html = r.party_3 && r.party_3.trim()
            ? `<div class="pt-2"><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Party 3</p><p class="text-sm font-bold text-slate-800 mt-0.5">${r.party_3}</p></div>`
            : '';
        let dateVal = '—';
        if (r.date_captured) {
            try { dateVal = r.date_captured.substring(0, 16).replace('T', ' '); } catch(e) { dateVal = r.date_captured; }
        }
        body.append(`
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4 hover:border-orange-300 transition-all duration-300">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-orange-50 text-orange-800 border border-orange-200 uppercase tracking-wide">
                    <i class="fas fa-file-contract text-orange-600 text-[10px]"></i> ${r.instrument_type || 'Deed of Surrender'}
                </span>
                <span class="px-2.5 py-1 text-xs font-mono font-bold bg-slate-100 text-slate-700 rounded-lg border border-slate-200">${reg}</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Surrenderor (Party 1)</p><p class="text-sm font-bold text-slate-800 mt-0.5">${p1}</p></div>
                <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Surrenderee (Party 2)</p><p class="text-sm font-bold text-slate-800 mt-0.5">${p2}</p></div>
            </div>
            ${p3Html}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                <div class="flex items-center gap-1.5"><i class="fas fa-database text-slate-400 text-[10px]"></i><span>Source: <strong class="text-slate-700">${r.source || '—'}</strong></span></div>
                <div class="flex items-center gap-1.5 md:justify-end"><i class="fas fa-calendar-alt text-slate-400 text-[10px]"></i><span>Captured: <strong class="text-slate-700">${dateVal}</strong></span></div>
            </div>
        </div>`);
    });

    $('#sr_details_modal').removeClass('hidden');
    $('body').addClass('overflow-hidden');
}

function closeSrDetailsModal() {
    $('#sr_details_modal').addClass('hidden');
    $('body').removeClass('overflow-hidden');
}

window.openSrDetailsModal  = openSrDetailsModal;
window.closeSrDetailsModal = closeSrDetailsModal;
</script>
@endpush

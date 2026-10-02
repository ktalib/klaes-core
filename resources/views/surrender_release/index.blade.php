@extends('layouts.app')

@section('page-title')
    Surrender & Release Records
@endsection
@section('content')
<div class="flex-1 overflow-auto bg-slate-50">
    @include('admin.header', ['PageTitle' => 'Surrender & Release Records', 'PageDescription' => 'Comprehensive list of Deed of Surrender and Release records from PRA, Instrument Capture, and File History Staging.'])
    
    <div class="p-6 space-y-6">
        {{-- ① Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">

            {{-- Daily Combined --}}
            <div class="premium-card group border-l-4 border-l-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Captured Today</p>
                        <h3 class="text-2xl font-black text-orange-700 mt-0.5">{{ number_format($dailyRecords) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center group-hover:bg-orange-500 transition-colors duration-300">
                        <i data-lucide="calendar" class="w-5 h-5 text-orange-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- Instrument Capture --}}
            <div class="premium-card group border-l-4 border-l-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Instrument Capture</p>
                        <h3 class="text-2xl font-black text-purple-700 mt-0.5">{{ number_format($icTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center group-hover:bg-purple-500 transition-colors duration-300">
                        <i data-lucide="scan-line" class="w-5 h-5 text-purple-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- Property Records (PRA) --}}
            <div class="premium-card group border-l-4 border-l-teal-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Property Records (PRA)</p>
                        <h3 class="text-2xl font-black text-teal-700 mt-0.5">{{ number_format($praTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center group-hover:bg-teal-500 transition-colors duration-300">
                        <i data-lucide="database" class="w-5 h-5 text-teal-600 group-hover:text-white transition-colors"></i>
                    </div>
                </div>
            </div>

            {{-- File History Staging --}}
            <div class="premium-card group border-l-4 border-l-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">File History Staging</p>
                        <h3 class="text-2xl font-black text-blue-700 mt-0.5">{{ number_format($fhsTotal) }}</h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-500 transition-colors duration-300">
                        <i data-lucide="archive" class="w-5 h-5 text-blue-600 group-hover:text-white transition-colors"></i>
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
                    Surrender & Release Records
                </h2>
                <div class="flex items-center gap-2">
                    <button id="refresh_btn" class="p-2 hover:bg-slate-100 rounded-lg transition text-slate-400 hover:text-indigo-600" title="Refresh Table">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto w-full">
                    <table id="surrender_release_table" class="w-full text-sm min-w-[1400px]">
                        <thead>
                            <tr class="text-left text-xs font-bold text-slate-500 uppercase tracking-wider bg-slate-50">
                                <th class="px-4 py-4 rounded-tl-xl">S/N</th>
                                <th class="px-4 py-4">File Number</th>
                                <th class="px-4 py-4">Registration Particulars</th>
                                <th class="px-4 py-4">Associated Mortgage</th>
                                <th class="px-4 py-4">Party 1</th>
                                <th class="px-4 py-4">Party 2</th>
                                <th class="px-4 py-4">Party 3</th>
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

    {{-- Mortgage Details Modal --}}
    <div id="mortgage_modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closeMortgagesModal()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-slate-100">
                <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-5 flex items-center justify-between">
                    <h3 class="text-lg font-black text-white flex items-center gap-2" id="modal-title">
                        <i class="fas fa-file-invoice-dollar text-xl"></i>
                        Associated Mortgage Details
                    </h3>
                    <button type="button" class="text-white/80 hover:text-white transition cursor-pointer" onclick="closeMortgagesModal()">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="px-6 py-6 space-y-4 max-h-[70vh] overflow-y-auto bg-slate-50" id="mortgage_modal_body">
                    {{-- Dynamically populated --}}
                </div>

                <div class="bg-white px-6 py-4 border-t border-slate-100 flex justify-end">
                    <button type="button" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-sm transition shadow-md hover:shadow-lg cursor-pointer" onclick="closeMortgagesModal()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL 1: Instrument Capture (IC) — mirrors IC form layout
     ══════════════════════════════════════════════════════════ --}}
<div id="sr-modal-ic" class="sr-modal fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm sr-backdrop"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Edit Instrument Capture Record</h2>
                <p class="text-sm text-gray-500 mt-0.5" id="ic-sr-modal-filelabel"></p>
            </div>
            <button class="sr-close p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <form id="ic-sr-edit-form" class="flex-1 overflow-y-auto p-6 space-y-6">
            <input type="hidden" id="ic-sr-record-id">

            {{-- File Number + Instrument Type + Entry Date --}}
            <div class="grid grid-cols-3 gap-6">
                <div class="border-l-4 border-blue-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">File Number</h3>
                    <input id="ic-sr-file-number" type="text" readonly
                        class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 cursor-not-allowed font-mono">
                </div>
                <div class="border-l-4 border-purple-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Instrument Type <span class="text-red-500">*</span></h3>
                    <select id="ic-sr-instrument-type" class="ic-sr-input w-full">
                        <option value="Deed of Surrender and Release">Deed of Surrender and Release</option>
                        <option value="Deed of Surrender & Release">Deed of Surrender &amp; Release</option>
                        <option value="Deed of Surrender, and Release">Deed of Surrender, and Release</option>
                        <option value="Surrender and Release">Surrender and Release</option>
                        <option value="Surrender & Release">Surrender &amp; Release</option>
                    </select>
                </div>
                <div class="border-l-4 border-gray-400 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Entry Date</h3>
                    <input id="ic-sr-entry-date" type="date" class="ic-sr-input w-full">
                </div>
            </div>

            <hr class="border-gray-200">

            {{-- Party 1 (Surrenderor) | Party 2 (Surrenderee) | Surrender Details --}}
            <div class="grid grid-cols-3 gap-6">
                <div class="border-l-4 border-green-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Party 1 – Surrenderor</h3>
                    <div class="space-y-3">
                        <input id="ic-sr-party-1-name" type="text" placeholder="Name" class="ic-sr-input w-full">
                        <input id="ic-sr-party-1-address" type="text" placeholder="Street Address" class="ic-sr-input w-full">
                        <div class="grid grid-cols-2 gap-2">
                            <input id="ic-sr-party-1-city" type="text" placeholder="City" class="ic-sr-input">
                            <input id="ic-sr-party-1-state" type="text" placeholder="State" class="ic-sr-input">
                        </div>
                        <input id="ic-sr-party-1-phone" type="text" placeholder="Phone" class="ic-sr-input w-full">
                    </div>
                </div>
                <div class="border-l-4 border-amber-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Party 2 – Surrenderee</h3>
                    <div class="space-y-3">
                        <input id="ic-sr-party-2-name" type="text" placeholder="Name" class="ic-sr-input w-full">
                        <input id="ic-sr-party-2-address" type="text" placeholder="Street Address" class="ic-sr-input w-full">
                        <div class="grid grid-cols-2 gap-2">
                            <input id="ic-sr-party-2-city" type="text" placeholder="City" class="ic-sr-input">
                            <input id="ic-sr-party-2-state" type="text" placeholder="State" class="ic-sr-input">
                        </div>
                        <input id="ic-sr-party-2-phone" type="text" placeholder="Phone" class="ic-sr-input w-full">
                    </div>
                </div>
                <div class="border-l-4 border-rose-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Surrender Details</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="ic-sr-label">Reason for Surrender</label>
                            <input id="ic-sr-surrender-reason" type="text" placeholder="Reason" class="ic-sr-input w-full">
                        </div>
                        <div>
                            <label class="ic-sr-label">Compensation Amount</label>
                            <input id="ic-sr-compensation-amount" type="text" placeholder="Amount" class="ic-sr-input w-full">
                        </div>
                        <div>
                            <label class="ic-sr-label">Bank Name</label>
                            <input id="ic-sr-bank-name" type="text" placeholder="Bank name" class="ic-sr-input w-full">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Release / Original Instrument Particulars --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="ic-sr-label">Release Reg Particulars</label>
                    <input id="ic-sr-release-reg-particulars" type="text" placeholder="Release particulars" class="ic-sr-input w-full">
                </div>
                <div>
                    <label class="ic-sr-label">Original Instrument Reg Particulars</label>
                    <input id="ic-sr-original-instrument-reg-particulars" type="text" placeholder="Original instrument particulars" class="ic-sr-input w-full">
                </div>
            </div>

            {{-- Co-Surrenderor section --}}
            <div class="border border-green-100 rounded-lg p-4 bg-green-50/30 space-y-3">
                <h4 class="text-sm font-semibold text-green-800">Co-Surrenderor (Party 3)</h4>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-1">
                        <label class="ic-sr-label">Name</label>
                        <input id="ic-sr-party-3-name" type="text" placeholder="Co-Surrenderor name" class="ic-sr-input w-full">
                    </div>
                    <div class="col-span-1">
                        <label class="ic-sr-label">Address</label>
                        <input id="ic-sr-party-3-address" type="text" placeholder="Address" class="ic-sr-input w-full">
                    </div>
                    <div class="col-span-1">
                        <label class="ic-sr-label">Phone</label>
                        <input id="ic-sr-party-3-phone" type="text" placeholder="Phone" class="ic-sr-input w-full">
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
                            <label class="ic-sr-label">District</label>
                            <select id="ic-sr-district-select" class="ic-sr-input w-full">
                                <option value="">Select District</option>
                                <option value="Other">Other</option>
                            </select>
                            <div id="ic-sr-district-custom-wrap" class="hidden mt-1">
                                <input id="ic-sr-district-custom" type="text" placeholder="Specify district" class="ic-sr-input w-full">
                            </div>
                        </div>
                        <div>
                            <label class="ic-sr-label">LGA</label>
                            <select id="ic-sr-lga-select" class="ic-sr-input w-full">
                                <option value="">Select LGA</option>
                            </select>
                        </div>
                        <div>
                            <label class="ic-sr-label">State</label>
                            <input type="text" value="KANO" readonly class="ic-sr-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                    </div>
                    <div>
                        <label class="ic-sr-label">Built Location Description</label>
                        <input id="ic-sr-location-built" type="text" readonly placeholder="Auto-built from District + LGA"
                            class="ic-sr-input w-full bg-blue-50 text-blue-800 font-semibold uppercase cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <textarea id="ic-sr-property-description" rows="2" placeholder="Property Description / Address"
                        class="ic-sr-input w-full resize-none"></textarea>
                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="ic-sr-label">Plot No.</label>
                                <input id="ic-sr-plot-number" type="text" placeholder="Plot Number" class="ic-sr-input w-full">
                            </div>
                            <div>
                                <label class="ic-sr-label">TP No.</label>
                                <input id="ic-sr-tp-no" type="text" placeholder="TP No" class="ic-sr-input w-full">
                            </div>
                        </div>
                        <div>
                            <label class="ic-sr-label">Size</label>
                            <input id="ic-sr-plot-size" type="text" placeholder="Plot size" class="ic-sr-input w-full">
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
                            <label class="ic-sr-label">Serial No.</label>
                            <input id="ic-sr-serial-no" type="text" placeholder="e.g. 5" class="ic-sr-input w-full">
                        </div>
                        <div>
                            <label class="ic-sr-label">Page No.</label>
                            <input id="ic-sr-page-no" type="text" placeholder="e.g. 5" class="ic-sr-input w-full">
                        </div>
                        <div>
                            <label class="ic-sr-label">Volume No.</label>
                            <input id="ic-sr-volume-no" type="text" placeholder="e.g. 1" class="ic-sr-input w-full">
                        </div>
                    </div>
                    <div>
                        <label class="ic-sr-label">Registration Date</label>
                        <input id="ic-sr-reg-date" type="date" class="ic-sr-input w-full">
                    </div>
                </div>
                <div class="border-l-4 border-indigo-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Solicitor</h3>
                    <div class="space-y-2">
                        <input id="ic-sr-solicitor-name" type="text" placeholder="Name" class="ic-sr-input w-full">
                        <input id="ic-sr-solicitor-address" type="text" placeholder="Address" class="ic-sr-input w-full">
                    </div>
                </div>
            </div>

            <div id="ic-sr-error-box" class="hidden bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700"></div>
        </form>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button class="sr-cancel px-5 py-2.5 text-gray-700 font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">Cancel</button>
            <button id="ic-sr-save-btn" class="px-6 py-2.5 text-white font-medium bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2 text-sm">
                <i data-lucide="save" class="h-4 w-4"></i>
                <span id="ic-sr-save-text">Save Changes</span>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL 2: PRA — mirrors Property Transaction Details card
     ══════════════════════════════════════════════════════════ --}}
<div id="sr-modal-pra" class="sr-modal fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm sr-backdrop"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Edit Property Record (PRA)</h2>
                <p class="text-sm text-gray-500 mt-0.5" id="pra-sr-modal-filelabel"></p>
            </div>
            <button class="sr-close p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <form id="pra-sr-edit-form" class="flex-1 overflow-y-auto p-6 space-y-4">
            <input type="hidden" id="pra-sr-record-id">

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">File Information</h4>
                <div class="grid grid-cols-2 gap-2 text-sm text-blue-700">
                    <div><strong>File Number:</strong> <span id="pra-sr-info-fileno"></span></div>
                    <div><strong>Source:</strong> Property Records (PRA)</div>
                </div>
            </div>

            <div class="border border-gray-300 rounded-lg p-4 bg-white shadow-sm space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instrument Type <span class="text-red-500">*</span></label>
                        <select id="pra-sr-instrument-type" class="pra-sr-input w-full">
                            <option value="Deed of Surrender and Release">Deed of Surrender and Release</option>
                            <option value="Deed of Surrender & Release">Deed of Surrender &amp; Release</option>
                            <option value="Deed of Surrender, and Release">Deed of Surrender, and Release</option>
                            <option value="Surrender and Release">Surrender and Release</option>
                            <option value="Surrender & Release">Surrender &amp; Release</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date (Deeds Date)</label>
                        <input id="pra-sr-deeds-date" type="date" class="pra-sr-input w-full">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration Number</label>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Serial No.</label>
                            <input id="pra-sr-serial-no" type="text" placeholder="e.g. 5" class="pra-sr-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Page No. <span class="text-xs text-gray-400">(auto)</span></label>
                            <input id="pra-sr-page-no" type="text" readonly class="pra-sr-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Volume No.</label>
                            <input id="pra-sr-volume-no" type="text" placeholder="e.g. 2" class="pra-sr-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Reg Date</label>
                            <input id="pra-sr-reg-date" type="date" class="pra-sr-input w-full">
                        </div>
                    </div>
                    <div id="pra-sr-reg-preview" class="hidden mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-sm">
                        <span class="font-semibold text-blue-700">Reg No:</span>
                        <span id="pra-sr-reg-preview-val" class="font-bold text-blue-800 ml-2"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                    <select id="pra-sr-land-use" class="pra-sr-input w-full">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 1 / Surrenderor</label>
                        <input id="pra-sr-party-1" type="text" placeholder="Surrenderor name" class="pra-sr-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 2 / Surrenderee</label>
                        <input id="pra-sr-party-2" type="text" placeholder="Surrenderee name" class="pra-sr-input w-full">
                    </div>
                </div>

                <div class="border-l-2 border-blue-100 pl-4 space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Co-Surrenderor (Surrenderor 2)</label>
                    <input id="pra-sr-surrenderor-2" type="text" placeholder="Second surrenderor name (optional)" class="pra-sr-input w-full">
                </div>

                {{-- Location Builder --}}
                <div class="border border-emerald-100 rounded-lg p-3 bg-emerald-50/30">
                    <p class="text-xs font-semibold text-emerald-700 mb-2 flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3 h-3"></i> Location Builder
                    </p>
                    <div class="grid grid-cols-3 gap-2 mb-2">
                        <div>
                            <label class="pra-sr-label">District</label>
                            <select id="pra-sr-district-select" class="pra-sr-input w-full">
                                <option value="">Select District</option>
                                <option value="Other">Other</option>
                            </select>
                            <div id="pra-sr-district-custom-wrap" class="hidden mt-1">
                                <input id="pra-sr-district-custom" type="text" placeholder="Specify district" class="pra-sr-input w-full">
                            </div>
                        </div>
                        <div>
                            <label class="pra-sr-label">LGA</label>
                            <select id="pra-sr-lga-select" class="pra-sr-input w-full">
                                <option value="">Select LGA</option>
                            </select>
                        </div>
                        <div>
                            <label class="pra-sr-label">State</label>
                            <input type="text" value="KANO" readonly class="pra-sr-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                    </div>
                    <div>
                        <label class="pra-sr-label">Built Location Description</label>
                        <input id="pra-sr-location-built" type="text" readonly placeholder="Auto-built from District + LGA"
                            class="pra-sr-input w-full bg-emerald-50 text-emerald-800 font-semibold uppercase cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remarks / Comments</label>
                    <textarea id="pra-sr-comments" rows="2" placeholder="Additional remarks..." class="pra-sr-input w-full resize-none"></textarea>
                </div>
            </div>

            <div id="pra-sr-error-box" class="hidden bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700"></div>
        </form>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button class="sr-cancel px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">Cancel</button>
            <button id="pra-sr-save-btn" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                <i data-lucide="save" class="h-4 w-4"></i>
                <span id="pra-sr-save-text">Save Transaction Details</span>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL 3: FHS — same layout as PRA + Party 3 & 4
     ══════════════════════════════════════════════════════════ --}}
<div id="sr-modal-fhs" class="sr-modal fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm sr-backdrop"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Edit File History Staging Record</h2>
                <p class="text-sm text-gray-500 mt-0.5" id="fhs-sr-modal-filelabel"></p>
            </div>
            <button class="sr-close p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <form id="fhs-sr-edit-form" class="flex-1 overflow-y-auto p-6 space-y-4">
            <input type="hidden" id="fhs-sr-record-id">

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-amber-800 mb-2">File Information</h4>
                <div class="grid grid-cols-2 gap-2 text-sm text-amber-700">
                    <div><strong>File Number:</strong> <span id="fhs-sr-info-fileno"></span></div>
                    <div><strong>Source:</strong> File History Staging</div>
                </div>
            </div>

            <div class="border border-gray-300 rounded-lg p-4 bg-white shadow-sm space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instrument Type <span class="text-red-500">*</span></label>
                        <select id="fhs-sr-instrument-type" class="fhs-sr-input w-full">
                            <option value="Deed of Surrender and Release">Deed of Surrender and Release</option>
                            <option value="Deed of Surrender & Release">Deed of Surrender &amp; Release</option>
                            <option value="Deed of Surrender, and Release">Deed of Surrender, and Release</option>
                            <option value="Surrender and Release">Surrender and Release</option>
                            <option value="Surrender & Release">Surrender &amp; Release</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date (Deeds Date)</label>
                        <input id="fhs-sr-deeds-date" type="date" class="fhs-sr-input w-full">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration Number</label>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Serial No.</label>
                            <input id="fhs-sr-serial-no" type="text" placeholder="e.g. 5" class="fhs-sr-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Page No. <span class="text-xs text-gray-400">(auto)</span></label>
                            <input id="fhs-sr-page-no" type="text" readonly class="fhs-sr-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Volume No.</label>
                            <input id="fhs-sr-volume-no" type="text" placeholder="e.g. 2" class="fhs-sr-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Reg Date</label>
                            <input id="fhs-sr-reg-date" type="date" class="fhs-sr-input w-full">
                        </div>
                    </div>
                    <div id="fhs-sr-reg-preview" class="hidden mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-sm">
                        <span class="font-semibold text-blue-700">Reg No:</span>
                        <span id="fhs-sr-reg-preview-val" class="font-bold text-blue-800 ml-2"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                    <select id="fhs-sr-land-use" class="fhs-sr-input w-full">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 1 / Surrenderor</label>
                        <input id="fhs-sr-party-1" type="text" placeholder="Surrenderor name" class="fhs-sr-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 2 / Surrenderee</label>
                        <input id="fhs-sr-party-2" type="text" placeholder="Surrenderee name" class="fhs-sr-input w-full">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 3 (Co-Surrenderor)</label>
                        <input id="fhs-sr-party-3" type="text" placeholder="Co-surrenderor (optional)" class="fhs-sr-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Party 4 (Third Party)</label>
                        <input id="fhs-sr-party-4" type="text" placeholder="Third party (optional)" class="fhs-sr-input w-full">
                    </div>
                </div>

                {{-- Location Builder --}}
                <div class="border border-amber-100 rounded-lg p-3 bg-amber-50/30">
                    <p class="text-xs font-semibold text-amber-700 mb-2 flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3 h-3"></i> Location Builder
                    </p>
                    <div class="grid grid-cols-3 gap-2 mb-2">
                        <div>
                            <label class="fhs-sr-label">District</label>
                            <select id="fhs-sr-district-select" class="fhs-sr-input w-full">
                                <option value="">Select District</option>
                                <option value="Other">Other</option>
                            </select>
                            <div id="fhs-sr-district-custom-wrap" class="hidden mt-1">
                                <input id="fhs-sr-district-custom" type="text" placeholder="Specify district" class="fhs-sr-input w-full">
                            </div>
                        </div>
                        <div>
                            <label class="fhs-sr-label">LGA</label>
                            <select id="fhs-sr-lga-select" class="fhs-sr-input w-full">
                                <option value="">Select LGA</option>
                            </select>
                        </div>
                        <div>
                            <label class="fhs-sr-label">State</label>
                            <input type="text" value="KANO" readonly class="fhs-sr-input w-full bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>
                    </div>
                    <div>
                        <label class="fhs-sr-label">Built Location Description</label>
                        <input id="fhs-sr-location-built" type="text" readonly placeholder="Auto-built from District + LGA"
                            class="fhs-sr-input w-full bg-amber-50 text-amber-800 font-semibold uppercase cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remarks / Comments</label>
                    <textarea id="fhs-sr-comments" rows="2" placeholder="Additional remarks..." class="fhs-sr-input w-full resize-none"></textarea>
                </div>
            </div>

            <div id="fhs-sr-error-box" class="hidden bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700"></div>
        </form>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button class="sr-cancel px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">Cancel</button>
            <button id="fhs-sr-save-btn" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                <i data-lucide="save" class="h-4 w-4"></i>
                <span id="fhs-sr-save-text">Save Transaction Details</span>
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
    #surrender_release_table_wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* DataTable Overrides */
    #surrender_release_table_wrapper .dataTables_length select {
        border-radius: 12px;
        padding: 6px 32px 6px 12px;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        color: #475569;
    }
    #surrender_release_table_wrapper .dataTables_filter input {
        border-radius: 12px;
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        width: 300px;
        transition: all 0.2s;
    }
    #surrender_release_table_wrapper .dataTables_filter input:focus {
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
    .source-ic { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
    .source-pra { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .source-fhs { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }

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

    /* Surrender & Release Edit Modals */
    .sr-modal.flex { display: flex !important; }

    .ic-sr-label, .pra-sr-label, .fhs-sr-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 4px;
    }
    .ic-sr-input, .pra-sr-input, .fhs-sr-input {
        padding: 7px 10px;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        color: #374151;
        font-size: 13px;
        background: #fff;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .ic-sr-input:focus, .pra-sr-input:focus, .fhs-sr-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
    }
    select.ic-sr-input, select.pra-sr-input, select.fhs-sr-input {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 28px;
    }

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
    const table = $('#surrender_release_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('surrender-release.data') }}",
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
                    
                    let html = `<div class="flex flex-col justify-center items-start py-1 whitespace-nowrap">
                                    <span class="file-no-link whitespace-nowrap" style="white-space: nowrap;">${fileNo}</span>`;
                    
                    if (fileNo !== '—' || propId) {
                        html += `<button type="button" class="timeline-badge" 
                                            onclick="openPropertyTimeline('${propId}', '${fileNo}')"
                                            title="View full property timeline">
                                        <i class="fas fa-history mr-1 opacity-70"></i> Timeline (${count})
                                    </button>`;
                    }
                    
                    html += `</div>`;
                    return html;
                }
            },
            { data: 'registration_particulars', name: 'registration_particulars', defaultContent: '0/0/0' },
            {
                data: 'associated_mortgages',
                name: 'associated_mortgages',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    const mortgages = data || [];
                    if (mortgages.length === 0) {
                        return `<span class="text-slate-400 text-xs italic">No Mortgage</span>`;
                    }
                    
                    return `<div class="py-1">
                                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 transition duration-150 uppercase tracking-wide cursor-pointer mortgage-trigger shadow-sm hover:shadow" title="View Mortgage Details">
                                    <i class="fas fa-file-invoice-dollar text-[11px] text-amber-600"></i> Mortgage (${mortgages.length})
                                </button>
                            </div>`;
                }
            },
            { data: 'party_1', name: 'party_1', defaultContent: '—' },
            { data: 'party_2', name: 'party_2', defaultContent: '—' },
            { data: 'party_3', name: 'party_3', defaultContent: '—' },
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
                                class="edit-sr-btn inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition"
                                data-id="${data}">
                                <i data-lucide="pencil" class="w-3 h-3"></i> Edit
                            </button>`;
                }
            }
        ],
        pageLength: 25,
        order: [[8, 'desc']],
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

    // Delegate click event on Mortgage button triggers
    $('#surrender_release_table').on('click', '.mortgage-trigger', function(e) {
        e.preventDefault();
        const tr = $(this).closest('tr');
        const rowData = table.row(tr).data();
        if (rowData && rowData.associated_mortgages) {
            openMortgagesModal(rowData.associated_mortgages, rowData.file_number || '—');
        }
    });

    $('#refresh_btn').on('click', function() {
        table.ajax.reload();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
});

// ─────────────────────────────────────────────────────────────────────────
// 3-Modal Edit System (Surrender & Release)
// ─────────────────────────────────────────────────────────────────────────

$(function() {
    function srSetVal(id, v) { $(id).val(v || ''); }
    function srSetDt(id, v)  { $(id).val(v ? String(v).substring(0, 10) : ''); }

    function splitRegNo(regNo) {
        const parts = String(regNo || '').split('/');
        return { s: parts[0] || '', p: parts[1] || '', v: parts[2] || '' };
    }

    function closeAllSrModals() {
        $('.sr-modal').addClass('hidden').removeClass('flex');
    }

    function srSave(compositeId, payload, errorBox, saveBtn, saveText) {
        $(errorBox).addClass('hidden').text('');
        $(saveText).text('Saving…');
        $(saveBtn).prop('disabled', true);

        payload['_method'] = 'PUT';

        $.ajax({
            url: '/surrender-release/' + compositeId,
            method: 'POST',
            data: payload,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function() {
                closeAllSrModals();
                srTable.ajax.reload(null, false);
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

    // ─── Reference Data + Location Builder ───────────────────────────────────
    let _srRefDistricts = null, _srRefLgas = null;

    function srLoadRefData(callback) {
        if (_srRefDistricts && _srRefLgas) { callback(); return; }
        $.when(
            $.getJSON('/api/reference/districts'),
            $.getJSON('/api/reference/lgas')
        ).done(function(dRes, lRes) {
            _srRefDistricts = dRes[0].data || [];
            _srRefLgas      = lRes[0].data || [];
            callback();
        });
    }

    function srPopulateLocationDropdowns(prefix, districtVal, lgaVal) {
        const $distSel = $('#' + prefix + '-district-select');
        const $lgaSel  = $('#' + prefix + '-lga-select');

        $distSel.find('option:not([value=""]):not([value="Other"])').remove();
        (_srRefDistricts || []).forEach(function(d) {
            $distSel.append($('<option>', { value: d.name, text: d.name }));
        });
        $lgaSel.find('option:not([value=""])').remove();
        (_srRefLgas || []).forEach(function(l) {
            $lgaSel.append($('<option>', { value: l.name, text: l.name }));
        });

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

        if (lgaVal) { $lgaSel.val(lgaVal); } else { $lgaSel.val(''); }

        srBuildLocationString(prefix);
    }

    function srBuildLocationString(prefix) {
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

    ['ic-sr', 'pra-sr', 'fhs-sr'].forEach(function(p) {
        $(document).on('change', '#' + p + '-district-select', function() {
            $('#' + p + '-district-custom-wrap').toggleClass('hidden', $(this).val() !== 'Other');
            srBuildLocationString(p);
        });
        $(document).on('input',  '#' + p + '-district-custom', function() { srBuildLocationString(p); });
        $(document).on('change', '#' + p + '-lga-select',      function() { srBuildLocationString(p); });
    });
    // ─────────────────────────────────────────────────────────────────────────

    // Store DataTable reference for reload after save
    const srTable = $('#surrender_release_table').DataTable();

    // ── Open the right modal ──
    $('#surrender_release_table').on('click', '.edit-sr-btn', function() {
        const compositeId = $(this).data('id');
        const prefix = compositeId.split('_')[0]; // ic | pra | fhs

        $.ajax({
            url: '/surrender-release/' + compositeId + '/edit',
            method: 'GET',
            success: function(d) {
                closeAllSrModals();
                if (prefix === 'ic') {
                    openIcSrModal(compositeId, d);
                } else if (prefix === 'pra') {
                    openPraSrModal(compositeId, d);
                } else {
                    openFhsSrModal(compositeId, d);
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            },
            error: function() {
                alert('Failed to load record. Please try again.');
            }
        });
    });

    // ── IC Modal ──
    function openIcSrModal(id, d) {
        const reg = splitRegNo(d.registration_particulars);

        srSetVal('#ic-sr-record-id', id);
        $('#ic-sr-modal-filelabel').text('File: ' + (d.file_number || '—'));
        srSetVal('#ic-sr-file-number', d.file_number);
        srSetVal('#ic-sr-instrument-type', d.instrument_type);
        srSetDt('#ic-sr-entry-date', d.entry_date);
        // Party 1
        srSetVal('#ic-sr-party-1-name',    d.party_1);
        srSetVal('#ic-sr-party-1-address', d.party_1_address);
        srSetVal('#ic-sr-party-1-phone',   d.party_1_phone);
        srSetVal('#ic-sr-party-1-city',    d.party_1_city);
        srSetVal('#ic-sr-party-1-state',   d.party_1_state);
        // Party 2
        srSetVal('#ic-sr-party-2-name',    d.party_2);
        srSetVal('#ic-sr-party-2-address', d.party_2_address);
        srSetVal('#ic-sr-party-2-phone',   d.party_2_phone);
        srSetVal('#ic-sr-party-2-city',    d.party_2_city);
        srSetVal('#ic-sr-party-2-state',   d.party_2_state);
        // Party 3 — Co-Surrenderor
        srSetVal('#ic-sr-party-3-name',    d.party_3);
        srSetVal('#ic-sr-party-3-address', d.party_3_address);
        srSetVal('#ic-sr-party-3-phone',   d.party_3_phone);
        // Surrender details
        srSetVal('#ic-sr-surrender-reason',                    d.surrender_reason);
        srSetVal('#ic-sr-compensation-amount',                 d.compensation_amount);
        srSetVal('#ic-sr-bank-name',                           d.bank_name);
        srSetVal('#ic-sr-release-reg-particulars',             d.release_reg_particulars);
        srSetVal('#ic-sr-original-instrument-reg-particulars', d.original_instrument_reg_particulars);
        // Property
        srSetVal('#ic-sr-property-description', d.property_description);
        srSetVal('#ic-sr-plot-number', d.plot_number);
        srSetVal('#ic-sr-plot-size',   d.plot_size);
        srLoadRefData(function() { srPopulateLocationDropdowns('ic-sr', d.district, d.lga); });
        // Registration
        srSetVal('#ic-sr-serial-no', reg.s);
        srSetVal('#ic-sr-page-no',   reg.p);
        srSetVal('#ic-sr-volume-no', reg.v);
        srSetDt('#ic-sr-reg-date', d.transaction_date);
        // Solicitor
        srSetVal('#ic-sr-solicitor-name',    d.solicitor_name);
        srSetVal('#ic-sr-solicitor-address', d.solicitor_address);
        // Show
        $('#ic-sr-error-box').addClass('hidden');
        $('#ic-sr-save-text').text('Save Changes');
        $('#ic-sr-save-btn').prop('disabled', false);
        $('#sr-modal-ic').removeClass('hidden').addClass('flex');
    }

    $('#ic-sr-serial-no').on('input', function() { srSetVal('#ic-sr-page-no', $(this).val()); });

    $('#ic-sr-save-btn').on('click', function() {
        const id  = $('#ic-sr-record-id').val();
        const s   = $('#ic-sr-serial-no').val(), p = $('#ic-sr-page-no').val(), v = $('#ic-sr-volume-no').val();
        srSave(id, {
            _method:                  'PUT',
            instrument_type:          $('#ic-sr-instrument-type').val(),
            registration_particulars: [s, p, v].filter(Boolean).join('/'),
            transaction_date:         $('#ic-sr-reg-date').val(),
            entry_date:               $('#ic-sr-entry-date').val(),
            party_1:                  $('#ic-sr-party-1-name').val(),
            party_1_address:          $('#ic-sr-party-1-address').val(),
            party_1_phone:            $('#ic-sr-party-1-phone').val(),
            party_1_city:             $('#ic-sr-party-1-city').val(),
            party_1_state:            $('#ic-sr-party-1-state').val(),
            party_2:                  $('#ic-sr-party-2-name').val(),
            party_2_address:          $('#ic-sr-party-2-address').val(),
            party_2_phone:            $('#ic-sr-party-2-phone').val(),
            party_2_city:             $('#ic-sr-party-2-city').val(),
            party_2_state:            $('#ic-sr-party-2-state').val(),
            party_3:                  $('#ic-sr-party-3-name').val(),
            party_3_address:          $('#ic-sr-party-3-address').val(),
            party_3_phone:            $('#ic-sr-party-3-phone').val(),
            surrender_reason:                    $('#ic-sr-surrender-reason').val(),
            compensation_amount:                 $('#ic-sr-compensation-amount').val(),
            bank_name:                           $('#ic-sr-bank-name').val(),
            release_reg_particulars:             $('#ic-sr-release-reg-particulars').val(),
            original_instrument_reg_particulars: $('#ic-sr-original-instrument-reg-particulars').val(),
            property_description:     $('#ic-sr-property-description').val(),
            plot_number:              $('#ic-sr-plot-number').val(),
            plot_size:                $('#ic-sr-plot-size').val(),
            district:                 $('#ic-sr-district-select').val() === 'Other' ? $('#ic-sr-district-custom').val() : $('#ic-sr-district-select').val(),
            lga:                      $('#ic-sr-lga-select').val(),
            location:                 $('#ic-sr-location-built').val(),
            solicitor_name:           $('#ic-sr-solicitor-name').val(),
            solicitor_address:        $('#ic-sr-solicitor-address').val(),
        }, '#ic-sr-error-box', '#ic-sr-save-btn', '#ic-sr-save-text');
    });

    // ── PRA Modal ──
    function openPraSrModal(id, d) {
        const reg = splitRegNo(d.registration_particulars);
        srSetVal('#pra-sr-record-id', id);
        $('#pra-sr-modal-filelabel').text('File: ' + (d.file_number || '—'));
        $('#pra-sr-info-fileno').text(d.file_number || '—');
        srSetVal('#pra-sr-instrument-type', d.instrument_type);
        srSetDt('#pra-sr-deeds-date', d.transaction_date);
        srSetVal('#pra-sr-serial-no', reg.s);
        srSetVal('#pra-sr-page-no',   reg.p);
        srSetVal('#pra-sr-volume-no', reg.v);
        srSetDt('#pra-sr-reg-date',   d.transaction_date);
        updatePraSrPreview();
        srSetVal('#pra-sr-land-use',      d.land_use);
        srSetVal('#pra-sr-party-1',        d.party_1);
        srSetVal('#pra-sr-party-2',        d.party_2);
        srSetVal('#pra-sr-surrenderor-2',  d.surrenderor_2);
        srLoadRefData(function() { srPopulateLocationDropdowns('pra-sr', d.district, d.lga); });
        srSetVal('#pra-sr-comments', d.comments);
        $('#pra-sr-error-box').addClass('hidden');
        $('#pra-sr-save-text').text('Save Transaction Details');
        $('#pra-sr-save-btn').prop('disabled', false);
        $('#sr-modal-pra').removeClass('hidden').addClass('flex');
    }

    function updatePraSrPreview() {
        const s = $('#pra-sr-serial-no').val(), p = $('#pra-sr-page-no').val(), v = $('#pra-sr-volume-no').val();
        const preview = [s, p, v].filter(Boolean).join('/');
        $('#pra-sr-reg-preview').toggleClass('hidden', !preview);
        $('#pra-sr-reg-preview-val').text(preview);
    }
    $('#pra-sr-serial-no').on('input', function() { srSetVal('#pra-sr-page-no', $(this).val()); updatePraSrPreview(); });
    $('#pra-sr-volume-no').on('input', updatePraSrPreview);

    $('#pra-sr-save-btn').on('click', function() {
        const id = $('#pra-sr-record-id').val();
        const s  = $('#pra-sr-serial-no').val(), p = $('#pra-sr-page-no').val(), v = $('#pra-sr-volume-no').val();
        srSave(id, {
            _method:                  'PUT',
            instrument_type:          $('#pra-sr-instrument-type').val(),
            registration_particulars: [s, p, v].filter(Boolean).join('/'),
            transaction_date:         $('#pra-sr-deeds-date').val(),
            party_1:                  $('#pra-sr-party-1').val(),
            party_2:                  $('#pra-sr-party-2').val(),
            surrenderor_2:            $('#pra-sr-surrenderor-2').val(),
            land_use:                 $('#pra-sr-land-use').val(),
            district:                 $('#pra-sr-district-select').val() === 'Other' ? $('#pra-sr-district-custom').val() : $('#pra-sr-district-select').val(),
            lga:                      $('#pra-sr-lga-select').val(),
            location:                 $('#pra-sr-location-built').val(),
            comments:                 $('#pra-sr-comments').val(),
        }, '#pra-sr-error-box', '#pra-sr-save-btn', '#pra-sr-save-text');
    });

    // ── FHS Modal ──
    function openFhsSrModal(id, d) {
        const reg = splitRegNo(d.registration_particulars);
        srSetVal('#fhs-sr-record-id', id);
        $('#fhs-sr-modal-filelabel').text('File: ' + (d.file_number || '—'));
        $('#fhs-sr-info-fileno').text(d.file_number || '—');
        srSetVal('#fhs-sr-instrument-type', d.instrument_type);
        srSetDt('#fhs-sr-deeds-date', d.transaction_date);
        srSetVal('#fhs-sr-serial-no', reg.s);
        srSetVal('#fhs-sr-page-no',   reg.p);
        srSetVal('#fhs-sr-volume-no', reg.v);
        srSetDt('#fhs-sr-reg-date',   d.transaction_date);
        updateFhsSrPreview();
        srSetVal('#fhs-sr-land-use', d.land_use);
        srSetVal('#fhs-sr-party-1',  d.party_1);
        srSetVal('#fhs-sr-party-2',  d.party_2);
        srSetVal('#fhs-sr-party-3',  d.party_3);
        srSetVal('#fhs-sr-party-4',  d.party_4);
        srLoadRefData(function() { srPopulateLocationDropdowns('fhs-sr', d.district, d.lga); });
        srSetVal('#fhs-sr-comments', d.comments);
        $('#fhs-sr-error-box').addClass('hidden');
        $('#fhs-sr-save-text').text('Save Transaction Details');
        $('#fhs-sr-save-btn').prop('disabled', false);
        $('#sr-modal-fhs').removeClass('hidden').addClass('flex');
    }

    function updateFhsSrPreview() {
        const s = $('#fhs-sr-serial-no').val(), p = $('#fhs-sr-page-no').val(), v = $('#fhs-sr-volume-no').val();
        const preview = [s, p, v].filter(Boolean).join('/');
        $('#fhs-sr-reg-preview').toggleClass('hidden', !preview);
        $('#fhs-sr-reg-preview-val').text(preview);
    }
    $('#fhs-sr-serial-no').on('input', function() { srSetVal('#fhs-sr-page-no', $(this).val()); updateFhsSrPreview(); });
    $('#fhs-sr-volume-no').on('input', updateFhsSrPreview);

    $('#fhs-sr-save-btn').on('click', function() {
        const id = $('#fhs-sr-record-id').val();
        const s  = $('#fhs-sr-serial-no').val(), p = $('#fhs-sr-page-no').val(), v = $('#fhs-sr-volume-no').val();
        srSave(id, {
            _method:                  'PUT',
            instrument_type:          $('#fhs-sr-instrument-type').val(),
            registration_particulars: [s, p, v].filter(Boolean).join('/'),
            transaction_date:         $('#fhs-sr-deeds-date').val(),
            party_1:                  $('#fhs-sr-party-1').val(),
            party_2:                  $('#fhs-sr-party-2').val(),
            party_3:                  $('#fhs-sr-party-3').val(),
            party_4:                  $('#fhs-sr-party-4').val(),
            land_use:                 $('#fhs-sr-land-use').val(),
            district:                 $('#fhs-sr-district-select').val() === 'Other' ? $('#fhs-sr-district-custom').val() : $('#fhs-sr-district-select').val(),
            lga:                      $('#fhs-sr-lga-select').val(),
            location:                 $('#fhs-sr-location-built').val(),
            comments:                 $('#fhs-sr-comments').val(),
        }, '#fhs-sr-error-box', '#fhs-sr-save-btn', '#fhs-sr-save-text');
    });

    // ── Close handlers ──
    $(document).on('click', '.sr-backdrop, .sr-close, .sr-cancel', closeAllSrModals);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') closeAllSrModals(); });
});

// Global functions for open/close Mortgage details modal
function openMortgagesModal(mortgages, fileNo) {
    const body = $('#mortgage_modal_body');
    body.empty();
    
    $('#modal-title').html(`<i class="fas fa-file-invoice-dollar text-xl"></i> Mortgages for ${fileNo}`);

    mortgages.forEach(function(m) {
        const mType = m.instrument_type || 'Deed of Mortgage';
        const reg = m.registration_particulars && m.registration_particulars.trim() ? m.registration_particulars : 'Unregistered';
        const mortgagor = m.party_1 || '—';
        const mortgagee = m.party_2 || '—';
        const party3Html = m.party_3 && m.party_3.trim() ? `
        <div class="pt-2">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Party 3</p>
            <p class="text-sm font-bold text-slate-800 mt-0.5">${m.party_3}</p>
        </div>` : '';
        
        let dateVal = '—';
        if (m.date_captured) {
            try {
                dateVal = m.date_captured.substring(0, 16).replace('T', ' ');
            } catch (e) {
                dateVal = m.date_captured;
            }
        }
        
        const cardHtml = `
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4 hover:border-amber-300 transition-all duration-300">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-amber-50 text-amber-800 border border-amber-200 uppercase tracking-wide">
                    <i class="fas fa-file-invoice-dollar text-amber-600 text-[10px]"></i> ${mType}
                </span>
                <span class="px-2.5 py-1 text-xs font-mono font-bold bg-slate-100 text-slate-700 rounded-lg border border-slate-200">
                    ${reg}
                </span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Mortgagor (Party 1)</p>
                    <p class="text-sm font-bold text-slate-800 mt-0.5">${mortgagor}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Mortgagee (Party 2)</p>
                    <p class="text-sm font-bold text-slate-800 mt-0.5">${mortgagee}</p>
                </div>
            </div>

            ${party3Html}

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                <div class="flex items-center gap-1.5">
                    <i class="fas fa-database text-slate-400 text-[10px]"></i>
                    <span>Source: <strong class="text-slate-700">${m.source || '—'}</strong></span>
                </div>
                <div class="flex items-center gap-1.5 md:justify-end">
                    <i class="fas fa-calendar-alt text-slate-400 text-[10px]"></i>
                    <span>Date Captured: <strong class="text-slate-700">${dateVal}</strong></span>
                </div>
            </div>
        </div>`;
        
        body.append(cardHtml);
    });

    $('#mortgage_modal').removeClass('hidden');
    $('body').addClass('overflow-hidden');
}

function closeMortgagesModal() {
    $('#mortgage_modal').addClass('hidden');
    $('body').removeClass('overflow-hidden');
}

window.openMortgagesModal = openMortgagesModal;
window.closeMortgagesModal = closeMortgagesModal;
</script>
@endpush

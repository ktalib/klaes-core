@extends('layouts.app')

@section('page-title')
    OP Verification
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', ['PageTitle' => 'OP Verification', 'PageDescription' => 'Verify the authenticity of an Occupancy Permit.'])

    <div class="p-6 space-y-6">

        {{-- ⓪ Dashboard --}}
        <div class="space-y-4" id="verification_dashboard">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="gauge" class="w-4 h-4 text-teal-600"></i>
                        Verification Overview
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Where the OP register stands, and what officers have checked.</p>
                </div>
                <button type="button" id="toggle_dashboard"
                    class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-1.5">
                    <span id="toggle_dashboard_icon"><i data-lucide="chevron-up" class="w-3.5 h-3.5"></i></span>
                    <span id="toggle_dashboard_label">Hide</span>
                </button>
            </div>

            <div id="dashboard_body" class="space-y-4">
                {{-- Stat cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-start gap-3">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 text-slate-600 shrink-0">
                            <i data-lucide="file-badge" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider font-bold">OPs in Register</div>
                            <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="kpi_total_ops">—</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-start gap-3">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 shrink-0">
                            <i data-lucide="badge-check" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider font-bold">OPs Verified</div>
                            <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="kpi_ops_verified">—</div>
                            <div class="text-[11px] text-slate-400 mt-0.5" id="kpi_coverage_note">—</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-start gap-3">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-amber-100 text-amber-600 shrink-0">
                            <i data-lucide="shield-alert" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider font-bold">Flagged Suspicious</div>
                            <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="kpi_ops_flagged">—</div>
                            <div class="text-[11px] text-slate-400 mt-0.5" id="kpi_unmatched_note">—</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-start gap-3">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-teal-100 text-teal-600 shrink-0">
                            <i data-lucide="clipboard-check" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider font-bold">Checks Logged</div>
                            <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="kpi_checks_total">—</div>
                            <div class="text-[11px] text-slate-400 mt-0.5" id="kpi_checks_split">—</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-start gap-3">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 shrink-0">
                            <i data-lucide="calendar-check" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider font-bold">Checked Today</div>
                            <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="kpi_checks_today">—</div>
                            <div class="text-[11px] text-slate-400 mt-0.5" id="kpi_checks_week">—</div>
                        </div>
                    </div>
                </div>

                {{-- Coverage + officers --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2 mb-4">
                            <i data-lucide="target" class="w-4 h-4 text-teal-600"></i>
                            Register coverage
                        </h3>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-500 mb-2">
                            <span>OPs with a verification on record</span>
                            <span class="text-slate-700" id="coverage_label">—</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div id="coverage_bar" class="h-2 rounded-full transition-all" style="width:0%;background:#0d9488"></div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2">Share of OPs in the register with at least one verification on record.</p>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2 mb-4">
                            <i data-lucide="users" class="w-4 h-4 text-teal-600"></i>
                            Most active officers
                        </h3>
                        <div id="officers_list" class="space-y-3">
                            <p class="text-sm text-slate-400">Loading…</p>
                        </div>
                    </div>
                </div>
            </div>{{-- /#dashboard_body --}}
        </div>{{-- /#verification_dashboard --}}

        {{-- ① Verification method --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3">Verification Method</div>

            <div class="flex flex-wrap gap-3 mb-5">
                <button type="button" id="method_serial"
                    class="method-btn active-method flex items-center gap-2 px-4 py-2.5 rounded-xl border text-sm font-semibold transition">
                    <i data-lucide="hash" class="w-4 h-4"></i>
                    OP Serial No
                </button>

                <button type="button" id="method_qr" disabled
                    title="QR verification is not available yet"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-400 text-sm font-semibold cursor-not-allowed">
                    <i data-lucide="qr-code" class="w-4 h-4"></i>
                    Scan QR Code
                    <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-200 text-slate-500 text-[10px] uppercase tracking-wider">Coming soon</span>
                </button>
            </div>

            {{-- Serial search --}}
            <form id="opSearchForm" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                    <input type="text" id="serial_input" autocomplete="off"
                        class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all shadow-sm"
                        placeholder="Enter OP Serial No (e.g. 333)">
                </div>
                <button type="submit"
                    class="px-6 py-2.5 text-sm font-bold text-white bg-teal-600 hover:bg-teal-700 rounded-xl transition-all shadow-md shadow-teal-200 flex items-center justify-center gap-2">
                    <i data-lucide="scan-search" class="w-4 h-4"></i>
                    Find OP
                </button>
            </form>
            <p class="text-xs text-slate-400 mt-2">
                Every OP is listed below. Serial numbers are not unique — searching one keeps all its matches, so pick the correct record before verifying.
            </p>
        </div>

        {{-- Row action menu (kept at body level so the table can scroll) --}}
        <div id="row_menu" class="hidden fixed z-[90] w-52 bg-white rounded-xl border border-slate-200 shadow-xl py-1.5">
            <button type="button" data-menu-status="verified" class="row-menu-item w-full text-left px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="badge-check" class="w-4 h-4 text-teal-600"></i> Verify OP
            </button>
            <button type="button" data-menu-status="suspicious" class="row-menu-item w-full text-left px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="shield-alert" class="w-4 h-4 text-amber-600"></i> Flag as Suspicious
            </button>
        </div>

        {{-- ② Tabs: All OPs / Recent Verifications --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
            <div class="border-b border-slate-200 px-5 pt-4 flex items-center gap-1 flex-wrap" id="op_tabs">
                <button type="button" class="op-tab-btn active-tab px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 border-transparent transition flex items-center gap-2"
                    data-tab="all_ops">
                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                    <span id="results_title">All OPs</span>
                    <span id="result_count" class="text-slate-400 font-medium"></span>
                </button>
                <button type="button" class="op-tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 border-transparent transition flex items-center gap-2"
                    data-tab="recent">
                    <i data-lucide="history" class="w-4 h-4"></i>
                    Recent Verifications
                    <span id="recent_count" class="text-slate-400 font-medium">({{ $recent->count() }})</span>
                </button>
            </div>

            <div class="p-4">
                {{-- Tab: All OPs --}}
                <div id="tab_all_ops" class="op-tab-content space-y-3">
                    <div class="flex items-center justify-end">
                        <button type="button" id="clear_filter" class="hidden px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-1.5">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            Clear filter
                        </button>
                    </div>

                    <table id="op_table" class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                                <th class="px-4 py-3">FileNo</th>
                                <th class="px-4 py-3">OP Type</th>
                                <th class="px-4 py-3">OP Serial No</th>
                                <th class="px-4 py-3">Registration Particulars</th>
                                <th class="px-4 py-3">Party 2</th>
                                <th class="px-4 py-3">Property Description</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                    </table>

                    {{-- Shown when a searched serial matches nothing --}}
                    <div id="not_found_panel" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <div class="flex items-start gap-4">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-amber-100 text-amber-600 shrink-0">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-slate-800">No OP found for serial <span id="nf_serial" class="font-mono"></span></h3>
                    <p class="text-sm text-slate-500 mt-1">
                        The serial number does not match any record. Log the outcome so the check is on record.
                    </p>
                    <div class="flex flex-wrap gap-3 mt-4">
                        <button type="button" data-nf-status="suspicious"
                            class="nf-action px-4 py-2 text-sm font-semibold text-amber-700 bg-white border border-amber-200 rounded-xl hover:bg-amber-100 transition-colors shadow-sm flex items-center gap-2">
                            <i data-lucide="shield-alert" class="w-4 h-4"></i>
                            Flag as Suspicious
                        </button>
                    </div>
                </div>
            </div>
                    </div>{{-- /#not_found_panel --}}
                </div>{{-- /#tab_all_ops --}}

                {{-- Tab: Recent Verifications --}}
                <div id="tab_recent" class="op-tab-content hidden overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-500 font-bold">
                                <th class="px-5 py-3">Serial No</th>
                                <th class="px-5 py-3">File No</th>
                                <th class="px-5 py-3">Allottee</th>
                                <th class="px-5 py-3">Outcome</th>
                                <th class="px-5 py-3">Remarks</th>
                                <th class="px-5 py-3">Officer</th>
                                <th class="px-5 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody id="recent_body" class="text-sm divide-y divide-slate-100">
                        @forelse($recent as $v)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-3 font-mono font-bold text-slate-800">{{ $v->op_serial_number ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $v->mls_file_number ?: ($v->temp_fileno ?: '—') }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $v->allottee ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @php $s = $v->status; @endphp
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold
                                        @if($s === 'verified') bg-emerald-100 text-emerald-800
                                        @elseif($s === 'suspicious') bg-amber-100 text-amber-800
                                        @else bg-slate-100 text-slate-600 @endif">
                                        {{ $v->status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-500 max-w-xs truncate">{{ $v->remarks ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $v->verified_by_name ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">
                                    {{ $v->verified_at ? $v->verified_at->format('d M Y, H:i') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr id="recent_empty">
                                <td colspan="7" class="px-5 py-10 text-center text-slate-400">No verifications logged yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>{{-- /#tab_recent --}}
            </div>{{-- /.p-4 --}}
        </div>{{-- /tab card --}}

    </div>{{-- /.p-6 --}}
    @include('admin.footer')
</div>

{{-- Verify modal --}}
<div id="verifyModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="verifyBackdrop"></div>

    <div class="relative bg-white rounded-3xl w-full max-w-lg shadow-2xl scale-95 opacity-0 transition-all duration-300 transform" id="verifyContent">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 rounded-t-3xl">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="badge-check" class="h-5 w-5 text-teal-600"></i>
                Verify OP
            </h3>
            <button type="button" onclick="closeVerifyModal()" class="text-slate-400 hover:text-slate-600 transition-colors bg-white hover:bg-slate-100 p-2 rounded-xl shadow-sm border border-slate-200">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <div class="p-6 space-y-5">
            <div id="modal_summary" class="rounded-2xl bg-slate-50 border border-slate-100 p-4 text-sm"></div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Outcome</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="status_options">
                    <label data-status="verified" class="status-option flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                        <input type="radio" name="status" value="verified" class="h-4 w-4 border-slate-300">
                        <span class="text-sm font-semibold text-slate-700">OP Verified</span>
                    </label>
                    <label data-status="suspicious" class="status-option flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                        <input type="radio" name="status" value="suspicious" class="h-4 w-4 border-slate-300">
                        <span class="text-sm font-semibold text-slate-700">Suspicious</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Remarks <span class="text-slate-300 normal-case font-medium">(optional)</span></label>
                <textarea id="remarks_input" rows="3"
                    class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all shadow-sm"
                    placeholder="Anything the officer should note about this check"></textarea>
            </div>

            <p id="modal_error" class="hidden text-sm text-rose-600 font-medium"></p>
        </div>

        <div class="px-6 py-4 bg-slate-50 rounded-b-3xl flex justify-end gap-3 border-t border-slate-100">
            <button type="button" onclick="closeVerifyModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-800 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                Cancel
            </button>
            <button type="button" id="save_verification"
                class="px-5 py-2.5 text-sm font-bold text-white bg-teal-600 hover:bg-teal-700 rounded-xl transition-all shadow-md shadow-teal-200 flex items-center gap-2">
                <i data-lucide="save" class="h-4 w-4"></i>
                Verify
            </button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* ── Palette shim ──────────────────────────────────────────────────────
       The app loads Tailwind 2.2 from CDN, which has no slate / teal /
       emerald / amber / rose scales and no v3 opacity-slash syntax. Only the
       utilities this page needs are defined here. */
    .bg-white\/0 { background: transparent; }
    .bg-slate-50, .bg-slate-50\/60, .bg-slate-50\/50 { background-color: #f8fafc; }
    .bg-slate-100 { background-color: #f1f5f9; }
    .bg-slate-200 { background-color: #e2e8f0; }
    .bg-slate-900\/40 { background-color: rgba(15, 23, 42, .4); }
    .bg-teal-50, .bg-teal-50\/40 { background-color: #f0fdfa; }
    .bg-teal-600 { background-color: #0d9488; }
    .hover\:bg-teal-700:hover { background-color: #0f766e; }
    .bg-emerald-50 { background-color: #ecfdf5; }
    .bg-emerald-100 { background-color: #d1fae5; }
    .bg-amber-50 { background-color: #fffbeb; }
    .bg-amber-100, .hover\:bg-amber-100:hover { background-color: #fef3c7; }
    .bg-rose-50 { background-color: #fff1f2; }
    .bg-rose-100, .hover\:bg-rose-100:hover { background-color: #ffe4e6; }
    .bg-indigo-50 { background-color: #eef2ff; }
    .hover\:bg-slate-50:hover, .hover\:bg-slate-50\/50:hover { background-color: #f8fafc; }
    .hover\:bg-slate-100:hover { background-color: #f1f5f9; }

    .text-slate-900 { color: #0f172a; }
    .text-slate-800 { color: #1e293b; }
    .text-slate-700 { color: #334155; }
    .text-slate-600 { color: #475569; }
    .text-slate-500 { color: #64748b; }
    .text-slate-400 { color: #94a3b8; }
    .text-slate-300 { color: #cbd5e1; }
    .hover\:text-slate-800:hover { color: #1e293b; }
    .hover\:text-slate-600:hover { color: #475569; }
    .text-teal-600 { color: #0d9488; }
    .text-teal-700 { color: #0f766e; }
    .text-emerald-600 { color: #059669; }
    .text-emerald-800 { color: #065f46; }
    .text-amber-600 { color: #d97706; }
    .text-amber-700 { color: #b45309; }
    .text-amber-800 { color: #92400e; }
    .text-rose-600 { color: #e11d48; }
    .text-rose-700 { color: #be123c; }
    .text-rose-800 { color: #9f1239; }
    .text-indigo-700 { color: #4338ca; }

    .border-slate-100 { border-color: #f1f5f9; }
    .border-slate-200 { border-color: #e2e8f0; }
    .border-slate-300 { border-color: #cbd5e1; }
    .border-slate-400 { border-color: #94a3b8; }
    .border-teal-500 { border-color: #14b8a6; }
    .border-emerald-200 { border-color: #a7f3d0; }
    .border-emerald-500 { border-color: #10b981; }
    .border-amber-200 { border-color: #fde68a; }
    .border-amber-500 { border-color: #f59e0b; }
    .border-rose-200 { border-color: #fecdd3; }
    .border-rose-500 { border-color: #f43f5e; }
    .divide-slate-100 > * + * { border-color: #f1f5f9; }
    .border-teal-200 { border-color: #99f6e4; }
    .bg-teal-100, .hover\:bg-teal-100:hover { background-color: #ccfbf1; }
    .bg-orange-100 { background-color: #ffedd5; }
    .text-orange-700 { color: #c2410c; }
    .bg-sky-100 { background-color: #e0f2fe; }
    .text-sky-700 { color: #0369a1; }
    .bg-indigo-100 { background-color: #e0e7ff; }
    .text-indigo-600 { color: #4f46e5; }
    .bg-emerald-500 { background-color: #10b981; }
    .bg-amber-500 { background-color: #f59e0b; }

    /* v3-only arbitrary values used on this page */
    .text-\[11px\] { font-size: 11px; }
    .text-\[10px\] { font-size: 10px; }
    .z-\[90\]  { z-index: 90; }
    .z-\[100\] { z-index: 100; }

    /* DataTables tweaks, mirroring the OPs Dashboard */
    table.dataTable thead th {
        background: #f8fafc;
        font-weight: 600;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
    }
    table.dataTable tbody tr:hover td { background: #f8fafc; }
    table.dataTable tbody tr.bg-emerald-50 td { background: #ecfdf5; }
    table.dataTable tbody td { padding: 10px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: #0d9488 !important; color: #fff !important; border-color: #0d9488 !important; border-radius: 6px;
    }
    .dataTables_wrapper .dataTables_length select { border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 6px; }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_length { font-size: 13px; color: #64748b; }

    /* Stacked file cell, mirroring the OPs Dashboard */
    .file-cell-mls   { font-weight: 700; font-size: 13px; color: #1e293b; }
    .file-cell-temp  { font-family: ui-monospace, monospace; font-size: 11px; color: #94a3b8; }
    .file-cell-empty { color: #cbd5e1; font-size: 11px; }
    .shadow-teal-200 { box-shadow: 0 4px 10px -2px rgba(153, 246, 228, .8); }
    .backdrop-blur-sm { backdrop-filter: blur(4px); }
    .rounded-3xl { border-radius: 1.5rem; }
    #serial_input:focus, #remarks_input:focus { border-color: #14b8a6; box-shadow: 0 0 0 3px rgba(20, 184, 166, .18); }

    /* ── Tabs ── */
    .op-tab-btn { color: #64748b; background: transparent; border-color: transparent; }
    .op-tab-btn:hover { color: #0f766e; background: #f0fdfa; }
    .op-tab-btn.active-tab {
        color: #0f766e;
        background: #fff;
        border-color: #e2e8f0 #e2e8f0 #fff;
        font-weight: 700;
    }

    /* ── Method toggle ── */
    .method-btn { border-color: #e2e8f0; color: #475569; background: #fff; }
    .method-btn.active-method { border-color: #0d9488; color: #0f766e; background: #f0fdfa; }

    /* ── Outcome picker (no :has() support in Tailwind 2) ── */
    .status-option.is-selected[data-status="verified"]   { border-color: #10b981; background: #ecfdf5; }
    .status-option.is-selected[data-status="suspicious"] { border-color: #f59e0b; background: #fffbeb; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const tableUrl  = @json(route('op-verifications.table'));
    const storeUrl  = @json(route('op-verifications.store'));
    const statsUrl  = @json(route('op-verifications.dashboard'));
    const csrf      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const STATUS_STYLES = {
        verified:   { badge: 'bg-emerald-100 text-emerald-800', icon: 'badge-check' },
        suspicious: { badge: 'bg-amber-100 text-amber-800',     icon: 'shield-alert' },
    };
    const STATUS_FALLBACK = { badge: 'bg-slate-100 text-slate-600', icon: 'file-x' };

    let currentRecord = null;   // record being verified in the modal
    let lastSerial    = '';

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function refreshIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function badgeHtml(v) {
        if (!v) return '';
        const style = STATUS_STYLES[v.status] || STATUS_FALLBACK;
        return `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ${style.badge}">
                    <i data-lucide="${style.icon}" class="w-3.5 h-3.5"></i>${esc(v.label)}
                </span>`;
    }

    function cell(value) {
        return value ? esc(value) : '<span class="text-slate-300">—</span>';
    }

    // Same presentation as the OPs Dashboard
    function titleCase(value) {
        if (!value) return '<span class="text-slate-300">—</span>';
        return esc(String(value).replace(/\w\S*/g, w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()));
    }

    function opTypeCell(val) {
        if (!val) return '<span class="text-slate-300">—</span>';
        const cls = val.toLowerCase().includes('resettlement')
            ? 'bg-orange-100 text-orange-700'
            : 'bg-sky-100 text-sky-700';
        return `<span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold ${cls}">${esc(val)}</span>`;
    }

    function fileCell(r) {
        const mls  = r.mls_file_number || '';
        const temp = r.temp_fileno || '';
        const isCoN = r.is_change_of_name === '1';

        if (isCoN && mls && temp) return `<div class="file-cell-mls">${esc(mls)}</div><div class="file-cell-temp">${esc(temp)}</div>`;
        if (mls)  return `<div class="file-cell-mls">${esc(mls)}</div>`;
        if (temp) return `<div class="file-cell-mls">${esc(temp)}</div>`;
        return '<div class="file-cell-empty">—</div>';
    }

    /** The OP being verified, as shown at the top of the modal. */
    function summaryHtml(r) {
        const fileNo = r.mls_file_number || r.temp_fileno || '';
        const v      = r.verification;

        const field = (label, value, mono) => value
            ? `<div>
                   <div class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">${esc(label)}</div>
                   <div class="text-slate-700 mt-0.5 ${mono ? 'font-mono' : ''}">${esc(value)}</div>
               </div>`
            : '';

        const details = [
            field('OP Type', r.op_type),
            field('Reg. Particulars', r.registration_number, true),
            field('Party 1', r.grantor),
            field('Party 2', r.allottee),
            field('Property', r.property_description),
            field('Prop ID', r.prop_id, true),
            field('Captured', r.created_at),
        ].join('');

        return `
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <div class="font-mono font-extrabold text-slate-900 text-base">${esc(r.op_serial_number || '—')}</div>
                    ${fileNo
                        ? `<div class="text-slate-600 mt-0.5 font-semibold">${esc(fileNo)}</div>`
                        : `<div class="text-amber-600 mt-0.5 font-semibold">No matching record</div>`}
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    ${r.is_change_of_name === '1' ? `<span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold">Change of Name</span>` : ''}
                    ${badgeHtml(v)}
                </div>
            </div>
            ${details ? `<div class="grid grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-200 text-xs">${details}</div>` : ''}
            ${v ? `<div class="mt-3 pt-3 border-t border-slate-200 text-[11px] text-slate-400">
                    Last check: ${esc(v.label)} by ${esc(v.verified_by || '—')} on ${esc(v.verified_at || '—')}${v.remarks ? ` — ${esc(v.remarks)}` : ''}
                   </div>` : ''}`;
    }

    function serialCell(r) {
        return `<div class="font-mono font-bold text-slate-800">${cell(r.op_serial_number)}</div>
                <div class="js-badge mt-1">${badgeHtml(r.verification)}</div>`;
    }

    function actionCell(r) {
        const verified = r.verification && r.verification.status === 'verified';
        return `<div class="flex justify-center">
            <button type="button" class="js-actions inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-teal-700 bg-teal-50 hover:bg-teal-100 border border-teal-200 transition">
                <i data-lucide="badge-check" class="w-3.5 h-3.5"></i>
                ${verified ? 'Re-verify' : 'Verify'}
                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
            </button>
        </div>`;
    }

    /* ── dashboard ──────────────────────────────────────────────────────── */
    // The register size is not queried again for the dashboard — the OP table
    // already reports it as recordsTotal, and re-running the OpRecordSource
    // union a second time on every page load is not worth one number.
    let registerTotal = null;
    let opsVerified   = null;

    function fmt(n) {
        return Number(n || 0).toLocaleString();
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function paintCoverage() {
        if (registerTotal === null || opsVerified === null) return;

        const pct   = registerTotal > 0 ? (opsVerified / registerTotal) * 100 : 0;
        // A handful of checks against tens of thousands of OPs still deserves a
        // readable figure rather than a flat 0.0%.
        const shown = (pct > 0 && pct < 0.1) ? pct.toFixed(2) : pct.toFixed(1);

        document.getElementById('coverage_bar').style.width = Math.min(100, pct) + '%';
        setText('coverage_label', `${fmt(opsVerified)} of ${fmt(registerTotal)} (${shown}%)`);
        setText('kpi_coverage_note', `${shown}% of the register`);
    }

    function setRegisterTotal(total) {
        registerTotal = Number(total) || 0;
        setText('kpi_total_ops', fmt(registerTotal));
        paintCoverage();
    }

    function renderOfficers(officers) {
        const box = document.getElementById('officers_list');

        if (!Array.isArray(officers) || officers.length === 0) {
            box.innerHTML = '<p class="text-sm text-slate-400">No verifications logged yet.</p>';
            return;
        }

        const max = Math.max(...officers.map(o => Number(o.total) || 0), 1);

        box.innerHTML = officers.map(o => `
            <div>
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="font-semibold text-slate-700 truncate">${esc(o.name)}</span>
                    <span class="font-bold text-slate-800">${fmt(o.total)}</span>
                </div>
                <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden mt-1.5">
                    <div class="h-1.5 rounded-full" style="width:${(Number(o.total) / max) * 100}%;background:#0d9488"></div>
                </div>
                <div class="text-[11px] text-slate-400 mt-1">${fmt(o.verified)} verified · ${fmt(o.suspicious)} suspicious</div>
            </div>`).join('');
    }

    function loadDashboard() {
        fetch(statsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                opsVerified = Number(data.ops_verified) || 0;

                setText('kpi_ops_verified', fmt(opsVerified));
                setText('kpi_ops_flagged', fmt(data.ops_flagged));
                setText('kpi_unmatched_note', data.unmatched
                    ? `${fmt(data.unmatched)} serial${data.unmatched === 1 ? '' : 's'} matched no record`
                    : 'No unmatched serials');
                setText('kpi_checks_total', fmt(data.checks.total));
                setText('kpi_checks_split', `${fmt(data.checks.verified)} verified · ${fmt(data.checks.suspicious)} suspicious`);
                setText('kpi_checks_today', fmt(data.checks_today));
                setText('kpi_checks_week', `${fmt(data.checks_week)} in the last 7 days`);

                renderOfficers(data.officers);
                paintCoverage();
                refreshIcons();
            })
            .catch(() => {
                document.getElementById('officers_list').innerHTML =
                    '<p class="text-sm text-rose-600">Could not load the dashboard.</p>';
            });
    }

    document.getElementById('toggle_dashboard').addEventListener('click', function () {
        const body     = document.getElementById('dashboard_body');
        const collapsed = body.classList.toggle('hidden');

        setText('toggle_dashboard_label', collapsed ? 'Show' : 'Hide');
        document.getElementById('toggle_dashboard_icon').innerHTML =
            `<i data-lucide="chevron-${collapsed ? 'down' : 'up'}" class="w-3.5 h-3.5"></i>`;
        refreshIcons();
    });

    /* ── the OP table (server-side, every OP in the register) ───────────── */
    const form          = document.getElementById('opSearchForm');
    const serialInput   = document.getElementById('serial_input');
    const clearBtn      = document.getElementById('clear_filter');
    const resultsTitle  = document.getElementById('results_title');
    const resultCount   = document.getElementById('result_count');
    const notFoundPanel = document.getElementById('not_found_panel');

    const opTable = $('#op_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: tableUrl },
        columns: [
            { data: null, name: 'temp_fileno', render: r => fileCell(r) },
            { data: 'op_type', name: 'op_type', render: v => opTypeCell(v) },
            { data: null, name: 'op_serial_number', render: r => serialCell(r) },
            { data: 'registration_number', name: 'registration_number', render: v => cell(v) },
            // Party 1 is the Kano State Government on every OP — not worth a column.
            { data: 'allottee', name: 'allottee', render: v => titleCase(v) },
            { data: 'property_description', name: 'property_description', render: v => titleCase(v) },
            { data: 'created_at', name: 'created_at' },
            { data: null, name: 'actions', orderable: false, searchable: false, render: r => actionCell(r) },
        ],
        dom: '<"flex items-center justify-between mb-3"l>rtip',
        pageLength: 15,
        order: [[6, 'desc']],
        language: {
            processing: 'Loading OPs…',
            emptyTable: 'No OPs found.',
            zeroRecords: 'No OP matches that serial number.',
        },
        drawCallback: function (settings) {
            const total = settings.json ? settings.json.recordsFiltered : 0;

            // recordsTotal is the unfiltered register size, so the dashboard
            // card stays correct while a serial filter is applied.
            if (settings.json && registerTotal === null) setRegisterTotal(settings.json.recordsTotal);

            resultCount.textContent = lastSerial
                ? `(${total} found for “${lastSerial}”)`
                : `(${Number(total).toLocaleString()} total)`;

            // Offer the "not found" outcomes only when a serial was searched for
            notFoundPanel.classList.toggle('hidden', !(lastSerial && total === 0));
            if (lastSerial && total === 0) document.getElementById('nf_serial').textContent = lastSerial;

            closeRowMenu();
            refreshIcons();
        },
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        lastSerial = serialInput.value.trim();
        resultsTitle.textContent = lastSerial ? 'Matching OPs' : 'All OPs';
        clearBtn.classList.toggle('hidden', !lastSerial);
        opTable.column('op_serial_number:name').search(lastSerial).draw();
    });

    clearBtn.addEventListener('click', function () {
        serialInput.value = '';
        lastSerial = '';
        resultsTitle.textContent = 'All OPs';
        clearBtn.classList.add('hidden');
        notFoundPanel.classList.add('hidden');
        opTable.column('op_serial_number:name').search('').draw();
    });

    /* ── tabs ───────────────────────────────────────────────────────────── */
    document.getElementById('op_tabs').addEventListener('click', function (e) {
        const btn = e.target.closest('.op-tab-btn');
        if (!btn) return;

        document.querySelectorAll('.op-tab-btn').forEach(b => b.classList.toggle('active-tab', b === btn));
        document.querySelectorAll('.op-tab-content').forEach(pane => {
            pane.classList.toggle('hidden', pane.id !== 'tab_' + btn.dataset.tab);
        });

        closeRowMenu();
        // DataTables mis-measures columns while hidden; recalculate on return.
        if (btn.dataset.tab === 'all_ops') opTable.columns.adjust();
        refreshIcons();
    });

    function showTab(name) {
        const btn = document.querySelector(`.op-tab-btn[data-tab="${name}"]`);
        if (btn) btn.click();
    }

    /* ── row action menu ────────────────────────────────────────────────── */
    const rowMenu = document.getElementById('row_menu');
    let menuRow   = null;

    function closeRowMenu() {
        rowMenu.classList.add('hidden');
        menuRow = null;
    }

    document.querySelector('#op_table tbody').addEventListener('click', function (e) {
        const btn = e.target.closest('.js-actions');
        if (!btn) return;
        e.stopPropagation();

        const row = btn.closest('tr');
        if (menuRow === row) { closeRowMenu(); return; }

        menuRow = row;
        const rect = btn.getBoundingClientRect();
        rowMenu.classList.remove('hidden');
        // Flip above the button when there is no room below.
        const below = window.innerHeight - rect.bottom;
        rowMenu.style.top  = (below > rowMenu.offsetHeight + 12 ? rect.bottom + 6 : rect.top - rowMenu.offsetHeight - 6) + 'px';
        rowMenu.style.left = Math.max(8, Math.min(rect.right - rowMenu.offsetWidth, window.innerWidth - rowMenu.offsetWidth - 8)) + 'px';
        refreshIcons();
    });

    rowMenu.addEventListener('click', function (e) {
        const item = e.target.closest('.row-menu-item');
        if (!item || !menuRow) return;
        const row = menuRow;
        closeRowMenu();
        openVerifyModal(opTable.row(row).data(), row, item.dataset.menuStatus || '');
    });

    document.addEventListener('click', function (e) {
        if (!rowMenu.classList.contains('hidden') && !rowMenu.contains(e.target)) closeRowMenu();
    });
    window.addEventListener('resize', closeRowMenu);
    window.addEventListener('scroll', closeRowMenu, true);

    document.querySelectorAll('.nf-action').forEach(btn => {
        btn.addEventListener('click', function () {
            openVerifyModal({
                op_serial_number: lastSerial,
                record_key: 'serial:' + lastSerial,
            }, null, this.dataset.nfStatus);
        });
    });

    const modal    = document.getElementById('verifyModal');
    const backdrop = document.getElementById('verifyBackdrop');
    const content  = document.getElementById('verifyContent');
    const errorBox = document.getElementById('modal_error');

    let activeRow = null;

    function paintStatusOptions() {
        document.querySelectorAll('#status_options .status-option').forEach(opt => {
            const input = opt.querySelector('input[name="status"]');
            opt.classList.toggle('is-selected', !!input && input.checked);
        });
    }

    document.getElementById('status_options').addEventListener('change', paintStatusOptions);

    function openVerifyModal(record, row, presetStatus) {
        currentRecord = record;
        activeRow     = row;
        errorBox.classList.add('hidden');
        document.getElementById('remarks_input').value = '';
        document.querySelectorAll('#status_options input[name="status"]').forEach(r => {
            r.checked = presetStatus ? r.value === presetStatus : false;
        });
        paintStatusOptions();

        document.getElementById('modal_summary').innerHTML = summaryHtml(record);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            backdrop.classList.replace('opacity-0', 'opacity-100');
            content.classList.remove('opacity-0', 'scale-95');
            content.classList.add('opacity-100', 'scale-100');
        }, 10);
        refreshIcons();
    }

    window.closeVerifyModal = function () {
        backdrop.classList.replace('opacity-100', 'opacity-0');
        content.classList.remove('opacity-100', 'scale-100');
        content.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 250);
    };

    /* ── save ───────────────────────────────────────────────────────────── */
    document.getElementById('save_verification').addEventListener('click', function () {
        const picked = document.querySelector('#status_options input[name="status"]:checked');
        if (!picked) {
            errorBox.textContent = 'Select an outcome before saving.';
            errorBox.classList.remove('hidden');
            return;
        }

        const r = currentRecord || {};
        const payload = {
            status:               picked.value,
            op_serial_number:     r.op_serial_number || lastSerial,
            record_key:           r.record_key || '',
            prop_id:              r.prop_id || null,
            source_capture_id:    r.source_capture_id || null,
            pra_id:               r.pra_id || null,
            mls_file_number:      r.mls_file_number || null,
            temp_fileno:          r.temp_fileno || null,
            op_type:              r.op_type || null,
            grantor:              r.grantor || null,
            allottee:             r.allottee || null,
            property_description: r.property_description || null,
            remarks:              document.getElementById('remarks_input').value.trim() || null,
        };

        const btn = this;
        btn.disabled = true;
        btn.classList.add('opacity-60');

        fetch(storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
            .then(res => res.json().then(body => ({ ok: res.ok, body })))
            .then(({ ok, body }) => {
                if (!ok || !body.success) {
                    errorBox.textContent = body.message || 'Could not save the verification.';
                    errorBox.classList.remove('hidden');
                    return;
                }

                const v = body.verification;
                if (activeRow) {
                    // Keep the cached row data in step, then repaint in place
                    // so the table does not lose its page or filter.
                    const data = opTable.row(activeRow).data();
                    if (data) data.verification = v;
                    activeRow.querySelector('.js-badge').innerHTML = badgeHtml(v);
                    activeRow.querySelector('.js-actions').innerHTML =
                        `<i data-lucide="badge-check" class="w-3.5 h-3.5"></i>${v.status === 'verified' ? 'Re-verify' : 'Verify'}<i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>`;
                    activeRow.classList.toggle('bg-emerald-50', v.status === 'verified');
                }
                prependRecent(v, payload);
                loadDashboard();
                // A serial that matched nothing has no row to update, so show
                // the officer where the outcome was recorded.
                if (!activeRow) showTab('recent');
                refreshIcons();
                window.closeVerifyModal();
            })
            .catch(() => {
                errorBox.textContent = 'Could not save the verification.';
                errorBox.classList.remove('hidden');
            })
            .finally(() => {
                btn.disabled = false;
                btn.classList.remove('opacity-60');
            });
    });

    function prependRecent(v, payload) {
        const empty = document.getElementById('recent_empty');
        if (empty) empty.remove();

        const counter = document.getElementById('recent_count');
        const current = parseInt((counter.textContent || '').replace(/\D/g, ''), 10) || 0;
        counter.textContent = `(${current + 1})`;

        const style = STATUS_STYLES[v.status] || STATUS_FALLBACK;
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50/50 transition-colors bg-teal-50/40';
        tr.innerHTML = `
            <td class="px-5 py-3 font-mono font-bold text-slate-800">${esc(payload.op_serial_number || '—')}</td>
            <td class="px-5 py-3 text-slate-600">${esc(payload.mls_file_number || payload.temp_fileno || '—')}</td>
            <td class="px-5 py-3 text-slate-600">${esc(payload.allottee || '—')}</td>
            <td class="px-5 py-3"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ${style.badge}">${esc(v.label)}</span></td>
            <td class="px-5 py-3 text-slate-500 max-w-xs truncate">${esc(payload.remarks || '—')}</td>
            <td class="px-5 py-3 text-slate-600">${esc(v.verified_by || '—')}</td>
            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">${esc(v.verified_at || '—')}</td>`;
        document.getElementById('recent_body').prepend(tr);
    }

    loadDashboard();
    refreshIcons();
})();
</script>
@endpush

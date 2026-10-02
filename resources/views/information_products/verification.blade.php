@extends('layouts.app')

@section('page-title')
    Document Verification
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header', [
        'PageTitle'       => 'Document Verification',
        'PageDescription' => 'Verify any document issued by the Ministry — Letters of Grant/RofO, Occupancy Permits, Certificates of Occupancy, Site Plans, Deeds Registration documents (RDS / CoR) and registry workflow sheets.'
    ])

    <div class="p-6 space-y-6">

        {{-- ============================= VERIFICATION CONSOLE ============================= --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                </span>
                <div>
                    <div class="text-sm font-bold text-slate-800">Verify a Document</div>
                    <div class="text-[11px] text-slate-400">Scan the document QR code, or search by serial number, registration number, tracking ID or file number.</div>
                </div>
            </div>

            <div class="p-5 space-y-4">

                {{-- Document type — a single trigger opening the picker. Fourteen
                     types as inline pills wrapped onto three rows and buried the
                     search box below the fold. --}}
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Document Type</label>
                    <button type="button" id="ipv_type_trigger" class="ipv-type-trigger">
                        <span class="ipv-type-trigger-icon" id="ipv_type_icon">
                            <i data-lucide="layers" class="w-4 h-4"></i>
                        </span>
                        <span class="flex-1 text-left">
                            <span class="block text-sm font-semibold text-slate-800" id="ipv_type_label">All Document Types</span>
                            <span class="block text-[11px] text-slate-400" id="ipv_type_hint">Every document the Ministry issues</span>
                        </span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    </button>
                </div>

                {{-- Search row --}}
                <div class="flex flex-col lg:flex-row gap-3">
                    <div class="relative flex-1">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" id="ipv_reference" autocomplete="off"
                               placeholder="Paste a QR payload (KLAES-Q1:…) or type a reference — KN/CofO/2024/00812, RDS/2025/00417, TRK-2026-000345 …"
                               class="w-full border border-slate-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 focus:outline-none">
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="ipv_btn_scan"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg flex items-center gap-2 transition">
                            <i data-lucide="qr-code" class="w-4 h-4"></i> Scan QR
                        </button>
                        <button id="ipv_btn_verify"
                                class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center gap-2 transition">
                            <i data-lucide="unlock-keyhole" class="w-4 h-4"></i> Decrypt
                        </button>
                        <button id="ipv_btn_clear"
                                class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center gap-2 transition">
                            <i data-lucide="x" class="w-4 h-4"></i> Clear
                        </button>
                    </div>
                </div>

                {{-- Live token reader --}}
                <div id="ipv_token_reader" class="hidden rounded-lg border px-4 py-2.5 text-[11px] flex items-start gap-2">
                    <i data-lucide="key-round" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
                    <span id="ipv_token_reader_text"></span>
                </div>

                {{-- Advanced filters --}}
                <div>
                    <button type="button" id="ipv_toggle_advanced" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1">
                        <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                        <span>Additional matching criteria (optional)</span>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" id="ipv_adv_chevron"></i>
                    </button>
                    <div id="ipv_advanced" class="hidden mt-3 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Holder / Grantee</label>
                            <input type="text" id="ipv_holder" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">File No.</label>
                            <input type="text" id="ipv_fileno" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Tracking ID</label>
                            <input type="text" id="ipv_tracking" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Plot No.</label>
                            <input type="text" id="ipv_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Issued From</label>
                            <input type="date" id="ipv_from" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Issued To</label>
                            <input type="date" id="ipv_to" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ============================= STATS ============================= --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-slate-100 text-slate-600 shrink-0"><i data-lucide="scan-line" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Checks Today</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_total">—</div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-100 text-emerald-600 shrink-0"><i data-lucide="badge-check" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Authentic</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_authentic">—</div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-100 text-amber-600 shrink-0"><i data-lucide="alert-triangle" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Flagged / Revoked</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_flagged">—</div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-rose-100 text-rose-600 shrink-0"><i data-lucide="shield-x" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Tampered QR</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_tampered">—</div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-slate-100 text-slate-500 shrink-0"><i data-lucide="file-x" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Not in Register</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_notfound">—</div>
                </div>
            </div>
        </div>

        {{-- ============================= DECRYPT PROGRESS ============================= --}}
        <div id="ipv_progress" class="hidden bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 shrink-0">
                    <i data-lucide="unlock-keyhole" class="w-5 h-5"></i>
                </span>
                <div class="flex-1">
                    <div class="text-sm font-bold text-slate-800" id="ipv_progress_title">Decrypting token…</div>
                    <div class="text-[11px] text-slate-400" id="ipv_progress_sub">Authenticating against the Ministry key</div>
                </div>
                <div class="text-lg font-extrabold text-indigo-600 tabular-nums" id="ipv_progress_pct">0%</div>
            </div>

            <div class="ipv-bar"><div class="ipv-bar-fill" id="ipv_progress_bar"></div></div>

            <ol class="mt-4 space-y-1.5" id="ipv_progress_steps"></ol>
        </div>

        {{-- ============================= EMPTY STATE ============================= --}}
        <div id="ipv_empty" class="bg-white rounded-xl border border-dashed border-slate-300 py-16 text-center">
            <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-slate-100 text-slate-400 mb-3">
                <i data-lucide="qr-code" class="w-7 h-7"></i>
            </span>
            <div class="text-sm font-semibold text-slate-600">No document verified yet</div>
            <div class="text-xs text-slate-400 mt-1">Scan a document QR code, or enter a reference above to begin.</div>
        </div>

        {{-- ============================= RESULT ============================= --}}
        <div id="ipv_result" class="hidden space-y-6">

            {{-- Verdict banner --}}
            <div id="ipv_verdict" class="rounded-xl border p-5 flex flex-col lg:flex-row lg:items-center gap-4">
                <span id="ipv_verdict_icon" class="inline-flex items-center justify-center w-14 h-14 rounded-full shrink-0">
                    <i data-lucide="badge-check" class="w-7 h-7"></i>
                </span>
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span id="ipv_verdict_title" class="text-lg font-extrabold"></span>
                        <span id="ipv_doc_badge" class="ipv-badge"></span>
                        <span id="ipv_token_chip" class="ipv-badge"></span>
                    </div>
                    <div id="ipv_verdict_text" class="text-sm mt-0.5"></div>
                </div>
                <div class="flex items-center gap-2 flex-wrap ipv-noprint">
                    <button id="ipv_btn_print" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print Report
                    </button>
                    <button id="ipv_btn_flag" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2 transition">
                        <i data-lucide="flag" class="w-4 h-4"></i> Flag
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                {{-- LEFT: document, holder, audit --}}
                <div class="xl:col-span-2 space-y-6">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">Document Details</div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4" id="ipv_doc_fields"></div>
                    </div>

                    {{-- Print audit --}}
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                            <div class="text-sm font-bold text-slate-800">Print History</div>
                            <span id="ipv_print_summary" class="text-[11px] font-semibold text-slate-400"></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200">
                                        <th class="px-4 py-2.5 font-semibold">#</th>
                                        <th class="px-4 py-2.5 font-semibold">Printed By</th>
                                        <th class="px-4 py-2.5 font-semibold">Printed At</th>
                                        <th class="px-4 py-2.5 font-semibold">Reason</th>
                                        <th class="px-4 py-2.5 font-semibold">Copy</th>
                                    </tr>
                                </thead>
                                <tbody id="ipv_print_body"></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Scan audit --}}
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                            <div class="text-sm font-bold text-slate-800">Scan History</div>
                            <span id="ipv_scan_summary" class="text-[11px] font-semibold text-slate-400"></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200">
                                        <th class="px-4 py-2.5 font-semibold">Scanned At</th>
                                        <th class="px-4 py-2.5 font-semibold">Scanned By</th>
                                        <th class="px-4 py-2.5 font-semibold">Channel</th>
                                        <th class="px-4 py-2.5 font-semibold">Result</th>
                                    </tr>
                                </thead>
                                <tbody id="ipv_scan_body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">Document Lifecycle</div>
                        <div class="p-5">
                            <ol class="ipv-timeline" id="ipv_timeline"></ol>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: chain + token --}}
                <div class="space-y-6">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">Verification Chain</div>
                        <div class="p-4 space-y-1" id="ipv_checks"></div>
                        <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-400">Confidence</span>
                            <span id="ipv_confidence" class="text-sm font-extrabold text-slate-800">—</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">QR Token</div>
                        <div class="p-5 text-center">
                            <div id="ipv_qr" class="inline-block p-3 bg-white border border-slate-200 rounded-lg"></div>
                            <div class="mt-3 text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Payload on the document</div>
                            <div id="ipv_payload" class="font-mono text-[11px] font-semibold text-slate-600 mt-0.5 break-all">—</div>
                        </div>
                        <div class="px-5 pb-5 space-y-3" id="ipv_token_fields"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================= RECENT LOG ============================= --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm ipv-noprint">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <div class="text-sm font-bold text-slate-800">Recent Verifications</div>
                <button id="ipv_btn_export" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Export
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200">
                            <th class="px-4 py-2.5 font-semibold">Time</th>
                            <th class="px-4 py-2.5 font-semibold">Reference</th>
                            <th class="px-4 py-2.5 font-semibold">Document</th>
                            <th class="px-4 py-2.5 font-semibold">Holder</th>
                            <th class="px-4 py-2.5 font-semibold">QR</th>
                            <th class="px-4 py-2.5 font-semibold">Channel</th>
                            <th class="px-4 py-2.5 font-semibold">Verified By</th>
                            <th class="px-4 py-2.5 font-semibold">Result</th>
                        </tr>
                    </thead>
                    <tbody id="ipv_log_body"></tbody>
                </table>
            </div>
        </div>

    </div>
    @include('admin.footer')
</div>

{{-- ============================= DOCUMENT TYPE PICKER ============================= --}}
<div id="ipv_type_modal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-slate-900/60 p-4 pt-[8vh]">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[80vh]">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3 shrink-0">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600 shrink-0">
                <i data-lucide="layers" class="w-4 h-4"></i>
            </span>
            <div class="flex-1">
                <div class="text-sm font-bold text-slate-800">Select Document Type</div>
                <div class="text-[11px] text-slate-400">Narrows verification to one kind of document. Leave on “All” to search everything.</div>
            </div>
            <button class="ipv-modal-close text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>

        <div class="px-5 py-3 border-b border-slate-100 shrink-0">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input type="text" id="ipv_type_search" autocomplete="off" placeholder="Filter document types…"
                       class="w-full border border-slate-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 focus:outline-none">
            </div>
        </div>

        <div class="p-5 overflow-y-auto flex-1" id="ipv_type_groups"></div>

        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between shrink-0">
            <span class="text-[11px] text-slate-400">Press <kbd class="ipv-kbd">Esc</kbd> to close</span>
            <button class="ipv-modal-close bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold px-4 py-2 rounded-lg">Cancel</button>
        </div>
    </div>
</div>

{{-- ============================= QR SCAN MODAL ============================= --}}
<div id="ipv_scan_modal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-slate-900/60 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="text-sm font-bold text-slate-800">Scan Document QR Code</div>
            <button class="ipv-modal-close text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div class="aspect-square rounded-lg bg-slate-900 relative overflow-hidden flex items-center justify-center">
                {{-- Live camera. Muted + playsinline are both required for autoplay
                     to be allowed without a further gesture on iOS/Safari. --}}
                <video id="ipv_scan_video" class="absolute inset-0 w-full h-full object-cover hidden" muted playsinline></video>
                <div class="ipv-scan-frame hidden" id="ipv_scan_frame"></div>
                <div class="ipv-scan-line hidden" id="ipv_scan_line"></div>

                <div id="ipv_scan_idle" class="relative text-center px-6">
                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-800 text-slate-400 mb-3">
                        <i data-lucide="camera" class="w-6 h-6"></i>
                    </span>
                    <div class="text-slate-300 text-xs font-semibold">Camera is off</div>
                    <div class="text-slate-500 text-[11px] mt-1">Start the camera to scan.</div>
                    <button id="ipv_scan_start" class="mt-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg inline-flex items-center gap-2">
                        <i data-lucide="camera" class="w-3.5 h-3.5"></i> Start Camera
                    </button>
                </div>

                <div id="ipv_scan_error" class="hidden relative text-center px-6">
                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-rose-500/20 text-rose-400 mb-3">
                        <i data-lucide="camera-off" class="w-6 h-6"></i>
                    </span>
                    <div class="text-rose-300 text-xs font-semibold" id="ipv_scan_error_title">Camera unavailable</div>
                    <div class="text-slate-400 text-[11px] mt-1 leading-relaxed" id="ipv_scan_error_text"></div>
                    <button id="ipv_scan_retry" class="mt-3 bg-slate-700 hover:bg-slate-600 text-white text-xs font-semibold px-4 py-2 rounded-lg">Try again</button>
                </div>
            </div>

            <div id="ipv_scan_status" class="hidden text-[11px] text-center font-semibold text-indigo-600">
                Point the camera at the QR code…
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Encryption Key or Token</label>
                <input type="text" id="ipv_scan_manual" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-300 focus:outline-none" placeholder="KLAES-Q1:…">
                <p class="text-[11px] text-slate-400 mt-1">Accepts both the secure <strong>KLAES-Q1</strong> token and legacy QR codes that carry a raw tracking ID.</p>
            </div>
        </div>
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
            <button class="ipv-modal-close bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold px-4 py-2 rounded-lg">Cancel</button>
            <button id="ipv_scan_use" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg inline-flex items-center gap-2"><i data-lucide="unlock-keyhole" class="w-4 h-4"></i> Decrypt &amp; Verify</button>
        </div>
    </div>
</div>

{{-- ============================= FLAG MODAL ============================= --}}
<div id="ipv_flag_modal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-slate-900/60 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="text-sm font-bold text-slate-800">Flag Document for Review</div>
            <button class="ipv-modal-close text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Reference</label>
                <input type="text" id="ipv_flag_ref" readonly class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono">
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Reason</label>
                <select id="ipv_flag_reason" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option>Reference not found in register</option>
                    <option>QR token failed authentication</option>
                    <option>Holder name does not match register</option>
                    <option>Tracking ID does not match the file</option>
                    <option>Suspected altered document</option>
                    <option>Seal / signature irregular</option>
                    <option>Document reported lost or revoked</option>
                    <option>Other</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Notes</label>
                <textarea id="ipv_flag_notes" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none" placeholder="Describe what was observed on the physical document…"></textarea>
            </div>
        </div>
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
            <button class="ipv-modal-close bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold px-4 py-2 rounded-lg">Cancel</button>
            <button id="ipv_flag_submit" class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-4 py-2 rounded-lg">Submit Flag</button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* document type trigger — reads as a rich select */
    .ipv-type-trigger {
        display: flex; align-items: center; gap: 12px; width: 100%;
        border: 1px solid #cbd5e1; background: #fff; border-radius: 10px;
        padding: 10px 14px; transition: all .15s ease; text-align: left;
    }
    .ipv-type-trigger:hover { border-color: #a5b4fc; background: #fafaff; }
    .ipv-type-trigger:focus-visible { outline: 2px solid #6366f1; outline-offset: 1px; }
    .ipv-type-trigger-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
        background: #eef2ff; color: #4f46e5;
    }

    /* picker */
    .ipv-group-title {
        font-size: 10px; text-transform: uppercase; letter-spacing: .08em;
        font-weight: 800; color: #94a3b8; margin-bottom: 10px;
        display: flex; align-items: center; gap: 8px;
    }
    .ipv-group-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
    .ipv-group + .ipv-group { margin-top: 22px; }

    .ipv-type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr)); gap: 8px; }

    .ipv-type-card {
        display: flex; align-items: flex-start; gap: 10px; text-align: left;
        border: 1px solid #e2e8f0; background: #fff; border-radius: 10px;
        padding: 10px 12px; transition: all .12s ease; width: 100%;
    }
    .ipv-type-card:hover { border-color: #a5b4fc; background: #fafaff; transform: translateY(-1px); }
    .ipv-type-card-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    }
    .ipv-type-card-label { font-size: 12.5px; font-weight: 700; color: #1e293b; line-height: 1.3; }
    .ipv-type-card-note  { font-size: 10.5px; color: #94a3b8; margin-top: 1px; line-height: 1.35; }
    .ipv-type-card-check { margin-left: auto; color: #4f46e5; display: none; flex-shrink: 0; }

    .ipv-type-card-active {
        border-color: #4f46e5; background: #eef2ff;
        box-shadow: 0 0 0 1px #4f46e5 inset;
    }
    .ipv-type-card-active .ipv-type-card-check { display: inline-flex; }

    /* decrypt progress */
    .ipv-bar { height: 6px; border-radius: 9999px; background: #e2e8f0; overflow: hidden; }
    .ipv-bar-fill {
        height: 100%; width: 0; border-radius: 9999px;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        transition: width .28s ease;
    }
    .ipv-step { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #94a3b8; transition: color .2s ease; }
    .ipv-step-dot {
        width: 16px; height: 16px; border-radius: 9999px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        background: #e2e8f0; color: #94a3b8;
    }
    .ipv-step-active { color: #4f46e5; font-weight: 600; }
    .ipv-step-active .ipv-step-dot { background: #e0e7ff; color: #4f46e5; }
    .ipv-step-done { color: #475569; }
    .ipv-step-done .ipv-step-dot { background: #d1fae5; color: #059669; }

    .ipv-type-empty { text-align: center; padding: 32px 0; color: #94a3b8; font-size: 12.5px; }

    .ipv-kbd {
        background: #fff; border: 1px solid #cbd5e1; border-bottom-width: 2px;
        border-radius: 4px; padding: 0 4px; font-size: 10px; font-family: ui-monospace, monospace;
        color: #475569;
    }

    .ipv-code {
        background: #fff; border: 1px solid #fcd34d; border-radius: 4px; color: #92400e;
        padding: 0 4px; font-family: ui-monospace, monospace; font-size: 10px; white-space: nowrap;
        cursor: pointer;
    }
    .ipv-code:hover { background: #fef3c7; }

    .ipv-field-label {
        font-size: 10px; text-transform: uppercase; letter-spacing: .06em;
        font-weight: 700; color: #94a3b8; margin-bottom: 2px;
    }
    .ipv-field-value { font-size: 13px; font-weight: 600; color: #1e293b; word-break: break-word; }

    .ipv-badge {
        display: inline-flex; align-items: center; padding: 3px 12px; border-radius: 9999px;
        font-size: 11px; font-weight: 700; letter-spacing: .02em;
    }
    .ipv-badge:empty { display: none; }
    .ipv-badge-blue    { background: #dbeafe; color: #1e40af; }
    .ipv-badge-amber   { background: #fef3c7; color: #92400e; }
    .ipv-badge-violet  { background: #ede9fe; color: #5b21b6; }
    .ipv-badge-emerald { background: #d1fae5; color: #065f46; }
    .ipv-badge-rose    { background: #ffe4e6; color: #9f1239; }
    .ipv-badge-cyan    { background: #cffafe; color: #155e75; }
    .ipv-badge-indigo  { background: #e0e7ff; color: #3730a3; }
    .ipv-badge-slate   { background: #e2e8f0; color: #334155; }

    /* QR token version chips */
    .ipv-token-q1     { background: #dcfce7; color: #166534; }
    .ipv-token-q0     { background: #fef3c7; color: #92400e; }
    .ipv-token-bad    { background: #fee2e2; color: #991b1b; }

    /* verdict tones */
    .ipv-tone-pass { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .ipv-tone-pass #ipv_verdict_icon { background: #d1fae5; color: #059669; }
    .ipv-tone-warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .ipv-tone-warn #ipv_verdict_icon { background: #fef3c7; color: #d97706; }
    .ipv-tone-fail { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .ipv-tone-fail #ipv_verdict_icon { background: #fee2e2; color: #dc2626; }
    .ipv-tone-none { background: #f8fafc; border-color: #e2e8f0; color: #334155; }
    .ipv-tone-none #ipv_verdict_icon { background: #e2e8f0; color: #64748b; }

    /* token reader strip */
    .ipv-reader-q1  { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .ipv-reader-q0  { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .ipv-reader-txt { background: #f8fafc; border-color: #e2e8f0; color: #475569; }

    /* verification chain */
    .ipv-check { display: flex; align-items: flex-start; gap: 10px; padding: 8px 10px; border-radius: 8px; }
    .ipv-check:hover { background: #f8fafc; }
    .ipv-check-icon {
        width: 18px; height: 18px; border-radius: 9999px; display: inline-flex;
        align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
    }
    .ipv-check-pass .ipv-check-icon { background: #d1fae5; color: #059669; }
    .ipv-check-warn .ipv-check-icon { background: #fef3c7; color: #d97706; }
    .ipv-check-fail .ipv-check-icon { background: #fee2e2; color: #dc2626; }
    .ipv-check-info .ipv-check-icon { background: #e0e7ff; color: #4f46e5; }
    .ipv-check-skip .ipv-check-icon { background: #e2e8f0; color: #64748b; }
    .ipv-check-title { font-size: 12.5px; font-weight: 600; color: #1e293b; }
    .ipv-check-note  { font-size: 11px; color: #94a3b8; }

    /* timeline */
    .ipv-timeline { position: relative; padding-left: 22px; margin: 0; list-style: none; }
    .ipv-timeline::before { content: ''; position: absolute; left: 5px; top: 4px; bottom: 4px; width: 2px; background: #e2e8f0; }
    .ipv-timeline li { position: relative; padding-bottom: 16px; }
    .ipv-timeline li:last-child { padding-bottom: 0; }
    .ipv-timeline li::before {
        content: ''; position: absolute; left: -22px; top: 4px; width: 12px; height: 12px;
        border-radius: 9999px; background: #fff; border: 2px solid #6366f1;
    }
    .ipv-timeline li.is-alert::before { border-color: #dc2626; }
    .ipv-tl-title { font-size: 13px; font-weight: 700; color: #1e293b; }
    .ipv-tl-meta  { font-size: 11px; color: #94a3b8; }

    /* audit + log tables */
    #ipv_log_body td, #ipv_print_body td, #ipv_scan_body td {
        padding: 10px 16px; border-bottom: 1px solid #f1f5f9; white-space: nowrap;
    }
    #ipv_log_body tr:hover td, #ipv_print_body tr:hover td, #ipv_scan_body tr:hover td { background: #f8fafc; }
    .ipv-result-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; }
    .ipv-result-authentic { background: #d1fae5; color: #065f46; }
    .ipv-result-review    { background: #fef3c7; color: #92400e; }
    .ipv-result-revoked   { background: #fee2e2; color: #991b1b; }
    .ipv-result-tampered  { background: #ffe4e6; color: #9f1239; }
    .ipv-result-notfound  { background: #e2e8f0; color: #475569; }

    /* scanner decoration */
    .ipv-scan-frame { position: absolute; inset: 18%; border: 2px solid rgba(99,102,241,.8); border-radius: 12px; }
    .ipv-scan-line  { position: absolute; left: 18%; right: 18%; height: 2px; background: #6366f1; box-shadow: 0 0 12px #6366f1; animation: ipvScan 2.2s ease-in-out infinite; }
    @keyframes ipvScan { 0%, 100% { top: 20%; } 50% { top: 78%; } }

    @media print {
        .ipv-noprint, #ipv_empty { display: none !important; }
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    'use strict';

    /* =====================================================================
     * DOCUMENT REGISTRY
     * Every printable KLAES document that carries a QR code. Groups drive the
     * category tabs; `badge` is the colour used wherever the type is shown.
     * ===================================================================== */
    var DOC_TYPES = {
        rofo:           { label: 'Letter of Grant / RofO',      short: 'RofO',           group: 'ip',       badge: 'ipv-badge-blue',    icon: 'scroll-text',     note: 'Offer of grant / conveyance' },
        op:             { label: 'Occupancy Permit (OP)',       short: 'OP',             group: 'ip',       badge: 'ipv-badge-amber',   icon: 'stamp',           note: 'Permit to occupy' },
        cofo:           { label: 'Certificate of Occupancy',    short: 'CofO',           group: 'ip',       badge: 'ipv-badge-violet',  icon: 'award',           note: 'Statutory right of occupancy' },
        siteplan:       { label: 'Site / Parcel Plan',          short: 'Site Plan',      group: 'ip',       badge: 'ipv-badge-emerald', icon: 'map',             note: 'Survey & cadastral plan' },

        rds:            { label: 'RDS',                         short: 'RDS',            group: 'deeds',    badge: 'ipv-badge-rose',    icon: 'book-marked',     note: 'Registered deed sheet' },
        cor:            { label: 'Certificate of Registration', short: 'CoR',            group: 'deeds',    badge: 'ipv-badge-rose',    icon: 'badge-check',     note: 'Issued against an RDS' },
        deeds_bill:     { label: 'Deeds Bill',                  short: 'Deeds Bill',     group: 'deeds',    badge: 'ipv-badge-slate',   icon: 'receipt',         note: 'Assessed registration fees' },
        deeds_balance:  { label: 'Deeds Balance',               short: 'Deeds Balance',  group: 'deeds',    badge: 'ipv-badge-slate',   icon: 'wallet',          note: 'Outstanding on a deeds bill' },

        st:             { label: 'Sectional Title (ST)',        short: 'ST',             group: 'registry', badge: 'ipv-badge-cyan',    icon: 'building-2',      note: 'Multi-unit development' },
        sltr:           { label: 'SLTR',                        short: 'SLTR',           group: 'registry', badge: 'ipv-badge-cyan',    icon: 'trees',           note: 'Systematic land titling' },
        recommendation: { label: 'Land Recommendation',         short: 'Recommendation', group: 'registry', badge: 'ipv-badge-indigo',  icon: 'clipboard-check', note: 'Recommendation for grant' },
        tracking_sheet: { label: 'Tracking Sheet',              short: 'Tracking Sheet', group: 'registry', badge: 'ipv-badge-slate',   icon: 'route',           note: 'File movement record' },
        commissioning:  { label: 'File Commissioning',          short: 'Commissioning',  group: 'registry', badge: 'ipv-badge-indigo',  icon: 'file-check-2',    note: 'Land / ST / OSS commissioning' },
        confirmation:   { label: 'Confirmation Sheet',          short: 'Confirmation',   group: 'registry', badge: 'ipv-badge-indigo',  icon: 'file-badge',      note: 'Allocation confirmation' }
    };

    var DOC_GROUPS = {
        ip:       'Information Products',
        deeds:    'Deeds Registration',
        registry: 'Registry & Workflow'
    };

    /* =====================================================================
     * SAMPLE DATA — front-end only.
     * Replace lookup() with the real endpoint; keep this response shape and
     * nothing else on the page has to change.
     * ===================================================================== */
    var SAMPLES = {
        'KN/COFO/2024/00812': {
            reference: 'KN/CofO/2024/00812',
            verdict: 'authentic',
            product: 'cofo',
            confidence: '98%',
            token: {
                version: 'Q1',
                payload: 'KLAES-Q1:8f42a917cc03be51d7…',
                fields: {
                    'Token version': 'Q1 — authenticated (AES-256-GCM)',
                    'Document ID': '98231',
                    'Document type': 'COFO',
                    'File indexing ID': '124417',
                    'Auth tag': 'Verified against the server key'
                }
            },
            document: {
                'Serial No.': 'KN/CofO/2024/00812',
                'Registration No.': '812/812/2024',
                'File No.': 'MLS/KN/RES/2019/1420',
                'Tracking ID': 'TRK-2024-008120',
                'KANGIS No.': 'KNML 04120',
                'Date of Issue': '14 Mar 2024',
                'Term of Years': '99 Years (from 01 Jan 2024)',
                'Issuing Authority': 'New KANGIS CofO',
                'Signed By': 'Hon. Commissioner, Ministry of Land & Physical Planning',
                'Created By': 'A. Yakubu · 14 Mar 2024',
                'Status': 'Active'
            },
            property: {
                'Holder / Grantee': 'Aliyu Musa Abdullahi',
                'Plot No.': 'Plot 41',
                'Street': 'Ahmadu Bello Way',
                'District': 'Nassarawa GRA',
                'LGA': 'Nassarawa',
                'Land Use': 'Residential',
                'Plot Size': '1,200 sqm',
                'Ground Rent': 'Paid to 2026',
                'Encumbrance': 'None recorded'
            },
            checks: [
                ['pass', 'QR token signature valid',      'AES-256-GCM auth tag verified against the server key'],
                ['pass', 'Document record resolved',      'documents #98231 · type COFO'],
                ['pass', 'File indexing record exists',   'file_indexings #124417'],
                ['pass', 'File number matches',           'MLS/KN/RES/2019/1420'],
                ['pass', 'Tracking ID matches the file',  'TRK-2024-008120 confirmed via grouping record'],
                ['pass', 'Source document exists',        'Matched CofO_staging entry #00812'],
                ['pass', 'Holder matches register',       'Aliyu Musa Abdullahi'],
                ['pass', 'Print record exists',           '3 prints logged, first 14 Mar 2024'],
                ['pass', 'No revocation recorded',        'Title status: Active']
            ],
            prints: [
                [1, 'A. Yakubu',  '14 Mar 2024 09:15', 'Original issue',   'Original'],
                [2, 'S. Ibrahim', '02 Apr 2024 11:32', 'Certified copy',   'Reprint'],
                [3, 'A. Yakubu',  '17 Jan 2025 08:41', 'Damaged original', 'Reprint']
            ],
            scans: [
                ['23 Aug 2026 09:41', 'A. Yakubu',       'QR Scan', 'authentic', 'Registry counter'],
                ['11 Jun 2026 14:08', 'Zenith Bank Plc', 'Public',  'authentic', 'Mortgage due diligence'],
                ['04 Feb 2026 10:22', 'M. Danladi',      'QR Scan', 'authentic', 'Legal search']
            ],
            timeline: [
                ['Application received',            '02 Nov 2023 · Land Administration'],
                ['RofO granted',                    '18 Jan 2024 · Letter of Grant KN/RofO/2024/0331'],
                ['Certificate of Occupancy issued', '14 Mar 2024 · Commissioner signed'],
                ['Collected by holder',             '29 Mar 2024 · Registry counter']
            ]
        },

        'RES-2024-1906': {
            reference: 'RES-2024-1906',
            verdict: 'review',
            product: 'rofo',
            confidence: '61%',
            token: {
                version: 'Q0',
                payload: 'TRK-2024-019060',
                fields: {
                    'Token version': 'Q0 — legacy raw tracking ID',
                    'Document ID': 'Not encoded in legacy QR',
                    'Document type': 'Inferred from the resolved record',
                    'File indexing ID': '119802 (resolved by tracking ID)',
                    'Auth tag': 'None — legacy QR carries no signature'
                }
            },
            document: {
                'Serial No.': 'RES-2024-1906',
                'Registration No.': '1906/1906/2024',
                'File No.': 'KN 8841',
                'Tracking ID': 'TRK-2024-019060',
                'KANGIS No.': '—',
                'Date of Issue': '07 Jun 2024',
                'Term of Years': '99 Years',
                'Issuing Authority': 'Ministry of Land & Physical Planning',
                'Signed By': 'Director, Land Administration',
                'Created By': 'M. Danladi · 07 Jun 2024',
                'Status': 'Under review'
            },
            property: {
                'Holder / Grantee': 'Hauwa Ibrahim Sani',
                'Plot No.': 'Plot 7B',
                'Street': 'Zaria Road',
                'District': 'Sharada',
                'LGA': 'Kano Municipal',
                'Land Use': 'Commercial',
                'Plot Size': '860 sqm',
                'Ground Rent': 'Outstanding (2023–2024)',
                'Encumbrance': 'Mortgage lodged 2024'
            },
            checks: [
                ['warn', 'QR token signature valid',      'Legacy Q0 QR — raw tracking ID, nothing to authenticate'],
                ['pass', 'Document record resolved',      'Resolved by tracking ID TRK-2024-019060'],
                ['pass', 'File indexing record exists',   'file_indexings #119802'],
                ['pass', 'File number matches',           'KN 8841'],
                ['pass', 'Tracking ID matches the file',  'Confirmed via grouping record'],
                ['pass', 'Source document exists',        'Matched pra entry #1906'],
                ['warn', 'Holder matches register',       'Register reads "Hauwa I. Sani" — slight difference'],
                ['pass', 'Print record exists',           '1 print logged, 07 Jun 2024'],
                ['warn', 'Ground rent outstanding',       '2 years outstanding as at today']
            ],
            prints: [
                [1, 'M. Danladi', '07 Jun 2024 12:04', 'Original issue', 'Original']
            ],
            scans: [
                ['23 Aug 2026 09:28', 'A. Yakubu', 'Manual', 'review', 'Holder name mismatch flagged']
            ],
            timeline: [
                ['Application received',    '11 Feb 2024 · Land Administration'],
                ['Recommendation approved', '21 May 2024 · Director'],
                ['Letter of Grant issued',  '07 Jun 2024 · RES-2024-1906'],
                ['Mortgage lodged',         '30 Sep 2024 · Deeds Registry']
            ]
        },

        'KN/OP/2021/00455': {
            reference: 'KN/OP/2021/00455',
            verdict: 'revoked',
            product: 'op',
            confidence: '99%',
            token: {
                version: 'Q1',
                payload: 'KLAES-Q1:7c150083aa2641f0b9…',
                fields: {
                    'Token version': 'Q1 — authenticated (AES-256-GCM)',
                    'Document ID': '41277',
                    'Document type': 'OP',
                    'File indexing ID': '90114',
                    'Auth tag': 'Verified against the server key'
                }
            },
            document: {
                'Serial No.': 'KN/OP/2021/00455',
                'Registration No.': '455/455/2021',
                'File No.': 'CON/RES/2021/0455',
                'Tracking ID': 'TRK-2021-004550',
                'KANGIS No.': 'KNML 00455',
                'Date of Issue': '19 Aug 2021',
                'Term of Years': '99 Years',
                'Issuing Authority': 'Ministry of Land & Physical Planning',
                'Signed By': 'Permanent Secretary',
                'Created By': 'S. Ibrahim · 19 Aug 2021',
                'Status': 'Revoked'
            },
            property: {
                'Holder / Grantee': 'Bello Yusuf Garba',
                'Plot No.': 'Plot 19',
                'Street': 'Court Road',
                'District': 'Bompai',
                'LGA': 'Nassarawa',
                'Land Use': 'Industrial',
                'Plot Size': '2,400 sqm',
                'Ground Rent': 'Not applicable',
                'Encumbrance': 'Revocation notice served'
            },
            checks: [
                ['pass', 'QR token signature valid',     'Auth tag verified'],
                ['pass', 'Document record resolved',     'documents #41277 · type OP'],
                ['pass', 'File indexing record exists',  'file_indexings #90114'],
                ['pass', 'File number matches',          'CON/RES/2021/0455'],
                ['pass', 'Tracking ID matches the file', 'TRK-2021-004550'],
                ['pass', 'Source document exists',       'Matched pra Occupancy Permit row'],
                ['pass', 'Holder matches register',      'Bello Yusuf Garba'],
                ['fail', 'Revocation recorded',          'Revoked 12 Feb 2025 — Certificate KN/REV/2025/0031'],
                ['fail', 'Document not surrendered',     'Original never returned to the registry']
            ],
            prints: [
                [1, 'S. Ibrahim', '19 Aug 2021 10:07', 'Original issue', 'Original']
            ],
            scans: [
                ['23 Aug 2026 08:57', 'M. Danladi', 'QR Scan', 'revoked', 'Presented at the registry counter'],
                ['18 Mar 2025 15:31', 'A. Yakubu',  'QR Scan', 'revoked', 'Post-revocation check']
            ],
            timeline: [
                ['Occupancy Permit issued', '19 Aug 2021 · Permanent Secretary'],
                ['Breach notice served',    '04 Oct 2024 · Non-development'],
                ['Title revoked',           '12 Feb 2025 · Certificate KN/REV/2025/0031', true],
                ['Parcel returned to pool', '01 Mar 2025 · Land Administration', true]
            ]
        },

        'RDS/2025/00417': {
            reference: 'RDS/2025/00417',
            verdict: 'authentic',
            product: 'rds',
            confidence: '97%',
            token: {
                version: 'Q1',
                payload: 'KLAES-Q1:b71c4402ef19aa73d5…',
                fields: {
                    'Token version': 'Q1 — authenticated (AES-256-GCM)',
                    'Document ID': '77401',
                    'Document type': 'RDS',
                    'File indexing ID': '131290',
                    'Auth tag': 'Verified against the server key'
                }
            },
            document: {
                'Document': 'RDS — Deeds Registration',
                'RDS No.': 'RDS/2025/00417',
                'Instrument': 'Deed of Assignment',
                'Registration No.': '417/417/2025',
                'File No.': 'MLS/KN/COM/2021/0833',
                'Tracking ID': 'TRK-2025-004170',
                'Date Registered': '22 Apr 2025',
                'Registered By': 'Deeds Registry — Registration Unit',
                'Created By': 'F. Abdulkadir · 22 Apr 2025',
                'Batch': 'RDS batch RB-2025-041',
                'Status': 'Registered'
            },
            property: {
                'Party 1 (Grantor)': 'Kano Textile Mills Ltd',
                'Party 2 (Grantee)': 'Sabo Holdings Ltd',
                'Plot No.': 'Plot 3',
                'Street': 'Maiduguri Road',
                'District': 'Bompai',
                'LGA': 'Nassarawa',
                'Land Use': 'Commercial',
                'Consideration': '₦185,000,000.00',
                'Encumbrance': 'None recorded'
            },
            checks: [
                ['pass', 'QR token signature valid',     'Auth tag verified'],
                ['pass', 'Document record resolved',     'documents #77401 · type RDS'],
                ['pass', 'File indexing record exists',  'file_indexings #131290'],
                ['pass', 'File number matches',          'MLS/KN/COM/2021/0833'],
                ['pass', 'Tracking ID matches the file', 'TRK-2025-004170'],
                ['pass', 'Instrument registered',        'Deed of Assignment · reg. 417/417/2025'],
                ['pass', 'CoR issued for this RDS',      'KN/CoR/2025/00417 issued 24 Apr 2025'],
                ['pass', 'Print record exists',          '2 prints logged, first 22 Apr 2025'],
                ['pass', 'No revocation recorded',       'Registration status: Registered']
            ],
            prints: [
                [1, 'F. Abdulkadir', '22 Apr 2025 13:26', 'Batch print RB-2025-041', 'Original'],
                [2, 'F. Abdulkadir', '24 Apr 2025 09:10', 'Reissue with CoR',        'Reprint']
            ],
            scans: [
                ['23 Aug 2026 08:33', 'A. Yakubu', 'QR Scan', 'authentic', 'Legal search']
            ],
            timeline: [
                ['Instrument lodged',        '03 Mar 2025 · Deeds Registry'],
                ['Deeds bill raised',        '11 Mar 2025 · ₦1,240,000.00'],
                ['Bill settled in full',     '02 Apr 2025 · No balance outstanding'],
                ['RDS generated and printed','22 Apr 2025 · Batch RB-2025-041'],
                ['CoR issued',               '24 Apr 2025 · KN/CoR/2025/00417']
            ]
        },

        'KN/COR/2025/00417': {
            reference: 'KN/CoR/2025/00417',
            verdict: 'authentic',
            product: 'cor',
            confidence: '98%',
            token: {
                version: 'Q1',
                payload: 'KLAES-Q1:c0193fa5772be48810…',
                fields: {
                    'Token version': 'Q1 — authenticated (AES-256-GCM)',
                    'Document ID': '77418',
                    'Document type': 'COR',
                    'File indexing ID': '131290',
                    'Auth tag': 'Verified against the server key'
                }
            },
            document: {
                'Document': 'Certificate of Registration (CoR)',
                'CoR No.': 'KN/CoR/2025/00417',
                'Issued Against RDS': 'RDS/2025/00417',
                'Instrument': 'Deed of Assignment',
                'Registration No.': '417/417/2025',
                'File No.': 'MLS/KN/COM/2021/0833',
                'Tracking ID': 'TRK-2025-004170',
                'Date of Issue': '24 Apr 2025',
                'Signed By': 'Registrar of Deeds',
                'Created By': 'F. Abdulkadir · 24 Apr 2025',
                'Status': 'Active'
            },
            property: {
                'Party 1 (Grantor)': 'Kano Textile Mills Ltd',
                'Party 2 (Grantee)': 'Sabo Holdings Ltd',
                'Plot No.': 'Plot 3',
                'Street': 'Maiduguri Road',
                'District': 'Bompai',
                'LGA': 'Nassarawa',
                'Land Use': 'Commercial',
                'Consideration': '₦185,000,000.00',
                'Encumbrance': 'None recorded'
            },
            checks: [
                ['pass', 'QR token signature valid',      'Auth tag verified'],
                ['pass', 'Document record resolved',      'documents #77418 · type COR'],
                ['pass', 'Parent RDS exists',             'RDS/2025/00417 registered 22 Apr 2025'],
                ['pass', 'File indexing record exists',   'file_indexings #131290'],
                ['pass', 'File number matches',           'MLS/KN/COM/2021/0833'],
                ['pass', 'Tracking ID matches the file',  'TRK-2025-004170 — same as the parent RDS'],
                ['pass', 'Deeds bill fully settled',      'No balance outstanding as at 02 Apr 2025'],
                ['pass', 'Registrar signature on file',   'Signature registry ref. SIG/2025/206'],
                ['pass', 'Print record exists',           '1 print logged, 24 Apr 2025']
            ],
            prints: [
                [1, 'F. Abdulkadir', '24 Apr 2025 09:12', 'Original issue', 'Original']
            ],
            scans: [
                ['23 Aug 2026 08:35', 'A. Yakubu',       'QR Scan', 'authentic', 'Legal search'],
                ['09 Jul 2026 11:44', 'Access Bank Plc', 'Public',  'authentic', 'Title confirmation']
            ],
            timeline: [
                ['RDS generated',        '22 Apr 2025 · RDS/2025/00417'],
                ['Registrar signed CoR', '24 Apr 2025'],
                ['CoR released',         '24 Apr 2025 · Deeds Registry counter']
            ]
        },

        'ST/KN/2026/0092': {
            reference: 'ST/KN/2026/0092',
            verdict: 'authentic',
            product: 'st',
            confidence: '94%',
            token: {
                version: 'Q1',
                payload: 'KLAES-Q1:d4419b08cc7712ef3a…',
                fields: {
                    'Token version': 'Q1 — authenticated (AES-256-GCM)',
                    'Document ID': '88012',
                    'Document type': 'ST',
                    'File indexing ID': '142880',
                    'Auth tag': 'Verified against the server key'
                }
            },
            document: {
                'Document': 'Sectional Title (ST)',
                'ST File No.': 'ST/KN/2026/0092',
                'Primary File No.': 'MLS/KN/RES/2018/2210',
                'Tracking ID': 'ST-TRK-2026-000092',
                'Tracking ID Source': 'Auto-generated at file commissioning',
                'Units Registered': '24',
                'Date Commissioned': '16 Feb 2026',
                'Commissioned By': 'File Commissioning Office',
                'Created By': 'H. Sule · 16 Feb 2026',
                'Status': 'Commissioned'
            },
            property: {
                'Holder / Developer': 'Rahama Heights Ltd',
                'Plot No.': 'Plot 22',
                'Street': 'Yahaya Gusau Road',
                'District': 'Nassarawa GRA',
                'LGA': 'Nassarawa',
                'Land Use': 'Residential (Multi-unit)',
                'Plot Size': '3,600 sqm',
                'Blocks': 'Block A – Block C',
                'Encumbrance': 'None recorded'
            },
            checks: [
                ['pass', 'QR token signature valid',      'Auth tag verified'],
                ['pass', 'Document record resolved',      'documents #88012 · type ST'],
                ['pass', 'File indexing record exists',   'file_indexings #142880'],
                ['pass', 'File number matches',           'ST/KN/2026/0092'],
                ['info', 'Grouping record — not applicable', 'ST files have no grouping table; the tracking ID is auto-generated at commissioning'],
                ['pass', 'Tracking ID matches commissioning record', 'ST-TRK-2026-000092 issued 16 Feb 2026'],
                ['pass', 'Primary file resolved',         'MLS/KN/RES/2018/2210'],
                ['pass', 'Unit fragmentation consistent', '24 units indexed against the ST primary'],
                ['pass', 'Print record exists',           '2 prints logged, first 16 Feb 2026']
            ],
            prints: [
                [1, 'H. Sule',    '16 Feb 2026 10:48', 'Original issue', 'Original'],
                [2, 'A. Yakubu',  '03 Mar 2026 14:19', 'Developer copy', 'Reprint']
            ],
            scans: [
                ['23 Aug 2026 08:21', 'M. Danladi', 'QR Scan', 'authentic', 'Registry counter']
            ],
            timeline: [
                ['ST application received',  '04 Nov 2025 · Land Administration'],
                ['Units surveyed and indexed','21 Jan 2026 · 24 units'],
                ['File commissioned',        '16 Feb 2026 · Tracking ID auto-generated'],
                ['ST document issued',       '16 Feb 2026 · ST/KN/2026/0092']
            ]
        },

        'SP/KN/2023/7781': {
            reference: 'SP/KN/2023/7781',
            verdict: 'authentic',
            product: 'siteplan',
            confidence: '96%',
            token: {
                version: 'Q0',
                payload: 'TRK-2023-077810',
                fields: {
                    'Token version': 'Q0 — legacy raw tracking ID',
                    'Document ID': 'Not encoded in legacy QR',
                    'Document type': 'Inferred from the resolved record',
                    'File indexing ID': '108551 (resolved by tracking ID)',
                    'Auth tag': 'None — legacy QR carries no signature'
                }
            },
            document: {
                'Serial No.': 'SP/KN/2023/7781',
                'Registration No.': '—',
                'File No.': 'MLS/KN/COM/2022/0917',
                'Tracking ID': 'TRK-2023-077810',
                'KANGIS No.': 'KNML 09170',
                'Date of Issue': '05 Dec 2023',
                'Issuing Authority': 'Survey & Cadastral Department',
                'Signed By': 'Surveyor-General, Kano State',
                'Created By': 'I. Lawal · 05 Dec 2023',
                'Status': 'Active'
            },
            property: {
                'Holder / Grantee': 'Northern Agro Ventures Ltd',
                'Plot No.': 'Plot 12–14',
                'Street': 'Hadejia Road',
                'District': 'Gwagwarwa',
                'LGA': 'Nassarawa',
                'Land Use': 'Commercial',
                'Plot Size': '4,050 sqm',
                'Scale / Datum': '1:1000 · Minna Datum',
                'Encumbrance': 'None recorded'
            },
            checks: [
                ['warn', 'QR token signature valid',               'Legacy Q0 QR — nothing to authenticate'],
                ['pass', 'Plan number found in cadastral register','Matched survey record #7781'],
                ['pass', 'File indexing record exists',            'file_indexings #108551'],
                ['pass', 'Tracking ID matches the file',           'TRK-2023-077810'],
                ['pass', 'Coordinates match parcel geometry',      'Beacon set KN/BC/7781 verified'],
                ['pass', 'Surveyor-General seal present',          'Seal reference SG/2023/441'],
                ['pass', 'No overlap with adjoining parcels',      'Cadastral overlay clean'],
                ['pass', 'Print record exists',                    '1 print logged, 05 Dec 2023']
            ],
            prints: [
                [1, 'I. Lawal', '05 Dec 2023 16:02', 'Original issue', 'Original']
            ],
            scans: [
                ['23 Aug 2026 08:44', 'M. Danladi', 'Manual', 'authentic', 'Counter check']
            ],
            timeline: [
                ['Survey instruction issued', '18 Sep 2023 · Survey & Cadastral'],
                ['Field survey completed',    '14 Oct 2023'],
                ['Plan checked and approved', '28 Nov 2023 · Chief Cartographer'],
                ['Site Plan released',        '05 Dec 2023 · Surveyor-General signed']
            ]
        },

        'KLAES-Q1:TAMPERED9X': {
            reference: 'KLAES-Q1:TAMPERED9x',
            verdict: 'tampered',
            product: null,
            confidence: '0%',
            token: {
                version: 'BAD',
                payload: 'KLAES-Q1:TAMPERED9x',
                fields: {
                    'Token version': 'Q1 header present',
                    'Document ID': 'Unreadable — payload failed authentication',
                    'Document type': 'Unreadable',
                    'File indexing ID': 'Unreadable',
                    'Auth tag': 'REJECTED — does not match the server key'
                }
            },
            document: {
                'Payload scanned': 'KLAES-Q1:TAMPERED9x',
                'Token version': 'Q1',
                'Failure': 'Authentication tag mismatch',
                'Scanned at': '—'
            },
            property: {},
            checks: [
                ['fail', 'QR token signature valid',    'Authentication tag rejected — the payload was altered or forged'],
                ['skip', 'Document record resolved',    'Not attempted — the token was rejected first'],
                ['skip', 'File indexing record exists', 'Not attempted'],
                ['skip', 'Tracking ID matches',         'Not attempted'],
                ['warn', 'Report to the registry',      'Retain the physical document and raise a flag']
            ],
            prints: [],
            scans: [],
            timeline: [
                ['Tampered QR presented', 'Rejected at the verification console', true]
            ]
        }
    };

    var LOG = [
        ['09:41', 'KN/CofO/2024/00812', 'cofo',     'Aliyu Musa Abdullahi',       'Q1', 'QR Scan', 'A. Yakubu',     'authentic'],
        ['09:28', 'RES-2024-1906',      'rofo',     'Hauwa Ibrahim Sani',         'Q0', 'Manual',  'A. Yakubu',     'review'],
        ['08:57', 'KN/OP/2021/00455',   'op',       'Bello Yusuf Garba',          'Q1', 'QR Scan', 'M. Danladi',    'revoked'],
        ['08:44', 'SP/KN/2023/7781',    'siteplan', 'Northern Agro Ventures Ltd', 'Q0', 'Manual',  'M. Danladi',    'authentic'],
        ['08:35', 'KN/CoR/2025/00417',  'cor',      'Sabo Holdings Ltd',          'Q1', 'QR Scan', 'A. Yakubu',     'authentic'],
        ['08:33', 'RDS/2025/00417',     'rds',      'Sabo Holdings Ltd',          'Q1', 'QR Scan', 'A. Yakubu',     'authentic'],
        ['08:21', 'ST/KN/2026/0092',    'st',       'Rahama Heights Ltd',         'Q1', 'QR Scan', 'M. Danladi',    'authentic'],
        ['08:12', 'KN/CofO/2019/00113', null,       '—',                          '—',  'Manual',  'S. Ibrahim',    'notfound']
    ];

    var VERDICTS = {
        authentic: {
            tone: 'ipv-tone-pass', icon: 'badge-check', title: 'Authentic Document',
            text: 'This document matches the Ministry register and every verification step passed.'
        },
        review: {
            tone: 'ipv-tone-warn', icon: 'alert-triangle', title: 'Requires Manual Review',
            text: 'The record exists but one or more steps did not fully match. Refer to the registry officer before accepting.'
        },
        revoked: {
            tone: 'ipv-tone-fail', icon: 'shield-x', title: 'Title Revoked',
            text: 'This document was validly issued but the title has since been revoked. It is no longer valid evidence of interest.'
        },
        tampered: {
            tone: 'ipv-tone-fail', icon: 'shield-alert', title: 'QR Code Failed Authentication',
            text: 'The QR payload did not authenticate against the Ministry key. Treat the document as forged or altered and retain it.'
        },
        error: {
            tone: 'ipv-tone-warn', icon: 'plug-zap', title: 'Verification Could Not Be Completed',
            text: 'The check did not run, so this document is neither confirmed nor rejected. Try again before drawing any conclusion.'
        },
        notfound: {
            tone: 'ipv-tone-none', icon: 'file-x', title: 'Not Found in Register',
            text: 'No document matching this reference exists in the register. Treat the document as unverified.'
        }
    };

    var activeType  = 'all';
    var lastChannel = 'manual';   // set to qr_scan when the camera resolves a code

    function esc(v) { return $('<span>').text(v == null ? '' : v).html(); }
    function icons() { if (window.lucide && lucide.createIcons) { lucide.createIcons(); } }
    function typeLabel(t) { return (DOC_TYPES[t] && DOC_TYPES[t].label) || '—'; }
    function typeShort(t) { return (DOC_TYPES[t] && DOC_TYPES[t].short) || '—'; }
    function typeBadge(t) { return (DOC_TYPES[t] && DOC_TYPES[t].badge) || 'ipv-badge-slate'; }

    /* ---------------------- document type picker ---------------------- */
    function typeIcon(t) { return (DOC_TYPES[t] && DOC_TYPES[t].icon) || 'layers'; }

    function typeCard(key, def) {
        var active = (activeType === key);
        return '<button type="button" class="ipv-type-card' + (active ? ' ipv-type-card-active' : '') + '" data-type="' + key + '">' +
            '<span class="ipv-type-card-icon ' + def.badge + '"><i data-lucide="' + def.icon + '" class="w-4 h-4"></i></span>' +
            '<span>' +
                '<span class="ipv-type-card-label">' + esc(def.label) + '</span>' +
                '<span class="ipv-type-card-note">' + esc(def.note) + '</span>' +
            '</span>' +
            '<span class="ipv-type-card-check"><i data-lucide="check" class="w-4 h-4"></i></span>' +
        '</button>';
    }

    function renderTypePicker() {
        var term = $.trim($('#ipv_type_search').val() || '').toLowerCase();
        var $wrap = $('#ipv_type_groups').empty();
        var shown = 0;

        // "All types" always leads, and is never filtered out by a search that
        // matches nothing — the reset has to stay reachable.
        if (!term) {
            $wrap.append(
                '<div class="ipv-group">' +
                    '<div class="ipv-group-title">Everything</div>' +
                    '<div class="ipv-type-grid">' +
                        typeCard('all', { label: 'All Document Types', note: 'Search across every document', badge: 'ipv-badge-indigo', icon: 'layers' }) +
                    '</div>' +
                '</div>'
            );
        }

        $.each(DOC_GROUPS, function (groupKey, groupLabel) {
            var cards = '';

            $.each(DOC_TYPES, function (key, def) {
                if (def.group !== groupKey) { return; }
                if (term && (def.label + ' ' + def.short + ' ' + def.note).toLowerCase().indexOf(term) === -1) { return; }
                cards += typeCard(key, def);
                shown++;
            });

            if (cards) {
                $wrap.append(
                    '<div class="ipv-group">' +
                        '<div class="ipv-group-title">' + esc(groupLabel) + '</div>' +
                        '<div class="ipv-type-grid">' + cards + '</div>' +
                    '</div>'
                );
            }
        });

        if (term && shown === 0) {
            $wrap.append('<div class="ipv-type-empty">No document type matches “' + esc(term) + '”.</div>');
        }

        icons();
    }

    function syncTypeTrigger() {
        var def = DOC_TYPES[activeType];

        $('#ipv_type_label').text(def ? def.label : 'All Document Types');
        $('#ipv_type_hint').text(def
            ? (DOC_GROUPS[def.group] + ' · ' + def.note)
            : 'Every document the Ministry issues');
        $('#ipv_type_icon').html('<i data-lucide="' + typeIcon(activeType) + '" class="w-4 h-4"></i>');
        icons();
    }

    /* ---------------------- QR token reader -------------------------- */
    // Mirrors the server-side format detection: KLAES-Q1: prefix = secure token,
    // TRK-/ST-TRK- = a legacy QR that carries a raw tracking ID, anything else
    // is treated as a typed reference.
    function readToken(value) {
        var v = $.trim(value || '');
        if (!v) { return null; }
        if (/^KLAES-Q1:/i.test(v)) {
            return { version: 'Q1', tone: 'ipv-reader-q1',
                     text: '<strong>Secure QR token (KLAES-Q1)</strong> detected. The payload will be decrypted and authenticated against the Ministry key before any record is resolved.' };
        }
        if (/^(ST-)?TRK-/i.test(v)) {
            return { version: 'Q0', tone: 'ipv-reader-q0',
                     text: '<strong>Legacy QR (Q0)</strong> — this payload is a raw tracking ID with no signature. It resolves to a record but cannot prove the document was not altered.' };
        }
        return { version: 'REF', tone: 'ipv-reader-txt',
                 text: 'Reading this as a <strong>typed reference</strong> (serial, registration, tracking or file number) rather than a QR payload.' };
    }

    function updateTokenReader() {
        var t = readToken($('#ipv_reference').val());
        var $r = $('#ipv_token_reader');
        if (!t) { $r.addClass('hidden'); return; }
        $r.removeClass('hidden ipv-reader-q1 ipv-reader-q0 ipv-reader-txt').addClass(t.tone);
        $('#ipv_token_reader_text').html(t.text);
        icons();
    }

    /* ---- Live verification endpoint --------------------------------------
     * Falls back to the bundled sample set only when the request itself fails,
     * so the demo references in the hint banner keep working offline.
     * --------------------------------------------------------------------- */
    function lookup(reference, done) {
        $.ajax({
            url: '{{ route('information-products.verify') }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                reference: reference,
                channel: lastChannel
            },
            timeout: 20000
        }).done(function (res) {
            if (res && res.success && res.data) {
                var d = res.data;
                if (activeType !== 'all' && d.product && d.product !== activeType) {
                    d = { reference: reference, verdict: 'notfound', token: d.token,
                          document: { 'Reference searched': reference,
                                      'Register match': 'Found, but not a ' + typeLabel(activeType) } };
                }
                done(d);
                return;
            }
            done(sampleLookup(reference));
        }).fail(function (xhr, status) {
            // A failed request must NEVER be reported as "not found in register".
            // Telling an officer that a genuine document is absent from the
            // register because the network blipped is the worst answer this
            // screen can give. Only fall back to the bundled samples for the
            // demo references; everything else surfaces the failure honestly.
            var sample = SAMPLES[$.trim(reference || '').toUpperCase()];
            if (sample) { done(sample); return; }

            done(serviceError(reference, status, xhr));
        });
    }

    function serviceError(reference, status, xhr) {
        var why = status === 'timeout'
            ? 'The verification service did not respond within 20 seconds.'
            : 'The verification service could not be reached' +
              (xhr && xhr.status ? ' (HTTP ' + xhr.status + ').' : '.');

        return {
            reference: reference,
            verdict: 'error',
            product: null,
            confidence: '—',
            token: { version: (readToken(reference) || {}).version, payload: reference, fields: {} },
            document: { 'Reference searched': reference, 'Outcome': why },
            checks: [
                ['warn', 'Verification service reachable', why],
                ['skip', 'QR token signature valid', 'Not attempted — the check never ran'],
                ['skip', 'Document record resolved', 'Not attempted']
            ],
            prints: [], scans: [],
            timeline: [['Verification could not be completed', why, true]]
        };
    }

    function sampleLookup(reference) {
        var key = $.trim(reference || '').toUpperCase();
        var hit = SAMPLES[key];
        if (!hit) { return { reference: reference, verdict: 'notfound', token: { version: (readToken(reference) || {}).version } }; }
        if (activeType !== 'all' && hit.product !== activeType) { return { reference: reference, verdict: 'notfound', token: hit.token }; }
        return hit;
    }

    function renderFields($target, obj) {
        $target.empty();
        var any = false;
        $.each(obj || {}, function (label, value) {
            any = true;
            $target.append(
                '<div>' +
                    '<div class="ipv-field-label">' + esc(label) + '</div>' +
                    '<div class="ipv-field-value">' + esc(value || '—') + '</div>' +
                '</div>'
            );
        });
        if (!any) { $target.append('<div class="text-xs text-slate-400">Not available for this result.</div>'); }
    }

    function renderChecks(checks) {
        var $c = $('#ipv_checks').empty();
        var ICON = { pass: 'check', warn: 'alert-triangle', fail: 'x', info: 'info', skip: 'minus' };
        $.each(checks || [], function (_, row) {
            var state = row[0];
            $c.append(
                '<div class="ipv-check ipv-check-' + state + '">' +
                    '<span class="ipv-check-icon"><i data-lucide="' + (ICON[state] || 'circle') + '" class="w-3 h-3"></i></span>' +
                    '<div>' +
                        '<div class="ipv-check-title">' + esc(row[1]) + '</div>' +
                        '<div class="ipv-check-note">' + esc(row[2]) + '</div>' +
                    '</div>' +
                '</div>'
            );
        });
    }

    function renderTimeline(items) {
        var $t = $('#ipv_timeline').empty();
        $.each(items || [], function (_, row) {
            $t.append(
                '<li class="' + (row[2] ? 'is-alert' : '') + '">' +
                    '<div class="ipv-tl-title">' + esc(row[0]) + '</div>' +
                    '<div class="ipv-tl-meta">' + esc(row[1]) + '</div>' +
                '</li>'
            );
        });
    }

    function renderPrints(rows) {
        var $b = $('#ipv_print_body').empty();
        if (!rows || !rows.length) {
            $b.append('<tr><td colspan="5" class="text-slate-400 text-xs">No print record for this document.</td></tr>');
            $('#ipv_print_summary').text('0 prints');
            return;
        }
        $.each(rows, function (_, r) {
            var copyClass = r[4] === 'Original' ? 'ipv-badge-emerald' : 'ipv-badge-slate';
            $b.append('<tr>' +
                '<td class="font-mono font-bold text-slate-700">' + esc(r[0]) + '</td>' +
                '<td class="text-slate-700 font-semibold">' + esc(r[1]) + '</td>' +
                '<td class="text-slate-500">' + esc(r[2]) + '</td>' +
                '<td class="text-slate-600">' + esc(r[3]) + '</td>' +
                '<td><span class="ipv-badge ' + copyClass + '">' + esc(r[4]) + '</span></td>' +
            '</tr>');
        });
        $('#ipv_print_summary').text(rows.length + ' print' + (rows.length === 1 ? '' : 's') + ' · first ' + rows[0][2]);
    }

    function renderScans(rows) {
        var $b = $('#ipv_scan_body').empty();
        if (!rows || !rows.length) {
            $b.append('<tr><td colspan="4" class="text-slate-400 text-xs">This document has never been scanned before.</td></tr>');
            $('#ipv_scan_summary').text('0 scans');
            return;
        }
        $.each(rows, function (_, r) {
            $b.append('<tr>' +
                '<td class="text-slate-500">' + esc(r[0]) + '</td>' +
                '<td class="text-slate-700 font-semibold">' + esc(r[1]) + '</td>' +
                '<td class="text-slate-500">' + esc(r[2]) + '</td>' +
                '<td>' + resultChip(r[3]) + ' <span class="text-[11px] text-slate-400">' + esc(r[4] || '') + '</span></td>' +
            '</tr>');
        });
        $('#ipv_scan_summary').text(rows.length + ' scan' + (rows.length === 1 ? '' : 's') + ' · last ' + rows[0][0]);
    }

    function renderTokenPanel(token) {
        var t = token || {};
        var chipClass = t.version === 'Q1' ? 'ipv-token-q1' : (t.version === 'Q0' ? 'ipv-token-q0' : 'ipv-token-bad');
        var chipText  = t.version === 'Q1' ? 'QR: Secure (Q1)'
                      : t.version === 'Q0' ? 'QR: Legacy (Q0)'
                      : t.version === 'BAD' ? 'QR: Rejected'
                      : '';
        $('#ipv_token_chip').attr('class', 'ipv-badge ' + chipClass).text(chipText);
        $('#ipv_payload').text(t.payload || '—');
        $('#ipv_qr').html(t.payload && t.version !== 'BAD'
            ? renderQr(t.payload)
            : '<div class="text-[11px] text-slate-400 py-6 px-4">No readable token</div>');
        renderFields($('#ipv_token_fields'), t.fields);
        $('#ipv_token_fields').removeClass('grid').addClass('space-y-3');
    }

    // Decorative fingerprint block — swap for the register's real QR image.
    function renderQr(seed) {
        var n = 11, h = 0, i;
        var html = '<div style="display:grid;grid-template-columns:repeat(' + n + ',8px);gap:1px">';
        for (i = 0; i < seed.length; i++) { h = (h * 31 + seed.charCodeAt(i)) >>> 0; }
        for (i = 0; i < n * n; i++) {
            h = (h * 1103515245 + 12345) >>> 0;
            var col = i % n, rowIdx = Math.floor(i / n);
            var corner = (col < 3 && rowIdx < 3) || (col > n - 4 && rowIdx < 3) || (col < 3 && rowIdx > n - 4);
            var on = corner ? (col % 2 === 0 || rowIdx % 2 === 0) : ((h >>> 16) & 1);
            html += '<span style="width:8px;height:8px;background:' + (on ? '#0f172a' : '#fff') + '"></span>';
        }
        return html + '</div>';
    }

    function renderResult(data) {
        var v = VERDICTS[data.verdict] || VERDICTS.notfound;

        $('#ipv_empty').addClass('hidden');
        $('#ipv_result').removeClass('hidden');

        $('#ipv_verdict')
            .removeClass('ipv-tone-pass ipv-tone-warn ipv-tone-fail ipv-tone-none')
            .addClass(v.tone);
        $('#ipv_verdict_icon').html('<i data-lucide="' + v.icon + '" class="w-7 h-7"></i>');
        $('#ipv_verdict_title').text(v.title);
        $('#ipv_verdict_text').html(esc(v.text) + ' <span class="font-mono font-bold">' + esc(data.reference || '') + '</span>');

        $('#ipv_doc_badge')
            .attr('class', 'ipv-badge ' + (data.product ? typeBadge(data.product) : ''))
            .text(data.product ? typeLabel(data.product) : '');

        if (data.verdict === 'notfound') {
            renderFields($('#ipv_doc_fields'), {
                'Reference searched': data.reference,
                'Register match': 'None',
                'Searched at': new Date().toLocaleString()
            });
            renderChecks([
                ['fail', 'Reference found in register', 'No matching record'],
                ['skip', 'QR token signature valid',    'Nothing to authenticate'],
                ['warn', 'Manual registry search advised', 'Check archived / pre-digital registers']
            ]);
            renderPrints([]);
            renderScans([]);
            renderTimeline([['No register history', 'This reference has never been issued as a Ministry document', true]]);
            $('#ipv_confidence').text('0%');
            renderTokenPanel({ version: null, payload: null, fields: {} });
        } else {
            renderFields($('#ipv_doc_fields'), data.document);
            renderChecks(data.checks);
            renderPrints(data.prints);
            renderScans(data.scans);
            renderTimeline(data.timeline);
            $('#ipv_confidence').text(data.confidence || '—');
            renderTokenPanel(data.token);
        }

        prependLog(data);
        icons();
        $('html, body').animate({ scrollTop: $('#ipv_result').offset().top - 90 }, 250);
    }

    function resultChip(kind) {
        var label = {
            authentic: 'Authentic', review: 'Review', revoked: 'Revoked',
            tampered: 'Tampered', notfound: 'Not Found'
        }[kind] || kind;
        return '<span class="ipv-result-chip ipv-result-' + kind + '">' + label + '</span>';
    }

    function logRow(r) {
        return '<tr>' +
            '<td class="text-slate-500">' + esc(r[0]) + '</td>' +
            '<td class="font-mono font-semibold text-slate-800">' + esc(r[1]) + '</td>' +
            '<td>' + (r[2] ? '<span class="ipv-badge ' + typeBadge(r[2]) + '">' + esc(typeShort(r[2])) + '</span>' : '<span class="text-slate-400">—</span>') + '</td>' +
            '<td class="text-slate-600">' + esc(r[3]) + '</td>' +
            '<td class="font-mono text-[11px] font-bold text-slate-500">' + esc(r[4]) + '</td>' +
            '<td class="text-slate-500">' + esc(r[5]) + '</td>' +
            '<td class="text-slate-500">' + esc(r[6]) + '</td>' +
            '<td>' + resultChip(r[7]) + '</td>' +
        '</tr>';
    }

    function renderLog() {
        var $b = $('#ipv_log_body').empty();
        $.each(LOG, function (_, r) { $b.append(logRow(r)); });
    }

    function prependLog(data) {
        var now = new Date();
        var hh = ('0' + now.getHours()).slice(-2), mm = ('0' + now.getMinutes()).slice(-2);
        var p = data.property || {};
        var holder = p['Holder / Grantee'] || p['Holder / Developer'] || p['Party 2 (Grantee)'] || '—';
        var ver = (data.token && data.token.version) || '—';
        LOG.unshift([hh + ':' + mm, data.reference || '—', data.product || null, holder, ver, 'Manual', 'You', data.verdict]);
        renderLog();
        renderStats();
    }

    function renderStats() {
        var count = function (k) { return LOG.filter(function (r) { return r[7] === k; }).length; };
        $('#ipv_stat_total').text(LOG.length);
        $('#ipv_stat_authentic').text(count('authentic'));
        $('#ipv_stat_flagged').text(count('review') + count('revoked'));
        $('#ipv_stat_tampered').text(count('tampered'));
        $('#ipv_stat_notfound').text(count('notfound'));
    }

    /* ------------------------------ events ------------------------------ */
    $('#ipv_type_trigger').on('click', function () {
        $('#ipv_type_search').val('');
        renderTypePicker();
        openModal('ipv_type_modal');
        setTimeout(function () { $('#ipv_type_search').focus(); }, 30);
    });

    $('#ipv_type_search').on('input', renderTypePicker);

    $('#ipv_type_groups').on('click', '.ipv-type-card', function () {
        activeType = String($(this).data('type'));
        syncTypeTrigger();
        closeModal($('#ipv_type_modal'));
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal($('#ipv_type_modal, #ipv_scan_modal, #ipv_flag_modal').filter(':visible'));
        }
    });

    $('#ipv_toggle_advanced').on('click', function () {
        $('#ipv_advanced').toggleClass('hidden');
        $('#ipv_adv_chevron').toggleClass('rotate-180');
    });

    $('#ipv_reference').on('input', updateTokenReader);

    $(document).on('click', '.ipv-try', function () {
        $('#ipv_reference').val($(this).text());
        updateTokenReader();
        doVerify();
    });

    /* --------------------------- decrypt progress --------------------------- */
    var progTimer = null, progStep = 0, progSteps = [], progDone = null, progAtEnd = false, progGuard = null;

    function progressSteps(ref) {
        var t = readToken(ref) || {};

        if (t.version === 'Q1') {
            return [
                ['Reading QR payload',        'KLAES-Q1 token detected'],
                ['Decrypting token',          'AES-256-GCM, Ministry signing key'],
                ['Authenticating signature',  'Checking the payload was not altered'],
                ['Resolving document record', 'Matching the register'],
                ['Checking print register',   'Issue and reprint history']
            ];
        }

        if (t.version === 'Q0') {
            return [
                ['Reading QR payload',        'Legacy QR — no signature to decrypt'],
                ['Identifying tracking ID',   'Matching against known formats'],
                ['Resolving document record', 'Matching the register'],
                ['Checking print register',   'Issue and reprint history']
            ];
        }

        return [
            ['Reading reference',         'Typed reference, not a QR payload'],
            ['Searching the register',    'Serial, registration, tracking or file number'],
            ['Checking print register',   'Issue and reprint history']
        ];
    }

    function progressRender() {
        var $l = $('#ipv_progress_steps').empty();

        $.each(progSteps, function (i, st) {
            var cls = i < progStep ? 'ipv-step-done' : (i === progStep ? 'ipv-step-active' : '');
            var icon = i < progStep ? 'check' : (i === progStep ? 'loader-2' : 'circle');
            $l.append(
                '<li class="ipv-step ' + cls + '">' +
                    '<span class="ipv-step-dot"><i data-lucide="' + icon + '" class="w-2.5 h-2.5' +
                        (i === progStep ? ' animate-spin' : '') + '"></i></span>' +
                    '<span>' + esc(st[0]) + '</span>' +
                '</li>'
            );
        });

        var cur = progSteps[Math.min(progStep, progSteps.length - 1)];
        var pct = Math.round((progStep / progSteps.length) * 100);

        $('#ipv_progress_title').text(cur[0] + '…');
        $('#ipv_progress_sub').text(cur[1]);
        $('#ipv_progress_pct').text(pct + '%');
        $('#ipv_progress_bar').css('width', pct + '%');
        icons();
    }

    function progressStart(ref) {
        progSteps = progressSteps(ref);
        progStep  = 0;
        progAtEnd = false;
        progDone  = null;

        $('#ipv_empty, #ipv_result').addClass('hidden');
        $('#ipv_progress').removeClass('hidden');
        progressRender();

        if (progTimer) { clearInterval(progTimer); }

        // Hard stop. Whatever happens to the request, the bar is never left
        // sitting at 80% with the button stuck on "Verifying…".
        if (progGuard) { clearTimeout(progGuard); }
        progGuard = setTimeout(function () {
            if (progTimer) { clearInterval(progTimer); progTimer = null; }
            $('#ipv_progress').addClass('hidden');
            renderResult(serviceError($('#ipv_reference').val(), 'timeout', null));
            $('#ipv_btn_verify').prop('disabled', false)
                .html('<i data-lucide="unlock-keyhole" class="w-4 h-4"></i> Decrypt');
            icons();
        }, 25000);

        progTimer = setInterval(function () {
            // Hold on the last step until the response actually lands, so the
            // bar never claims to be finished before the work is.
            if (progStep < progSteps.length - 1) {
                progStep++;
                progressRender();
            } else if (progDone) {
                progStep = progSteps.length;
                progressRender();
                clearInterval(progTimer);
                progTimer = null;

                var cb = progDone;
                progDone = null;
                setTimeout(function () { $('#ipv_progress').addClass('hidden'); cb(); }, 260);
            } else {
                progAtEnd = true;
            }
        }, 230);
    }

    function progressFinish(cb) {
        if (progGuard) { clearTimeout(progGuard); progGuard = null; }
        progDone = cb;

        // The response beat the stepper to the end; close it out immediately.
        if (progAtEnd && !progTimer) {
            progStep = progSteps.length;
            progressRender();
            progDone = null;
            setTimeout(function () { $('#ipv_progress').addClass('hidden'); cb(); }, 260);
        }
    }

    function doVerify() {
        var ref = $.trim($('#ipv_reference').val());
        if (!ref) {
            $('#ipv_reference').addClass('border-rose-400').focus();
            setTimeout(function () { $('#ipv_reference').removeClass('border-rose-400'); }, 1200);
            return;
        }
        var $btn = $('#ipv_btn_verify')
            .prop('disabled', true)
            .html('<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Verifying…');
        icons();
        progressStart(ref);

        lookup(ref, function (data) {
            // Let the stepper finish its run before the verdict replaces it —
            // a decrypt that flashes past is indistinguishable from one that
            // never happened, and the officer needs to see the check occur.
            progressFinish(function () {
                renderResult(data);
                lastChannel = 'manual';
                $btn.prop('disabled', false).html('<i data-lucide="unlock-keyhole" class="w-4 h-4"></i> Decrypt');
                icons();
            });
        });
    }

    $('#ipv_btn_verify').on('click', doVerify);
    $('#ipv_reference').on('keydown', function (e) { if (e.key === 'Enter') { doVerify(); } });

    $('#ipv_btn_clear').on('click', function () {
        $('#ipv_reference, #ipv_holder, #ipv_fileno, #ipv_tracking, #ipv_plot, #ipv_from, #ipv_to').val('');
        $('#ipv_result').addClass('hidden');
        $('#ipv_empty').removeClass('hidden');
        $('#ipv_token_reader').addClass('hidden');
        $('#ipv_reference').focus();
    });

    /* modals */
    function openModal(id) { $('#' + id).removeClass('hidden').addClass('flex'); icons(); }
    function closeModal($m) {
        $m.addClass('hidden').removeClass('flex');
        // Lets the scanner release the camera however the modal was dismissed —
        // the X, Cancel, the backdrop or Esc.
        $m.trigger('ipv:closed');
    }

    $('.ipv-modal-close').on('click', function () {
        closeModal($(this).closest('#ipv_type_modal, #ipv_scan_modal, #ipv_flag_modal'));
    });
    $('#ipv_type_modal, #ipv_scan_modal, #ipv_flag_modal').on('click', function (e) {
        if (e.target === this) { closeModal($(this)); }
    });

    /* --------------------------- camera scanner --------------------------- */
    // Uses the browser's built-in BarcodeDetector — no library, and therefore no
    // CDN request. A CDN would reintroduce exactly the outbound dependency the
    // local QR renderer was built to remove, on a network that has already been
    // shown not to reach the public internet.
    var scanStream = null;
    var scanTimer  = null;
    var detector   = null;

    function scanShow(state) {           // 'idle' | 'live' | 'error'
        $('#ipv_scan_idle').toggleClass('hidden', state !== 'idle');
        $('#ipv_scan_error').toggleClass('hidden', state !== 'error');
        $('#ipv_scan_video, #ipv_scan_frame, #ipv_scan_line').toggleClass('hidden', state !== 'live');
        $('#ipv_scan_status').toggleClass('hidden', state !== 'live');
        icons();
    }

    function scanFail(title, text) {
        scanStop();
        $('#ipv_scan_error_title').text(title);
        $('#ipv_scan_error_text').html(text);
        scanShow('error');
    }

    function scanStop() {
        if (scanTimer) { clearInterval(scanTimer); scanTimer = null; }
        if (scanStream) {
            scanStream.getTracks().forEach(function (t) { t.stop(); });
            scanStream = null;
        }
        var v = document.getElementById('ipv_scan_video');
        if (v) { v.srcObject = null; }
    }

    function scanStart() {
        // getUserMedia only exists in a secure context: HTTPS, or localhost.
        // Over plain http:// the property is simply absent, which otherwise
        // surfaces as a confusing "undefined" error.
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            scanFail('Camera needs a secure connection',
                'Browsers only allow camera access over <strong>https://</strong> (or on localhost). ' +
                'This page is on <strong>' + esc(window.location.protocol) + '//</strong>. ' +
                'Paste the payload below instead, or ask IT to serve KLAES over HTTPS.');
            return;
        }

        if (!('BarcodeDetector' in window)) {
            scanFail('Scanning not supported in this browser',
                'This browser has no built-in QR decoder. Chrome or Edge support it; ' +
                'Firefox and Safari do not. Paste the payload below instead.');
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                scanStream = stream;

                var video = document.getElementById('ipv_scan_video');
                video.srcObject = stream;
                video.play();
                scanShow('live');

                try { detector = detector || new window.BarcodeDetector({ formats: ['qr_code'] }); }
                catch (e) {
                    scanFail('QR format unavailable', 'This browser cannot decode the qr_code format.');
                    return;
                }

                // ~6 reads/second is plenty and keeps the main thread free.
                scanTimer = setInterval(function () {
                    if (!scanStream || video.readyState !== 4) { return; }

                    detector.detect(video).then(function (codes) {
                        if (!codes || !codes.length) { return; }

                        var value = $.trim(codes[0].rawValue || '');
                        if (!value) { return; }

                        scanStop();
                        closeModal($('#ipv_scan_modal'));
                        lastChannel = 'qr_scan';
                        $('#ipv_reference').val(value);
                        updateTokenReader();
                        doVerify();
                    }).catch(function () { /* transient decode errors are normal */ });
                }, 160);
            })
            .catch(function (err) {
                var name = (err && err.name) || '';

                if (name === 'NotAllowedError' || name === 'SecurityError') {
                    scanFail('Camera permission denied',
                        'Allow camera access for this site in your browser settings, then try again.');
                } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
                    scanFail('No camera found', 'No camera is attached to this device.');
                } else if (name === 'NotReadableError') {
                    scanFail('Camera is in use', 'Another application is holding the camera. Close it and try again.');
                } else {
                    scanFail('Camera could not start', esc(err && err.message ? err.message : 'Unknown error.'));
                }
            });
    }

    $('#ipv_btn_scan').on('click', function () {
        $('#ipv_scan_manual').val('');
        scanShow('idle');
        openModal('ipv_scan_modal');
    });

    $('#ipv_scan_start, #ipv_scan_retry').on('click', scanStart);

    // The camera must be released whenever the modal leaves the screen —
    // otherwise the recording indicator stays lit after "Cancel".
    $('#ipv_scan_modal').on('ipv:closed', scanStop);

    $('#ipv_scan_use').on('click', function () {
        var v = $.trim($('#ipv_scan_manual').val());
        closeModal($('#ipv_scan_modal'));
        if (v) { $('#ipv_reference').val(v); updateTokenReader(); doVerify(); }
    });

    $('#ipv_btn_flag').on('click', function () {
        $('#ipv_flag_ref').val($.trim($('#ipv_reference').val()));
        openModal('ipv_flag_modal');
    });
    $('#ipv_flag_submit').on('click', function () {
        closeModal($('#ipv_flag_modal'));
        alert('Flag captured — front-end only, no request was sent.');
    });

    $('#ipv_btn_print').on('click', function () { window.print(); });
    $('#ipv_btn_export').on('click', function () { alert('Export pending the backend endpoint.'); });

    /* ------------------------------- boot ------------------------------- */
    syncTypeTrigger();
    renderLog();
    renderStats();
    icons();
});
</script>
@endpush

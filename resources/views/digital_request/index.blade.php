@extends('layouts.app')
@section('page-title') Digital File Request @endsection

@section('content')
<div class="flex-1 overflow-y-auto overflow-x-hidden">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    @include('admin.header')

    {{-- ── Module Banner ───────────────────────────────────────────────────── --}}
    @if(!empty($isKangis))
    <div class="bg-gradient-to-r from-orange-600 via-orange-500 to-orange-700 px-6 py-3 flex items-center gap-3 shadow-sm">
        <i data-lucide="send" class="h-5 w-5 text-white shrink-0"></i>
        <div class="flex items-center gap-2">
            <span class="text-white font-bold text-sm uppercase tracking-widest">KANGIS</span>
            <span class="text-orange-100 text-sm">·</span>
            <span class="text-white text-sm font-medium">Digital File Request</span>
            <span class="text-orange-100 text-sm">·</span>
            <span class="text-orange-100 text-sm">Request a Physical File</span>
        </div>
    </div>
    @else
    <div class="bg-gradient-to-r from-[#450a0a] via-[#6b1010] to-[#450a0a] px-6 py-3 flex items-center gap-3 shadow-sm">
        <i data-lucide="send" class="h-5 w-5 text-white shrink-0"></i>
        <div class="flex items-center gap-2">
            <span class="text-white font-bold text-sm uppercase tracking-widest">Digital File Request</span>
            <span class="text-red-200 text-sm">·</span>
            <span class="text-white text-sm font-medium">e-Registry</span>
            <span class="text-red-200 text-sm">·</span>
            <span class="text-red-200 text-sm">Request a Physical File</span>
        </div>
    </div>
    @endif

    <div class="p-6">
        <div class="container mx-auto py-6 space-y-6">

            {{-- ── Stats cards ─────────────────────────────────────────────── --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex items-center gap-3">
                    <div class="p-2 bg-red-50 rounded-lg"><i data-lucide="inbox" class="h-5 w-5 text-[#450a0a]"></i></div>
                    <div><p class="text-xs text-gray-500">Total Requests</p><p class="text-xl font-bold text-gray-800">{{ number_format($stats['total']) }}</p></div>
                </div>
                <div id="dr-pending-card" class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex items-center gap-3 relative {{ $stats['pending'] > 0 ? 'border-yellow-300 ring-2 ring-yellow-200' : '' }}">
                    <div class="p-2 bg-yellow-100 rounded-lg"><i data-lucide="clock" class="h-5 w-5 text-yellow-600"></i></div>
                    <div>
                        <p class="text-xs text-gray-500">Pending</p>
                        <p class="text-xl font-bold text-gray-800" id="dr-pending-count">{{ number_format($stats['pending']) }}</p>
                    </div>
                    {{-- Pulsing dot when there are pending requests --}}
                    @if($stats['pending'] > 0)
                    <span class="absolute top-2 right-2 flex h-2.5 w-2.5" id="dr-pending-pulse">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-yellow-500"></span>
                    </span>
                    @else
                    <span class="absolute top-2 right-2 flex h-2.5 w-2.5 hidden" id="dr-pending-pulse">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-yellow-500"></span>
                    </span>
                    @endif
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex items-center gap-3">
                    <div class="p-2 bg-red-50 rounded-lg"><i data-lucide="check-circle" class="h-5 w-5 text-[#450a0a]"></i></div>
                    <div><p class="text-xs text-gray-500">Approved</p><p class="text-xl font-bold text-gray-800">{{ number_format($stats['approved']) }}</p></div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex items-center gap-3">
                    <div class="p-2 bg-red-100 rounded-lg"><i data-lucide="x-circle" class="h-5 w-5 text-red-600"></i></div>
                    <div><p class="text-xs text-gray-500">Rejected</p><p class="text-xl font-bold text-gray-800">{{ number_format($stats['rejected']) }}</p></div>
                </div>
            </div>

            {{-- ── Table card ───────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

                {{-- New-request alert banner (hidden by default, shown by JS) --}}
                <div id="dr-new-request-banner" class="hidden px-5 py-3 bg-yellow-50 border-b border-yellow-200 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm text-yellow-800 font-medium">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-yellow-500"></span>
                        </span>
                        <i data-lucide="bell-ring" class="h-4 w-4 text-yellow-600"></i>
                        <span id="dr-new-request-msg">New file request received!</span>
                    </div>
                    <button id="dr-banner-view-btn"
                        class="px-3 py-1 bg-yellow-600 hover:bg-yellow-700 text-white text-xs font-semibold rounded-lg transition-colors">
                        View Pending
                    </button>
                </div>

                {{-- Toolbar --}}
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-[#450a0a]"></i>
                        File Requests
                        <span id="dr-pending-badge" class="hidden inline-flex items-center justify-center ml-1 min-w-[1.2rem] h-5 px-1.5 rounded-full bg-yellow-500 text-white text-[10px] font-black"></span>
                    </h2>
                    <div class="flex flex-wrap items-center gap-2">
                        {{-- Live-refresh indicator --}}
                        <span id="dr-live-indicator" class="inline-flex items-center gap-1 text-xs text-gray-400" title="Auto-refreshes every 30s">
                            <span class="flex h-1.5 w-1.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#450a0a] opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-[#450a0a]"></span>
                            </span>
                            Live
                        </span>
                        {{-- Search --}}
                        <input type="text" id="dr-search" placeholder="Search file no, officer…"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a] w-52">

                        {{-- Status filter --}}
                        <select id="dr-status-filter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a]">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Redirected">Redirected</option>
                            <option value="In Transit">In Transit</option>
                            <option value="Completed">Completed</option>
                            <option value="Canceled">Canceled</option>
                        </select>

                        {{-- Date range --}}
                        <input type="date" id="dr-date-from" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a]">
                        <input type="date" id="dr-date-to"   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a]">

                        {{-- My Digital Files link --}}
                        <a href="{{ route('digital-request.my-files') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-2 border border-red-200 text-[#450a0a] bg-red-50 hover:bg-red-100 text-sm font-medium rounded-lg transition-colors">
                            <i data-lucide="folder-open" class="h-4 w-4"></i>
                            My Digital Files
                        </a>
                    </div>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider w-10 text-center">S/N</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">File No</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">File Title</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Requesting Officer</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Destination Office</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Approved By</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dr-table-body" class="divide-y divide-gray-100">
                            <tr><td colspan="9" class="px-4 py-10 text-center text-gray-400 text-sm">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
                    <span id="dr-pagination-info">—</span>
                    <div class="flex gap-2">
                        <button id="dr-prev" class="px-3 py-1.5 border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">← Prev</button>
                        <button id="dr-next" class="px-3 py-1.5 border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next →</button>
                    </div>
                </div>
            </div>

        </div>{{-- /container --}}
    </div>{{-- /p-6 --}}

</div>{{-- /flex-1 --}}

{{-- ═══════════════════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════════════════════ --}}

{{-- ── Create Request Modal ─────────────────────────────────────────────────── --}}
<div id="modal-create" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                <i data-lucide="send" class="h-4 w-4 text-[#450a0a]"></i>New Digital File Request
            </h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-create-request" class="px-6 py-5 space-y-4">
            @csrf

            {{-- ── Request Type Toggle ─────────────────────────────────── --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-2">Request Type</label>
                <div class="flex gap-2">
                    <button type="button" data-type="Physical" id="cr-type-physical"
                        class="cr-type-btn flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all
                               border-[#450a0a] bg-[#450a0a] text-white shadow-sm">
                        <i data-lucide="package" class="h-4 w-4"></i> Physical File
                    </button>
                    <button type="button" data-type="Digital" id="cr-type-digital"
                        class="cr-type-btn flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all
                               border-gray-200 bg-white text-gray-500 hover:border-blue-300 hover:text-blue-600">
                        <i data-lucide="monitor" class="h-4 w-4"></i> Digital Access
                    </button>
                </div>
                <input type="hidden" id="cr-request-type" name="request_type" value="Physical">

                {{-- Digital-mode info banner --}}
                <div id="cr-digital-banner" class="hidden mt-2 flex items-start gap-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                    <i data-lucide="info" class="h-3.5 w-3.5 text-blue-500 shrink-0 mt-0.5"></i>
                    <span>A temporary digital copy will be made from the scanned archive. Access lasts <strong>5 working days</strong> and is view-only — no downloads.</span>
                </div>
            </div>

            {{-- File Number + lookup --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">File Number <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input type="text" id="cr-file-no" name="file_no" placeholder="e.g. RES-2026-1234"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a]">
                    <button type="button" id="btn-check-availability"
                        class="px-4 py-2 bg-[#450a0a] hover:bg-[#5c0c0c] text-white text-sm rounded-lg font-medium transition-colors">
                        Check
                    </button>
                </div>
                <div id="cr-availability-msg" class="mt-2 hidden text-sm rounded-lg px-3 py-2"></div>
            </div>

            {{-- File Title (auto-filled) --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">File Title</label>
                <input type="text" id="cr-file-title" name="file_title" readonly
                    class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-600">
            </div>

            {{-- Current Location (auto-filled from check) --}}
            <div id="cr-current-location-wrapper" class="hidden">
                <label class="block text-xs font-medium text-gray-700 mb-1">Current File Location</label>
                <input type="text" id="cr-current-location" readonly
                    class="w-full px-3 py-2 border border-orange-200 bg-orange-50 rounded-lg text-sm text-orange-700">
                <input type="hidden" id="cr-current-file-location" name="current_file_location">
                <input type="hidden" id="cr-current-file-holder"   name="current_file_holder">
                <input type="hidden" id="cr-is-redirected"         name="is_redirected" value="0">
            </div>

            {{-- Destination Office (hidden for Digital type) --}}
            <div id="cr-physical-fields">
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Destination Office <span class="text-red-500">*</span></label>
                    <select id="cr-destination-office" name="destination_office_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a]">
                        <option value="">— Select Destination Office —</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" data-name="{{ $office->office_name }}">
                                {{ $office->office_name }} ({{ $office->department }})
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" id="cr-destination-office-name" name="destination_office_name">
                    <p class="text-xs text-gray-400 mt-1">Your department's offices are listed first.</p>
                </div>

                {{-- Receiving Officer --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Receiving Officer</label>
                    <select id="cr-receiving-officer" name="receiving_officer"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a]">
                        <option value="">— Select Officer —</option>
                        @foreach($officers as $officer)
                            <option value="{{ $officer->first_name }} {{ $officer->last_name }}">
                                {{ $officer->first_name }} {{ $officer->last_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Remarks --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Remarks / Purpose</label>
                <textarea id="cr-remarks" name="remarks" rows="3" placeholder="Reason for requesting this file…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a] resize-none"></textarea>
            </div>

            {{-- Requester info (read-only display) --}}
            <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-xs text-[#450a0a]">
                <p><span class="font-semibold">Requesting Officer:</span> {{ trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) }}</p>
                <p class="mt-0.5"><span class="font-semibold">Department:</span> {{ auth()->user()->department?->name ?? '—' }}</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" id="btn-submit-request"
                    class="px-5 py-2 bg-[#450a0a] hover:bg-[#5c0c0c] text-white text-sm font-medium rounded-lg transition-colors">
                    <span id="btn-submit-label">Submit Request</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── View Details Modal ───────────────────────────────────────────────────── --}}
<div id="modal-view" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    {{-- Outer wrapper: fixed height, flex column so header sticks and body scrolls --}}
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col" style="max-height:90vh">
        {{-- Sticky header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-red-50 to-white rounded-t-2xl shrink-0">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-100 shrink-0">
                    <i data-lucide="clipboard-list" class="h-4 w-4 text-[#450a0a]"></i>
                </div>
                <div>
                    <p class="text-[10px] font-semibold text-[#450a0a] uppercase tracking-widest">Request Summary</p>
                    <h3 class="text-sm font-bold text-gray-800 leading-snug">
                        <span class="font-mono text-[#450a0a]" id="vd-file-no"></span>
                        <span id="vd-file-title-sep" class="mx-1 text-gray-300">·</span>
                        <span id="vd-file-title" class="text-gray-600 font-normal"></span>
                    </h3>
                </div>
            </div>
            <button class="modal-close p-1 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        {{-- Scrollable body --}}
        <div class="px-6 py-5 overflow-y-auto flex-1" id="view-detail-body">
            <div class="flex items-center justify-center py-10 text-gray-400 text-sm gap-2">
                <i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Loading…
            </div>
        </div>
    </div>
</div>

{{-- ── Approve Modal ────────────────────────────────────────────────────────── --}}
<div id="modal-approve" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                <i data-lucide="check-circle" class="h-4 w-4 text-[#450a0a]"></i>Approve Request
            </h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-approve" class="px-6 py-5 space-y-4">
            <input type="hidden" id="approve-id">
            <p class="text-sm text-gray-600">Approving request <strong id="approve-req-no" class="text-[#450a0a]"></strong>. The file tracker will be updated and the requester will be notified.</p>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Remarks (optional)</label>
                <textarea name="remarks" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#450a0a] resize-none"></textarea>
            </div>

            {{-- ── Digital Signature Verification ── --}}
            <div class="rounded-xl border border-red-100 bg-gradient-to-br from-red-50/60 to-white p-4 space-y-3">
                <div class="flex items-center gap-2 mb-1">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-100 shrink-0">
                        <i data-lucide="shield-check" class="h-3.5 w-3.5 text-[#450a0a]"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">Digital Signature Required</p>
                        <p class="text-[11px] text-slate-500">Verify your identity to authorize this approval.</p>
                    </div>
                </div>

                <input type="hidden" id="approve-sig-verified" value="0">

                <div class="grid gap-3 sm:grid-cols-2">
                    {{-- Method --}}
                    <div class="space-y-1">
                        <label class="block text-[10px] font-semibold text-slate-600 uppercase tracking-wide">Verification Method</label>
                        <select id="approve-sig-method"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#450a0a] focus:ring-2 focus:ring-red-100">
                            <option value="">Select Method</option>
                            <option value="email">Email OTP</option>
                            <option value="sms">SMS OTP</option>
                            <option value="password">Password Confirmation</option>
                        </select>
                    </div>

                    {{-- OTP --}}
                    <div class="space-y-1" id="approve-sig-otp-wrapper" style="display:none">
                        <label class="block text-[10px] font-semibold text-slate-600 uppercase tracking-wide">OTP Code</label>
                        <div class="flex gap-2">
                            <input type="text" id="approve-sig-otp-code" maxlength="6" placeholder="Enter OTP"
                                class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-[#450a0a] focus:ring-2 focus:ring-red-100">
                            <button type="button" id="approve-sig-send-otp"
                                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 hover:bg-indigo-700 px-3 py-2 text-xs font-bold text-white whitespace-nowrap">
                                Send OTP
                            </button>
                        </div>
                    </div>

                    {{-- Password --}}
                    <div class="space-y-1" id="approve-sig-pwd-wrapper" style="display:none">
                        <label class="block text-[10px] font-semibold text-slate-600 uppercase tracking-wide">Confirm Password</label>
                        <input type="password" id="approve-sig-password" placeholder="Your account password"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-[#450a0a] focus:ring-2 focus:ring-red-100">
                    </div>
                </div>

                <div class="flex items-center gap-3 flex-wrap">
                    <button type="button" id="approve-sig-verify-btn"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#450a0a] hover:bg-[#5c0c0c] px-4 py-2 text-xs font-bold text-white shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                        <span id="approve-sig-verify-text">Verify</span>
                    </button>
                    <span id="approve-sig-feedback" class="text-xs font-medium text-slate-500"></span>

                    <div id="approve-sig-img-wrap" class="hidden flex items-center gap-2 ml-auto">
                        <img id="approve-sig-img" src="" alt="Your Signature"
                            class="h-8 object-contain border border-red-200 rounded bg-white px-2 py-0.5">
                        <span class="inline-flex items-center gap-1 text-[10px] font-black text-[#450a0a] uppercase tracking-wide">
                            <i data-lucide="check-circle" class="h-3 w-3"></i> Verified
                        </span>
                    </div>
                </div>

                <p class="text-[10px] text-slate-400 italic">
                    Approving as: <strong class="text-slate-600">{{ trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) }}</strong>
                </p>
            </div>

            <div class="flex justify-end gap-3 pt-1">
                <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" id="approve-submit-btn" disabled
                    class="px-5 py-2 bg-[#450a0a] hover:bg-[#5c0c0c] text-white text-sm font-medium rounded-lg transition-colors opacity-50 cursor-not-allowed">
                    Confirm Approve
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Reject Modal ─────────────────────────────────────────────────────────── --}}
<div id="modal-reject" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                <i data-lucide="x-circle" class="h-4 w-4 text-red-600"></i>Reject Request
            </h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-reject" class="px-6 py-5 space-y-4">
            <input type="hidden" id="reject-id">
            <p class="text-sm text-gray-600">Rejecting request <strong id="reject-req-no" class="text-red-700"></strong>. Please provide a reason.</p>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                <textarea id="reject-reason" name="rejection_reason" rows="3" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"
                    placeholder="Explain why this request is being rejected…"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════════════════ --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
(function () {
    'use strict';

    // ── CSRF ──────────────────────────────────────────────────────────────────
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── State ─────────────────────────────────────────────────────────────────
    let currentPage   = 1;
    let totalPages    = 1;
    let isLoading     = false;
    let searchTimer   = null;
    let deptOfficeIds = [];  // IDs of offices in user's department (for sorting)

    // ── Helpers ───────────────────────────────────────────────────────────────
    function $(sel, ctx = document) { return ctx.querySelector(sel); }
    function $$(sel, ctx = document) { return [...ctx.querySelectorAll(sel)]; }

    function showModal(id) {
        const m = document.getElementById(id);
        m.classList.remove('hidden');
        m.classList.add('flex');
    }
    function hideModal(id) {
        const m = document.getElementById(id);
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    function toast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    }

    async function apiFetch(url, options = {}) {
        const res = await fetch(url, {
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', ...(options.headers ?? {}) },
            ...options,
        });
        return res;
    }

    // ── Load table rows ───────────────────────────────────────────────────────
    async function loadTable(page = 1) {
        if (isLoading) return;
        isLoading   = true;
        currentPage = page;

        const search   = $('#dr-search').value.trim();
        const status   = $('#dr-status-filter').value;
        const dateFrom = $('#dr-date-from').value;
        const dateTo   = $('#dr-date-to').value;

        const params = new URLSearchParams({ page, per_page: 25, search, status, date_from: dateFrom, date_to: dateTo });
        @if(!empty($isKangis)) params.set('kangis', '1'); @endif
        const tbody  = $('#dr-table-body');
        tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-10 text-center text-gray-400 text-sm">Loading…</td></tr>';

        try {
            const res  = await fetch(`/digital-request/list?${params}`);
            const json = await res.json();

            totalPages = json.last_page ?? 1;
            renderTable(json.data ?? []);
            updatePagination(json);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-10 text-center text-red-400 text-sm">Failed to load data.</td></tr>';
        } finally {
            isLoading = false;
            lucide.createIcons();
        }
    }

    function renderTable(rows) {
        const tbody = $('#dr-table-body');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-10 text-center text-gray-400 text-sm">No requests found.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map((r, i) => `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-xs text-gray-400 font-medium text-center">${((currentPage - 1) * 25) + i + 1}</td>
                <td class="px-4 py-3 text-xs font-semibold text-gray-800 whitespace-nowrap font-mono">${r.file_no ?? '—'}</td>
                <td class="px-4 py-3 text-xs text-gray-600 max-w-[160px] truncate" title="${escHtml(r.file_title ?? '')}">${r.file_title ?? '—'}</td>
                <td class="px-4 py-3 text-xs text-gray-700">${r.sending_officer}</td>
                <td class="px-4 py-3 text-xs text-gray-600">${r.destination_office_name ?? '—'}${r.is_redirected ? ' <span class="ml-1 text-orange-500 text-[10px] font-semibold">[REDIRECTED]</span>' : ''}</td>
                <td class="px-4 py-3 text-xs text-gray-600">${r.approved_by_name ? `<span class="font-medium text-[#450a0a]">${r.approved_by_name}</span>` : '<span class="text-gray-300">—</span>'}</td>
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">${r.requested_at ?? '—'}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${r.status_badge_class}">${r.request_status}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <button class="action-menu-btn p-1.5 rounded-md hover:bg-gray-100 text-gray-500" data-id="${r.id}" data-req-no="${r.request_no}" data-status="${r.request_status}">
                        <i data-lucide="more-vertical" class="h-4 w-4"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function updatePagination(json) {
        const info = $('#dr-pagination-info');
        const from = ((json.current_page - 1) * json.per_page) + 1;
        const to   = Math.min(json.current_page * json.per_page, json.total);
        info.textContent = json.total ? `Showing ${from}–${to} of ${json.total}` : 'No records';
        $('#dr-prev').disabled = json.current_page <= 1;
        $('#dr-next').disabled = json.current_page >= json.last_page;
    }

    function escHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    // ── Action dropdown ────────────────────────────────────────────────────────
    // Shared dropdown element — appended to body so it floats above the table
    const _dropdown = document.createElement('div');
    _dropdown.id = 'dr-action-dropdown';
    _dropdown.className = 'hidden fixed z-[9999] min-w-[170px] rounded-xl border border-gray-200 bg-white shadow-xl py-1 text-sm';
    // Whether this account may approve or reject a file request at all. "Log a File" is the
    // module these requests belong to; see config/module_permissions.php.
    const CAN_APPROVE_REQUESTS = @json(auth()->check() && auth()->user()->canDo('Log a File', 'approve'));

    _dropdown.innerHTML = `
        <button data-action="view"
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-left text-gray-700 hover:bg-red-50 hover:text-[#450a0a] transition-colors">
            <i data-lucide="eye" class="h-3.5 w-3.5 shrink-0"></i> View Details
        </button>
        <div id="dr-dd-divider" class="my-1 border-t border-gray-100"></div>
        <button data-action="approve" id="dr-dd-approve"
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-left text-[#450a0a] hover:bg-red-50 transition-colors">
            <i data-lucide="check-circle" class="h-3.5 w-3.5 shrink-0"></i> Approve
        </button>
        <button data-action="reject" id="dr-dd-reject"
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-left text-red-600 hover:bg-red-50 transition-colors">
            <i data-lucide="x-circle" class="h-3.5 w-3.5 shrink-0"></i> Reject
        </button>`;
    document.body.appendChild(_dropdown);

    let _activeDropdownData = {};

    function openActionDropdown(btn) {
        const id     = btn.dataset.id;
        const reqNo  = btn.dataset.reqNo;
        const status = btn.dataset.status;
        const isPending = status === 'Pending';

        _activeDropdownData = { id, reqNo, status };

        /*
         | Approve and Reject need BOTH a pending request and the approve permission on this
         | module. The dropdown is built in JavaScript, so the permission travels as a
         | constant rather than through a blade guard; the endpoints behind these two items
         | are gated independently by the route middleware, which is the part that enforces.
        */
        const mayDecide = isPending && CAN_APPROVE_REQUESTS;

        const approveBtn = _dropdown.querySelector('#dr-dd-approve');
        const rejectBtn  = _dropdown.querySelector('#dr-dd-reject');
        const divider    = _dropdown.querySelector('#dr-dd-divider');
        if (approveBtn) approveBtn.classList.toggle('hidden', !mayDecide);
        if (rejectBtn)  rejectBtn.classList.toggle('hidden', !mayDecide);
        if (divider)    divider.classList.toggle('hidden', !mayDecide);

        // Position: below button, right-aligned
        const rect = btn.getBoundingClientRect();
        const scrollY = window.scrollY || document.documentElement.scrollTop;
        const scrollX = window.scrollX || document.documentElement.scrollLeft;
        _dropdown.classList.remove('hidden');

        // Refresh icons in dropdown
        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [_dropdown] });

        // Check if dropdown would overflow right edge
        const ddWidth = 180;
        let left = rect.right + scrollX - ddWidth;
        if (left < 0) left = rect.left + scrollX;
        _dropdown.style.top  = (rect.bottom + scrollY + 4) + 'px';
        _dropdown.style.left = left + 'px';
    }

    function closeActionDropdown() {
        _dropdown.classList.add('hidden');
        _activeDropdownData = {};
    }

    // Button click
    document.getElementById('dr-table-body').addEventListener('click', function (e) {
        const btn = e.target.closest('.action-menu-btn');
        if (!btn) return;
        e.stopPropagation();
        // Toggle
        if (!_dropdown.classList.contains('hidden') && _activeDropdownData.id === btn.dataset.id) {
            closeActionDropdown();
        } else {
            openActionDropdown(btn);
        }
    });

    // Dropdown item click
    _dropdown.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const action = btn.dataset.action;
        const { id, reqNo } = _activeDropdownData;
        closeActionDropdown();
        if (action === 'view')    openViewModal(id);
        if (action === 'approve') openApproveModal(id, reqNo);
        if (action === 'reject')  openRejectModal(id, reqNo);
    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        if (!_dropdown.classList.contains('hidden') && !_dropdown.contains(e.target)) {
            closeActionDropdown();
        }
    });

    // Close on scroll / ESC
    window.addEventListener('scroll', closeActionDropdown, { passive: true });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeActionDropdown(); });

    // ── View Details ──────────────────────────────────────────────────────────
    async function openViewModal(id) {
        showModal('modal-view');
        const body = document.getElementById('view-detail-body');
        body.innerHTML = `<div class="flex items-center justify-center py-10 text-gray-400 text-sm gap-2">
            <i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Loading…</div>`;
        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [body] });

        const res = await fetch(`/digital-request/show/${id}`);
        const d   = await res.json();
        document.getElementById('vd-file-no').textContent = d.file_no ?? d.request_no;
        const _fileTitleEl  = document.getElementById('vd-file-title');
        const _fileTitleSep = document.getElementById('vd-file-title-sep');
        const _ft = d.file_title ?? '';
        if (_fileTitleEl)  _fileTitleEl.textContent = _ft;
        if (_fileTitleSep) _fileTitleSep.classList.toggle('hidden', !_ft);

        // ── icon row helper ──────────────────────────────────────────────
        const irow = (icon, label, val, cls = '') => val ? `
            <div class="flex items-start gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 shrink-0 mt-0.5">
                    <i data-lucide="${icon}" class="h-3.5 w-3.5 text-gray-500"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">${label}</p>
                    <p class="text-xs font-medium text-gray-800 mt-0.5 ${cls}">${val}</p>
                </div>
            </div>` : '';

        const section = (icon, title) => `
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="${icon}" class="h-3.5 w-3.5 text-[#450a0a]"></i>
                <span class="text-[10px] font-black text-[#450a0a] uppercase tracking-widest">${title}</span>
                <div class="flex-1 border-t border-red-100"></div>
            </div>`;

        const statusIcon = {
            'Pending': 'clock', 'Approved': 'check-circle',
            'Rejected': 'x-circle', 'In Transit': 'truck',
            'Completed': 'check-square', 'Canceled': 'ban',
        }[d.request_status] ?? 'circle';

        body.innerHTML = `
        <div class="space-y-5">

            {{-- ── File Information ── --}}
            <div>
                ${section('file-text', 'File Information')}
                <div class="grid grid-cols-2 gap-3">
                    ${irow('hash',          'File Number',  `<span class="font-mono font-bold text-[#450a0a]">${escHtml(d.file_no ?? '—')}</span>`)}
                    ${irow('file-text',     'File Title',   escHtml(d.file_title ?? '—'))}
                    ${irow(statusIcon,      'Status',       `<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${d.status_badge_class}">${d.request_status}</span>`)}
                    ${d.is_redirected ? irow('corner-right-down', 'Note', '<span class="text-orange-600">Redirected to current file holder</span>') : ''}
                </div>
            </div>

            {{-- ── People & Offices ── --}}
            <div>
                ${section('users', 'People & Offices')}
                <div class="grid grid-cols-2 gap-3">
                    ${irow('user',         'Requesting Officer', escHtml(d.sending_officer ?? '—'))}
                    ${irow('user-check',   'Receiving Officer',  escHtml(d.receiving_officer ?? '—'))}
                    ${irow('building',     'Source Office',      escHtml(d.source_office_name ?? '—'))}
                    ${irow('building-2',   'Destination Office', escHtml(d.destination_office_name ?? '—'))}
                    ${d.current_file_location ? irow('map-pin', 'File Location', escHtml(d.current_file_location)) : ''}
                    ${d.current_file_holder   ? irow('user-cog','File Held By',  escHtml(d.current_file_holder)) : ''}
                </div>
            </div>

            {{-- ── Timeline & Remarks ── --}}
            <div>
                ${section('clock', 'Timeline & Remarks')}
                <div class="grid grid-cols-2 gap-3">
                    ${irow('calendar',      'Requested At',   escHtml(d.requested_at ?? '—'))}
                    ${d.approved_at ? irow('check-circle', 'Approved At', escHtml(d.approved_at) + (d.approved_by_name ? `<br><span class="text-[#450a0a]">by ${escHtml(d.approved_by_name)}</span>` : '')) : ''}
                    ${d.rejected_at ? irow('x-circle',     'Rejected At', escHtml(d.rejected_at) + (d.rejected_by_name ? `<br><span class="text-red-600">by ${escHtml(d.rejected_by_name)}</span>` : '')) : ''}
                </div>
                ${d.remarks ? `
                <div class="mt-3 flex items-start gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 shrink-0 mt-0.5">
                        <i data-lucide="message-square" class="h-3.5 w-3.5 text-gray-500"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Reason</p>
                        <p class="text-xs text-gray-700 mt-0.5 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">${escHtml(d.remarks)}</p>
                    </div>
                </div>` : ''}
                ${d.rejection_reason ? `
                <div class="mt-3 flex items-start gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-50 shrink-0 mt-0.5">
                        <i data-lucide="alert-circle" class="h-3.5 w-3.5 text-red-500"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold text-red-400 uppercase tracking-wide">Rejection Reason</p>
                        <p class="text-xs text-red-700 mt-0.5 bg-red-50 rounded-lg px-3 py-2 border border-red-100">${escHtml(d.rejection_reason)}</p>
                    </div>
                </div>` : ''}
            </div>

            {{-- ── Signatures ── --}}
            <div>
                ${section('pen-tool', 'Signatures')}
                <div class="grid grid-cols-2 gap-4">
                    {{-- Requester --}}
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 text-center space-y-2">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Requesting Officer</p>
                        ${d.requester_signature_url
                            ? `<div class="flex items-center justify-center h-14">
                                   <img src="${escHtml(d.requester_signature_url)}" alt="Signature" class="max-h-14 max-w-full object-contain">
                               </div>`
                            : `<div class="flex items-center justify-center h-14 text-gray-300">
                                   <i data-lucide="minus" class="h-5 w-5"></i>
                               </div>`}
                        <div class="border-t border-dashed border-gray-300 pt-2">
                            <p class="text-xs font-semibold text-gray-700">${escHtml(d.sending_officer ?? '—')}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Requesting Officer</p>
                        </div>
                    </div>
                    {{-- Approver --}}
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 text-center space-y-2 ${!d.approved_by_name ? 'opacity-50' : ''}">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Approving Officer</p>
                        ${d.approver_signature_url
                            ? `<div class="flex items-center justify-center h-14">
                                   <img src="${escHtml(d.approver_signature_url)}" alt="Signature" class="max-h-14 max-w-full object-contain">
                               </div>`
                            : `<div class="flex items-center justify-center h-14 text-gray-300">
                                   <i data-lucide="${d.approved_by_name ? 'minus' : 'lock'}" class="h-5 w-5"></i>
                               </div>`}
                        <div class="border-t border-dashed border-gray-300 pt-2">
                            <p class="text-xs font-semibold text-gray-700">${d.approved_by_name ? escHtml(d.approved_by_name) : '—'}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">${d.approved_by_name ? 'Approving Officer' : 'Pending Approval'}</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>`;

        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [body] });
    }

    // ── Approve ───────────────────────────────────────────────────────────────
    function resetApproveSignature() {
        $('#approve-sig-verified').value = '0';
        $('#approve-sig-method').value   = '';
        $('#approve-sig-otp-code').value  = '';
        $('#approve-sig-password').value  = '';
        $('#approve-sig-otp-wrapper').style.display  = 'none';
        $('#approve-sig-pwd-wrapper').style.display  = 'none';
        $('#approve-sig-img-wrap').classList.add('hidden');
        $('#approve-sig-img').src = '';
        $('#approve-sig-feedback').textContent = '';
        $('#approve-sig-feedback').className   = 'text-xs font-medium text-slate-500';
        const verifyBtn = $('#approve-sig-verify-btn');
        const verifyTxt = $('#approve-sig-verify-text');
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.classList.remove('bg-[#450a0a]', 'hover:bg-[#5c0c0c]');
            verifyBtn.classList.add('bg-[#450a0a]', 'hover:bg-[#5c0c0c]');
        }
        if (verifyTxt) verifyTxt.textContent = 'Verify';
        const submitBtn = $('#approve-submit-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    function openApproveModal(id, reqNo) {
        $('#approve-id').value           = id;
        $('#approve-req-no').textContent = reqNo;
        $('textarea', $('#form-approve')).value = '';
        resetApproveSignature();
        showModal('modal-approve');
        if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
    }

    // ── Wire approve signature controls ──────────────────────────────────────
    (function () {
        const sigMethod    = $('#approve-sig-method');
        const otpWrapper   = $('#approve-sig-otp-wrapper');
        const pwdWrapper   = $('#approve-sig-pwd-wrapper');
        const otpCode      = $('#approve-sig-otp-code');
        const pwdInput     = $('#approve-sig-password');
        const sendOtpBtn   = $('#approve-sig-send-otp');
        const verifyBtn    = $('#approve-sig-verify-btn');
        const verifyTxt    = $('#approve-sig-verify-text');
        const feedback     = $('#approve-sig-feedback');
        const sigImgWrap   = $('#approve-sig-img-wrap');
        const sigImg       = $('#approve-sig-img');
        const sigVerifiedH = $('#approve-sig-verified');
        const submitBtn    = $('#approve-submit-btn');

        function syncMethodUI() {
            if (!sigMethod) return;
            const m = sigMethod.value;
            if (otpWrapper) otpWrapper.style.display = (m === 'email' || m === 'sms') ? '' : 'none';
            if (pwdWrapper) pwdWrapper.style.display  = m === 'password' ? '' : 'none';
        }

        function onInputChange() {
            // Reset verification when inputs change
            if (sigVerifiedH) sigVerifiedH.value = '0';
            if (sigImgWrap)  sigImgWrap.classList.add('hidden');
            if (feedback)    { feedback.textContent = ''; feedback.className = 'text-xs font-medium text-slate-500'; }
            if (verifyTxt)   verifyTxt.textContent = 'Verify';
            if (verifyBtn)   {
                verifyBtn.disabled = false;
                verifyBtn.classList.remove('bg-[#450a0a]', 'hover:bg-[#5c0c0c]');
                verifyBtn.classList.add('bg-[#450a0a]', 'hover:bg-[#5c0c0c]');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        if (sigMethod) sigMethod.addEventListener('change', () => { syncMethodUI(); onInputChange(); });
        if (otpCode)   otpCode.addEventListener('input', onInputChange);
        if (pwdInput)  pwdInput.addEventListener('input', onInputChange);

        if (sendOtpBtn) {
            sendOtpBtn.addEventListener('click', async function () {
                const method = sigMethod ? sigMethod.value : '';
                if (!(method === 'email' || method === 'sms')) {
                    Swal.fire('Required', 'Select Email OTP or SMS OTP to request a code.', 'warning');
                    return;
                }
                sendOtpBtn.disabled = true;
                sendOtpBtn.textContent = 'Sending…';
                try {
                    const res  = await apiFetch('/digital-request/send-otp', { method: 'POST', body: JSON.stringify({ method }) });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'OTP Sent', text: data.message, timer: 2500, showConfirmButton: false });
                    } else {
                        Swal.fire('Error', data.message || 'Could not send OTP.', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Network error. Please try again.', 'error');
                } finally {
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.textContent = 'Send OTP';
                }
            });
        }

        if (verifyBtn) {
            verifyBtn.addEventListener('click', async function () {
                const method   = sigMethod ? sigMethod.value : '';
                const otp      = otpCode   ? otpCode.value.trim() : '';
                const pwd      = pwdInput  ? pwdInput.value       : '';

                if (!method) { Swal.fire('Required', 'Please select a verification method.', 'warning'); return; }
                if ((method === 'email' || method === 'sms') && !otp) {
                    Swal.fire('Required', 'Please enter the OTP code sent to you.', 'warning'); return;
                }
                if (method === 'password' && !pwd) {
                    Swal.fire('Required', 'Please enter your password to confirm.', 'warning'); return;
                }

                verifyBtn.disabled = true;
                if (verifyTxt) verifyTxt.textContent = 'Verifying…';
                if (feedback) { feedback.textContent = ''; feedback.className = 'text-xs font-medium text-slate-500'; }

                try {
                    const res  = await apiFetch('/digital-request/verify-signature', {
                        method: 'POST',
                        body: JSON.stringify({ method, verification_code: otp, password: pwd }),
                    });
                    const data = await res.json();

                    if (data.success) {
                        if (sigVerifiedH) sigVerifiedH.value = '1';
                        if (data.signature_url && sigImg) sigImg.src = data.signature_url;
                        if (sigImgWrap) sigImgWrap.classList.remove('hidden');
                        verifyBtn.classList.remove('bg-[#450a0a]', 'hover:bg-[#5c0c0c]');
                        verifyBtn.classList.add('bg-[#450a0a]', 'hover:bg-[#5c0c0c]');
                        if (verifyTxt) verifyTxt.textContent = 'Verified ✓';
                        if (feedback) { feedback.textContent = 'Signature verified.'; feedback.className = 'text-xs font-medium text-[#450a0a]'; }
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        }
                        if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
                    } else {
                        verifyBtn.disabled = false;
                        if (verifyTxt) verifyTxt.textContent = 'Retry Verify';
                        if (feedback) { feedback.textContent = data.message || 'Verification failed.'; feedback.className = 'text-xs font-medium text-red-600'; }
                    }
                } catch (e) {
                    verifyBtn.disabled = false;
                    if (verifyTxt) verifyTxt.textContent = 'Retry Verify';
                    if (feedback) { feedback.textContent = 'Network error. Please try again.'; feedback.className = 'text-xs font-medium text-red-600'; }
                }
            });
        }
    })();

    $('#form-approve').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id      = $('#approve-id').value;
        const remarks = $('textarea', this).value;

        // Guard: signature must be verified
        if ($('#approve-sig-verified').value !== '1') {
            Swal.fire('Signature Required', 'Please verify your digital signature before approving.', 'warning');
            return;
        }

        const submitBtn = $('#approve-submit-btn');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Approving…'; }

        const res  = await apiFetch(`/digital-request/approve/${id}`, { method: 'POST', body: JSON.stringify({ remarks }) });
        const json = await res.json();

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Confirm Approve';
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }

        hideModal('modal-approve');
        if (json.success) { toast('success', json.message); loadTable(currentPage); }
        else              { toast('error',   json.message); }
    });

    // ── Reject ────────────────────────────────────────────────────────────────
    function openRejectModal(id, reqNo) {
        $('#reject-id').value            = id;
        $('#reject-req-no').textContent  = reqNo;
        $('#reject-reason').value        = '';
        showModal('modal-reject');
    }

    $('#form-reject').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id     = $('#reject-id').value;
        const reason = $('#reject-reason').value.trim();

        if (!reason) { toast('warning', 'Please enter a rejection reason.'); return; }

        const res  = await apiFetch(`/digital-request/reject/${id}`, { method: 'POST', body: JSON.stringify({ rejection_reason: reason }) });
        const json = await res.json();

        hideModal('modal-reject');
        if (json.success) { toast('success', json.message); loadTable(currentPage); }
        else              { toast('error',   json.message); }
    });

    // ── File Availability Check ────────────────────────────────────────────────
    $('#btn-check-availability').addEventListener('click', async function () {
        const fileNo = $('#cr-file-no').value.trim();
        if (!fileNo) { toast('warning', 'Enter a file number first.'); return; }

        const msg = $('#cr-availability-msg');
        msg.className = 'mt-2 text-sm rounded-lg px-3 py-2 bg-gray-100 text-gray-600';
        msg.textContent = 'Checking…';
        msg.classList.remove('hidden');

        const res  = await apiFetch('/digital-request/check-availability', { method: 'POST', body: JSON.stringify({ file_no: fileNo }) });
        const json = await res.json();

        // Populate title
        if (json.file_title) $('#cr-file-title').value = json.file_title;

        if (!json.found) {
            msg.className = 'mt-2 text-sm rounded-lg px-3 py-2 bg-red-50 text-red-700';
            msg.textContent = json.message;
            return;
        }

        if (json.available) {
            msg.className = 'mt-2 text-sm rounded-lg px-3 py-2 bg-red-50 text-[#450a0a]';
            msg.textContent = '✓ File is available in the Registry.';
            $('#cr-is-redirected').value = '0';
            $('#cr-current-location-wrapper').classList.add('hidden');
        } else {
            msg.className = 'mt-2 text-sm rounded-lg px-3 py-2 bg-orange-50 text-orange-700';

            // Ask user whether to redirect or cancel
            const choice = await Swal.fire({
                icon: 'warning',
                title: 'File Not in Registry',
                text: json.message,
                showCancelButton: true,
                confirmButtonText: 'Send Request to Current Holder',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#9333ea',
            });

            if (choice.isConfirmed) {
                msg.textContent = `⚠ File currently at: ${json.current_office}. Request will be redirected.`;
                $('#cr-is-redirected').value              = '1';
                $('#cr-current-file-location').value      = json.current_office;
                $('#cr-current-file-holder').value        = json.receiving_officer;
                $('#cr-current-location').value           = `${json.current_office} — ${json.receiving_officer ?? ''}`;
                $('#cr-current-location-wrapper').classList.remove('hidden');
            } else {
                msg.textContent = 'Request cancelled. Please adjust the file number or wait until the file is returned.';
                msg.className = 'mt-2 text-sm rounded-lg px-3 py-2 bg-gray-50 text-gray-600';
                $('#cr-is-redirected').value = '0';
            }
        }
    });

    // ── Destination office selection → capture name ────────────────────────────
    $('#cr-destination-office').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        $('#cr-destination-office-name').value = opt.dataset.name ?? opt.text;
    });

    // Pre-sort dept offices to top when modal opens
    async function sortDeptOfficesFirst() {
        try {
            const res  = await fetch('/digital-request/offices-by-department');
            const list = await res.json();
            deptOfficeIds = list.map(o => String(o.id));

            const sel = $('#cr-destination-office');
            const options = [...sel.options].filter(o => o.value !== '');
            const deptOpts  = options.filter(o => deptOfficeIds.includes(o.value));
            const otherOpts = options.filter(o => !deptOfficeIds.includes(o.value));

            // Clear and rebuild
            while (sel.options.length > 1) sel.remove(1);

            if (deptOpts.length) {
                const grp = document.createElement('optgroup');
                grp.label = 'Your Department';
                deptOpts.forEach(o => grp.appendChild(o));
                sel.appendChild(grp);
            }
            if (otherOpts.length) {
                const grp = document.createElement('optgroup');
                grp.label = 'Other Offices';
                otherOpts.forEach(o => grp.appendChild(o));
                sel.appendChild(grp);
            }
        } catch (e) { /* non-fatal */ }
    }

    // ── Request Type Toggle ────────────────────────────────────────────────────
    (function () {
        const physBtn     = document.getElementById('cr-type-physical');
        const digBtn      = document.getElementById('cr-type-digital');
        const typeInput   = document.getElementById('cr-request-type');
        const physFields  = document.getElementById('cr-physical-fields');
        const digBanner   = document.getElementById('cr-digital-banner');
        const submitLabel = document.getElementById('btn-submit-label');

        function setType(type) {
            typeInput.value = type;
            const isDigital = type === 'Digital';

            // Physical button style
            physBtn.className = 'cr-type-btn flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all ' +
                (!isDigital
                    ? 'border-[#450a0a] bg-[#450a0a] text-white shadow-sm'
                    : 'border-gray-200 bg-white text-gray-500 hover:border-red-300 hover:text-[#450a0a]');

            // Digital button style
            digBtn.className = 'cr-type-btn flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all ' +
                (isDigital
                    ? 'border-blue-500 bg-blue-600 text-white shadow-sm'
                    : 'border-gray-200 bg-white text-gray-500 hover:border-blue-300 hover:text-blue-600');

            // Show/hide physical-only fields
            if (physFields) physFields.classList.toggle('hidden', isDigital);
            if (digBanner)  digBanner.classList.toggle('hidden', !isDigital);
            if (submitLabel) submitLabel.textContent = isDigital ? 'Request Digital Access' : 'Submit Request';
        }

        if (physBtn) physBtn.addEventListener('click', () => setType('Physical'));
        if (digBtn)  digBtn.addEventListener('click',  () => setType('Digital'));
    })();

    // ── Create form submit ─────────────────────────────────────────────────────
    $('#form-create-request').addEventListener('submit', async function (e) {
        e.preventDefault();
        const form      = this;
        const btn       = $('#btn-submit-request');
        const labelEl   = document.getElementById('btn-submit-label');
        const reqType   = document.getElementById('cr-request-type')?.value || 'Physical';
        const isDigital = reqType === 'Digital';
        const resetLabel = () => { btn.disabled = false; if (labelEl) labelEl.textContent = isDigital ? 'Request Digital Access' : 'Submit Request'; };

        btn.disabled = true;
        if (labelEl) labelEl.textContent = 'Submitting…';

        const body = {
            file_no:                 $('#cr-file-no').value.trim(),
            file_title:              $('#cr-file-title').value.trim(),
            request_type:            reqType,
            destination_office_id:   isDigital ? null : ($('#cr-destination-office')?.value || null),
            destination_office_name: isDigital ? null : ($('#cr-destination-office-name')?.value || null),
            receiving_officer:       isDigital ? null : ($('#cr-receiving-officer')?.value || null),
            remarks:                 $('#cr-remarks').value.trim(),
            is_redirected:           !isDigital && ($('#cr-is-redirected')?.value === '1'),
            current_file_location:   $('#cr-current-file-location')?.value || null,
            current_file_holder:     $('#cr-current-file-holder')?.value || null,
            force:                   0,
        };

        if (!body.file_no) {
            toast('warning', 'File number is required.');
            resetLabel();
            return;
        }
        if (!isDigital && !body.destination_office_id) {
            toast('warning', 'Destination office is required for physical file requests.');
            resetLabel();
            return;
        }

        let res  = await apiFetch('/digital-request/store', { method: 'POST', body: JSON.stringify(body) });
        let json = await res.json();

        // Duplicate guard: file already requested & sent to SCB. Warn, but allow proceeding.
        if (!json.success && json.duplicate) {
            const ex = json.existing || {};
            const ok = confirm(
                'This file has already been requested.\n\n' +
                'Requested by: ' + (ex.requester_name || '—') +
                (ex.request_no ? '\nRequest: ' + ex.request_no : '') +
                '\nStatus: ' + (ex.status || '') + (ex.requested_at ? ' (' + ex.requested_at + ')' : '') +
                '\n\nIt has already been sent to SCB and is awaiting a response.\n\nDo you want to request it anyway?'
            );
            if (!ok) { resetLabel(); return; }
            body.force = 1;
            res  = await apiFetch('/digital-request/store', { method: 'POST', body: JSON.stringify(body) });
            json = await res.json();
        }

        resetLabel();

        if (json.success) {
            hideModal('modal-create');
            this.reset();
            // Reset toggle back to Physical
            document.getElementById('cr-request-type').value = 'Physical';
            document.getElementById('cr-physical-fields')?.classList.remove('hidden');
            document.getElementById('cr-digital-banner')?.classList.add('hidden');
            if (labelEl) labelEl.textContent = 'Submit Request';
            // Reset type buttons
            const pb = document.getElementById('cr-type-physical');
            const db = document.getElementById('cr-type-digital');
            if (pb) pb.className = 'cr-type-btn flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all border-[#450a0a] bg-[#450a0a] text-white shadow-sm';
            if (db) db.className = 'cr-type-btn flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border-2 text-sm font-semibold transition-all border-gray-200 bg-white text-gray-500 hover:border-blue-300 hover:text-blue-600';

            $('#cr-availability-msg').classList.add('hidden');
            $('#cr-current-location-wrapper').classList.add('hidden');
            $('#cr-file-title').value = '';
            toast('success', json.message);
            loadTable(1);
        } else {
            toast('error', json.message ?? 'Failed to submit request.');
        }
    });

    // ── Modal close buttons ────────────────────────────────────────────────────
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function () {
            ['modal-create', 'modal-view', 'modal-approve', 'modal-reject'].forEach(hideModal);
        });
    });
    // Close on backdrop click
    ['modal-create', 'modal-view', 'modal-approve', 'modal-reject'].forEach(id => {
        document.getElementById(id).addEventListener('click', function (e) {
            if (e.target === this) hideModal(id);
        });
    });

    // ── Filters ───────────────────────────────────────────────────────────────
    $('#dr-status-filter').addEventListener('change', () => loadTable(1));
    $('#dr-date-from').addEventListener('change', () => loadTable(1));
    $('#dr-date-to').addEventListener('change', () => loadTable(1));
    $('#dr-search').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadTable(1), 350);
    });
    $('#dr-prev').addEventListener('click', () => { if (currentPage > 1) loadTable(currentPage - 1); });
    $('#dr-next').addEventListener('click', () => { if (currentPage < totalPages) loadTable(currentPage + 1); });

    // ── Live Pending-Request Polling ─────────────────────────────────────────
    let lastKnownPending   = {{ $stats['pending'] }};
    let pollingActive      = true;

    function updatePendingUI(count) {
        // Update stats card number
        const countEl = document.getElementById('dr-pending-count');
        if (countEl) countEl.textContent = count.toLocaleString();

        // Pulsing dot on the pending card
        const pulse = document.getElementById('dr-pending-pulse');
        const card  = document.getElementById('dr-pending-card');
        if (pulse) {
            pulse.classList.toggle('hidden', count === 0);
        }
        if (card) {
            if (count > 0) {
                card.classList.add('border-yellow-300', 'ring-2', 'ring-yellow-200');
            } else {
                card.classList.remove('border-yellow-300', 'ring-2', 'ring-yellow-200');
            }
        }

        // Badge next to table header
        const badge = document.getElementById('dr-pending-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    function showNewRequestBanner(newCount, prev) {
        const diff    = newCount - prev;
        const banner  = document.getElementById('dr-new-request-banner');
        const msgEl   = document.getElementById('dr-new-request-msg');
        if (!banner) return;
        msgEl.textContent = `${diff} new file request${diff > 1 ? 's' : ''} received — ${newCount} pending total`;
        banner.classList.remove('hidden');

        // Also show a SweetAlert toast popup
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            iconColor: '#d97706',
            title: diff === 1 ? 'New File Request' : `${diff} New File Requests`,
            html: `<span style="font-size:0.8rem">${diff} pending request${diff > 1 ? 's' : ''} require${diff === 1 ? 's' : ''} your attention.</span>`,
            showConfirmButton: true,
            confirmButtonText: 'Review Now',
            confirmButtonColor: '#7c3aed',
            showDenyButton: true,
            denyButtonText: 'Dismiss',
            denyButtonColor: '#9ca3af',
            timer: 10000,
            timerProgressBar: true,
            background: '#fffbeb',
            customClass: { popup: 'border border-yellow-300 rounded-xl shadow-xl' },
        }).then(result => {
            if (result.isConfirmed) {
                // Show only pending
                const statusFilter = document.getElementById('dr-status-filter');
                if (statusFilter) statusFilter.value = 'Pending';
                loadTable(1);
            }
        });

        // Play a subtle notification sound if supported
        try {
            const ctx   = new (window.AudioContext || window.webkitAudioContext)();
            const osc   = ctx.createOscillator();
            const gain  = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        } catch (_) { /* audio not supported — silent */ }
    }

    async function pollPendingCount() {
        if (!pollingActive) return;
        try {
            const res  = await fetch('/digital-request/pending-count', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            });
            if (!res.ok) return;
            const json = await res.json();
            const newCount = json.count ?? 0;

            if (newCount > lastKnownPending) {
                // New requests came in while the page was open
                showNewRequestBanner(newCount, lastKnownPending);
                loadTable(currentPage);  // refresh table automatically
            }

            lastKnownPending = newCount;
            updatePendingUI(newCount);
        } catch (_) { /* network error — retry next tick */ }
    }

    // Banner "View Pending" button
    const bannerViewBtn = document.getElementById('dr-banner-view-btn');
    if (bannerViewBtn) {
        bannerViewBtn.addEventListener('click', function () {
            document.getElementById('dr-new-request-banner').classList.add('hidden');
            const statusFilter = document.getElementById('dr-status-filter');
            if (statusFilter) statusFilter.value = 'Pending';
            loadTable(1);
        });
    }

    // Poll every 30 seconds
    setInterval(pollPendingCount, 30000);

    // ── Boot ──────────────────────────────────────────────────────────────────
    lucide.createIcons();
    loadTable(1);
    // Run once immediately to set up badge state
    updatePendingUI(lastKnownPending);
})();
</script>
@endsection

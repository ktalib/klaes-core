{{-- Registry Checkout Approval Tab Content --}}
@php
    $registryLabel = match (strtolower($module ?? '')) {
        'sltr' => 'SLTR Registry',
        'st'   => 'ST Registry',
        'dciv' => 'DCIV Registry',
        'cadastral' => 'Cadastral Registry',
        default => 'KANGIS Registry',
    };
    $accentColor = match (strtolower($module ?? '')) {
        'sltr' => 'lime',
        'st'   => 'blue',
        'dciv' => 'emerald',
        'cadastral' => 'stone',
        default => 'red',
    };
@endphp
<div class="p-6 space-y-6">

    {{-- Section header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <i data-lucide="shield-check" class="h-5 w-5 text-{{ $accentColor }}-600"></i>
                <span class="text-sm font-semibold uppercase tracking-wide text-{{ $accentColor }}-600">{{ $registryLabel }}</span>
            </div>
            <h4 class="mt-1 text-xl font-semibold text-gray-900">Registry Checkout Approvals</h4>
            <p class="text-sm text-gray-600">Checkout records are created automatically when files are logged out of the {{ $registryLabel }}.</p>
        </div>
        <div class="flex items-center gap-2">
            <button id="co-refresh-btn"
                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>Refresh
            </button>
        </div>
    </div>

    {{-- Stats strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                <i data-lucide="clock" class="h-5 w-5 text-amber-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Pending</p>
                <p id="co-stat-pending" class="text-2xl font-bold text-amber-600">0</p>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                <i data-lucide="check-circle" class="h-5 w-5 text-green-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Approved</p>
                <p id="co-stat-approved" class="text-2xl font-bold text-green-600">0</p>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <i data-lucide="x-circle" class="h-5 w-5 text-red-600"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Rejected</p>
                <p id="co-stat-rejected" class="text-2xl font-bold text-red-600">0</p>
            </div>
        </div>
    </div>

    {{-- Filter & search bar --}}
    <div class="bg-white rounded-lg border border-gray-200 p-4 flex flex-wrap gap-3 items-center">
        <div class="flex gap-2">
            <button class="co-filter-btn active px-3 py-1.5 text-xs font-medium rounded-md bg-amber-100 text-amber-800 ring-2 ring-amber-400 ring-offset-1 transition-all" data-filter="all">All</button>
            <button class="co-filter-btn px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-700 hover:bg-amber-50 transition-all" data-filter="pending">Pending</button>
            <button class="co-filter-btn px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-700 hover:bg-green-50 transition-all" data-filter="approved">Approved</button>
            <button class="co-filter-btn px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-700 hover:bg-red-50 transition-all" data-filter="rejected">Rejected</button>
        </div>
        <div class="flex-1 min-w-[200px]">
            <div class="relative">
                <input type="text" id="co-search" placeholder="Search by file number, title, requester…"
                    class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                <i data-lucide="search" class="absolute left-3 top-2.5 h-4 w-4 text-gray-400"></i>
            </div>
        </div>
    </div>

    {{-- Results table --}}
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                <i data-lucide="list" class="h-4 w-4"></i>
                Checkout Requests
            </h3>
            <span id="co-result-count" class="text-xs text-gray-500">0 records</span>
        </div>

        <div id="co-loading" class="py-12 text-center text-gray-400 hidden">
            <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-amber-200 border-t-amber-500"></div>
            <p class="text-sm">Loading…</p>
        </div>

        <div id="co-empty" class="py-12 text-center text-gray-400 hidden">
            <i data-lucide="inbox" class="h-12 w-12 mx-auto mb-3 opacity-40"></i>
            <p class="text-sm">No checkout requests found.</p>
        </div>

        <div id="co-table-wrap" class="overflow-x-auto hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Log ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Office</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Handler</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Log In</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Log Out</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Notes</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody id="co-tbody" class="divide-y divide-gray-100">
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     REJECT MODAL
════════════════════════════════════════════════════════════════ --}}
<div id="co-reject-modal" class="fixed inset-0 bg-black bg-opacity-50 z-[200] hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Reject Checkout Request</h2>
            <button id="co-reject-close" class="text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="co-reject-id">
            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">Rejection Reason <span class="text-red-500">*</span></label>
                <textarea id="co-reject-reason" rows="4" placeholder="Provide a clear reason for rejection…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"></textarea>
            </div>
            <div id="co-reject-error" class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200">
            <button id="co-reject-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
            <button id="co-reject-confirm"
                class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 shadow-sm">
                <i data-lucide="x-circle" class="h-4 w-4 mr-2"></i>Reject
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     ACKNOWLEDGEMENT SHEET PRINT MODAL
════════════════════════════════════════════════════════════════ --}}
<div id="co-ack-modal" class="fixed inset-0 bg-black bg-opacity-60 z-[200] hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 shrink-0">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Acknowledgement Sheet</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ $registryLabel }} File Checkout</p>
            </div>
            <div class="flex items-center gap-2">
                <button id="co-ack-print"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                    <i data-lucide="printer" class="h-4 w-4 mr-2"></i>Print
                </button>
                <button id="co-ack-close" class="text-gray-400 hover:text-gray-600 ml-1">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
        </div>
        <div id="co-ack-content" class="overflow-y-auto flex-1 p-6">
            {{-- dynamically populated --}}
        </div>
    </div>
</div>

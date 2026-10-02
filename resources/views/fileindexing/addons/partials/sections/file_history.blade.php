<div class="form-section" id="file-history-section">
    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div class="flex items-center gap-2">
            <i data-lucide="history" class="h-5 w-5 text-blue-600"></i>
            <h3 class="form-section-title" style="margin-bottom: 0;">File History</h3>
        </div>
        <button
            type="button"
            id="file-history-timeline-btn"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-blue-200 bg-blue-50 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:opacity-60 disabled:cursor-not-allowed"
            disabled
        >
            <i data-lucide="list-tree" class="h-4 w-4"></i>
            View Timeline
        </button>
    </div>

    <div class="space-y-4" id="file-history-messages">
        <div id="file-history-loading" class="hidden text-sm text-blue-600">
            <span class="inline-flex items-center gap-2">
                <span class="inline-flex h-4 w-4 items-center justify-center">
                    <span class="loading-spinner"></span>
                </span>
                <span>Loading file history...</span>
            </span>
        </div>
        <div id="file-history-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-md px-3 py-2"></div>
        <div id="file-history-empty" class="text-sm text-gray-500">
            Select a file number to load historical transactions.
        </div>
    </div>

    {{--
        Root of Title / Original Holder / Current Holder — three distinct concepts
        (client spec 2026-08-20 §12), resolved server-side by TitleHolderResolver
        and delivered on the /api/file-history response. Read-only: this states
        what the transaction chain says, and does not touch the Original/Current
        Holder INPUTS elsewhere on the form, which stay the indexer's own entry.
    --}}
    <div class="hidden mb-5 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3" id="file-history-title-holders">
        <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-500">Title</span>
            <span class="text-[10px] font-semibold text-gray-400" id="file-history-application-type"></span>
        </div>
        {{--
            Rows are built from the resolver's `lines` list, because WHICH lines
            print depends on the Application Type: a Direct Allocation shows only
            Original Holder and Current Holder (spec table row iii).
        --}}
        <dl class="space-y-1.5" id="file-history-title-lines"></dl>
    </div>

    <div class="space-y-6" id="file-history-list" aria-live="polite" aria-busy="false"></div>
</div>

<div class="fixed inset-0 hidden items-center justify-center bg-black/60 backdrop-blur-sm z-[70]" id="file-history-timeline-modal" aria-hidden="true">
    <div class="dialog max-w-4xl w-full mx-4 shadow-2xl bg-white rounded-xl overflow-hidden">
        <!-- Header -->
        <div class="dialog-header bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
            <div class="dialog-title text-white font-semibold text-lg flex items-center gap-3">
                <div class="p-2 bg-white/20 rounded-lg">
                    <i data-lucide="list-tree" class="h-5 w-5"></i>
                </div>
                File History Timeline
            </div>
            <button type="button" id="file-history-timeline-close" class="text-white/80 hover:text-white transition-colors" style="background: none; border: none; cursor: pointer;">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>
        
        <!-- Description -->
        <div class="px-6 py-3 bg-blue-50 border-b border-blue-100">
            <p class="text-sm text-blue-700">
                <i data-lucide="info" class="h-4 w-4 inline mr-2"></i>
                A chronological summary of all recorded transactions for this property.
            </p>
        </div>
        
        <!-- Content -->
        <div class="dialog-content max-h-[70vh] overflow-y-auto p-6" id="file-history-timeline-content">
            <div class="flex items-center justify-center py-12">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <i data-lucide="clock" class="h-8 w-8 text-gray-400"></i>
                    </div>
                    <p class="text-sm text-gray-500 mb-2">No timeline data available</p>
                    <p class="text-xs text-gray-400">Select a file and load its history to view the timeline.</p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="flex justify-between items-center gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="text-xs text-gray-500">
                <i data-lucide="shield-check" class="h-4 w-4 inline mr-1"></i>
                All data shown is read-only for verification purposes
            </div>
            <button type="button" id="file-history-timeline-dismiss" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                <i data-lucide="check" class="h-4 w-4"></i>
                Close
            </button>
        </div>
    </div>
</div>

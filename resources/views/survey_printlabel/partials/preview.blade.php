<div id="preview-tab" class="tab-content mt-6">
    <div class="bg-white rounded-lg border">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold">Preview and Print</h3>
            <p class="text-sm text-gray-600">
                Review the QR code labels below, confirm the details, then send them to the printer when you’re ready.
            </p>
        </div>
        <div class="p-6 space-y-6">
            <div>
                <p id="previewDescription" class="text-sm text-gray-600">
                    Select up to 30 files to generate a 30-in-1 sheet. You’ll see a live preview here before printing.
                </p>
            </div>

            <div id="previewArea" class="preview-shell">
                <h4 class="preview-heading">Label Preview</h4>
                <div id="previewContent" class="preview-canvas">
                    <div class="preview-empty">
                        <div class="preview-empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="16" rx="2" ry="2"></rect>
                                <path d="M3 10h18"></path>
                                <path d="M8 6v4"></path>
                            </svg>
                        </div>
                        <p class="text-sm">No files selected yet.</p>
                        <p class="text-xs text-slate-500">Pick some files and configure the settings to see the labels here.</p>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <h5 class="text-sm font-semibold text-slate-700">Shelf/Rack Usage</h5>
                    <span id="rackStatsHeadline" class="text-xs text-slate-500"></span>
                </div>
                <div id="rackStatsContent" class="mt-3 space-y-3 text-sm text-slate-600">
                    <p class="text-sm text-slate-500">Rack usage statistics will appear once labels are generated.</p>
                </div>
            </div>

            <div class="print-summary-card">
                <h5 class="print-summary-title">Print Summary</h5>
                <div id="printSummary" class="print-summary-body">
                    <p class="preview-note">Once you select files, we’ll summarise the number of labels, copies, and template details here.</p>
                </div>
            </div>
        </div>
        <div class="p-6 border-t flex justify-between">
            <button
                id="backToSettingsBtn"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Back to Settings
            </button>
            <button
                id="finalPrintBtn"
                class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
            >
                Print Labels
            </button>
        </div>
    </div>

    <div id="printSection" class="hidden"></div>
</div>

<div id="blind-scan-modal" class="dialog-backdrop hidden" aria-hidden="true" data-blind-scan-modal>
  <div class="dialog-content max-w-4xl animate-fade-in">
    <div class="p-6 border-b border-gray-200 flex items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-3">
          <div class="p-2 bg-indigo-100 rounded-lg">
            <i data-lucide="radar" class="h-5 w-5 text-indigo-600"></i>
          </div>
          <div>
            <h2 class="text-xl font-bold text-gray-900">Blind Scan Documents</h2>
            <p class="text-sm text-gray-600" id="blind-scan-modal-file-number">Searching for folder...</p>
          </div> 
        </div>
        <p class="text-xs text-gray-500 mt-3">
          The system will move selected files into Scan Uploads and register them automatically.
        </p>
      </div>
      <button type="button" class="btn btn-ghost" data-blind-scan-close>
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="px-6 py-5 space-y-4">
      <div id="blind-scan-loading" class="flex items-center gap-3 text-sm text-gray-600">
        <div class="loading-spinner"></div>
        <span>Scanning blind scan storage...</span>
      </div>

      <div id="blind-scan-error" class="hidden p-4 bg-red-50 border border-red-200 rounded-md text-sm text-red-700"></div>

      <div id="blind-scan-empty" class="hidden p-5 border border-dashed border-gray-300 rounded-lg text-center text-sm text-gray-600">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
          <i data-lucide="folder-open" class="h-6 w-6 text-gray-500"></i>
        </div>
        <p class="font-semibold text-gray-900">No blind scan documents found.</p>
        <p class="mt-1">Ask the scanning team to confirm the folder exists in EDMS/BLIND_SCAN.</p>
      </div>

      <div id="blind-scan-summary" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
          <p class="text-xs uppercase text-indigo-600 font-semibold">Total Files</p>
          <p class="text-2xl font-bold text-indigo-900" id="blind-scan-total">0</p>
        </div>
        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <p class="text-xs uppercase text-blue-600 font-semibold">Paper Sizes</p>
          <div class="mt-2 space-y-1 text-sm text-blue-900" id="blind-scan-paper-sizes"></div>
        </div>
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
          <p class="text-xs uppercase text-emerald-600 font-semibold">Document Types</p>
          <div class="mt-2 space-y-1 text-sm text-emerald-900" id="blind-scan-document-types"></div>
        </div>
      </div>

      <div id="blind-scan-table-wrapper" class="hidden border border-gray-200 rounded-lg overflow-hidden">
        <div class="max-h-72 overflow-y-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide text-xs text-gray-600">File Name</th>
                <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide text-xs text-gray-600">Relative Path</th>
                <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide text-xs text-gray-600">Paper</th>
                <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide text-xs text-gray-600">Type</th>
                <th class="px-4 py-2 text-right font-semibold uppercase tracking-wide text-xs text-gray-600">Size</th>
              </tr>
            </thead>
            <tbody id="blind-scan-table-body" class="bg-white divide-y divide-gray-100"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <p class="text-xs text-gray-500" id="blind-scan-footnote">
        Confirm to move documents into Scan Uploads. This action is atomic and logged.
      </p>
      <div class="flex gap-3 justify-end">
        <button type="button" class="btn btn-outline" data-blind-scan-close-secondary>Cancel</button>
        <button type="button" class="btn btn-primary" id="blind-scan-transfer-btn" disabled>
          <i data-lucide="arrow-right" class="h-4 w-4"></i>
          Next
        </button>
      </div>
    </div>
  </div>
</div>

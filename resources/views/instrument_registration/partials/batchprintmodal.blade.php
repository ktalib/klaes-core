<div id="batchPrintSessionModal" class="modal">
    <div class="modal-content modal-center-y lg:max-w-[720px]" style="width: 90%; max-width: 720px;">
        <div class="modal-header flex justify-between items-center p-4 border-b">
            <h2 class="text-lg font-semibold modal-title">Print Batch RDS and CoR</h2>
            <button type="button" onclick="closeBatchPrintSessionModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-6 space-y-5">
            <div>
                <p class="text-sm text-gray-600">
                    Select an active registration batch session, then run the RDS and CoR steps separately. CoR actions unlock after the full RDS batch PDF has been downloaded once.
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    The session is removed from this list after the CoR batch PDF is downloaded.
                </p>
            </div>

            <div>
                <label for="batchPrintSessionSelect" class="block text-sm font-medium text-gray-700 mb-2">Batch Session</label>
                <select id="batchPrintSessionSelect" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Loading sessions...</option>
                </select>
                <div id="batchPrintSessionEmptyState" class="hidden mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    No active batch registration sessions are available right now.
                </div>
            </div>

            <div id="batchPrintSessionSummary" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-900 hidden">
                <div class="font-semibold mb-3">Selected Session</div>
                <div id="batchPrintSessionSummaryText" class="text-slate-700"></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                    <div class="rounded-lg border border-blue-200 bg-white px-3 py-3">
                        <div class="text-xs uppercase tracking-wide text-blue-700 font-semibold">RDS Generated</div>
                        <div id="batchPrintRdsGeneratedStat" class="text-lg font-semibold text-blue-900">0 / 0</div>
                    </div>
                    <div class="rounded-lg border border-indigo-200 bg-white px-3 py-3">
                        <div class="text-xs uppercase tracking-wide text-indigo-700 font-semibold">RDS Printed</div>
                        <div id="batchPrintRdsPrintedStat" class="text-lg font-semibold text-indigo-900">0 / 0</div>
                    </div>
                    <div class="rounded-lg border border-amber-200 bg-white px-3 py-3">
                        <div class="text-xs uppercase tracking-wide text-amber-700 font-semibold">CoR Generated</div>
                        <div id="batchPrintCorGeneratedStat" class="text-lg font-semibold text-amber-900">0 / 0</div>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-white px-3 py-3">
                        <div class="text-xs uppercase tracking-wide text-emerald-700 font-semibold">Next Step</div>
                        <div id="batchPrintNextStepStat" class="text-sm font-semibold text-emerald-900 mt-1">Select a session</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                <button type="button" id="generateBatchRdsBtn" onclick="runBatchPrintAction('generate_rds')"
                    class="bg-blue-600 text-white px-4 py-3 rounded-md disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Generate RDS
                </button>
                <button type="button" id="downloadBatchRdsBtn" onclick="runBatchPrintAction('download_rds')"
                    class="bg-indigo-600 text-white px-4 py-3 rounded-md disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Print All RDS
                </button>
                <button type="button" id="generateBatchCorBtn" onclick="runBatchPrintAction('generate_cor')"
                    class="bg-amber-500 text-white px-4 py-3 rounded-md disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Generate CoR
                </button>
                <button type="button" id="downloadBatchCorBtn" onclick="runBatchPrintAction('download_cor')"
                    class="bg-emerald-600 text-white px-4 py-3 rounded-md disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Print All CoR
                </button>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t">
                <button type="button" onclick="closeBatchPrintSessionModal()" class="px-4 py-2 border rounded-md">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
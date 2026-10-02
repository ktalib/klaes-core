<div class="form-section">
    <div class="flex items-center justify-between mb-3">
        <h3 class="form-section-title">Automatic Batch Assignment</h3>
        <button type="button" id="refresh-assignment-btn" class="inline-flex items-center gap-1 px-3 py-2 text-xs font-medium text-white bg-blue-500 rounded-md hover:bg-blue-600 transition">
            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
            <span>Refresh</span>
        </button>
    </div>
    <p class="text-sm text-gray-600 mb-3">We will fetch the latest batch details so the record lands in the correct shelf without manual steps.</p>
    <div id="auto-assignment-status" class="text-sm leading-relaxed bg-blue-50 border border-blue-100 rounded-md p-3 text-blue-900">
        Checking current batch availability...
    </div>

    <input type="hidden" id="batch-no" name="batch_no">
    <input type="hidden" id="shelf-location" name="shelf_location">
    <input type="hidden" id="shelf_label_id" name="shelf_label_id">
    <input type="hidden" id="batch_id" name="batch_id">
</div>

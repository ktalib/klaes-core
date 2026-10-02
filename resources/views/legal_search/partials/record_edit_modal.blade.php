{{--
  Edit Record modal — markup only.

  Shared shell for the on-premise Legal Search screen and the PHS "Correct
  Search Result" page. The body is rendered dynamically by
  legal_search.partials.record_edit_modal_js, which both pages also include, so
  the two screens present the same fields for the same table.

  Element ids are load-bearing: record_edit_modal_js binds to edit-record-modal,
  edit-modal-body, edit-modal-close, edit-modal-cancel and edit-modal-save.
--}}
<div id="edit-record-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
  <div class="flex items-center justify-center min-h-screen px-4">
    <div class="fixed inset-0 bg-black/50 transition-opacity" id="edit-modal-backdrop"></div>
    <div class="relative bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] overflow-y-auto z-10">
      <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
        <h3 class="text-lg font-semibold">Edit Record</h3>
        <button id="edit-modal-close" class="text-gray-400 hover:text-gray-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
      <div class="px-6 py-5 bg-gray-50/40" id="edit-modal-body">
        <!-- Fields rendered dynamically -->
      </div>
      <div class="sticky bottom-0 bg-gray-50 border-t px-6 py-3 flex justify-end gap-2">
        <button id="edit-modal-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
        <button id="edit-modal-save" class="px-4 py-2 text-sm font-medium text-white bg-black rounded-md hover:bg-black/90">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<div id="ackModal" class="fixed inset-0 z-[1100] hidden bg-black/50 flex items-center justify-center">
  <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b">
      <h3 class="text-lg font-semibold">Title Document Status (submitted)</h3>
      <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeAckModal()">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6">
      <form id="ackDocsForm">
        <input type="hidden" id="ackApplicationId" name="application_id" />
        <div class="space-y-3">
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_ro" class="h-4 w-4" />
            <span>(a) Right of Occupancy</span>
          </label>
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_cofo" class="h-4 w-4" />
            <span>(b) Certificate of Occupancy</span>
          </label>
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_deed_assignment" class="h-4 w-4" />
            <span>(c) Deed of Assignment</span>
          </label>
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_deed_sublease" class="h-4 w-4" />
            <span>(d) Deed of Sublease</span>
          </label>
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_deed_mortgage" class="h-4 w-4" />
            <span>(e) Deed of Mortgage</span>
          </label>
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_deed_gift" class="h-4 w-4" />
            <span>(f) Deed of Gift</span>
          </label>
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_poa" class="h-4 w-4" />
            <span>(g) Power of Attorney</span>
          </label>
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_devolution" class="h-4 w-4" />
            <span>(h) Devolution Order</span>
          </label>
          <label class="flex items-center gap-3">
            <input type="checkbox" name="doc_letter_admin" class="h-4 w-4" />
            <span>(i) Letter of Administration</span>
          </label>
          <div class="space-y-2">
            <label class="flex items-center gap-3">
              <input type="checkbox" name="doc_other" id="ackDocOtherChk" class="h-4 w-4" onchange="toggleAckOtherInput(this.checked)" />
              <span>(j) Others</span>
            </label>
            <input type="text" id="ackDocOtherText" name="doc_other_text" class="w-full border rounded px-3 py-2 hidden" placeholder="Specify other document" />
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3 border-t pt-4">
          <button type="button" class="px-4 py-2 bg-gray-500 text-white rounded" onclick="closeAckModal()">Cancel</button>
          <button type="submit" id="ackDocsSubmitBtn" class="px-4 py-2 bg-blue-600 text-white rounded">Save & Generate</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  function openAckModal(applicationId) {
    document.getElementById('ackApplicationId').value = applicationId;
    document.getElementById('ackModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  function closeAckModal() {
    document.getElementById('ackModal').classList.add('hidden');
    document.body.style.overflow = '';
    const form = document.getElementById('ackDocsForm');
    form.reset();
    document.getElementById('ackDocOtherText').classList.add('hidden');
  }
  function toggleAckOtherInput(show) {
    const el = document.getElementById('ackDocOtherText');
    if (show) el.classList.remove('hidden'); else el.classList.add('hidden');
  }
  document.getElementById('ackDocsForm').addEventListener('submit', function(e){
    e.preventDefault();
    const id = document.getElementById('ackApplicationId').value;
    const submitBtn = document.getElementById('ackDocsSubmitBtn');
    const original = submitBtn.textContent;
    submitBtn.textContent = 'Saving...';
    submitBtn.disabled = true;

    const formData = new FormData(this);
    const params = new URLSearchParams();
    for (const [k,v] of formData.entries()) { params.append(k, v); }

    fetch(`/recertification/${id}/acknowledgement/submit`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: params
    }).then(r=>r.json()).then(data=>{
      if (data.success) {
        closeAckModal();
        if (typeof showToast === 'function') showToast('Acknowledgement saved', 'success');
        // Update buttons in current dropdown
        const genBtn = document.querySelector(`button[data-action="generate-ack"][data-app-id="${id}"]`);
        const viewBtn = document.querySelector(`button[data-action="view-ack"][data-app-id="${id}"]`);
        if (genBtn) { genBtn.disabled = true; genBtn.classList.add('opacity-50','cursor-not-allowed'); }
        if (viewBtn) { viewBtn.disabled = false; viewBtn.classList.remove('opacity-50','cursor-not-allowed'); }
        // Reload table data
        if (typeof loadApplicationsData === 'function') loadApplicationsData();
        // Optionally open acknowledgement_1
        if (data.ack1_url) window.open(data.ack1_url, '_blank');
      } else {
        if (typeof Swal !== 'undefined') Swal.fire({icon:'error', title:'Error', text: data.message || 'Failed to save'});
      }
    }).catch(err=>{
      console.error(err);
      if (typeof Swal !== 'undefined') Swal.fire({icon:'error', title:'Error', text: 'Failed to save'});
    }).finally(()=>{
      submitBtn.textContent = original;
      submitBtn.disabled = false;
    });
  });
  // Make available globally
  window.openAckModal = openAckModal;
  window.closeAckModal = closeAckModal;
</script>

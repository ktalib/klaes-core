{{-- Sticky footer bar for bulk reconciliation (the batch-actions pattern, copied for REV-M).
     Rows opt in with an .rv-batch-check checkbox carrying data-recon. A button opens only
     when EVERY selected payment can take that action; the action itself is rvMark()
     on the Reconciliation page. --}}
<div id="rv-batch-bar" class="hidden fixed bottom-5 left-1/2 transform -translate-x-1/2 z-50 bg-white border border-emerald-200 shadow-2xl rounded-2xl px-4 py-3 flex flex-wrap items-center gap-3">
  <span class="text-sm font-bold text-emerald-800"><span id="rv-batch-count">0</span> selected</span>
  <button type="button" id="rv-batch-reconcile" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">
    <i data-lucide="badge-check" class="w-3.5 h-3.5"></i> Reconcile
  </button>
  <button type="button" id="rv-batch-dispute" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">
    <i data-lucide="alert-octagon" class="w-3.5 h-3.5"></i> Dispute
  </button>
  <button type="button" id="rv-batch-reopen" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50">
    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Set back to unreconciled
  </button>
  <button type="button" id="rv-batch-clear" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Clear</button>
</div>

<script>
(function () {
  const bar = document.getElementById('rv-batch-bar');
  const countEl = document.getElementById('rv-batch-count');
  const checks = () => Array.from(document.querySelectorAll('.rv-batch-check'));
  const pickable = () => checks().filter((c) => !c.disabled);
  const selected = () => checks().filter((c) => c.checked);

  checks().forEach((c) => { if (c.disabled) { c.title = 'Only successful payments are reconciled'; c.classList.add('opacity-40', 'cursor-not-allowed'); } });

  const buttons = {
    reconcile: [document.getElementById('rv-batch-reconcile'), (c) => c.dataset.recon !== 'reconciled', 'Every selected payment must be unreconciled or disputed'],
    dispute: [document.getElementById('rv-batch-dispute'), (c) => c.dataset.recon !== 'disputed', 'Every selected payment must not be disputed already'],
    reopen: [document.getElementById('rv-batch-reopen'), (c) => c.dataset.recon !== 'unreconciled', 'Every selected payment must be reconciled or disputed'],
  };

  function refresh() {
    const picked = selected();
    countEl.textContent = picked.length;
    bar.classList.toggle('hidden', picked.length === 0);
    Object.values(buttons).forEach(([btn, ok, why]) => {
      const allowed = picked.length > 0 && picked.every(ok);
      btn.disabled = !allowed;
      btn.title = allowed ? '' : why;
      btn.classList.toggle('opacity-50', !allowed);
      btn.classList.toggle('cursor-not-allowed', !allowed);
    });
    document.querySelectorAll('.rv-batch-all').forEach((all) => {
      const total = pickable().length;
      all.checked = total > 0 && picked.length === total;
      all.indeterminate = picked.length > 0 && picked.length < total;
      all.disabled = total === 0;
    });
  }

  document.addEventListener('change', (e) => {
    if (e.target.classList.contains('rv-batch-all')) pickable().forEach((c) => { c.checked = e.target.checked; });
    if (e.target.classList.contains('rv-batch-check') || e.target.classList.contains('rv-batch-all')) refresh();
  });
  document.getElementById('rv-batch-clear').addEventListener('click', () => { checks().forEach((c) => { c.checked = false; }); refresh(); });
  Object.entries(buttons).forEach(([action, [btn]]) => {
    btn.addEventListener('click', () => { if (!btn.disabled) window.rvMark(action, selected().map((c) => Number(c.value))); });
  });

  refresh();
})();
</script>

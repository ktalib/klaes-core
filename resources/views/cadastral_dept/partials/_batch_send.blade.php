{{-- Batch send on a Cadastral desk (transcript, 2026-10-16: the head "can do batch, or single
     by going to the action menu"). The sticky footer bar of the parcel-update listings
     (deeds/parcel_update/partials/batch_decisions), copied for this desk rather than shared.

     Rows opt in with a checkbox:
       <input type="checkbox" class="cd-batch-check" value="{case id}" data-file="{file no}"
              data-url="{move url}" data-actions='[{"o":outcome,"l":label,"r":needs reason}]'>
     carrying the row's OWN menu actions (HeadApproval-shaped), so a batch never does what the
     row's menu would not. A row with no action is disabled. The bar offers only the actions
     EVERY selected file has, and posts each file to the existing move endpoint one at a time,
     so every server rule still applies; the result lists done and refused files. --}}
<div id="cd-batch-bar" class="hidden fixed bottom-5 left-1/2 transform -translate-x-1/2 z-50 bg-white border border-blue-200 shadow-2xl rounded-2xl px-4 py-3 flex flex-wrap items-center gap-3">
  <span class="text-sm font-bold text-blue-800"><span id="cd-batch-count">0</span> selected</span>
  <button type="button" id="cd-batch-send" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
    <i data-lucide="send" class="w-3.5 h-3.5"></i> Batch Send
  </button>
  <button type="button" id="cd-batch-clear" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Clear</button>
</div>

<script>
(function () {
  const CSRF = '{{ csrf_token() }}';
  const bar = document.getElementById('cd-batch-bar');
  const countEl = document.getElementById('cd-batch-count');
  const sendBtn = document.getElementById('cd-batch-send');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

  const checks = () => Array.from(document.querySelectorAll('.cd-batch-check'));
  const selected = () => checks().filter((c) => c.checked);
  const actionsOf = (c) => { try { return JSON.parse(c.dataset.actions || '[]'); } catch (e) { return []; } };
  const key = (a) => a.o + '|' + a.l;

  // Nothing this user can do on the row: it cannot be picked.
  checks().forEach((c) => {
    if (!actionsOf(c).length) {
      c.disabled = true;
      c.checked = false;
      c.title = 'Nothing you can send on this file now';
      c.classList.add('opacity-40', 'cursor-not-allowed');
    }
  });
  const pickable = () => checks().filter((c) => !c.disabled);

  // The actions every selected file offers (same answer, same label): the files are at the same step.
  function common() {
    const picked = selected();
    if (!picked.length) return [];
    let shared = actionsOf(picked[0]);
    picked.slice(1).forEach((c) => {
      const keys = actionsOf(c).map(key);
      shared = shared.filter((a) => keys.includes(key(a)));
    });
    return shared;
  }

  function refresh() {
    const n = selected().length;
    countEl.textContent = n;
    bar.classList.toggle('hidden', n === 0);
    const ok = n > 0 && common().length > 0;
    sendBtn.disabled = !ok;
    sendBtn.title = ok ? '' : 'The selected files are not at the same step: pick files with the same action';
    sendBtn.classList.toggle('opacity-50', !ok);
    sendBtn.classList.toggle('cursor-not-allowed', !ok);
    document.querySelectorAll('.cd-batch-all').forEach((all) => {
      const total = pickable().length;
      all.checked = total > 0 && n === total;
      all.indeterminate = n > 0 && n < total;
      all.disabled = total === 0;
    });
  }

  document.addEventListener('change', (e) => {
    if (e.target.classList.contains('cd-batch-all')) {
      pickable().forEach((c) => { c.checked = e.target.checked; });
    }
    if (e.target.classList.contains('cd-batch-check') || e.target.classList.contains('cd-batch-all')) refresh();
  });
  document.getElementById('cd-batch-clear').addEventListener('click', () => {
    checks().forEach((c) => { c.checked = false; });
    refresh();
  });

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify(body || {}),
    });
    let data = {};
    try { data = await res.json(); } catch (e) {}
    if (!res.ok || data.success === false) throw new Error(data.message || ('HTTP ' + res.status));
    return data;
  }

  sendBtn.addEventListener('click', async () => {
    const shared = common();
    if (!shared.length) return;
    const picked = selected();
    const options = shared.map((a, i) => '<label class="flex items-start gap-2 text-sm py-1"><input type="radio" name="cd-batch-pick" value="' + i + '"' + (shared.length === 1 ? ' checked' : '') + '> <span' + (a.r ? ' class="text-red-700"' : '') + '>' + esc(a.l) + '</span></label>').join('');
    const form = await Swal.fire({
      title: 'Batch Send',
      html: '<div class="text-left space-y-3">'
        + '<p class="text-xs text-gray-500">' + picked.length + ' file(s), sent one at a time. A file the system refuses is listed at the end.</p>'
        + '<div>' + options + '</div>'
        + '<label class="block text-xs font-bold text-gray-600">Remarks <span id="cd-batch-req" class="text-red-600 hidden">(required)</span></label>'
        + '<textarea id="cd-batch-remarks" class="swal2-textarea !m-0 !w-full" placeholder="Applied to every selected file"></textarea>'
        + '</div>',
      showCancelButton: true,
      confirmButtonText: 'Send ' + picked.length + ' file(s)',
      didOpen: () => {
        const sync = () => {
          const p = document.querySelector('input[name="cd-batch-pick"]:checked');
          document.getElementById('cd-batch-req').classList.toggle('hidden', !(p && shared[p.value].r));
        };
        document.querySelectorAll('input[name="cd-batch-pick"]').forEach((r) => r.addEventListener('change', sync));
        sync();
      },
      preConfirm: () => {
        const p = document.querySelector('input[name="cd-batch-pick"]:checked');
        const remarks = document.getElementById('cd-batch-remarks').value.trim();
        if (!p) { Swal.showValidationMessage('Choose what to do'); return false; }
        if (shared[p.value].r && !remarks) { Swal.showValidationMessage('Give the reason'); return false; }
        return { action: shared[p.value], remarks: remarks };
      },
    });
    if (!form.isConfirmed) return;

    const done = [], failed = [];
    Swal.fire({ title: form.value.action.l, html: 'Working… <b id="cd-batch-progress">0</b> of ' + picked.length, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    for (const c of picked) {
      try {
        await post(c.dataset.url, { outcome: form.value.action.o, remarks: form.value.remarks });
        done.push(c.dataset.file);
      } catch (err) {
        failed.push(c.dataset.file + ' — ' + err.message);
      }
      const p = document.getElementById('cd-batch-progress');
      if (p) p.textContent = done.length + failed.length;
    }
    const list = (items) => '<ul class="text-left text-xs mt-1 max-h-40 overflow-y-auto">' + items.map((i) => '<li>' + esc(i) + '</li>').join('') + '</ul>';
    let html = '<p class="text-sm"><b>' + done.length + '</b> of ' + picked.length + ' file(s) sent.</p>';
    if (failed.length) html += '<p class="text-xs font-bold text-red-600 mt-3">Refused:</p>' + list(failed);
    await Swal.fire({ title: form.value.action.l, html: html, icon: failed.length ? 'warning' : 'success' });
    window.location.reload();
  });

  refresh();
})();
</script>

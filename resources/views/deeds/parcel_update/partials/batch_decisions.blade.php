{{-- Batch KAMMA / Physical Planning and batch Approve / Reject for a parcel-update
     listing (Change Request 08102026). Rows opt in with the batch_check partial.

     Each selected file is sent to the module's EXISTING per-record endpoint, one at a
     time, so every server-side rule still applies to every file; a file its own menu
     would refuse is skipped up front and named in the result.

     Expects $batchRoutes = ['knupda' => url, 'approve' => url, 'reject' => url], each
     with __ID__ in place of the record id, and $batchLabel (e.g. "Change of Purpose"). --}}
<div id="pu-batch-bar" class="hidden fixed bottom-5 left-1/2 transform -translate-x-1/2 z-50 bg-white border border-violet-200 shadow-2xl rounded-2xl px-4 py-3 flex flex-wrap items-center gap-3">
    <span class="text-sm font-bold text-violet-800"><span id="pu-batch-count">0</span> selected</span>
    <button type="button" id="pu-batch-knupda" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
        <i data-lucide="ruler" class="w-3.5 h-3.5"></i> Batch KAMMA / Physical Planning
    </button>
    <button type="button" id="pu-batch-decision" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">
        <i data-lucide="scale" class="w-3.5 h-3.5"></i> Batch Approve / Reject
    </button>
    <button type="button" id="pu-batch-clear" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Clear</button>
</div>

<script>
(function () {
    const ROUTES = @json($batchRoutes);
    const LABEL = @json($batchLabel ?? 'Parcel Update');
    const CSRF = '{{ csrf_token() }}';
    const bar = document.getElementById('pu-batch-bar');
    const countEl = document.getElementById('pu-batch-count');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    const checks = () => Array.from(document.querySelectorAll('.pu-batch-check'));
    const selected = () => checks().filter((c) => c.checked);

    // A flag missing on the checkbox is read from the row's batch_flags marker (Duplex
    // computes its rights inside the actions cell, after the checkbox is drawn).
    function flag(check, name) {
        const key = 'can' + name.charAt(0).toUpperCase() + name.slice(1);
        if (check.dataset[key] !== undefined) return check.dataset[key] === '1';
        const marker = document.querySelector('[data-pu-batch-flags="' + check.value + '"]');
        return !!(marker && marker.dataset[key] === '1');
    }

    const knupdaBtn = document.getElementById('pu-batch-knupda');
    const decisionBtn = document.getElementById('pu-batch-decision');
    const canDecide = (c) => flag(c, 'approve') || flag(c, 'reject');

    // A row with nothing left to do (already approved, commissioned, rejected) cannot be
    // picked at all, so a selection only ever holds files still in the workflow.
    checks().forEach((c) => {
        if (!flag(c, 'knupda') && !canDecide(c)) {
            c.disabled = true;
            c.checked = false;
            c.title = 'Already decided — nothing to do at this step';
            c.classList.add('opacity-40', 'cursor-not-allowed');
        }
    });
    const pickable = () => checks().filter((c) => !c.disabled);

    // The steps run in order, so a button opens only when EVERY selected file is at its
    // step: Approve / Reject stays shut while any of them still waits for KAMMA.
    function gate(btn, ok, why) {
        btn.disabled = !ok;
        btn.title = ok ? '' : why;
        btn.classList.toggle('opacity-50', !ok);
        btn.classList.toggle('cursor-not-allowed', !ok);
    }

    function refresh() {
        const picked = selected();
        const n = picked.length;
        countEl.textContent = n;
        bar.classList.toggle('hidden', n === 0);
        gate(knupdaBtn, n > 0 && picked.every((c) => flag(c, 'knupda')),
            'Every selected file must be waiting for KAMMA / Physical Planning');
        gate(decisionBtn, n > 0 && picked.every(canDecide),
            'Every selected file must have passed KAMMA and be at the decision step');
        document.querySelectorAll('.pu-batch-all').forEach((all) => {
            const total = pickable().length;
            all.checked = total > 0 && n === total;
            all.indeterminate = n > 0 && n < total;
            all.disabled = total === 0;
        });
    }

    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('pu-batch-all')) {
            pickable().forEach((c) => { c.checked = e.target.checked; });
        }
        if (e.target.classList.contains('pu-batch-check') || e.target.classList.contains('pu-batch-all')) refresh();
    });
    document.getElementById('pu-batch-clear').addEventListener('click', () => {
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

    // Run one action over the eligible files, one at a time, and report every outcome.
    async function run(title, eligible, skipped, call) {
        const done = [], failed = [];
        Swal.fire({ title: title, html: 'Working… <b id="pu-batch-progress">0</b> of ' + eligible.length, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        for (const check of eligible) {
            try {
                await call(check);
                done.push(check.dataset.file);
            } catch (err) {
                failed.push(check.dataset.file + ' — ' + err.message);
            }
            const p = document.getElementById('pu-batch-progress');
            if (p) p.textContent = done.length + failed.length;
        }
        const list = (items) => '<ul class="text-left text-xs mt-1 max-h-40 overflow-y-auto">' + items.map((i) => '<li>' + esc(i) + '</li>').join('') + '</ul>';
        let html = '<p class="text-sm"><b>' + done.length + '</b> of ' + (eligible.length + skipped.length) + ' file(s) updated.</p>';
        if (skipped.length) html += '<p class="text-xs font-bold text-amber-700 mt-3">Skipped — not at this step:</p>' + list(skipped);
        if (failed.length) html += '<p class="text-xs font-bold text-red-600 mt-3">Refused:</p>' + list(failed);
        await Swal.fire({ title: title, html: html, icon: failed.length ? 'warning' : 'success' });
        window.location.reload();
    }

    function split(name) {
        const eligible = [], skipped = [];
        selected().forEach((c) => (flag(c, name) ? eligible : skipped).push(c));
        return { eligible, skipped: skipped.map((c) => c.dataset.file) };
    }

    document.getElementById('pu-batch-knupda').addEventListener('click', async () => {
        const { eligible, skipped } = split('knupda');
        if (!eligible.length) {
            Swal.fire('Nothing to update', 'None of the selected files is waiting for KAMMA / Physical Planning.', 'info');
            return;
        }
        const form = await Swal.fire({
            title: 'Batch KAMMA / Physical Planning',
            html: '<div class="text-left space-y-3">'
                + '<p class="text-xs text-slate-500">' + eligible.length + ' ' + esc(LABEL) + ' file(s). Each keeps its own land value and fee.</p>'
                + '<label class="block text-xs font-bold text-slate-600">Decision</label>'
                + '<select id="pu-batch-knupda-status" class="swal2-input !m-0 !w-full"><option value="Approved">Approved</option><option value="Declined">Declined</option><option value="Pending">Pending</option></select>'
                + '<label class="block text-xs font-bold text-slate-600">Remarks</label>'
                + '<textarea id="pu-batch-knupda-remarks" class="swal2-textarea !m-0 !w-full" placeholder="Applied to every selected file"></textarea>'
                + '</div>',
            showCancelButton: true,
            confirmButtonText: 'Apply to ' + eligible.length + ' file(s)',
            preConfirm: () => ({
                status: document.getElementById('pu-batch-knupda-status').value,
                remarks: document.getElementById('pu-batch-knupda-remarks').value,
            }),
        });
        if (!form.isConfirmed) return;
        await run('Batch KAMMA / Physical Planning', eligible, skipped, (check) => post(ROUTES.knupda.replace('__ID__', check.value), {
            land_value: check.dataset.landValue || null,
            knupda_fee: check.dataset.knupdaFee || null,
            knupda_status: form.value.status,
            knupda_remarks: form.value.remarks,
        }));
    });

    document.getElementById('pu-batch-decision').addEventListener('click', async () => {
        const choice = await Swal.fire({
            title: 'Batch Approve / Reject',
            text: selected().length + ' file(s) selected. Files not at the decision step are skipped.',
            input: 'radio',
            inputOptions: { approve: 'Approve', reject: 'Reject' },
            inputValidator: (v) => (!v ? 'Choose Approve or Reject' : null),
            showCancelButton: true,
        });
        if (!choice.isConfirmed) return;

        const approving = choice.value === 'approve';
        const { eligible, skipped } = split(approving ? 'approve' : 'reject');
        if (!eligible.length) {
            Swal.fire('Nothing to ' + choice.value, 'None of the selected files can be ' + (approving ? 'approved' : 'rejected') + ' yet.', 'info');
            return;
        }

        let reason = '';
        if (!approving) {
            const r = await Swal.fire({
                title: 'Reason for rejection',
                input: 'textarea',
                inputPlaceholder: 'Applied to every selected file',
                inputValidator: (v) => (!v || !v.trim() ? 'A reason is required' : null),
                showCancelButton: true,
                confirmButtonText: 'Reject ' + eligible.length + ' file(s)',
            });
            if (!r.isConfirmed) return;
            reason = r.value.trim();
        } else {
            const ok = await Swal.fire({
                title: 'Approve ' + eligible.length + ' file(s)?',
                html: skipped.length ? '<p class="text-xs text-amber-700">' + skipped.length + ' selected file(s) are not ready and will be skipped.</p>' : '',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Approve',
            });
            if (!ok.isConfirmed) return;
        }

        await run(approving ? 'Batch Approve' : 'Batch Reject', eligible, skipped, (check) => approving
            ? post(ROUTES.approve.replace('__ID__', check.value))
            : post(ROUTES.reject.replace('__ID__', check.value), { reason: reason }));
    });

    refresh();
})();
</script>

/**
 * InstrumentMasterActions - Master Delete and Move Registration for the
 * Instrument Capture and Instrument Registration tables.
 *
 * Needs SweetAlert2 and master-delete.js (MasterDelete.confirm). The server
 * re-checks the Supper Admin role, the typed confirmation and every rule; this
 * file only asks.
 *
 *   InstrumentMasterActions.masterDelete('deed_reg_12')
 *   InstrumentMasterActions.move('instr_capture_40')
 *   InstrumentMasterActions.move('40')            // Instrument Capture row id
 */
window.InstrumentMasterActions = (function () {
    'use strict';

    const base = () => (window.InstrumentMasterBase || '/instrument-master');

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || window.KlaesConfig?.csrfToken || '';
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function getJson(url) {
        const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const body = await r.json().catch(() => ({ success: false, message: 'Server returned an unreadable response.' }));
        if (!body.success) throw new Error(body.message || 'Request failed.');
        return body;
    }

    const TABLE_LABELS = {
        instrument_capture: 'Captured instrument',
        deed_registrations: 'Registration (deed_registrations)',
        rds_tracking: 'RDS record',
        CofO_staging: 'CofO record (CofO_staging mirror)',
        pra: 'Property Record Assistant entry',
        oss_applications: 'One-Stop Shop application',
        oss_acknowledgements: 'One-Stop Shop acknowledgement',
        oss_verifications: 'One-Stop Shop verification',
        oss_change_of_ownership: 'Change of ownership application',
        laas_transfer_candidates: 'LAAS transfer candidate',
        sltr_cofo_tdp: 'SLTR CofO TDP',
        mls_file_no: 'MLS commissioning file number'
    };

    const listOf = counts => Object.keys(counts || {}).map(t => `${esc(TABLE_LABELS[t] || t)} (${counts[t]})`);

    async function masterDelete(ref, options = {}) {
        let preview;
        try {
            Swal.fire({ title: 'Checking…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            preview = await getJson(`${base()}/${encodeURIComponent(ref)}/delete-preview`);
            Swal.close();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Master Delete unavailable', text: err.message });
            return;
        }

        const blockers = listOf(preview.blockers);
        if (blockers.length) {
            Swal.fire({
                icon: 'error',
                title: 'Still linked',
                html: `<div style="text-align:left;font-size:13px"><p><b>${esc(preview.label)}</b> is still referenced by:</p>`
                    + `<ul style="margin:6px 0 0 18px;list-style:disc">${blockers.map(b => `<li>${b}</li>`).join('')}</ul>`
                    + '<p style="margin-top:8px">Unlink those records first, then run the Master Delete again.</p></div>'
            });
            return;
        }

        MasterDelete.confirm({
            url: `${base()}/${encodeURIComponent(ref)}/master-destroy`,
            reference: preview.reference,
            title: 'Master Delete Instrument',
            lead: `This permanently deletes <b>${esc(preview.label)}</b>.`,
            targets: listOf(preview.counts),
            keeps: 'Its registration number becomes free again and is offered in the missing-number list for the next capture.',
            onDone: options.onDone
        });
    }

    function targetLabel(t) {
        if (!t.configured) return `${t.type} — no vault configured`;
        if (t.same_vault) return `${t.type} — same register, keeps current number`;
        return `${t.type} — next ${t.next}${t.blocked ? ' (paused)' : ''}`;
    }

    async function move(ref, options = {}) {
        let data;
        try {
            Swal.fire({ title: 'Loading registers…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            data = await getJson(`${base()}/${encodeURIComponent(ref)}/move-options`);
            Swal.close();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Move unavailable', text: err.message });
            return;
        }

        const reg = data.registration;
        const targets = data.targets || [];
        const usable = t => t.configured && (t.same_vault || !t.blocked);

        const step = await Swal.fire({
            title: 'Move Registration',
            width: 620,
            html: `
                <div style="text-align:left;font-size:13px">
                    <p style="margin-bottom:10px">
                        <b>${esc(reg.fileno)}</b> is registered as <b>${esc(reg.instrument_type)}</b>
                        <span style="font-family:monospace;background:#eff6ff;padding:1px 6px;border-radius:4px">${esc(reg.registration_number)}</span>.
                    </p>
                    <label style="font-weight:600;display:block;margin-bottom:4px">Move to instrument</label>
                    <select id="imaTarget" class="swal2-select" style="width:100%;margin:0 0 10px;font-size:13px">
                        <option value="">Select instrument…</option>
                        ${targets.map((t, i) => `<option value="${i}" ${usable(t) ? '' : 'disabled'}>${esc(targetLabel(t))}</option>`).join('')}
                    </select>
                    <p id="imaNote" style="color:#374151;font-size:13px;margin:0"></p>
                    ${(reg.rds_exists || reg.cor_exists) ? `<p style="color:#b91c1c;font-size:12px;margin-top:10px;font-weight:600">
                        The ${[reg.rds_exists ? 'RDS' : '', reg.cor_exists ? 'CoR' : ''].filter(Boolean).join(' and ')} already generated carry the old type and number.
                        They are voided by the move and must be generated and printed again.</p>` : ''}
                    <p style="color:#6b7280;font-size:12px;margin-top:10px">
                        Only related instruments (${esc(reg.family)}) are offered.
                    </p>
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'Move registration',
            confirmButtonColor: '#2563eb',
            focusCancel: true,
            didOpen: () => {
                const targetSel = document.getElementById('imaTarget');
                const note = document.getElementById('imaNote');

                targetSel.addEventListener('change', () => {
                    const t = targets[targetSel.value];
                    note.innerHTML = !t ? ''
                        : t.same_vault
                            ? `Keeps <b>${esc(reg.registration_number)}</b> (same register); only the type changes.`
                            : `Will be registered as <b>${esc(t.next)}</b>, the next number in the ${esc(t.type)} register. `
                              + `<b>${esc(reg.registration_number)}</b> becomes a missing number in the ${esc(reg.instrument_type)} register, free for the next capture.`;
                });
            },
            preConfirm: () => {
                const t = targets[document.getElementById('imaTarget').value];
                if (!t) { Swal.showValidationMessage('Choose the instrument to move to.'); return false; }
                return { target_type: t.type };
            }
        });

        if (!step.isConfirmed) return;

        Swal.fire({ title: 'Moving…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const r = await fetch(`${base()}/${encodeURIComponent(ref)}/move`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(step.value)
            });
            const result = await r.json().catch(() => ({ success: false, message: 'Server returned an unreadable response.' }));
            if (!result.success) throw new Error(result.message || 'Move failed.');

            await Swal.fire({ icon: 'success', title: 'Registration moved', text: result.message });
            if (typeof options.onDone === 'function') options.onDone(result);
            else window.location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Move failed', text: err.message });
        }
    }

    return { masterDelete, move };
})();

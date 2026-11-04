{{--
    Draft mode for the Commission New File Number card (MLS FC).

    The card saves itself to mls_commission_drafts (MlsCommissionDraftController):
    every few seconds while it is open and has changed, on Save Draft, when it is
    closed or cleared, and when the page is left. My Drafts lists the officer's open
    drafts; Resume opens a fresh card and puts the draft back on it.

    What a draft holds: the Alpine state of fileNumberGenerator() (read from the
    component definition, the way resetForm() does, so a field added later is kept
    without being listed here), the few inputs that are not bound to it, and the
    source-OP context the OP flow keeps on window.

    What it does not hold, by design: the passport (a file; attached again), the
    serial / tracking ID / reclaimed-serial pick (read afresh, nothing is reserved
    for a draft), a serial Override, and a new OP captured but not yet saved.

    Any Generate success calls mlsfDraftCommitted() (hooks in mls_js), which closes
    the draft. Loaded on the MLS FC page only; mls_js guards every hook.
--}}
<script>
(function () {
    'use strict';

    const DRAFTS_URL = @json(route('mls-fileno.drafts.index'));
    const AUTOSAVE_MS = 5000;
    const DEFAULT_STATUS = 'Draft mode: autosave starts once you fill something in';

    // Alpine properties never kept in a draft: the page's own lookup lists, transient
    // widget state, the passport, and values recomputed on resume.
    const SKIP = new Set([
        'allAllPrefixes', 'landUses', 'relatedFileTypes', 'hideBatchMode',
        'tpSearchResults', 'tpSearchLoading', 'tpSearchOpen', 'tpSearchTimer', 'tpFocusIndex',
        'passport', 'passportPreview', 'prefillPurpose', 'preview', 'serialRangePreview',
        // Today's year and series: a draft resumed in January commissions in January.
        'year',
        'useReclaimedSerial', 'reclaimedSerial', 'reclaimedLoadedFor', 'reclaimedLoading',
        'reclaimedNotice', 'reclaimedNoticeKind',
        // Rebuilt from the duplex plan on resume.
        'duplexStages', 'duplexStageIndex',
    ]);

    // Any of these holding a value makes the card worth keeping. Picking a file type or
    // land use alone does not: that is a card someone just opened.
    const CONTENT_KEYS = [
        'fileName', 'plotNo', 'houseNo', 'streetName', 'district', 'tpNo', 'phone_no', 'address',
        'rep_phone_no', 'rep_address', 'existingFileNo', 'relatedFileNo', 'oldFileNo', 'allocationId',
        'subdivisionAppId', 'mergerAppId', 'separationAppId', 'extensionAppId', 'copAppId',
        'duplexRecordId', 'sourcePropId', 'sourceInstrumentCaptureId', 'sourcePraId',
    ];

    const APPLICATION_TYPE_LABELS = {
        new: 'Direct Allocation',
        conversion: 'Conversion',
        change_of_purpose: 'Change of Purpose',
    };

    const st = {
        key: null,          // the draft this card writes to; created on its first save
        lastJson: null,     // what was last queued, so an unchanged card is not re-sent
        chain: Promise.resolve(),   // writes go out one at a time, in order
        restoring: false,
        // After a Generate the card still shows what was generated until it is reset;
        // nothing saves until it has been reset (or a fresh card is opened).
        committed: false,
        drafts: [],
    };

    let stateKeysCache = null;

    // ── helpers ─────────────────────────────────────────────────────────────────
    function component() {
        const el = document.querySelector('#generateModal [x-data^="fileNumberGenerator"]');
        return el && el._x_dataStack ? el._x_dataStack[0] : null;
    }

    function modalOpen() {
        const m = document.getElementById('generateModal');
        return !!m && !m.classList.contains('hidden');
    }

    function csrf() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function newKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'mlsf-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function clone(v) {
        return v && typeof v === 'object' ? JSON.parse(JSON.stringify(v)) : v;
    }

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function setStatus(text, tone) {
        const el = document.getElementById('mlsfDraftStatusText');
        if (!el) return;
        el.textContent = text;
        const wrap = document.getElementById('mlsfDraftStatus');
        if (wrap) {
            wrap.classList.remove('text-amber-700', 'text-green-700', 'text-red-600');
            wrap.classList.add(tone === 'ok' ? 'text-green-700' : (tone === 'error' ? 'text-red-600' : 'text-amber-700'));
        }
    }

    function clockTime() {
        const d = new Date();
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    // The data properties of the component definition (not getters, not methods).
    function stateKeys() {
        if (stateKeysCache) return stateKeysCache;
        if (typeof fileNumberGenerator !== 'function') return [];
        const fresh = fileNumberGenerator();
        stateKeysCache = Object.entries(Object.getOwnPropertyDescriptors(fresh))
            .filter(([key, desc]) => 'value' in desc && typeof desc.value !== 'function')
            .map(([key]) => key)
            .filter(key => !SKIP.has(key) && !key.startsWith('_location'));
        return stateKeysCache;
    }

    // ── snapshot ────────────────────────────────────────────────────────────────
    function snapshot() {
        const c = component();
        if (!c) return null;

        const state = {};
        stateKeys().forEach(key => {
            const value = c[key];
            if (value === undefined || typeof value === 'function') return;
            try { state[key] = clone(value); } catch (e) { /* not serialisable: skip */ }
        });

        const val = id => (document.getElementById(id)?.value || '').trim();
        const purposeMatch = (c.purposes || []).find(p => String(p.id) === String(c.purpose));

        let allocationLabel = '';
        const allocSel = document.getElementById('allocationSelect');
        if (allocSel && allocSel.selectedIndex >= 0 && allocSel.value) {
            allocationLabel = allocSel.options[allocSel.selectedIndex]?.text || '';
        }

        return {
            v: 1,
            state,
            dom: {
                email: val('generateEmail'),
                commissioned_by: val('commissionedBy'),
            },
            extra: {
                purpose_name: purposeMatch ? purposeMatch.name : '',
                allocation_label: allocationLabel,
                last_commission_source_op: window.lastCommissionSourceOp ? clone(window.lastCommissionSourceOp) : null,
                pending_existing_op_pra_context: window.pendingExistingOpPraContext ? clone(window.pendingExistingOpPraContext) : null,
                had_pending_new_op: !!window.pendingNewOpFormData,
                had_passport: !!c.passport,
                had_override: typeof isOverrideMode !== 'undefined' && !!isOverrideMode,
                had_reclaimed_serial: !!c.useReclaimedSerial,
            },
        };
    }

    function worthSaving(snap) {
        if (!snap) return false;
        const s = snap.state || {};
        if (CONTENT_KEYS.some(k => String(s[k] ?? '').trim() !== '')) return true;
        if ((snap.dom.email || '') !== '') return true;
        return Array.isArray(s.locationEntries)
            && s.locationEntries.some(e => e && (String(e.plotNo || '').trim() || String(e.file_name || '').trim()));
    }

    function summary(snap) {
        const s = snap.state || {};
        const first = s.batchMode && Array.isArray(s.locationEntries) ? (s.locationEntries[0] || {}) : {};

        let fileType = '';
        const typeSel = document.getElementById('fileTypeWorkflow');
        if (typeSel && typeSel.value && typeSel.selectedIndex >= 0) {
            fileType = typeSel.options[typeSel.selectedIndex].text.trim();
        }
        if (!fileType) fileType = APPLICATION_TYPE_LABELS[s.applicationType] || '';
        if (s.batchMode) fileType = (fileType ? fileType + ' · ' : '') + 'Batch of ' + (s.batchQuantity || 0);

        const cut = (v, n) => String(v || '').trim().slice(0, n);
        return {
            file_title: cut(s.fileName || first.file_name, 255),
            file_type: cut(fileType, 60),
            land_use: cut(s.prefix || s.landUse, 30),
            plot_no: cut(s.plotNo || first.plotNo, 100),
            district: cut(s.district || first.district, 150),
            lga: cut(s.lga || first.lga, 150),
        };
    }

    // ── save ────────────────────────────────────────────────────────────────────
    // The card is read and its draft key fixed synchronously, then the write is queued
    // behind any write still in flight. A caller (open, Clear Form, resume) may reset the
    // card right after calling this, and the card it had must still be what is saved.
    function save(opts) {
        opts = opts || {};
        if (st.restoring) return st.chain;

        const snap = snapshot();
        if (!snap) return st.chain;

        if (st.committed) {
            if (!worthSaving(snap)) { st.committed = false; return st.chain; }   // card was reset
            if (!opts.manual) return st.chain;
            st.committed = false;                                               // asked for: a new draft
            st.key = null;
        }

        if (!worthSaving(snap)) {
            if (opts.manual) {
                Swal.fire({ icon: 'info', title: 'Nothing to save yet', text: 'Fill in the file title, plot or another detail first.', confirmButtonColor: '#d97706' });
            }
            return st.chain;
        }

        const json = JSON.stringify(snap);
        if (!opts.manual && json === st.lastJson) return st.chain;

        if (!st.key) st.key = newKey();
        const key = st.key;
        const body = Object.assign({ draft_key: key, payload: json }, summary(snap));
        st.lastJson = json;

        st.chain = st.chain.then(() => send(key, json, body, opts));
        return st.chain;
    }

    async function send(key, json, body, opts) {
        setStatus('Saving draft…');

        try {
            const res = await fetch(DRAFTS_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));

            if (res.status === 409) {
                // Generated (or discarded) meanwhile: this card no longer writes to it.
                if (st.key === key) {
                    st.key = null;
                    st.lastJson = null;
                    st.committed = data.status === 'submitted';
                }
                setStatus(data.message || 'This draft is closed.');
                return;
            }
            if (!res.ok || !data.success) {
                throw new Error(data.message || ('HTTP ' + res.status));
            }

            if (data.csrf_token) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', data.csrf_token);
            }
            if (st.key === key) {
                setStatus('Draft saved at ' + clockTime() + ' · resume it from My Drafts', 'ok');
            }
            refreshCount();

            if (opts.manual) {
                Swal.fire({ icon: 'success', title: 'Draft saved', text: 'Resume it any time from My Drafts on this page.', timer: 1800, showConfirmButton: false });
            }
        } catch (error) {
            // Forget what was queued so the next autosave tick tries again.
            if (st.key === key && st.lastJson === json) st.lastJson = null;
            setStatus('Draft not saved: ' + (error.message || 'network error') + '. Will retry.', 'error');
            if (opts.manual) {
                Swal.fire({ icon: 'error', title: 'Draft not saved', text: error.message || 'Network error', confirmButtonColor: '#dc2626' });
            }
        }
    }

    // Leaving the page: a beacon (no custom headers, so the token rides in the body).
    function saveOnLeave() {
        if (!modalOpen() || st.restoring || st.committed) return;
        const snap = snapshot();
        if (!worthSaving(snap)) return;
        const json = JSON.stringify(snap);
        if (json === st.lastJson || !navigator.sendBeacon) return;

        if (!st.key) st.key = newKey();
        const fd = new FormData();
        fd.append('_token', csrf());
        fd.append('draft_key', st.key);
        fd.append('payload', json);
        Object.entries(summary(snap)).forEach(([k, v]) => fd.append(k, v));
        if (navigator.sendBeacon(DRAFTS_URL, fd)) st.lastJson = json;
    }

    // ── restore ─────────────────────────────────────────────────────────────────
    async function restore(payload) {
        const c = component();
        if (!c) throw new Error('The commissioning card is not ready.');

        const desired = payload.state || {};
        const extra = payload.extra || {};
        const dom = payload.dom || {};
        const known = new Set(stateKeys());
        const keys = Object.keys(desired).filter(k => known.has(k));

        // The OP flow's context lives on window; isOpFormHidden reads it.
        if (extra.last_commission_source_op) window.lastCommissionSourceOp = clone(extra.last_commission_source_op);
        if (extra.pending_existing_op_pra_context) window.pendingExistingOpPraContext = clone(extra.pending_existing_op_pra_context);

        // The watchers (applicationType, prefix, landUseId) rewrite neighbouring fields
        // when a value lands, so values are put back until the card stops changing.
        for (let pass = 0; pass < 5; pass++) {
            let changed = false;
            keys.forEach(k => {
                if (JSON.stringify(c[k]) !== JSON.stringify(desired[k])) {
                    c[k] = clone(desired[k]);
                    changed = true;
                }
            });
            if (!changed) break;
            await sleep(80);
        }

        // Inputs outside Alpine.
        const email = document.getElementById('generateEmail');
        if (email) email.value = dom.email || '';
        const by = document.getElementById('commissionedBy');
        if (by && dom.commissioned_by) by.value = dom.commissioned_by;
        const sourceRadio = document.querySelector('input[name="allocation_source_type"][value="' + (c._currentAllocationSourceType || 'default') + '"]');
        if (sourceRadio) sourceRadio.checked = true;

        // Select2 / native selects that do not follow the state on their own.
        if (c.batchMode && typeof c.loadLocationFieldsForEntry === 'function') {
            c.loadLocationFieldsForEntry();
        } else if (typeof c.syncLocationSelects === 'function') {
            c.syncLocationSelects();
        }

        if (c.allocatedByFilter === 'Allocation List' && c.allocationId) {
            if (typeof c.initAllocationSelect2 === 'function') c.initAllocationSelect2();
            await sleep(150);
            const allocSel = window.jQuery ? jQuery('#allocationSelect') : null;
            if (allocSel && allocSel.length) {
                const id = String(c.allocationId);
                if (!allocSel.find('option').filter(function () { return this.value === id; }).length) {
                    allocSel.append(new Option(extra.allocation_label || ('Allocation #' + id), id, true, true));
                }
                allocSel.val(id).trigger('change.select2');
            }
        }

        // Purpose: loading a land use's purposes clears the selection, so it is put back
        // once the list holds it (and again if a later load clears it).
        restorePurpose(c, desired.purpose, extra.purpose_name);

        // Serial and tracking ID come from today's counter, not the draft.
        if (typeof c.refreshSerialNumber === 'function') c.refreshSerialNumber();
        if (typeof c.updatePreview === 'function') c.updatePreview();

        if (extra.had_reclaimed_serial && typeof c.loadReclaimedSerials === 'function') {
            c.useReclaimedSerial = true;
            c.loadReclaimedSerials();
        }

        if (c.duplexRecordId && typeof window.renderDuplexPlan === 'function') {
            try { await window.renderDuplexPlan(c.duplexRecordId); } catch (e) { console.warn('[Draft] duplex plan', e); }
        }

        if (typeof c.refreshLocationMapState === 'function') {
            try { c.refreshLocationMapState(); } catch (e) { /* map optional */ }
        }
    }

    function restorePurpose(c, id, name) {
        if (!id && !name) return;
        const norm = v => String(v || '').trim().replace(/\s+/g, ' ').toUpperCase();
        const until = Date.now() + 10000;

        const tick = () => {
            if (!modalOpen() || Date.now() > until) return;
            if (String(c.purpose || '') === '') {
                const list = c.purposes || [];
                const match = list.find(p => String(p.id) === String(id)) || (name ? list.find(p => norm(p.name) === norm(name)) : null);
                if (match) {
                    c.purpose = String(match.id);
                    setTimeout(() => {
                        const sel = document.getElementById('purpose');
                        if (sel) sel.value = String(match.id);
                        if (typeof c.updatePreview === 'function') c.updatePreview();
                    }, 0);
                }
            }
            setTimeout(tick, 250);
        };
        tick();
    }

    function resumeNotes(extra) {
        const notes = [];
        if (extra.had_passport) notes.push('Attach the passport photograph again.');
        if (extra.had_pending_new_op) notes.push('The new OP captured on this card was not saved yet: capture it again before Generate.');
        if (extra.had_override) notes.push('The serial Override was not kept: the serial is the next one in the series. Use Override again if needed.');
        if (extra.had_reclaimed_serial) notes.push('Pick the reclaimed serial again (the list was reloaded).');
        return notes;
    }

    // ── public hooks (called from mls_js and the page) ──────────────────────────
    window.mlsfSaveDraftNow = function () {
        return save({ manual: true });
    };

    // A fresh card (opened, or Clear Form): keep the previous card, then start a new draft.
    window.mlsfDraftStartFresh = function () {
        if (st.restoring) return;
        save();
        st.key = null;
        st.lastJson = null;
        st.committed = false;
        setStatus(DEFAULT_STATUS);
    };

    // Cancel / X.
    window.mlsfDraftFlush = function () {
        save();
    };

    window.mlsfDraftCommitted = function (files) {
        const key = st.key;
        st.key = null;
        st.lastJson = null;
        st.committed = true;
        setStatus('Generated: this card is no longer a draft', 'ok');
        if (!key) return;

        const numbers = (Array.isArray(files) ? files : [files])
            .map(f => (typeof f === 'string' ? f : (f && (f.file_number || f.full_file_number || f.mlsfNo)) || ''))
            .map(f => String(f).trim())
            .filter(f => f && f !== 'N/A')
            .slice(0, 500);

        fetch(DRAFTS_URL + '/' + encodeURIComponent(key) + '/committed', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ file_numbers: numbers }),
        }).then(refreshCount).catch(e => console.warn('[Draft] committed', e));
    };

    window.mlsfResumeDraft = async function (key) {
        closeRowMenu();
        let draft;
        try {
            const res = await fetch(DRAFTS_URL + '/' + encodeURIComponent(key), { headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || 'Draft not found.');
            draft = data.draft;
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Cannot resume', text: error.message, confirmButtonColor: '#dc2626' });
            loadDrafts();
            return;
        }

        window.mlsfCloseDraftsModal();
        if (typeof showGlobalLoading === 'function') showGlobalLoading('Resuming draft…');

        try {
            // Exactly what Generate New File Number gives, then the draft on top.
            window.openGeneratorModalMain();
            st.restoring = true;
            st.key = draft.draft_key;
            if (typeof getNextSerialNumber === 'function') await getNextSerialNumber();
            await sleep(150);
            await restore(draft.payload || {});
        } catch (error) {
            console.error('[Draft] resume failed', error);
            Swal.fire({ icon: 'error', title: 'Draft only partly restored', text: error.message || 'Check the card before generating.', confirmButtonColor: '#dc2626' });
        } finally {
            st.restoring = false;
            const snap = snapshot();
            st.lastJson = snap ? JSON.stringify(snap) : null;
            if (typeof hideGlobalLoading === 'function') hideGlobalLoading();
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        setStatus('Resumed draft saved ' + (draft.last_saved_human || '') + ' · autosave on', 'ok');
        const notes = resumeNotes((draft.payload || {}).extra || {});
        if (notes.length) {
            Swal.fire({
                icon: 'info',
                title: 'Draft resumed',
                html: '<ul class="text-left text-sm list-disc pl-5 space-y-1">' + notes.map(n => '<li>' + esc(n) + '</li>').join('') + '</ul>',
                confirmButtonColor: '#d97706',
            });
        }
    };

    window.mlsfDiscardDraft = async function (key) {
        closeRowMenu();
        const row = st.drafts.find(d => d.draft_key === key) || {};
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Discard this draft?',
            text: (row.file_title || row.plot_no || 'This draft') + ' will be removed from My Drafts.',
            showCancelButton: true,
            confirmButtonText: 'Discard',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
        });
        if (!confirm.isConfirmed) return;

        try {
            const res = await fetch(DRAFTS_URL + '/' + encodeURIComponent(key), {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || ('HTTP ' + res.status));
            if (st.key === key) { st.key = null; st.lastJson = null; }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Not discarded', text: error.message, confirmButtonColor: '#dc2626' });
        }
        loadDrafts();
    };

    // ── My Drafts list ──────────────────────────────────────────────────────────
    function refreshCount() {
        return fetch(DRAFTS_URL, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) return;
                st.drafts = data.drafts || [];
                renderCount();
                const modal = document.getElementById('mlsfDraftsModal');
                if (modal && !modal.classList.contains('hidden')) renderDrafts();
            })
            .catch(() => {});
    }

    function renderCount() {
        const badge = document.getElementById('mlsfDraftsCount');
        if (!badge) return;
        badge.textContent = String(st.drafts.length);
        badge.classList.toggle('hidden', st.drafts.length === 0);
    }

    function loadDrafts() {
        const body = document.getElementById('mlsfDraftsBody');
        if (body && !st.drafts.length) {
            body.innerHTML = '<tr><td colspan="9" class="px-3 py-6 text-center text-gray-400">Loading drafts…</td></tr>';
        }
        return refreshCount().then(renderDrafts);
    }

    function renderDrafts() {
        const body = document.getElementById('mlsfDraftsBody');
        if (!body) return;

        const q = (document.getElementById('mlsfDraftsSearch')?.value || '').trim().toLowerCase();
        const rows = st.drafts.filter(d => !q || [d.file_title, d.file_type, d.land_use, d.plot_no, d.district, d.lga]
            .some(v => String(v || '').toLowerCase().includes(q)));

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="9" class="px-3 py-6 text-center text-gray-400">'
                + (st.drafts.length ? 'No draft matches your search.' : 'You have no open drafts.') + '</td></tr>';
            return;
        }

        const cell = v => (v ? esc(v) : '<span class="text-gray-300">—</span>');
        body.innerHTML = rows.map((d, i) => `
            <tr class="hover:bg-amber-50">
                <td class="px-3 py-2 text-gray-500">${i + 1}</td>
                <td class="px-3 py-2 font-medium text-gray-900">${cell(d.file_title)}</td>
                <td class="px-3 py-2 text-gray-700">${cell(d.file_type)}</td>
                <td class="px-3 py-2 text-gray-700">${cell(d.land_use)}</td>
                <td class="px-3 py-2 text-gray-700">${cell(d.plot_no)}</td>
                <td class="px-3 py-2 text-gray-700">${cell(d.district)}</td>
                <td class="px-3 py-2 text-gray-700">${cell(d.lga)}</td>
                <td class="px-3 py-2 text-gray-500 whitespace-nowrap" title="${esc(d.last_saved_at || '')}">${cell(d.last_saved_human)}</td>
                <td class="px-3 py-2 text-right">
                    <button type="button" class="mlsf-draft-menu-btn inline-flex items-center justify-center w-8 h-8 rounded-md hover:bg-gray-100 text-gray-600"
                            aria-label="Actions" aria-haspopup="menu" data-draft-key="${esc(d.draft_key)}">
                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                    </button>
                </td>
            </tr>`).join('');

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    window.mlsfOpenDraftsModal = function () {
        const modal = document.getElementById('mlsfDraftsModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        renderDrafts();
        loadDrafts();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    };

    window.mlsfCloseDraftsModal = function () {
        closeRowMenu();
        document.getElementById('mlsfDraftsModal')?.classList.add('hidden');
    };

    // Row action menu: one menu, placed next to whichever row opened it.
    function openRowMenu(btn) {
        const menu = document.getElementById('mlsfDraftRowMenu');
        if (!menu) return;
        if (!menu.classList.contains('hidden') && menu.dataset.draftKey === btn.dataset.draftKey) {
            closeRowMenu();
            return;
        }
        menu.dataset.draftKey = btn.dataset.draftKey;
        menu.classList.remove('hidden');

        const r = btn.getBoundingClientRect();
        const w = menu.offsetWidth;
        const h = menu.offsetHeight;
        let left = r.right - w;
        let top = r.bottom + 4;
        if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 4);
        left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        btn.setAttribute('aria-expanded', 'true');
    }

    function closeRowMenu() {
        const menu = document.getElementById('mlsfDraftRowMenu');
        if (!menu || menu.classList.contains('hidden')) return;
        menu.classList.add('hidden');
        document.querySelectorAll('.mlsf-draft-menu-btn[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
    }

    // ── wiring ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Out of the page's stacking contexts so position:fixed is the viewport's.
        const menu = document.getElementById('mlsfDraftRowMenu');
        if (menu && menu.parentElement !== document.body) document.body.appendChild(menu);

        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('.mlsf-draft-menu-btn');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                openRowMenu(trigger);
                return;
            }
            const action = e.target.closest('#mlsfDraftRowMenu [data-draft-action]');
            if (action) {
                e.preventDefault();
                e.stopPropagation();
                const key = document.getElementById('mlsfDraftRowMenu').dataset.draftKey;
                if (action.dataset.draftAction === 'resume') window.mlsfResumeDraft(key);
                if (action.dataset.draftAction === 'discard') window.mlsfDiscardDraft(key);
                return;
            }
            if (!e.target.closest('#mlsfDraftRowMenu')) closeRowMenu();
            // Backdrop click closes My Drafts.
            if (e.target.id === 'mlsfDraftsModal') window.mlsfCloseDraftsModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            const menuOpen = !document.getElementById('mlsfDraftRowMenu')?.classList.contains('hidden');
            if (menuOpen) { closeRowMenu(); return; }
            const modal = document.getElementById('mlsfDraftsModal');
            if (modal && !modal.classList.contains('hidden')) window.mlsfCloseDraftsModal();
        });
        window.addEventListener('resize', closeRowMenu);
        document.getElementById('mlsfDraftsModal')?.addEventListener('scroll', closeRowMenu, true);

        document.getElementById('mlsfDraftsSearch')?.addEventListener('input', function () {
            closeRowMenu();
            renderDrafts();
        });

        // Autosave: only while the card is open, and only when it changed.
        setInterval(function () {
            if (modalOpen() && !st.restoring) save();
        }, AUTOSAVE_MS);

        window.addEventListener('pagehide', saveOnLeave);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') saveOnLeave();
        });

        refreshCount();
    });
})();
</script>

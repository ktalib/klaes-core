{{--
    Capture OP Card — shared by the OSS Applications page and the MLS Commission page.

    Two modes, one card:
      * Single-record  — openCaptureOpModal(row): capture an OP and link it to an Awaiting TOT
                         (OSS only; needs the OP Batch modal's row cache).
      * Batch stepper  — window.openBatchCaptureOp(count, opType): capture N OPs with
                         Previous/Next, then Save Batch. Launched by the commission card
                         (openCommissionOpCaptureModal in mls_js) when Batch Mode is on.

    Requires on the host page: jQuery + select2, SweetAlert2 (Swal), lucide, a csrf-token meta
    tag, and the commission form's Alpine component (x-data="fileNumberGenerator()").

    Usage: @include('lands_one_stop_shop.partials.capture-op-card')
--}}

{{-- This card sits at z-[10000]. SweetAlert's container defaults to ~z-1080 (see global
     style.css), so without this its validation / duplicate / confirm dialogs render BEHIND
     the card and appear to do nothing. The OSS Applications page sets this itself; the MLS
     generator page does not — so the card ships the rule to cover every host page. --}}
<style>
    .swal2-container { z-index: 2000000 !important; }
    .swal2-backdrop-show { z-index: 1999999 !important; }
</style>
<div id="captureOpModal" class="fixed inset-0 bg-slate-900/60 hidden overflow-y-auto h-full w-full z-[10000]">
    <div class="relative top-6 mx-auto border w-full max-w-5xl shadow-2xl rounded-2xl bg-white overflow-hidden mb-10">
        <div class="flex items-start justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-violet-50 to-white">
            <div>
                <h3 class="text-lg font-semibold text-slate-900" id="copCardTitle">Capture OP</h3>
                <p class="text-sm text-slate-500 mt-0.5" id="copCardSubtitle">Match an existing OP, or capture a new one and link it to this Awaiting TOT</p>
            </div>
            <button type="button" onclick="closeCaptureOpModal()" class="text-slate-400 hover:text-slate-600 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Batch stepper — shown only when launched from the Commission card in Batch Mode.
             Mirrors the mls_js batch navigation (OP i of N, Prev/Next, Apply-to-all). --}}
        <div id="copBatchBar" class="hidden px-6 py-3 bg-violet-50 border-b border-violet-100 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-4">
                <span class="text-sm font-semibold text-violet-800">OP <span id="copBatchIndex">1</span> of <span id="copBatchCount">1</span></span>
                {{-- Shown only when resuming a saved, uncommissioned batch --}}
                <span id="copBatchResumeBadge" class="hidden px-2 py-0.5 rounded-full bg-violet-600 text-white text-[10px] font-mono font-semibold"></span>
                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" id="copApplyAll" onchange="copApplyLocationToAll()"
                           class="w-4 h-4 text-violet-600 border-slate-300 rounded focus:ring-violet-500">
                    Apply Location to All OPs in Batch
                    <span class="text-slate-400">— except Plot No</span>
                </label>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="copBatchAdd" onclick="copBatchAddRecord()"
                        class="px-3 py-1.5 border border-violet-300 bg-white rounded-lg text-sm text-violet-700 hover:bg-violet-50 transition">
                    + Add OP
                </button>
                <button type="button" id="copBatchRemove" onclick="copBatchRemoveRecord()"
                        class="px-3 py-1.5 border border-red-300 bg-white rounded-lg text-sm text-red-600 hover:bg-red-50 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    Remove
                </button>
                <span class="w-px h-5 bg-violet-200"></span>
                <button type="button" id="copBatchPrev" onclick="copBatchNav(-1)"
                        class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-white transition disabled:opacity-40 disabled:cursor-not-allowed">
                    &lt; Previous
                </button>
                <button type="button" id="copBatchNext" onclick="copBatchNav(1)"
                        class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-white transition disabled:opacity-40 disabled:cursor-not-allowed">
                    Next &gt;
                </button>
            </div>
        </div>

        {{-- The TOT being linked (single-record flow only) --}}
        <div id="copTotBand" class="px-6 py-3 bg-amber-50/60 border-b border-amber-100">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div>
                    <div class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Awaiting TOT</div>
                    <div id="copTotFileNo" class="font-mono font-bold text-slate-800 mt-0.5">—</div>
                </div>
                <div>
                    <div class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Party 1</div>
                    <div id="copTotParty1" class="text-rose-600 font-semibold mt-0.5">—</div>
                </div>
                <div>
                    <div class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Party 2</div>
                    <div id="copTotParty2" class="text-slate-700 font-semibold mt-0.5">—</div>
                </div>
            </div>
        </div>

        {{-- Live duplicate warning for the OP being edited — appears as soon as its six
             identifying fields match an existing record, without waiting for Save Batch. --}}
        <div id="copDupWarn" class="hidden mx-6 mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-xs text-amber-800 flex items-start gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0"></i>
            <span id="copDupWarnText"></span>
        </div>

        {{-- The card --}}
        <div class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Instrument Type <span class="text-rose-500">*</span></label>
                    <input type="text" id="copInstrumentType" value="Occupancy Permit" disabled
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-100 text-slate-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Occupancy Permit (OP) Type <span class="text-rose-500">*</span></label>
                    <select id="copOpType" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">Select OP Type</option>
                        <option value="OP Resettlement">OP Resettlement</option>
                        <option value="OP Direct Allocation">OP Direct Allocation</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status <span class="text-rose-500">*</span></label>
                    <select id="copStatus" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">Select Status</option>
                        <option value="Normal">Normal</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    OP Serial Number <span class="cop-batch-req hidden text-rose-500">*</span>
                </label>
                <input type="text" id="copOpSerial" placeholder="Enter OP serial number" inputmode="numeric"
                       oninput="copStripZeroPad(this); copLiveDupCheck()" onblur="copStripZeroPad(this); copLiveDupCheck()"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">System FileNo</label>
                <input type="text" id="copFileNo" disabled placeholder="Auto-generated temp file number"
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-100 text-slate-500">
            </div>
            {{-- Transaction Date · Land Use · Purpose on one row (1×3). Purpose is filtered by Land Use. --}}
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Transaction Date</label>
                    <input type="date" id="copTxDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Land Use</label>
                    {{-- Options populated from the commission land uses (value=code, data-id=land_use id).
                         Changing it filters the Purpose list by that land use. --}}
                    <select id="copLandUse" onchange="copOnLandUseChange()"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">Select Land Use</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Purpose <span class="text-rose-500">*</span></label>
                    {{-- Purposes are filtered by the selected Land Use (get-dependent-data). --}}
                    <select id="copPurpose" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">Select Purpose</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Party 1</label>
                <input type="text" id="copGrantor" value="KANO STATE GOVERNMENT" disabled
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-100 text-slate-600">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Party 2 <span class="text-violet-600 font-normal">— becomes the TOT's Party 1 (allottee)</span>
                </label>
                <input type="text" id="copGrantee" placeholder="ENTER PARTY 2 NAME"
                       oninput="copLiveDupCheck()" onblur="copLiveDupCheck()"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            {{-- LGA lives inside the builder (Plot No · District · LGA) — it's part of the address. --}}
            @include('lands_one_stop_shop.partials.location-builder', ['prefix' => 'cop', 'withLga' => true])
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Serial No <span class="cop-batch-req hidden text-rose-500">*</span>
                    </label>
                    {{-- Page No always mirrors Serial No, so typing here fills it. --}}
                    <input type="text" id="copSerialNo" inputmode="numeric"
                           oninput="copStripZeroPad(this); copMirrorSerialToPage(); copLiveDupCheck()"
                           onblur="copStripZeroPad(this); copMirrorSerialToPage(); copLiveDupCheck()"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Page No <span class="text-slate-400 font-normal">— same as Serial No</span>
                    </label>
                    <input type="text" id="copPageNo" readonly
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-100 text-slate-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Vol No <span class="cop-batch-req hidden text-rose-500">*</span>
                    </label>
                    <input type="text" id="copVolNo" inputmode="numeric"
                           oninput="copStripZeroPad(this); copLiveDupCheck()" onblur="copStripZeroPad(this); copLiveDupCheck()"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Deeds Date</label>
                <input type="date" id="copDeedsDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Deeds Time</label>
                <input type="time" id="copDeedsTime" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
            <div id="copModeHint" class="text-xs text-slate-500"></div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closeCaptureOpModal()"
                        class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-white transition">
                    Cancel
                </button>
                <button type="button" id="copCaptureBtn" onclick="copCaptureAndLink()"
                        class="px-5 py-2 bg-violet-600 text-white rounded-lg text-sm font-semibold hover:bg-violet-700 transition">
                    <i data-lucide="plus" class="w-4 h-4 inline"></i> Capture OP
                </button>
                <button type="button" id="copSaveBatchBtn" onclick="copSaveBatch()"
                        class="hidden px-5 py-2 bg-violet-600 text-white rounded-lg text-sm font-semibold hover:bg-violet-700 transition">
                    <i data-lucide="save" class="w-4 h-4 inline"></i> <span id="copSaveBatchLabel">Save Batch</span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Pings /session/keep-alive and rotates the CSRF token so a long batch fill never 419s.
     Guarded so the two host pages including this partial cannot double-load it. --}}
@once
    <script src="{{ asset('js/primaryform/session-keepalive.js') }}"></script>
@endonce

<script>
    /* ═══════════════════════════════════════════════════════════════════
       Capture OP Card — capture the OP directly and link it to the Awaiting TOT
       through a shared prop_id. (OP serial search / matching is disabled for now.)
       ═══════════════════════════════════════════════════════════════════ */
    let copTot = null;            // the Awaiting TOT being linked (single-record flow)

    /* ─── Location Builder: Plot No + District (searchable) → Full Location ─── */
    let opDistrictsCache = null;
    function opEnsureDistricts() {
        if (opDistrictsCache) return Promise.resolve(opDistrictsCache);
        return fetch('{{ route("lands-one-stop-shop.applications.op-districts") }}', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => { opDistrictsCache = (d && d.data) ? d.data : []; return opDistrictsCache; })
            .catch(err => { console.error('District load failed', err); return (opDistrictsCache = []); });
    }
    // Init select2 on the district dropdown once; wire recomposition on every field change.
    function initLocationBuilder(prefix, modalEl) {
        opEnsureDistricts().then(list => {
            const $sel = $('#' + prefix + 'District');
            if (!$sel.hasClass('select2-hidden-accessible')) {
                $sel.select2({
                    data: [{ id: '', text: 'Select district' }]
                        .concat(list.map(n => ({ id: n, text: n })))
                        .concat([{ id: '__OTHER__', text: 'Other (specify)' }]),
                    dropdownParent: $(modalEl),
                    width: '100%',
                    placeholder: 'Select district',
                });
                $sel.on('change', () => { toggleDistrictOther(prefix); composeLocation(prefix); });
                ['Plot', 'DistrictOther', 'Location'].forEach(s => {
                    const el = document.getElementById(prefix + s);
                    if (el) el.addEventListener('input', () => {
                        if (s !== 'Location') composeLocation(prefix);
                        // Plot No is one of the duplicate identifiers — live-check the OP card only.
                        if (prefix === 'cop' && s === 'Plot' && typeof window.copLiveDupCheck === 'function') window.copLiveDupCheck();
                    });
                });
                // LGA is part of the composed address — recompose when it changes.
                const lgaEl = document.getElementById(opLgaFieldId(prefix));
                if (lgaEl) $(lgaEl).on('change', () => composeLocation(prefix));
            }
        });
    }
    // The LGA field paired with a location-builder prefix (naming isn't uniform: cop→copLga, tot→utLga).
    function opLgaFieldId(prefix) {
        if (prefix === 'tot') return 'utLga';
        if (prefix === 'cop') return 'copLga';
        return prefix + 'Lga';
    }
    function toggleDistrictOther(prefix) {
        const isOther = $('#' + prefix + 'District').val() === '__OTHER__';
        document.getElementById(prefix + 'OtherWrap').classList.toggle('hidden', !isOther);
    }
    // Compose "<plot>, District, LGA" — only overwrites Location when it yields something.
    // (Street Name removed for now; LGA folded into the address; no "Plot" label prefix.)
    function composeLocation(prefix) {
        const v = id => (document.getElementById(prefix + id)?.value || '').trim();
        let district = $('#' + prefix + 'District').val() || '';
        if (district === '__OTHER__') district = v('DistrictOther');
        const lgaEl = document.getElementById(opLgaFieldId(prefix));
        const lga = lgaEl ? (lgaEl.value || '').trim() : '';
        const parts = [];
        if (v('Plot')) parts.push(v('Plot'));
        if (district) parts.push(district);
        if (lga) parts.push(lga);
        if (parts.length) document.getElementById(prefix + 'Location').value = parts.join(', ');
    }
    function resetLocationBuilder(prefix, plot) {
        document.getElementById(prefix + 'Plot').value = (plot && plot !== '—') ? plot : '';
        const streetEl = document.getElementById(prefix + 'Street');
        if (streetEl) streetEl.value = '';
        document.getElementById(prefix + 'DistrictOther').value = '';
        document.getElementById(prefix + 'Location').value = '';
        const $sel = $('#' + prefix + 'District');
        if ($sel.hasClass('select2-hidden-accessible')) $sel.val('').trigger('change.select2');
        document.getElementById(prefix + 'OtherWrap').classList.add('hidden');
    }

    // admin/header.blade.php enhances every input[type=date] with flatpickr (altInput), so the
    // field the user sees is a sibling — assigning .value on the original updates the submitted
    // value but not the display. Always route date writes through the instance when there is one.
    function copSetDateValue(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el._flatpickr) el._flatpickr.setDate(value || null, false);
        else el.value = value || '';
    }

    // Registry numbers are stored unpadded: 007 -> 7. Only all-digit values are touched, so an
    // alphanumeric OP serial keeps any leading zero that is part of the identifier itself.
    window.copStripZeroPad = function (el) {
        if (!el) return;
        const v = (el.value || '').trim();
        if (!/^\d+$/.test(v)) return;
        const stripped = v.replace(/^0+(?=\d)/, '');   // keeps a lone "0"
        if (stripped !== v) el.value = stripped;
    };

    // The number fields that must never carry zero padding.
    const COP_NUMBER_FIELDS = ['copOpSerial', 'copSerialNo', 'copVolNo'];
    function copNormalizeNumberFields() {
        COP_NUMBER_FIELDS.forEach(id => copStripZeroPad(document.getElementById(id)));
        copMirrorSerialToPage();
    }

    // Page No is always the Serial No — keep the (read-only) field in step as it's typed.
    window.copMirrorSerialToPage = function () {
        const serial = document.getElementById('copSerialNo');
        const page = document.getElementById('copPageNo');
        if (serial && page) page.value = serial.value;
    };

    // Searchable LGA dropdown data, shared with the Update ToT card.
    let opLgasCache = null;
    function opEnsureLgas() {
        if (opLgasCache) return Promise.resolve(opLgasCache);
        return fetch('{{ route("lands-one-stop-shop.applications.op-lgas") }}', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json()).then(d => (opLgasCache = (d && d.data) ? d.data : []))
            .catch(err => { console.error('LGA load failed', err); return (opLgasCache = []); });
    }

    function openCaptureOpModal(row) {
        copBatch = null;
        copSetBatchMode(false);   // single-record TOT flow: hide the batch stepper UI
        copTot = row;

        document.getElementById('copTotFileNo').textContent = row.file_number;
        // Party 1 is still the placeholder 'Kano State Government' until an OP is linked — hide it.
        const p1 = (row.party_1 || '').trim();
        const p1IsPlaceholder = /^kano state government$/i.test(p1) || p1 === '' || p1 === '—';
        const party1El = document.getElementById('copTotParty1');
        party1El.textContent = p1IsPlaceholder ? 'Not yet set' : p1;
        party1El.classList.toggle('text-rose-600', p1IsPlaceholder);
        party1El.classList.toggle('italic', p1IsPlaceholder);
        party1El.classList.toggle('text-slate-700', !p1IsPlaceholder);
        document.getElementById('copTotParty2').textContent = row.party_2;

        // Seed the card from the TOT: the rest is captured by hand.
        ['copOpSerial','copGrantee','copSerialNo','copPageNo','copVolNo','copDeedsTime']
            .forEach(id => { document.getElementById(id).value = ''; });
        ['copTxDate','copDeedsDate'].forEach(id => copSetDateValue(id, ''));   // flatpickr-aware
        document.getElementById('copLandUse').value = '';
        // Location builder: seed Plot from the TOT and pre-fill the composed Location (written back on link).
        const captureModal = document.getElementById('captureOpModal');
        initLocationBuilder('cop', captureModal);
        resetLocationBuilder('cop', row.plot_no);
        document.getElementById('copLocation').value = (row.location && row.location !== '—') ? row.location : '';
        // OP Type defaults from the TOT's op_type (if it matches an option); Status defaults Normal.
        const opTypeSel = document.getElementById('copOpType');
        const wantedOpType = (row.op_type && row.op_type !== '—') ? row.op_type : '';
        opTypeSel.value = [...opTypeSel.options].some(o => o.value === wantedOpType) ? wantedOpType : '';
        document.getElementById('copStatus').value = 'Normal';
        document.getElementById('copModeHint').textContent =
            'Capturing a new OP and linking it to this Awaiting TOT via the shared Prop ID.';

        // System FileNo is an auto-generated TEMP-XXXXX the OP will carry (not the TOT's number).
        copAllocateTempFileno();

        document.getElementById('captureOpModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        if (window.lucide) window.lucide.createIcons();
    }

    // Pull the next TEMP-XXXXX from temp_fileno_sequence and show it read-only as System FileNo.
    function copAllocateTempFileno() {
        const el = document.getElementById('copFileNo');
        el.value = 'Generating…';
        fetch('{{ route("lands-one-stop-shop.applications.op-next-temp-fileno") }}',
              { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => { el.value = (d && d.success && d.temp_fileno) ? d.temp_fileno : ''; })
            .catch(err => {
                console.error('Temp file number allocation failed', err);
                el.value = '';
            });
    }

    window.closeCaptureOpModal = function () {
        document.getElementById('captureOpModal').classList.add('hidden');
        document.body.style.overflow = 'hidden'; // batch modal is still open behind
        if (typeof copShowDupWarn === 'function') copShowDupWarn('');
        copTot = null;
        copBatch = null;
    };

    // Capture the OP directly and link it to the TOT. No serial matching / search.
    window.copCaptureAndLink = function () {
        if (!copTot) return;
        copNormalizeNumberFields();   // 007 -> 7 before it is stored
        const opType = document.getElementById('copOpType').value;
        const status = document.getElementById('copStatus').value;
        const grantee = document.getElementById('copGrantee').value.trim();
        // Required-field validation (Instrument Type is fixed, so it's always valid).
        if (!opType) {
            Swal.fire({ icon: 'warning', title: 'OP Type required', text: 'Select the Occupancy Permit (OP) Type.' });
            return;
        }
        if (!status) {
            Swal.fire({ icon: 'warning', title: 'Status required', text: 'Select a Status.' });
            return;
        }
        if (!grantee) {
            Swal.fire({ icon: 'warning', title: 'Party 2 required',
                text: "Party 2 is the allottee — it becomes the TOT's Party 1, so it can't be blank." });
            return;
        }
        Swal.fire({
            icon: 'question',
            title: 'Capture OP?',
            html: `This creates the OP record and links it to the Awaiting TOT through the shared Prop ID.
                   <div class="mt-3 text-left text-xs bg-slate-50 border border-slate-200 rounded-lg p-3">
                     <div><b>TOT:</b> ${copTot.file_number} (${copTot.op_batch})</div>
                     <div><b>Prop ID:</b> ${copTot.prop_id}</div>
                     <div class="mt-1"><b>Allottee (Party 1):</b> <span class="text-rose-600">${copTot.party_1}</span> → <span class="text-emerald-700 font-semibold">${grantee}</span></div>
                   </div>`,
            showCancelButton: true,
            confirmButtonText: 'Yes, Capture OP',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#7c3aed',
        }).then(result => {
            if (!result.isConfirmed) return;
            copPost('{{ route("lands-one-stop-shop.applications.op-capture-and-link") }}', {
                tot_pra_id: copTot.pra_id,
                grantee: grantee,
                op_type: opType,
                status: status,
                system_fileno: document.getElementById('copFileNo').value,
                op_serial_number: document.getElementById('copOpSerial').value,
                transaction_date: document.getElementById('copTxDate').value || null,
                land_use: document.getElementById('copLandUse').value,
                location: document.getElementById('copLocation').value,
                plot_no: document.getElementById('copPlot').value,
                serial_no: document.getElementById('copSerialNo').value,
                page_no: document.getElementById('copPageNo').value,
                volume_no: document.getElementById('copVolNo').value,
                deeds_date: document.getElementById('copDeedsDate').value,
                deeds_time: document.getElementById('copDeedsTime').value,
            });
        });
    };

    function copPost(url, payload) {
        const batchNo = copTot ? copTot.batch_no : null;
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': copCsrf(),
            },
            body: JSON.stringify(payload),
        })
            .then(r => r.json().then(d => ({ ok: r.ok, d })))
            .then(({ ok, d }) => {
                if (!ok || !d.success) {
                    Swal.fire({ icon: 'error', title: 'Could not link', text: d.message || 'Unknown error.' });
                    return;
                }
                Swal.fire({ icon: 'success', title: 'Linked', text: d.message, timer: 2600, showConfirmButton: false });
                closeCaptureOpModal();
                // Refresh the batch list — the OP Batch modal only exists on the OSS page.
                if (batchNo && typeof window.openOpBatchModal === 'function') window.openOpBatchModal(batchNo);
            })
            .catch(err => {
                console.error('Link request failed', err);
                Swal.fire({ icon: 'error', title: 'Request failed', text: 'See the console for details.' });
            });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Batch Capture OP — reuse this same card as a Prev/Next stepper.
       Launched from the Commission New File Number card when it is in
       Batch Mode. Captures N OPs in memory, backfills shared location into
       the commission form's locationEntries[], then saves all in one call
       as UNLINKED OP rows sharing a single Batch ID (op_batch). No TOT yet.
       ═══════════════════════════════════════════════════════════════════ */
    let copBatch = null;   // { opType, index, opBatch, forms: [] }  — count is always forms.length

    // A blank OP form. Records loaded from a saved batch carry op_id (the pra row) so the save
    // updates them in place instead of inserting duplicates.
    function copBlankForm(opType) {
        return {
            op_id: null,
            op_type: opType || '', status: 'Normal', system_fileno: '',
            op_serial_number: '', transaction_date: '', land_use: '', land_use_id: '', grantee: '',
            purpose_id: '', purpose_name: '', lga: '',
            plot: '', street: '', district: '', district_other: '', location: '',
            serial_no: '', page_no: '', volume_no: '', deeds_date: '', deeds_time: '',
        };
    }
    function copBatchSize() { return copBatch ? copBatch.forms.length : 0; }

    /* ── Session survival ───────────────────────────────────────────────
       A batch of 50 OPs is an hour of typing, and every record lives only in
       copBatch.forms[] until Save Batch. A 419 (expired CSRF token) at that
       moment would throw the whole sitting away, so: keep the session warm
       while the stepper is open, always post the freshest token we have, and
       on a 419 refresh the token and retry once before bothering the user.
       Nothing here ever clears copBatch — the user can always retry. */

    // SessionKeepAlive republishes the rotated token as window._freshCsrfToken; prefer it
    // over the meta tag, which is only accurate as of page load.
    function copCsrf() {
        if (window._freshCsrfToken) return window._freshCsrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function copKeepAliveStart() {
        if (window.SessionKeepAlive) {
            window.SessionKeepAlive.start({
                url: '{{ route("session.keepalive") }}',
                interval: 4 * 60 * 1000,
            });
        }
    }

    // Resolves to a fresh token, or null if the session is genuinely gone.
    function copRefreshToken() {
        return fetch('{{ route("session.keepalive") }}', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(r => (r.ok ? r.json() : null))
            .then(d => {
                if (d && d.csrf_token) {
                    window._freshCsrfToken = d.csrf_token;
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.setAttribute('content', d.csrf_token);
                    document.querySelectorAll('input[name="_token"]').forEach(el => { el.value = d.csrf_token; });
                    return d.csrf_token;
                }
                return null;
            })
            .catch(() => null);
    }

    // Every POST from this card goes through here. Returns { ok, status, d } where d is the
    // parsed body ({} when the response was not JSON — a 419 returns Laravel's HTML page).
    function copFetch(url, payload, _retried) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': copCsrf(),
            },
            body: JSON.stringify(payload),
        }).then(r => {
            if (r.status === 419 && !_retried) {
                // Stale token. Get a new one and replay the request once — invisible to the user.
                return copRefreshToken().then(token => {
                    if (!token) return { ok: false, status: 419, d: {} };
                    return copFetch(url, payload, true);
                });
            }
            return r.json()
                .then(d => ({ ok: r.ok, status: r.status, d: d || {} }))
                .catch(() => ({ ok: r.ok, status: r.status, d: {} }));
        });
    }

    // Shown when the retry still failed, i.e. the login itself is gone. Deliberately offers
    // re-login in a NEW tab so this tab — and the unsaved batch in it — stays alive.
    function copSessionExpiredDialog(onRetry) {
        Swal.fire({
            icon: 'warning',
            title: 'Session expired',
            html: '<p class="text-gray-700">Your login timed out, so this could not be saved.</p>'
                + '<p class="text-gray-700 mt-2"><strong>Your batch has not been lost.</strong> '
                + 'Log in again in the new tab, come back here, and press Retry.</p>',
            showCancelButton: true,
            confirmButtonText: 'Open Login in New Tab',
            cancelButtonText: 'Retry',
            confirmButtonColor: '#2563eb',
            allowOutsideClick: false,
        }).then(res => {
            if (res.isConfirmed) {
                window.open('{{ url("/login") }}', '_blank');
                copSessionExpiredDialog(onRetry);
            } else if (res.dismiss === Swal.DismissReason.cancel && typeof onRetry === 'function') {
                copRefreshToken().then(onRetry);
            }
        });
    }

    // Generic JSON POST returning the parsed body. Unlike copPost (which drives the TOT-link
    // flow and its own dialogs), this leaves all UI handling to the caller.
    function copPostJson(url, payload) {
        return copFetch(url, payload).then(({ d }) => d);
    }

    // Read/write the commission modal's Alpine component (fileNumberGenerator) so OP
    // location edits mirror into its per-applicant locationEntries[] and vice-versa.
    function copAlpine() {
        const el = document.querySelector('[x-data="fileNumberGenerator()"]');
        return (el && el._x_dataStack) ? el._x_dataStack[0] : null;
    }

    // Toggle the card between single-record (TOT link) and batch stepper modes.
    function copSetBatchMode(on) {
        // Serial/Vol/LGA are required for a batch only — the single TOT flow has always
        // allowed them blank, so the markers (and the checks in copSaveBatch) are batch-only.
        document.querySelectorAll('.cop-batch-req').forEach(el => el.classList.toggle('hidden', !on));
        document.getElementById('copBatchBar').classList.toggle('hidden', !on);
        document.getElementById('copTotBand').classList.toggle('hidden', on);
        document.getElementById('copCaptureBtn').classList.toggle('hidden', on);
        document.getElementById('copSaveBatchBtn').classList.toggle('hidden', !on);
        if (on) {
            copRefreshBatchChrome();
        } else {
            document.getElementById('copCardTitle').textContent = 'Capture OP';
            document.getElementById('copCardSubtitle').textContent =
                'Match an existing OP, or capture a new one and link it to this Awaiting TOT';
        }
    }

    // Title, subtitle and action label depend on whether this is a new batch or an edit of a
    // saved one — kept in one place so they stay consistent as the batch is navigated.
    function copRefreshBatchChrome() {
        const editing = !!(copBatch && copBatch.opBatch);
        document.getElementById('copCardTitle').textContent = editing ? 'Edit OP Batch' : 'Batch Capture OP';
        document.getElementById('copCardSubtitle').textContent = editing
            ? 'Edit any OP in this batch, add or remove records, then Update Batch.'
            : 'Capture each OP in the batch. Navigate with Previous / Next, then Save Batch.';
        document.getElementById('copSaveBatchLabel').textContent = editing ? 'Update Batch' : 'Save Batch';
    }

    // Entry point invoked by the commission card (openCommissionOpCaptureModal) in batch mode.
    // `resume` (optional) = { op_batch, forms } from opBatchRecords — loads a saved,
    // uncommissioned batch for editing instead of starting a fresh one.
    window.openBatchCaptureOp = function (count, opType, resume) {
        count = Math.max(1, parseInt(count) || 1);
        // Batch capture is long-running — keep the session (and the CSRF token) alive for it.
        copKeepAliveStart();
        copTot = null;
        const resuming = !!(resume && resume.op_batch && Array.isArray(resume.forms) && resume.forms.length);
        copBatch = {
            opType: opType || '',
            index: 0,
            opBatch: resuming ? resume.op_batch : null,
            forms: resuming
                ? resume.forms.map(r => Object.assign(copBlankForm(opType), r))
                : Array.from({ length: count }, () => copBlankForm(opType)),
        };
        const a = copAlpine();
        // Populate Land Use (with land_use ids, for Purpose filtering) and LGA options.
        copFillLandUseOptions(a);
        copFillLgaOptions();
        // A resumed record's Land Use arrives as a code (pra stores no land_use id) — resolve it
        // back to the option's data-id so Purpose filtering works as it does for fresh entries.
        if (resuming) {
            const luOpts = [...document.getElementById('copLandUse').options];
            copBatch.forms.forEach(f => {
                if (f.land_use && !f.land_use_id) {
                    const opt = luOpts.find(o => o.value === f.land_use);
                    if (opt) f.land_use_id = opt.dataset.id || '';
                }
            });
        }
        // Seed each OP's location from the matching commission applicant entry, if present.
        // Skipped when resuming — the saved record's own location must not be overwritten.
        if (!resuming && a && Array.isArray(a.locationEntries)) {
            copBatch.forms.forEach((f, i) => {
                const e = a.locationEntries[i];
                if (!e) return;
                f.plot = e.plotNo || '';
                f.location = e.location || '';
                f.district = e.district || '';
                f.lga = e.lga || '';
            });
        }
        // Seed batch-level Land Use / Purpose from the commission form if already chosen.
        if (a) {
            const luSeed = a.landUse || '';
            const luIdSeed = a.landUseId || '';
            const puSeed = a.purpose || '';
            const puName = (a.purposes || []).find(x => String(x.id) === String(puSeed));
            copBatch.forms.forEach(f => {
                if (!f.land_use && luSeed) { f.land_use = luSeed; f.land_use_id = luIdSeed; }
                if (!f.purpose_id && puSeed) { f.purpose_id = String(puSeed); f.purpose_name = puName ? puName.name : ''; }
            });
        }

        copSetBatchMode(true);
        const modal = document.getElementById('captureOpModal');
        initLocationBuilder('cop', modal);
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        document.getElementById('copApplyAll').checked = false;
        copBatchLoad(0);
        if (window.lucide) window.lucide.createIcons();
    };

    /**
     * Batch Mode entry: start a new batch, or resume an uncommissioned one.
     *
     * `onStart(quantity, resume)` is invoked once the choice is made, so the host page can set
     * its own Batch Mode state before the stepper opens — `resume` is null for a new batch, or
     * { op_batch, forms } when continuing a saved one.
     */
    window.copPromptBatchStart = function (opType, onStart) {
        const openNew = () => copPromptNewBatchQty(opType, onStart);

        fetch('{{ route("lands-one-stop-shop.applications.op-uncommissioned-batches") }}', {
            headers: { 'Accept': 'application/json' },
        })
            .then(r => r.json())
            .then(d => {
                const batches = (d && d.success && Array.isArray(d.batches)) ? d.batches : [];
                // Nothing to resume — skip the choice and go straight to the quantity prompt.
                if (!batches.length) { openNew(); return; }

                Swal.fire({
                    icon: 'question',
                    title: 'Batch Capture OP',
                    html: '<p class="text-sm text-gray-600">Start a new batch, or continue one you have already begun?</p>'
                        + '<p class="text-xs text-gray-500 mt-2">' + batches.length + ' uncommissioned batch'
                        + (batches.length === 1 ? '' : 'es') + ' available.</p>',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: 'Continue an Existing Batch',
                    denyButtonText: 'Start a New Batch',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#7c3aed',
                    denyButtonColor: '#2563eb',
                }).then(res => {
                    if (res.isConfirmed) copPromptPickBatch(batches, opType, onStart);
                    else if (res.isDenied) openNew();
                    else if (typeof onStart === 'function') onStart(null, null);   // cancelled
                });
            })
            .catch(err => {
                // The batch list is a convenience — never let its failure block a new batch.
                console.error('Could not load uncommissioned batches', err);
                openNew();
            });
    };

    // Quantity prompt for a brand-new batch.
    function copPromptNewBatchQty(opType, onStart) {
        Swal.fire({
            icon: 'question',
            title: 'Start a New Batch',
            html: '<p class="text-sm text-gray-600 mb-3">How many OPs are you capturing for <strong>'
                + (opType || 'this batch') + '</strong>?</p>'
                + '<input id="copNewBatchQty" type="number" min="2" max="100" value="2" class="swal2-input" '
                + 'style="width:8rem;margin:0 auto;" placeholder="Qty">'
                + '<p class="text-xs text-gray-500">You can add or remove records later, before commissioning.</p>',
            showCancelButton: true,
            confirmButtonText: 'Start',
            confirmButtonColor: '#2563eb',
            focusConfirm: false,
            preConfirm: () => {
                const qty = parseInt(document.getElementById('copNewBatchQty')?.value, 10);
                if (!qty || qty < 2 || qty > 100) {
                    Swal.showValidationMessage('Enter a batch quantity between 2 and 100.');
                    return false;
                }
                return qty;
            },
        }).then(res => {
            if (typeof onStart !== 'function') return;
            onStart(res.isConfirmed ? res.value : null, null);
        });
    }

    // Picker listing every uncommissioned batch, with enough detail to identify one on sight.
    function copPromptPickBatch(batches, opType, onStart) {
        const esc = s => String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        const when = ts => {
            const d = new Date(String(ts || '').replace(' ', 'T'));
            return isNaN(d) ? esc(ts) : d.toLocaleString();
        };

        const rows = batches.map(b => {
            const names = (b.allottees || []).map(esc).join(', ')
                + (b.more > 0 ? ' <span class="text-gray-400">+' + b.more + ' more</span>' : '');
            return '<label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-violet-50 text-left">'
                + '<input type="radio" name="copBatchPick" value="' + esc(b.op_batch) + '" class="mt-1">'
                + '<span class="flex-1">'
                + '<span class="block font-mono font-semibold text-sm text-violet-800">' + esc(b.op_batch) + '</span>'
                + '<span class="block text-xs text-gray-500 mt-0.5">' + when(b.created_at)
                + ' &middot; <strong>' + b.count + '</strong> record' + (b.count === 1 ? '' : 's')
                + (b.captured_by
                    ? ' &middot; by <span class="inline-block px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">'
                        + esc(b.captured_by) + '</span>'
                    : '')
                + '</span>'
                + '<span class="block text-xs text-gray-600 mt-1">' + (names || '<em class="text-gray-400">No names captured</em>') + '</span>'
                + '</span></label>';
        }).join('');

        Swal.fire({
            title: 'Continue an Existing Batch',
            html: '<div class="space-y-2 max-h-80 overflow-y-auto text-left">' + rows + '</div>',
            width: 640,
            showCancelButton: true,
            confirmButtonText: 'Load Batch',
            confirmButtonColor: '#7c3aed',
            focusConfirm: false,
            preConfirm: () => {
                const picked = document.querySelector('input[name="copBatchPick"]:checked');
                if (!picked) { Swal.showValidationMessage('Select a batch to continue.'); return false; }
                return picked.value;
            },
        }).then(res => {
            if (!res.isConfirmed) { if (typeof onStart === 'function') onStart(null, null); return; }
            copLoadBatchForResume(res.value, opType, onStart);
        });
    }

    // Fetch a batch's saved records and hand them to the host page to open the stepper.
    function copLoadBatchForResume(opBatch, opType, onStart) {
        const url = '{{ route("lands-one-stop-shop.applications.op-uncommissioned-batch-records") }}?op_batch=' + encodeURIComponent(opBatch);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => {
                if (!d || !d.success || !Array.isArray(d.forms) || !d.forms.length) {
                    Swal.fire({ icon: 'error', title: 'Could not load batch', text: (d && d.message) || 'That batch has no editable records.' });
                    if (typeof onStart === 'function') onStart(null, null);
                    return;
                }
                if (typeof onStart === 'function') onStart(d.forms.length, { op_batch: d.op_batch, forms: d.forms });
            })
            .catch(err => {
                console.error('Batch load failed', err);
                Swal.fire({ icon: 'error', title: 'Request failed', text: 'See the console for details.' });
                if (typeof onStart === 'function') onStart(null, null);
            });
    }

    // Land Use options come from the commission component's landUses (value=code, data-id=land_use id).
    function copDeriveLandUseCode(name) {
        const n = String(name || '').toUpperCase();
        if (n.includes('RESIDENTIAL')) return 'RES';
        if (n.includes('COMMERCIAL')) return 'COM';
        if (n.includes('INDUSTRIAL')) return 'IND';
        if (n.includes('AGRICULTURAL')) return 'AG';
        return n.substring(0, 3);
    }
    function copFillLandUseOptions(a) {
        const sel = document.getElementById('copLandUse');
        if (!sel) return;
        const lus = (a && Array.isArray(a.landUses)) ? a.landUses : [];
        sel.innerHTML = '<option value="">Select Land Use</option>'
            + lus.map(lu => '<option value="' + copDeriveLandUseCode(lu.landuse) + '" data-id="' + lu.id + '">'
                + String(lu.landuse || '').toUpperCase() + '</option>').join('');
    }
    function copSelectedLandUseId() {
        const opt = document.getElementById('copLandUse').selectedOptions[0];
        return opt ? (opt.dataset.id || '') : '';
    }

    // Purpose list is FILTERED by the selected Land Use (get-dependent-data?land_use_id=).
    // Cache per land_use id ('all' = full list when no land use is chosen).
    let copPurposeCache = {};
    function copLoadPurposesForLandUse(landUseId, targetPurposeId) {
        const sel = document.getElementById('copPurpose');
        if (!sel) return;
        const key = landUseId || 'all';
        const fill = (list) => {
            sel.innerHTML = '<option value="">Select Purpose</option>'
                + (list || []).map(p => '<option value="' + p.id + '">' + p.name + '</option>').join('');
            const want = (targetPurposeId != null && targetPurposeId !== '') ? String(targetPurposeId) : '';
            sel.value = (want && [...sel.options].some(o => o.value === want)) ? want : '';
        };
        if (copPurposeCache[key]) { fill(copPurposeCache[key]); return; }
        fetch('{{ route("mls-fileno.get-dependent-data") }}?land_use_id=' + encodeURIComponent(key), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => { copPurposeCache[key] = d.purposes || []; fill(copPurposeCache[key]); })
            .catch(err => { console.error('Purpose load failed', err); fill([]); });
    }
    // Land Use changed on the OP card → refilter Purpose (reset the current pick).
    window.copOnLandUseChange = function () {
        copLoadPurposesForLandUse(copSelectedLandUseId(), null);
    };

    // Fill #copLga from opLgas (values are LGA names, matching the commission entry's lga field).
    function copFillLgaOptions() {
        const sel = document.getElementById('copLga');
        if (!sel) return;
        opEnsureLgas().then(list => {
            const current = sel.value;
            sel.innerHTML = '<option value="">Select LGA</option>'
                + (list || []).map(n => '<option value="' + n + '">' + n + '</option>').join('');
            if (current) sel.value = current;
        });
    }

    // Read the card fields into forms[index].
    function copBatchStash() {
        if (!copBatch) return;
        const f = copBatch.forms[copBatch.index];
        if (!f) return;
        copNormalizeNumberFields();   // 007 -> 7 before it is stored
        composeLocation('cop');
        let district = $('#copDistrict').val() || '';
        f.op_type = document.getElementById('copOpType').value;
        f.status = document.getElementById('copStatus').value;
        f.op_serial_number = document.getElementById('copOpSerial').value;
        f.system_fileno = document.getElementById('copFileNo').value;
        f.transaction_date = document.getElementById('copTxDate').value;
        const luOpt = document.getElementById('copLandUse').selectedOptions[0];
        f.land_use = luOpt ? luOpt.value : '';
        f.land_use_id = luOpt ? (luOpt.dataset.id || '') : '';
        const purposeSel = document.getElementById('copPurpose');
        f.purpose_id = purposeSel.value;
        f.purpose_name = (purposeSel.selectedOptions[0]?.text || '').trim();
        if (f.purpose_name === 'Select Purpose') f.purpose_name = '';
        f.lga = document.getElementById('copLga').value;
        f.grantee = document.getElementById('copGrantee').value.trim();
        f.plot = document.getElementById('copPlot').value;
        f.street = document.getElementById('copStreet')?.value || '';   // Street field removed for now
        f.district = district;
        f.district_other = document.getElementById('copDistrictOther').value;
        f.location = document.getElementById('copLocation').value;
        f.serial_no = document.getElementById('copSerialNo').value;
        f.page_no = document.getElementById('copPageNo').value;
        f.volume_no = document.getElementById('copVolNo').value;
        f.deeds_date = document.getElementById('copDeedsDate').value;
        f.deeds_time = document.getElementById('copDeedsTime').value;
        copBackfillCommission(copBatch.index, f);
    }

    // Mirror an OP form's shared location fields back into the commission applicant entry.
    // Fills any missing entries up to i with the same shape mls_js's updateBatchPreview() uses.
    function copBackfillCommission(i, f) {
        const a = copAlpine();
        if (!a || !Array.isArray(a.locationEntries)) return;
        while (a.locationEntries.length <= i) {
            a.locationEntries.push({
                plotNo: '', tpNo: '', location: '', lga: '', district: '', tracking_id: null,
                file_name: a.fileName || '', phone_no: a.phone_no || '', address: a.address || '',
            });
        }
        const e = a.locationEntries[i];
        const districtName = (f.district === '__OTHER__') ? (f.district_other || '') : (f.district || '');
        e.plotNo = f.plot || '';
        e.location = f.location || '';
        e.district = districtName;
        e.lga = f.lga || '';
    }

    // Load forms[i] into the card fields; allocate a TEMP System FileNo on first visit.
    function copBatchLoad(i) {
        if (!copBatch) return;
        copBatch.index = i;
        const f = copBatch.forms[i];
        document.getElementById('copBatchIndex').textContent = (i + 1);
        document.getElementById('copBatchCount').textContent = copBatchSize();
        document.getElementById('copBatchPrev').disabled = (i === 0);
        document.getElementById('copBatchNext').disabled = (i >= copBatchSize() - 1);
        // A batch must keep at least one record.
        document.getElementById('copBatchRemove').disabled = (copBatchSize() <= 1);
        const badge = document.getElementById('copBatchResumeBadge');
        badge.classList.toggle('hidden', !copBatch.opBatch);
        badge.textContent = copBatch.opBatch || '';
        copRefreshBatchChrome();

        document.getElementById('copOpType').value = f.op_type || copBatch.opType || '';
        document.getElementById('copStatus').value = f.status || 'Normal';
        document.getElementById('copOpSerial').value = f.op_serial_number || '';
        copSetDateValue('copTxDate', f.transaction_date);
        // Land Use — restore by data-id so duplicate codes resolve to the exact land use.
        const luSel = document.getElementById('copLandUse');
        if (f.land_use_id) {
            const idx = [...luSel.options].findIndex(o => o.dataset.id === String(f.land_use_id));
            luSel.selectedIndex = idx >= 0 ? idx : 0;
        } else {
            luSel.value = f.land_use || '';
        }
        // Purpose — filtered by that land use, then select the saved purpose once loaded.
        copLoadPurposesForLandUse(f.land_use_id || '', f.purpose_id || '');
        document.getElementById('copLga').value = f.lga || '';
        document.getElementById('copGrantee').value = f.grantee || '';
        document.getElementById('copSerialNo').value = f.serial_no || '';
        document.getElementById('copVolNo').value = f.volume_no || '';
        copSetDateValue('copDeedsDate', f.deeds_date);
        document.getElementById('copDeedsTime').value = f.deeds_time || '';
        copMirrorSerialToPage();   // Page No is derived, never stored independently
        // Location builder fields.
        document.getElementById('copPlot').value = f.plot || '';
        const copStreetEl = document.getElementById('copStreet');   // removed for now
        if (copStreetEl) copStreetEl.value = f.street || '';
        document.getElementById('copDistrictOther').value = f.district_other || '';
        document.getElementById('copLocation').value = f.location || '';
        const $d = $('#copDistrict');
        if ($d.hasClass('select2-hidden-accessible')) $d.val(f.district || '').trigger('change.select2');
        toggleDistrictOther('cop');

        // Re-run the live duplicate check for the OP we just loaded.
        if (typeof window.copLiveDupCheck === 'function') window.copLiveDupCheck();

        // System FileNo: one TEMP per OP, allocated lazily and cached on the form.
        const fileNoEl = document.getElementById('copFileNo');
        if (f.system_fileno) {
            fileNoEl.value = f.system_fileno;
        } else {
            fileNoEl.value = 'Generating…';
            fetch('{{ route("lands-one-stop-shop.applications.op-next-temp-fileno") }}', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => {
                    const temp = (d && d.success && d.temp_fileno) ? d.temp_fileno : '';
                    f.system_fileno = temp;
                    if (copBatch && copBatch.index === i) fileNoEl.value = temp;
                })
                .catch(err => { console.error('Temp file number allocation failed', err); fileNoEl.value = ''; });
        }
    }

    window.copBatchNav = function (delta) {
        if (!copBatch) return;
        copBatchStash();
        const target = copBatch.index + delta;
        if (target < 0 || target >= copBatchSize()) return;
        copBatchLoad(target);
    };

    // Append a blank OP to the batch and jump to it. Works for both a fresh batch and a
    // resumed one — on save, records with no op_id are inserted under the same batch id.
    window.copBatchAddRecord = function () {
        if (!copBatch) return;
        copBatchStash();
        copBatch.forms.push(copBlankForm(copBatch.opType));
        copSyncCommissionQuantity();
        copBatchLoad(copBatch.forms.length - 1);
    };

    /**
     * Remove the current OP from the batch.
     *
     * A record that was never saved is simply dropped from memory. A saved one (op_id set) is
     * HARD deleted server-side first — these file numbers were never commissioned, so there is
     * nothing to preserve — and only removed from the stepper once the server confirms.
     */
    window.copBatchRemoveRecord = function () {
        if (!copBatch || copBatchSize() <= 1) return;
        copBatchStash();
        const i = copBatch.index;
        const f = copBatch.forms[i];
        const label = f.grantee ? ('“' + f.grantee + '”') : ('OP ' + (i + 1));

        const drop = () => {
            copBatch.forms.splice(i, 1);
            copSyncCommissionQuantity();
            copBatchLoad(Math.min(i, copBatch.forms.length - 1));
        };

        // Unsaved record: no server round-trip needed.
        if (!f.op_id) {
            Swal.fire({
                icon: 'question', title: 'Remove ' + label + ' from this batch?',
                text: 'This record has not been saved yet.',
                showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#dc2626',
            }).then(res => { if (res.isConfirmed) drop(); });
            return;
        }

        Swal.fire({
            icon: 'warning', title: 'Permanently delete ' + label + '?',
            html: 'This OP has been saved but <strong>not commissioned</strong>, so it will be '
                + 'permanently deleted from the database. This cannot be undone.',
            showCancelButton: true, confirmButtonText: 'Delete permanently', confirmButtonColor: '#dc2626',
        }).then(res => {
            if (!res.isConfirmed) return;
            copPostJson('{{ route("lands-one-stop-shop.applications.op-batch-delete-record") }}', {
                op_batch: copBatch.opBatch, op_id: f.op_id,
            })
                .then(d => {
                    if (!d || !d.success) {
                        Swal.fire({ icon: 'error', title: 'Could not delete', text: (d && d.message) || 'Unknown error.' });
                        return;
                    }
                    drop();
                    if (typeof Swal.fire === 'function') {
                        Swal.fire({
                            icon: 'success', title: 'Record deleted',
                            text: 'Batch ' + copBatch.opBatch + ' now has ' + copBatchSize() + ' record'
                                + (copBatchSize() === 1 ? '' : 's') + '.',
                            timer: 2200, showConfirmButton: false,
                        });
                    }
                })
                .catch(err => {
                    console.error('Batch record delete failed', err);
                    Swal.fire({ icon: 'error', title: 'Request failed', text: 'See the console for details.' });
                });
        });
    };

    // Keep the commission card's Batch Mode quantity in step with the OP count, so returning
    // to Commission New File Number shows the batch's true size (45, not the original 50).
    function copSyncCommissionQuantity() {
        const a = copAlpine();
        if (!a) return;
        const n = copBatchSize();
        a.batchQuantity = n;
        if (Array.isArray(a.locationEntries) && a.locationEntries.length > n) {
            a.locationEntries.splice(n);
        }
        if (typeof a.updateBatchPreview === 'function') a.updateBatchPreview();
    }

    // Compose "<plot>, District, LGA" from an in-memory OP form — the copApplyLocationToAll
    // equivalent of composeLocation(), which reads the live card fields instead.
    function copComposeFormLocation(f) {
        const district = (f.district === '__OTHER__') ? (f.district_other || '') : (f.district || '');
        const parts = [];
        if ((f.plot || '').trim()) parts.push(f.plot.trim());
        if (district) parts.push(district);
        if ((f.lga || '').trim()) parts.push(f.lga.trim());
        return parts.join(', ');
    }

    // Copy the current OP's location to every OP in the batch AND to every commission entry.
    // Plot No is deliberately EXCLUDED — each property in the batch has its own plot — so every
    // OP keeps its own plot and its Full Location is recomposed from it plus the shared parts.
    window.copApplyLocationToAll = function () {
        if (!copBatch || !document.getElementById('copApplyAll').checked) return;
        copBatchStash();
        const src = copBatch.forms[copBatch.index];
        copBatch.forms.forEach((f, i) => {
            f.street = src.street;
            f.district = src.district;
            f.district_other = src.district_other;
            f.lga = src.lga;
            f.land_use = src.land_use;
            f.land_use_id = src.land_use_id;
            f.purpose_id = src.purpose_id;
            f.purpose_name = src.purpose_name;
            const composed = copComposeFormLocation(f);
            if (composed) f.location = composed;
            copBackfillCommission(i, f);
        });
        copBatchLoad(copBatch.index);   // repaint (district select2)
        Swal.fire({
            icon: 'success', title: 'Location applied to all OPs',
            text: 'Plot No was left as-is on each OP.',
            timer: 1800, showConfirmButton: false,
        });
    };

    /* ─── Live duplicate warning for the OP being edited ─── */
    let copDupTimer = null;
    let copDupSeq = 0;   // guards against out-of-order responses when fields change quickly

    // variant: 'warn' (amber — will save as a separate new OP) | 'update' (blue — will backfill
    // an existing record). Defaults to amber.
    function copShowDupWarn(message, variant) {
        const box = document.getElementById('copDupWarn');
        const txt = document.getElementById('copDupWarnText');
        if (!box || !txt) return;
        if (message) {
            const isUpdate = variant === 'update';
            txt.textContent = message;
            box.classList.remove('hidden');
            box.classList.toggle('border-amber-300', !isUpdate);
            box.classList.toggle('bg-amber-50', !isUpdate);
            box.classList.toggle('text-amber-800', !isUpdate);
            box.classList.toggle('border-blue-300', isUpdate);
            box.classList.toggle('bg-blue-50', isUpdate);
            box.classList.toggle('text-blue-800', isUpdate);
            if (window.lucide) window.lucide.createIcons();
        } else {
            box.classList.add('hidden');
            txt.textContent = '';
        }
    }

    // The identifying fields for the current OP. Plot No may be missing, so it does NOT gate the
    // duplicate check — it's carried only for display. The required set is the other five.
    const COP_DUP_REQUIRED = ['op_serial_number', 'serial_no', 'page_no', 'volume_no', 'party_2'];
    function copCurrentIdentifiers() {
        return {
            op_serial_number: (document.getElementById('copOpSerial').value || '').trim(),
            serial_no: (document.getElementById('copSerialNo').value || '').trim(),
            page_no: (document.getElementById('copPageNo').value || '').trim(),
            volume_no: (document.getElementById('copVolNo').value || '').trim(),
            plot_no: (document.getElementById('copPlot').value || '').trim(),
            party_2: (document.getElementById('copGrantee').value || '').trim(),
        };
    }

    // Repaint this OP's duplicate banner from its remembered decision (_updateId).
    function copRenderDupBanner(form, dupe) {
        const files = (dupe.matches || []).map(m => m.file).filter(Boolean).join(', ');
        if (form && form._updateId) {
            copShowDupWarn('This OP will UPDATE the existing record'
                + (files ? ' (' + files + ')' : '') + ' — backfilling it instead of creating a new file.', 'update');
        } else {
            copShowDupWarn('An OP with these details already exists'
                + (files ? ' (' + files + ')' : '') + '. It will be saved as a new, separate OP.', 'warn');
        }
    }

    // A duplicate was found — ask whether to backfill (update) the existing record. The choice is
    // remembered on the form (_updateId = the pra id to update, or null to save as a new OP).
    function copPromptDuplicate(form, dupe, match) {
        const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
        const file = match.file || 'an existing record';
        Swal.fire({
            icon: 'warning',
            title: 'Possible duplicate OP',
            html: `An OP with the same OP Serial, Serial No, Page No, Vol No and Party 2 already exists:
                <div class="mt-3 text-left text-xs bg-slate-50 border border-slate-200 rounded-lg p-3">
                  <div><b>File:</b> ${esc(file)}</div>
                  <div><b>Party 2:</b> ${esc(match.party_2 || '—')}</div>
                  <div><b>Plot No:</b> ${esc(match.plot_no || '—')}</div>
                  ${match.op_batch ? `<div><b>Batch:</b> ${esc(match.op_batch)}</div>` : ''}
                </div>
                <div class="mt-3 text-left text-xs text-slate-600">Update this existing record with the
                details you're entering (backfill)? The rest of the batch will be saved as new OPs.</div>`,
            showCancelButton: true,
            confirmButtonText: 'Yes, update this record',
            cancelButtonText: 'No, keep as new',
            confirmButtonColor: '#2563eb',
        }).then(res => {
            if (!form) return;
            form._dupMatchId = match.id;                   // remember we've decided on this match
            form._updateId = res.isConfirmed ? match.id : null;
            copRenderDupBanner(form, dupe);
        });
    }

    // Debounced live check of the current OP. Fires once the five required identifiers are present
    // (Plot No excluded). On a match it prompts to backfill the existing record; the decision is
    // stored on the form and honoured at Save. Never blocks saving.
    window.copLiveDupCheck = function () {
        if (copDupTimer) clearTimeout(copDupTimer);
        const ids = copCurrentIdentifiers();
        const cur = copBatch ? copBatch.forms[copBatch.index] : null;
        // Incomplete identifiers → can't be a confirmed duplicate; clear this OP's decision.
        if (COP_DUP_REQUIRED.some(k => !ids[k])) {
            if (cur) { cur._updateId = null; cur._dupMatchId = null; }
            copShowDupWarn('');
            return;
        }

        const seq = ++copDupSeq;
        copDupTimer = setTimeout(() => {
            copCheckDuplicates([Object.assign({ sequence: (copBatch ? copBatch.index + 1 : 1), grantee: ids.party_2 }, ids)])
                .then(dupes => {
                    if (seq !== copDupSeq) return;   // a newer check superseded this one
                    if (!dupes.length) {
                        if (cur) { cur._updateId = null; cur._dupMatchId = null; }
                        copShowDupWarn('');
                        return;
                    }
                    const match = (dupes[0].matches || [])[0] || {};
                    // Already decided on this exact match for this OP — just repaint the banner.
                    if (cur && cur._dupMatchId === match.id) { copRenderDupBanner(cur, dupes[0]); return; }
                    copPromptDuplicate(cur, dupes[0], match);
                });
        }, 450);
    };

    // Per-OP validation for a batch save. Returns an error string, or '' when the OP is fine.
    // Numbers are checked rather than stripped on input, so nothing the user typed is lost.
    function copValidateForm(f) {
        if (!f.op_type) return 'Select the Occupancy Permit (OP) Type.';
        if (!f.status) return 'Select a Status.';
        if (!f.grantee) return "Party 2 (allottee) is required — it becomes the file's Party 1.";
        if (!f.purpose_id) return 'Select a Purpose.';
        if (!f.lga) return 'Select an LGA.';
        if (!(f.op_serial_number || '').trim()) return 'OP Serial Number is required.';
        if (!(f.serial_no || '').trim()) return 'Serial No is required.';
        if (!(f.volume_no || '').trim()) return 'Vol No is required.';
        if (!/^\d+$/.test((f.op_serial_number || '').trim())) return 'OP Serial Number must be a number.';
        if (!/^\d+$/.test((f.serial_no || '').trim())) return 'Serial No must be a number.';
        if (!/^\d+$/.test((f.volume_no || '').trim())) return 'Vol No must be a number.';
        // Page No mirrors Serial No; flag a mismatch rather than silently overwriting it.
        if ((f.page_no || '').trim() !== (f.serial_no || '').trim()) return 'Page No must match Serial No.';
        // Backstop: the fields strip padding on blur and on stash, so this only fires if a value
        // arrived some other way (e.g. seeded from an existing record).
        const padded = v => /^0\d+$/.test(String(v || '').trim());
        if (padded(f.op_serial_number)) return 'OP Serial Number must not be zero-padded (enter 7, not 007).';
        if (padded(f.serial_no)) return 'Serial No must not be zero-padded (enter 7, not 007).';
        if (padded(f.page_no)) return 'Page No must not be zero-padded (enter 7, not 007).';
        if (padded(f.volume_no)) return 'Vol No must not be zero-padded (enter 7, not 007).';
        return '';
    }

    // Build the POST payload for one OP form (shared by the duplicate check and the save).
    function copFormToPayload(f, i) {
        return {
            sequence: i + 1,
            op_type: f.op_type,
            status: f.status,
            grantee: f.grantee,
            // Set when the user chose to backfill a duplicate, or when editing a record that
            // was already saved under a resumed batch (op_id) — both update in place.
            update_op_id: f._updateId || f.op_id || null,
            system_fileno: f.system_fileno || null,
            op_serial_number: f.op_serial_number || null,
            transaction_date: f.transaction_date || null,
            land_use: f.land_use || null,
            purpose_id: f.purpose_id || null,
            purpose: f.purpose_name || null,
            lga: f.lga || null,
            location: f.location || null,
            plot_no: f.plot || null,
            district: (f.district === '__OTHER__') ? (f.district_other || null) : (f.district || null),
            serial_no: f.serial_no || null,
            page_no: f.page_no || null,
            volume_no: f.volume_no || null,
            deeds_date: f.deeds_date || null,
            deeds_time: f.deeds_time || null,
        };
    }

    // Ask the server whether any OP duplicates an existing record; returns a Promise of the
    // duplicates array (empty on any failure — a check outage must not block saving).
    function copCheckDuplicates(ops) {
        return copFetch('{{ route("lands-one-stop-shop.applications.op-check-duplicates") }}', {
            ops: ops.map(o => ({
                sequence: o.sequence, op_serial_number: o.op_serial_number, serial_no: o.serial_no,
                page_no: o.page_no, volume_no: o.volume_no, plot_no: o.plot_no, party_2: o.grantee,
            })),
        })
            .then(({ d }) => (d && d.success && Array.isArray(d.duplicates)) ? d.duplicates : [])
            .catch(err => { console.error('Duplicate check failed', err); return []; });
    }

    window.copSaveBatch = function () {
        if (!copBatch) return;
        copBatchStash();
        for (let i = 0; i < copBatch.forms.length; i++) {
            const problem = copValidateForm(copBatch.forms[i]);
            if (problem) {
                Swal.fire({ icon: 'warning', title: 'Incomplete OP #' + (i + 1), text: problem })
                    .then(() => copBatchLoad(i));
                return;
            }
        }

        const ops = copBatch.forms.map(copFormToPayload);
        // Snapshot the forms before we clear copBatch — used to backfill the commission card.
        const formsSnapshot = copBatch.forms.map(f => Object.assign({}, f));

        // Duplicates the user chose to backfill are saved as UPDATES to the existing rows; the
        // rest are new. Reflect that split in the confirmation.
        const resumeBatch = copBatch.opBatch || null;
        const updateCount = copBatch.forms.filter(f => f._updateId || f.op_id).length;
        const newCount = ops.length - updateCount;
        let summary = (resumeBatch ? 'Update ' : 'Save ') + ops.length + ' OP' + (ops.length === 1 ? '' : 's') + '?';
        if (updateCount > 0) {
            summary = newCount + ' new OP' + (newCount === 1 ? '' : 's') + ' and '
                + updateCount + ' updated OP' + (updateCount === 1 ? '' : 's') + ' — continue?';
        }

        Swal.fire({
            icon: 'question', title: summary,
            text: resumeBatch ? 'Saving into existing batch ' + resumeBatch + '.' : '',
            showCancelButton: true,
            confirmButtonText: resumeBatch ? 'Update Batch' : 'NEXT',
            confirmButtonColor: '#7c3aed',
        }).then(res => { if (res.isConfirmed) copPersistBatch(ops, formsSnapshot, resumeBatch); });
    };

    // Persist the validated batch, then offer to proceed to File Number Commissioning.
    function copPersistBatch(ops, formsSnapshot, resumeBatch) {
        copFetch('{{ route("lands-one-stop-shop.applications.op-batch-capture") }}', { ops, op_batch: resumeBatch || null })
            .then(({ ok, status, d }) => {
                if (status === 419 || status === 401 || status === 403) {
                    // copBatch is still intact — Retry replays this exact save.
                    copSessionExpiredDialog(() => copPersistBatch(ops, formsSnapshot, resumeBatch));
                    return;
                }
                if (!ok || !d.success) {
                    Swal.fire({ icon: 'error', title: 'Could not save batch', text: d.message || 'Unknown error.' });
                    return;
                }
                const opBatch = d.op_batch;
                const savedOps = d.ops || [];
                copBatch = null;
                closeCaptureOpModal();
                // Confirmation: proceed straight to File Number Commissioning for this batch?
                Swal.fire({
                    icon: 'success',
                    title: resumeBatch ? 'OP batch updated successfully' : 'OP batch captured successfully',
                    html: (d.message || (d.count + ' OPs saved under Batch ' + opBatch + '.')) + '<br><br>',
                    showCancelButton: true,
                    confirmButtonText: 'Next',
                    confirmButtonColor: '#2563eb',
                }).then(res => {
                    if (res.isConfirmed) {
                        copReopenCommissionForBatch(opBatch, savedOps, formsSnapshot);
                    } else if (typeof window.closeCommissionFileNoModal === 'function') {
                        window.closeCommissionFileNoModal();
                    }
                });
            })
            .catch(err => {
                console.error('Batch save failed', err);
                Swal.fire({ icon: 'error', title: 'Request failed', text: 'See the console for details.' });
            });
    }

    // Reopen the Commission New File Number card in Batch Mode, pre-filled from the captured OPs.
    // Common fields (Land Use, Purpose, Plot, Location, District, LGA) come from the OP forms so
    // the user only enters File Name + commissioning-only fields. Remembers the batch so the
    // generate step can link each commissioned file to its OP.
    // On pages where the commission form is inline (MLS), there is no modal to reopen — the
    // backfill below still applies to the on-page form.
    /**
     * Go back from Commission New File Number to the OP Batch card, to correct a mistake before
     * the file numbers are commissioned.
     *
     * Records are re-fetched from the server rather than reused from memory, so the card always
     * opens on the batch as actually stored — including any edits or deletions made since.
     */
    window.copBackToBatchCard = function () {
        const pending = window.pendingOpBatch;
        const opBatch = pending && pending.op_batch;
        if (!opBatch) return;

        copLoadBatchForResume(opBatch, '', (qty, resume) => {
            if (!qty || !resume) return;   // load failed — the helper has already reported it
            const a = copAlpine();
            if (a) {
                a.batchMode = true;
                a.batchQuantity = qty;
                // Re-set by copReopenCommissionForBatch when the batch is saved again.
                a.pendingOpBatchId = '';
            }
            // Close the commission modal where there is one (OSS); the MLS form is inline.
            if (typeof window.closeCommissionFileNoModal === 'function') {
                window.closeCommissionFileNoModal();
            }
            window.openBatchCaptureOp(qty, '', resume);
        });
    };

    function copReopenCommissionForBatch(opBatch, savedOps, forms) {
        window.pendingOpBatch = { op_batch: opBatch, ops: savedOps, count: forms.length };

        if (typeof window.openCommissionFileNoModal === 'function') {
            window.openCommissionFileNoModal();
        } else {
            const m = document.getElementById('generateModal');
            if (m) m.classList.remove('hidden');
        }

        setTimeout(() => {
            const a = copAlpine();
            if (!a) return;
            // Leave OP-capture mode so the full commissioning form shows (isOpFormHidden = false)
            // and generate does plain "new" commissioning (we link to the OPs afterwards).
            a.subSource = '';
            a.requireOpSource = false;
            a._currentAllocationSourceType = '';
            a.defaultAllocationType = '';
            a.allocatedByFilter = '';
            a.applicationType = 'new';
            // Drives the "Back to OP Batch" button — set only while an uncommissioned batch is
            // waiting to be commissioned.
            a.pendingOpBatchId = opBatch;

            // Batch-level: land use + purpose from the first OP.
            const first = forms[0] || {};
            if (first.land_use) a.landUse = first.land_use;
            if (first.purpose_id) a.purpose = String(first.purpose_id);

            // Each OP's Party 2, by sequence — shown under File Options as the applicant's
            // Party 1, and what the ToT row's party_1 gets set to when the batch is linked.
            a.opBatchAllottees = forms.map(f => (f.grantee || '').trim());

            // Turn Batch Mode on with the OP count.
            a.batchMode = true;
            a.batchQuantity = forms.length;
            if (typeof a.updateBatchPreview === 'function') a.updateBatchPreview();

            // Per-entry backfill (preserve any file_name/applicant already typed).
            const prev = Array.isArray(a.locationEntries) ? a.locationEntries : [];
            a.locationEntries = forms.map((f, i) => ({
                plotNo: f.plot || '',
                tpNo: (prev[i] && prev[i].tpNo) || '',
                location: f.location || '',
                lga: f.lga || '',
                district: (f.district === '__OTHER__') ? (f.district_other || '') : (f.district || ''),
                tracking_id: null,
                file_name: (prev[i] && prev[i].file_name) || '',
                phone_no: (prev[i] && prev[i].phone_no) || '',
                address: (prev[i] && prev[i].address) || '',
            }));
            a.currentEntryIndex = 0;
            if (typeof a.loadApplicantFromEntry === 'function') a.loadApplicantFromEntry();
            if (typeof a.loadLocationFieldsForEntry === 'function') a.$nextTick(() => a.loadLocationFieldsForEntry());
            if (typeof a.updatePreview === 'function') a.updatePreview();
            if (window.lucide) window.lucide.createIcons();
        }, 300);
    }
</script>

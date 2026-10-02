<div class="sticky bottom-0 bg-white/95 backdrop-blur-sm px-8 py-6 -mx-8 mt-12 border-t border-gray-100 z-20 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)]">
    <div class="flex justify-between items-center gap-4">
        <button type="button" 
            class="inline-flex items-center px-8 py-3 border border-gray-300 text-sm font-medium rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200" 
            id="cancel-btn">
            <i data-lucide="x-circle" class="h-4 w-4 mr-2"></i>
            Cancel
        </button>

        <div class="flex items-center gap-4">
            <button type="button"
                class="inline-flex items-center px-8 py-3 border border-amber-300 text-sm font-medium rounded-lg shadow-sm text-amber-700 bg-amber-50 hover:bg-amber-100 hover:border-amber-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all duration-200"
                id="refresh-form-btn">
                <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                Refresh
            </button>

            {{-- Opens the Property Transaction Details modal directly (backfills from the
                 selected File Number), without creating/updating the file index.
                 Restricted to Supper Admin users.

                 The label follows the data, not the screen: a file that already has rows
                 in file_history_staging / CofO_staging / pra is an UPDATE, and the modal
                 opens with those rows loaded. $hasPropertyRecords is the server's answer
                 for the record being edited; the JS below re-checks whenever the file
                 number on the form changes, so the create screen picks it up too. --}}
            @if(str_contains((string) (auth()->user()->assign_role ?? ''), 'Supper Admin'))
            <button type="button"
                class="inline-flex items-center gap-2 px-4 py-3 border border-emerald-300 text-sm font-medium rounded-lg shadow-sm text-emerald-700 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all duration-200"
                id="add-property-transaction-btn"
                data-add-label="Add Property Transaction Details"
                data-update-label="Update Property Transaction Details">
                <i data-lucide="{{ ($hasPropertyRecords ?? false) ? 'file-pen-line' : 'file-plus-2' }}" class="h-4 w-4" data-role="property-transaction-btn-icon"></i>
                <span id="property-transaction-btn-text">{{ ($hasPropertyRecords ?? false) ? 'Update Property Transaction Details' : 'Add Property Transaction Details' }}</span>
            </button>
            @endif
        </div>
        <button
            type="button"
            class="inline-flex items-center px-8 py-3 border border-transparent text-sm font-medium rounded-lg shadow-lg text-white bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200 transform hover:scale-105"
            id="create-file-btn"
            data-default-label="Create File Index"
            data-edit-label="Update File Index"
            data-mode="create"
        >
            <i data-lucide="save" class="h-4 w-4 mr-2"></i>
            <span data-state="create">Create File Index</span>
            <span data-state="edit" class="hidden">Update File Index</span>
        </button>
    </div>
</div>

<script>
    // Opens the Property Transaction Details modal straight from the indexing form,
    // WITHOUT submitting the form (i.e. without creating/updating the file index).
    (function () {
        if (window.__addPropertyTransactionBtnInit) return;
        window.__addPropertyTransactionBtnInit = true;

        // Locate the Alpine component that holds the property tabs (fileParams).
        function getIndexingComponent() {
            if (typeof Alpine === 'undefined') return null;
            const nodes = document.querySelectorAll('[x-data]');
            for (const el of nodes) {
                try {
                    const data = Alpine.$data(el);
                    if (data && Array.isArray(data.fileParams)) return data;
                } catch (e) { /* not an initialized component */ }
            }
            return null;
        }

        // Resolve the effective file number from whichever input holds it for the
        // current indexing mode (standard Select, KANGIS placeholder, KN-series, MLS…).
        function resolveFileNumber() {
            const candidateIds = [
                'fileno',
                'file-number-display',
                'new_kangis_file_no_hidden',
                'new_kangis_file_no_input',
                'kangis_file_no_hidden',
                'kangis_file_no_input',
                'mls_file_no_hidden',
                'mls_file_no_input',
                'kangis-fileno-placeholder',
            ];
            for (const id of candidateIds) {
                const val = (document.getElementById(id)?.value || '').trim();
                if (val) return val;
            }
            return '';
        }

        // Build the base payload from what's currently on the form.
        function buildPayload() {
            const fileNumber = resolveFileNumber();

            // Pull property details from the currently active property tab.
            let param = {};
            const comp = getIndexingComponent();
            if (comp) {
                const idx = comp.activeTab || 0;
                param = comp.fileParams[idx] || comp.fileParams[0] || {};
            }

            const resolveOther = (val, custom) =>
                (String(val || '').toLowerCase() === 'other') ? (custom || '') : (val || '');

            const district = resolveOther(param.district, param.custom_district);

            return {
                file_number: fileNumber,
                file_title: param.title || '',
                plot_no: param.plot_number || '',
                plot_number: param.plot_number || '',
                tp_no: param.tp_number || '',
                lpkn_no: param.lpkn_no || '',
                lga: param.lga || '',
                district: district,
                location: param.location || '',
                land_use_type: param.land_use_type || '',
                state: 'KANO',
                existing_records: []
            };
        }

        // Keep a form value when it's meaningful, otherwise fall back to the server value.
        function preferForm(formVal, serverVal) {
            const v = String(formVal || '').trim();
            return (v && v !== 'N/A') ? formVal : (serverVal || '');
        }

        // ---- Add vs Update -------------------------------------------------
        // Whether this file already has transaction rows decides both the button
        // label and what the modal opens with. The server answers it for the record
        // being edited ($hasPropertyRecords); on the create screen — and after the
        // operator changes the file number — we ask the same endpoint the legacy edit
        // page used, which searches file_history_staging, CofO_staging, pra and
        // deed_registrations across file-number variants and tags each row with the
        // table it came from (_source), so a saved edit goes back to that same table.
        const PROPERTY_RECORDS_CHECK_BASE = '/api/property-records/check/';

        function setTransactionBtnMode(hasRecords) {
            const btn = document.getElementById('add-property-transaction-btn');
            if (!btn) return;

            const label = hasRecords
                ? (btn.dataset.updateLabel || 'Update Property Transaction Details')
                : (btn.dataset.addLabel || 'Add Property Transaction Details');

            const text = document.getElementById('property-transaction-btn-text');
            if (text) text.textContent = label;

            const icon = btn.querySelector('[data-role="property-transaction-btn-icon"]');
            const wanted = hasRecords ? 'file-pen-line' : 'file-plus-2';
            // Lucide replaces the <i> with an <svg> on first render, so re-tag whichever
            // node is currently there and let it redraw.
            if (icon && icon.getAttribute('data-lucide') !== wanted) {
                icon.setAttribute('data-lucide', wanted);
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    try { window.lucide.createIcons(); } catch (e) { /* icons are cosmetic */ }
                }
            }
        }

        // Every stored transaction for this file, or [] when there are none / on failure.
        // A failed lookup must not block the modal — it just opens in Add mode.
        async function fetchExistingPropertyRecords(fileNumber) {
            const fileNo = String(fileNumber || '').trim();
            if (!fileNo) return [];

            try {
                const res = await fetch(PROPERTY_RECORDS_CHECK_BASE + encodeURIComponent(fileNo), {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return [];

                const data = await res.json();
                return (data && data.success && Array.isArray(data.records)) ? data.records : [];
            } catch (err) {
                console.warn('Existing property record lookup failed; opening in Add mode.', err);
                return [];
            }
        }

        // Refresh just the label (no modal). Debounced so retyping a file number does
        // not fire a request per keystroke.
        let labelProbeTimer = null;
        let lastProbedFileNumber = null;
        function refreshTransactionBtnLabel(immediate) {
            const run = async () => {
                const fileNo = resolveFileNumber();
                if (!fileNo) {
                    lastProbedFileNumber = null;
                    setTransactionBtnMode(false);
                    return;
                }
                if (fileNo === lastProbedFileNumber) return;
                lastProbedFileNumber = fileNo;

                const records = await fetchExistingPropertyRecords(fileNo);
                // The field may have moved on while the request was in flight.
                if (resolveFileNumber() !== fileNo) return;
                setTransactionBtnMode(records.length > 0);
            };

            clearTimeout(labelProbeTimer);
            labelProbeTimer = setTimeout(run, immediate ? 0 : 400);
        }

        // Backfill the payload from the saved record for this file number (property
        // details only), then open the modal.
        //
        // Transactions already stored for the file are loaded and handed to the modal,
        // which renders one card per row and switches itself to Update mode. A file with
        // no stored transactions still opens the single blank card it always did, so
        // first-time capture is unchanged.
        async function openWithBackfill(payload) {
            const registry =
                document.getElementById('registry')?.value ||
                document.getElementById('general-registry')?.value ||
                '';

            try {
                const url = new URL(@json(route('fileindex.check-indexed')), window.location.origin);
                url.searchParams.set('fileno', payload.file_number);
                if (registry) url.searchParams.set('registry', registry);

                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (data && data.exists && data.record) {
                    const r = data.record;
                    payload.file_title    = preferForm(payload.file_title, r.file_title);
                    payload.plot_no       = preferForm(payload.plot_no, r.plot_number);
                    payload.plot_number   = payload.plot_no;
                    payload.tp_no         = preferForm(payload.tp_no, r.tp_no);
                    payload.lpkn_no       = preferForm(payload.lpkn_no, r.lpkn_no);
                    payload.lga           = preferForm(payload.lga, r.lga);
                    payload.district      = preferForm(payload.district, r.district);
                    payload.location      = preferForm(payload.location, r.location);
                    payload.land_use_type = preferForm(payload.land_use_type, r.land_use_type);
                    payload.property_description = payload.location || r.location || '';
                } else if (data && data.grouping_found && data.grouping_record) {
                    const g = data.grouping_record;
                    payload.plot_no     = preferForm(payload.plot_no, g.plot_no || g.plot_number);
                    payload.plot_number = payload.plot_no;
                    payload.tp_no       = preferForm(payload.tp_no, g.tp_no);
                    payload.lga         = preferForm(payload.lga, g.lga || g.lgsaOrCity);
                    payload.district    = preferForm(payload.district, g.district || g.districtName);
                    payload.location    = preferForm(payload.location, g.location);
                    payload.property_description = payload.location || '';
                }

            } catch (err) {
                console.warn('Property transaction backfill lookup failed; opening with form data only.', err);
            }

            // Load whatever is already stored, so the modal opens showing every existing
            // transaction instead of an empty card. Fetched at click time rather than
            // reusing the label probe's result, so a row saved moments ago is included.
            payload.existing_records = await fetchExistingPropertyRecords(payload.file_number);
            setTransactionBtnMode(payload.existing_records.length > 0);

            if (typeof window.openPropertyTransactionModal === 'function') {
                window.openPropertyTransactionModal(payload);
            } else {
                console.error('openPropertyTransactionModal function not found');
            }
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('#add-property-transaction-btn');
            if (!btn) return;

            e.preventDefault();

            const payload = buildPayload();

            // A file number is required to SAVE (the modal's submit + backend enforce it),
            // but we no longer block opening the modal. If one is present, backfill the
            // property details from the saved record; otherwise just open a blank modal.
            if (payload.file_number) {
                openWithBackfill(payload);
            } else if (typeof window.openPropertyTransactionModal === 'function') {
                window.openPropertyTransactionModal(payload);
            } else {
                console.error('openPropertyTransactionModal function not found');
            }
        });

        // Initial state. On the update screen the server already knows the answer, so
        // trust it and skip the request; anywhere else, probe once if a file number is
        // already on the form (e.g. the operator picked an already-indexed file).
        const serverHasPropertyRecords = @json((bool) ($hasPropertyRecords ?? false));

        function initTransactionBtnState() {
            if (!document.getElementById('add-property-transaction-btn')) return;

            if (serverHasPropertyRecords) {
                lastProbedFileNumber = resolveFileNumber() || null;
                setTransactionBtnMode(true);
            } else {
                refreshTransactionBtnLabel(true);
            }

            // The file number can change after load: picked from the smart selector, typed
            // into the KANGIS/KN inputs, or written by the update bootstrap. Watch input and
            // change on the whole form rather than binding to each id, since which field is
            // live depends on the indexing mode.
            ['change', 'input'].forEach(function (evt) {
                document.addEventListener(evt, function (e) {
                    const el = e.target;
                    if (!el || !el.id) return;
                    if (!/^(fileno|file-number-display|new_kangis_file_no_|kangis_file_no_|mls_file_no_|kangis-fileno-placeholder)/.test(el.id)) return;
                    refreshTransactionBtnLabel(false);
                }, true);
            });

            // The update bootstrap fills the fields programmatically, which fires no
            // trusted input event on some browsers — re-probe once it says it is done.
            document.addEventListener('fileindex:update-populated', function () {
                refreshTransactionBtnLabel(true);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTransactionBtnState);
        } else {
            initTransactionBtnState();
        }
    })();
</script>

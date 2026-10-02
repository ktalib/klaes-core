{{-- Reusable CofO duplicate pre-check guard for File Indexing forms.
     Ports the PRA CofO duplicate check (see propertycard/partials/property_form_sweetalert.blade.php)
     so an inline "Existing CofO for this File" card is shown and the form is LOCKED when a
     matching Certificate of Occupancy already exists for the file number.

     Wrapped in @once so window.CofoDuplicateGuard is defined a single time even when several
     consumers (e.g. cofo_details + property_transaction_modal) are on the same page. --}}
@once
<script>
(function () {
    if (window.CofoDuplicateGuard) return;

    const CHECK_URL = @json(route('property-records.check-cofo-duplicate'));

    // Only these instrument types trigger the CofO duplicate pre-check (mirrors
    // PropertyRecordController::COFO_TRANSACTION_TYPES).
    const COFO_TRANSACTION_TYPES = [
        'Certificate of Occupancy',
        'ST Certificate of Occupancy',
        'SLTR Certificate of Occupancy',
    ];

    function escHtml(v) {
        if (v === null || v === undefined || v === '') return '—';
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fmtDate(v) {
        if (!v) return '—';
        try { return String(v).substring(0, 10); } catch (_) { return escHtml(v); }
    }

    function isCofoType(type) {
        return COFO_TRANSACTION_TYPES.includes(String(type || '').trim());
    }

    // Build the inline card HTML (styled like the PRA "Existing CofO for this File" card).
    // `warnOnly` consumers do not block saving — the server holds duplicates back for
    // confirmation instead — so the card is worded as a heads-up, not a wall.
    function buildCardHtml(records, locked, warnOnly) {
        const rowsHtml = records.map((r, i) => {
            const party2 = r.party_2 || r.Grantee || r.Assignee || r.Lessee || r.Mortgagee || '—';
            const regParts = [r.volumeNo, r.pageNo, r.serialNo].filter(Boolean).join(' / ');
            const loc = [r.plot_no, r.location || r.lgsaOrCity].filter(Boolean).join(', ') || '—';
            const fileNo = r.mlsFNo || r.kangisFileNo || r.NewKANGISFileno || r.np_fileno || r.temp_fileno || r.fileno;
            return `
                <div class="p-3 bg-white border ${locked ? 'border-red-300' : 'border-amber-200'} rounded-lg shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1 min-w-0 pr-2">
                            <div class="flex items-center">
                                <span class="mr-1.5 text-red-600 font-bold text-[10px]">${i + 1}.</span>
                                <div class="text-[11px] font-bold text-gray-800 truncate">${escHtml(r.transaction_type || 'Certificate of Occupancy')}</div>
                                <span class="ml-1 px-1.5 py-0.5 text-[8px] font-bold bg-red-100 text-red-700 rounded border border-red-200 uppercase">CofO</span>
                            </div>
                            <div class="text-[10px] text-gray-500 font-medium">
                                <span class="font-bold text-gray-700">Reg No.:</span>
                                <span class="text-blue-900 font-bold">${escHtml(r.regNo || regParts || '—')}</span>
                            </div>
                            <div class="text-[10px] text-blue-600 font-semibold mt-0.5">
                                <span class="font-bold text-gray-700">Transaction Date:</span> ${escHtml(fmtDate(r.transaction_date))}
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-gray-600 border-t border-gray-50 pt-1.5 mt-1.5 leading-tight">
                        <span class="font-semibold text-gray-700">File No:</span> ${escHtml(fileNo)}
                        <span class="text-gray-300 mx-1">|</span>
                        <span class="font-semibold text-gray-700">Party 2:</span> ${escHtml(party2)}
                        <span class="text-gray-300 mx-1">|</span>
                        <span class="font-semibold text-gray-700">Location:</span> ${escHtml(loc)}
                    </div>
                    <div class="text-[10px] text-gray-600 leading-tight mt-1">
                        <span class="font-semibold text-gray-700">Captured by:</span>
                        <span class="text-gray-800 font-semibold">${escHtml(r.captured_by_name || r.captured_by || r.created_by)}</span>
                    </div>
                </div>
            `;
        }).join('');

        const headerClass = (locked && !warnOnly)
            ? 'bg-red-50 border border-red-300 text-red-800'
            : 'bg-amber-50 border border-amber-200 text-amber-800';

        const notice = warnOnly
            ? 'A Certificate of Occupancy already exists for this file number. You can still save — anything that looks like a duplicate is held back and returned for you to confirm.'
            : (locked
                ? 'A Certificate of Occupancy already exists for this file number. Saving has been blocked to prevent a duplicate.'
                : 'A Certificate of Occupancy already exists for this file number. The form will be blocked if the remaining details also match.');

        return `
            <div class="rounded-lg ${headerClass} p-3 mb-2">
                <div class="flex items-center justify-between mb-1">
                    <div class="text-[10px] font-bold uppercase tracking-tight flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.2 16a2 2 0 001.73 3z"/>
                        </svg>
                        Existing CofO for this File (${records.length})
                    </div>
                </div>
                <div class="text-[11px]">${escHtml(notice)}</div>
            </div>
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">${rowsHtml}</div>
        `;
    }

    /**
     * Create a guard instance.
     * @param {Object} config
     * @param {HTMLElement} config.card   Container element to render the duplicate card into.
     * @param {Function}    config.getFields  Returns the match params object, or null to skip.
     * @param {Function}    config.setLocked  (locked, message, records) => void — lock/unlock the form.
     * @returns An object with run() and clear() methods.
     */
    function create(config) {
        const card = config.card;
        const getFields = config.getFields;
        const setLocked = config.setLocked || function () {};
        // When true, ANY existing CofO for the file number blocks the form (only one
        // CofO per file). When false, blocking follows the backend hit-count rule.
        const lockOnAnyMatch = !!config.lockOnAnyMatch;
        // Informational consumers: the card is a heads-up and setLocked is still called
        // (so the consumer can flag rows), but nothing is worded as a block.
        const warnOnly = !!config.warnOnly;

        let inFlight = false;
        let lastKey = '';
        let locked = false;

        function clearCard() {
            if (card) { card.innerHTML = ''; card.classList.add('hidden'); }
        }

        function unlock(silent) {
            if (locked) {
                locked = false;
                setLocked(false, null, []);
            }
            if (!silent) clearCard();
        }

        async function run(opts) {
            opts = opts || {};
            const params = typeof getFields === 'function' ? getFields() : null;

            if (!params || !params.file_number || !isCofoType(params.transaction_type)) {
                lastKey = '';
                unlock(false);
                return;
            }

            const key = JSON.stringify(params);
            if (!opts.force && key === lastKey) return;
            lastKey = key;

            if (inFlight) return;
            inFlight = true;

            try {
                const url = new URL(CHECK_URL, window.location.origin);
                Object.entries(params).forEach(([k, v]) => {
                    if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, v);
                });

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) { console.warn('[CofO guard] HTTP', res.status); return; }

                const data = await res.json().catch(() => null);
                if (!data || data.duplicate_type !== 'cofo_staging') { unlock(false); return; }

                const matches = Array.isArray(data.matches) ? data.matches : [];
                if (matches.length === 0) { unlock(false); return; }

                const shouldLock = lockOnAnyMatch ? matches.length > 0 : !!data.lock_form;
                if (card) {
                    card.innerHTML = buildCardHtml(matches, shouldLock && !warnOnly, warnOnly);
                    card.classList.remove('hidden');
                }

                if (shouldLock && !locked) {
                    locked = true;
                    const msg = data.message
                        || 'A Certificate of Occupancy already exists for this file number.';
                    setLocked(true, msg, matches);
                } else if (!shouldLock && locked) {
                    locked = false;
                    setLocked(false, null, matches);
                }
            } catch (err) {
                console.warn('[CofO guard] pre-check failed:', err);
            } finally {
                inFlight = false;
            }
        }

        return { run, clear: () => { unlock(false); lastKey = ''; }, isLocked: () => locked };
    }

    window.CofoDuplicateGuard = { create, isCofoType, COFO_TRANSACTION_TYPES };
})();
</script>
@endonce

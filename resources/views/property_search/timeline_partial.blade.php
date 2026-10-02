{{--
  timeline_partial.blade.php
  Renders the timeline content WITHOUT the full-page layout.
  Loaded via AJAX by property-timeline-modal.js when ?mode=partial is passed.
--}}
<style>
    .tl-source-tag {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
        border: 1px solid transparent;
    }
    .tl-source-tag-file_history_staging { background:#dbeafe;color:#1e40af;border-color:#bfdbfe; }
    .tl-source-tag-CofO_staging         { background:#d1fae5;color:#065f46;border-color:#a7f3d0; }
    .tl-source-tag-pra                  { background:#fef3c7;color:#92400e;border-color:#fde68a; }
    .tl-source-tag-deed_registrations   { background:#ede9fe;color:#5b21b6;border-color:#ddd6fe; }
    /* Synthetic rows emitted by the Legal Search report engine (not a real source table). */
    .tl-source-tag-file-commissioning    { background:#f3f4f6;color:#374151;border-color:#e5e7eb; }
    .tl-rot-tag { display:inline-flex;align-items:center;padding:0.15rem 0.5rem;border-radius:9999px;
                  font-size:0.6rem;font-weight:700;letter-spacing:0.04em;white-space:nowrap;
                  background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe; }

    .ptl-summary-grid { display:grid; grid-template-columns: repeat(2,1fr); gap:1rem; margin-bottom:1.25rem; }
    @media(min-width:640px){ .ptl-summary-grid { grid-template-columns: repeat(4,1fr); } }

    .ptl-timeline-line { position:absolute; left:1.25rem; top:0; bottom:0; width:2px; background:#e5e7eb; z-index:0; }
    .ptl-timeline-wrap { position:relative; padding-left:3rem; }
    .ptl-dot { position:absolute; left:0.6rem; width:1.25rem; height:1.25rem; border-radius:50%; border:2px solid; display:flex; align-items:center; justify-content:center; z-index:1; background:#fff; }
    .ptl-card { background:#fff; border:1px solid #e5e7eb; border-radius:0.5rem; padding:1rem 1.25rem; box-shadow:0 1px 3px rgba(0,0,0,.07); }
</style>

{{-- Modal header with title and close button --}}
<div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 bg-white sticky top-0 z-10">
    <div class="flex items-center gap-2">
        <i class="fas fa-history text-indigo-500"></i>
        <h3 class="text-base font-semibold text-gray-800">Property Timeline</h3>
    </div>
    <button type="button" data-dismiss="modal"
        class="text-gray-400 hover:text-gray-600 transition-colors rounded-full p-1 hover:bg-gray-100"
        title="Close">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<div class="p-4 space-y-5" id="ptl-container">
    {{-- Summary strip --}}
    <div class="ptl-summary-grid">
        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-100">
            <p class="text-xs text-gray-500 mb-1">File Number</p>
            <p class="font-semibold text-gray-800 text-sm truncate" id="ptl-file-number">—</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-100">
            <p class="text-xs text-gray-500 mb-1">Prop ID</p>
            <p class="font-semibold text-gray-800 text-sm" id="ptl-prop-id">—</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-100">
            <p class="text-xs text-gray-500 mb-1">Total Transactions</p>
            <p class="font-semibold text-gray-800 text-sm" id="ptl-total">0</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-100">
            <p class="text-xs text-gray-500 mb-1">Location</p>
            <p class="font-semibold text-gray-800 text-sm truncate" id="ptl-location">—</p>
        </div>
    </div>

    {{-- Source breakdown badges --}}
    <div class="flex flex-wrap gap-2 pb-1" id="ptl-source-badges"></div>

    {{-- Timeline items split: Weighted vs Non-Weighted --}}
    <div id="ptl-empty" class="text-center py-10 text-gray-400 hidden">
        <i class="fas fa-history fa-2x mb-3 opacity-40"></i>
        <p>No transactions found for this property.</p>
    </div>

    <div id="ptl-weighted-section" class="space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800"></h4>
            <span id="ptl-weighted-count" class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">0</span>
        </div>
        <div class="relative ptl-timeline-wrap">
            <div class="ptl-timeline-line"></div>
            <div class="space-y-5" id="ptl-items-weighted"></div>
        </div>
    </div>

    <div id="ptl-nonweighted-section" class="space-y-3 pt-2 border-t border-gray-100">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Supporting Records</h4>
            <span id="ptl-nonweighted-count" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">0</span>
        </div>
        <div class="relative ptl-timeline-wrap">
            <div class="ptl-timeline-line"></div>
            <div class="space-y-5" id="ptl-items-nonweighted"></div>
        </div>
    </div>

    {{-- Link to full-page timeline --}}
    <div class="text-right pt-2 border-t border-gray-100">
        <a id="ptl-fullpage-link" href="#" target="_blank"
           class="text-sm text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1">
            <i class="fas fa-external-link-alt text-xs"></i> Open full timeline
        </a>
    </div>
</div>

<script>
(function () {
    const payload = @json($historyPayload);

    const e = (v) => {
        if (v === null || v === undefined || String(v).trim() === '') return '<span class="text-gray-300">—</span>';
        return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    };

    const sourceLabel = {
        'file_history_staging': 'File History',
        'CofO_staging': 'CofO',
        'pra': 'PRA',
        'deed_registrations': 'Deed Reg.',
        'File Commissioning': 'File Commissioning',
        'Temporary File': 'Temporary File',
    };

    // source_table can be a synthetic label containing spaces ("File Commissioning"), which is
    // not a valid CSS class fragment — slugify before interpolating into tl-source-tag-*.
    const sourceClass = (src) => 'tl-source-tag-' + String(src || '')
        .trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

    const dotColors = {
        'file_history_staging': '#3b82f6',
        'CofO_staging': '#10b981',
        'pra': '#f59e0b',
        'deed_registrations': '#8b5cf6',
        'File Commissioning': '#6b7280',
        'Temporary File': '#6b7280',
    };

    // Populate summary
    document.getElementById('ptl-file-number').textContent = payload.fileNumber || '—';
    document.getElementById('ptl-prop-id').textContent    = payload.propId     || '—';
    document.getElementById('ptl-total').textContent      = payload.totalTransactions || 0;
    document.getElementById('ptl-location').textContent   = payload.location   || '—';

    // Full-page link
    const params = new URLSearchParams();
    if (payload.propId)      params.set('prop_id',     payload.propId);
    if (payload.fileNumber)  params.set('file_number', payload.fileNumber);
    const fullLink = document.getElementById('ptl-fullpage-link');
    if (fullLink) fullLink.href = '/property-search/timeline?' + params.toString();

    const txns = payload.transactions || [];
    const weightedTxnsSorted = payload.weighted || [];
    const nonWeightedTxnsSorted = payload.omitted || [];

    const badgesEl = document.getElementById('ptl-source-badges');
    const totalEl = document.getElementById('ptl-total');

    const renderSourceBadges = (rows) => {
        if (!badgesEl) return;
        badgesEl.innerHTML = '';

        if (!rows.length) return;

        const counts = {};
        rows.forEach((t) => {
            const src = t?.source_table || '';
            counts[src] = (counts[src] || 0) + 1;
        });

        Object.entries(counts).forEach(([src, cnt]) => {
            const span = document.createElement('span');
            span.className = `tl-source-tag ${sourceClass(src)}`;
            span.textContent = `${sourceLabel[src] || src}: ${cnt}`;
            badgesEl.appendChild(span);
        });
    };

    // Timeline items
    const weightedItemsEl = document.getElementById('ptl-items-weighted');
    const nonWeightedItemsEl = document.getElementById('ptl-items-nonweighted');
    const weightedSectionEl = document.getElementById('ptl-weighted-section');
    const nonWeightedSectionEl = document.getElementById('ptl-nonweighted-section');
    const weightedCountEl = document.getElementById('ptl-weighted-count');
    const nonWeightedCountEl = document.getElementById('ptl-nonweighted-count');
    const emptyEl  = document.getElementById('ptl-empty');

    if (!txns.length) {
        emptyEl.classList.remove('hidden');
        if (weightedSectionEl) weightedSectionEl.classList.add('hidden');
        if (nonWeightedSectionEl) nonWeightedSectionEl.classList.add('hidden');
        renderSourceBadges([]);
        if (totalEl) totalEl.textContent = '0';
        return;
    }

    // Keep summary cards consistent with what the modal actually renders.
    renderSourceBadges(txns);
    if (totalEl) totalEl.textContent = String(weightedTxnsSorted.length);

    if (weightedCountEl) weightedCountEl.textContent = String(weightedTxnsSorted.length);
    if (nonWeightedCountEl) nonWeightedCountEl.textContent = String(nonWeightedTxnsSorted.length);

    if (weightedSectionEl) {
        weightedSectionEl.classList.toggle('hidden', weightedTxnsSorted.length === 0);
        const header = weightedSectionEl.querySelector('h4');
        if (header) header.textContent = 'Primary Timeline';
    }
    if (nonWeightedSectionEl) {
        nonWeightedSectionEl.classList.toggle('hidden', nonWeightedTxnsSorted.length === 0);
        const header = nonWeightedSectionEl.querySelector('h4');
        if (header) header.textContent = 'Supporting Records';
    }

    const renderTxnCard = (t) => {
        const color  = dotColors[t.source_table] || '#6b7280';
        const label  = sourceLabel[t.source_table] || t.source_table;
        const srcCls = sourceClass(t.source_table);

        const parties = [
            t.primary_party   || t.party_1 || t.grantor || '',
            t.secondary_party || t.party_2 || t.grantee || '',
            t.party_3 || '',
        ].filter(Boolean).join(' → ');

        const regPart = t.reg_no || t.regNo
            ? `<span class="text-xs text-gray-400 ml-2">Reg: ${e(t.reg_no || t.regNo)}</span>`
            : '';

        return `
        <div class="relative">
            <div class="ptl-dot" style="border-color:${color}; top: 0.75rem;">
                <span style="width:6px;height:6px;border-radius:50%;background:${color};display:block;"></span>
            </div>
            <div class="ptl-card">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="tl-source-tag ${srcCls}">${e(label)}</span>
                        <span class="text-sm font-semibold text-gray-800">${e(t.transaction_type)}</span>
                        ${t.root_of_title ? `<span class="tl-rot-tag">RoT: ${e(t.root_of_title)}</span>` : ''}
                        ${regPart}
                    </div>
                    <span class="text-xs text-gray-500 whitespace-nowrap">${e(t.display_date || t.transaction_date)}</span>
                </div>
                ${parties ? `<p class="text-sm text-gray-600">${e(parties)}</p>` : ''}
                ${t.location ? `<p class="text-xs text-gray-400 mt-1"><i class="fas fa-map-pin mr-1"></i>${e(t.location)}</p>` : ''}
            </div>
        </div>`;
    };

    weightedTxnsSorted.forEach((t) => {
        if (weightedItemsEl) weightedItemsEl.insertAdjacentHTML('beforeend', renderTxnCard(t));
    });

    nonWeightedTxnsSorted.forEach((t) => {
        if (nonWeightedItemsEl) nonWeightedItemsEl.insertAdjacentHTML('beforeend', renderTxnCard(t));
    });
})();
</script>

<!-- Quick Actions Card -->
<div class="bg-white rounded-lg border border-gray-200 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
            <i data-lucide="file-search" class="h-5 w-5"></i>
            Quick Actions
        </h3>
        <p class="text-sm text-gray-600 mt-1">Perform common file tracking operations</p>
    </div>
    <div class="p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <!-- Search Files -->
            <button id="quick-search-files" class="quick-action-btn" data-action="search-files" title="Search existing file trackers">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-blue-100 rounded-xl">
                        <i data-lucide="file-search" class="h-7 w-7 text-blue-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Search Files</span>
                        <span class="block text-sm text-gray-500">Find trackers</span>
                    </div>
                </div>
            </button>

            {{-- Quick Search moved to a standalone page (Digital Archive → Quick Search). Kept here, commented out.
            <!-- Quick Search & File Location -->
            <button type="button" id="quick-qs-open" class="quick-action-btn js-qs-open" title="Locate a file and its current status instantly">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-indigo-100 rounded-xl">
                        <i data-lucide="map-pin" class="h-7 w-7 text-indigo-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Quick Search &amp; Location</span>
                        <span class="block text-sm text-gray-500">Locate a file</span>
                    </div>
                </div>
            </button>
            --}}

            <!-- Office List -->
            <button id="quick-office-list" class="quick-action-btn" data-action="office-list" title="View all offices and departments">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-green-100 rounded-xl">
                        <i data-lucide="building" class="h-7 w-7 text-green-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Office List</span>
                        <span class="block text-sm text-gray-500">View offices</span>
                    </div>
                </div>
            </button>

            <!-- Track Status -->
            <button id="quick-track-status" class="quick-action-btn" data-action="track-status" title="Check file tracking status">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-orange-100 rounded-xl">
                        <i data-lucide="activity" class="h-7 w-7 text-orange-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Track Status</span>
                        <span class="block text-sm text-gray-500">Check status</span>
                    </div>
                </div>
            </button>

            <!-- Statistics -->
            <button id="quick-statistics" class="quick-action-btn" data-action="statistics" title="View tracking statistics">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-purple-100 rounded-xl">
                        <i data-lucide="bar-chart-3" class="h-7 w-7 text-purple-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Statistics</span>
                        <span class="block text-sm text-gray-500">View stats</span>
                    </div>
                </div>
            </button>

            <!-- Assignment Center -->
            <button id="quick-assignment-center" class="quick-action-btn" data-action="assignment-center" title="Manage file assignments">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-indigo-100 rounded-xl">
                        <i data-lucide="user-plus" class="h-7 w-7 text-indigo-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Assignment Center</span>
                        <span class="block text-sm text-gray-500">Assign & review</span>
                    </div>
                </div>
            </button>

            @if(($module ?? '') === 'new_kangis')
            <!-- Scan QR — New KANGIS department log -->
            <button id="quick-scan-qr" class="quick-action-btn border-amber-300 hover:border-amber-500 hover:bg-amber-50" data-action="scan-qr" title="Scan QR code to log file into next department">
                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-amber-100 rounded-xl">
                        <i data-lucide="scan-line" class="h-7 w-7 text-amber-600"></i>
                    </div>
                    <div class="text-center">
                        <span class="block text-base font-medium text-gray-700">Scan QR</span>
                        <span class="block text-sm text-amber-600">Log to next dept</span>
                    </div>
                </div>
            </button>
            @endif

            <!-- Update Movement moved to history table action menu -->
        </div>
    </div>
</div>

<!-- Quick Actions Styles -->
<style>
.quick-action-btn {
    @apply w-full p-5 border border-gray-200 rounded-xl transition-all duration-200 hover:border-blue-400 hover:shadow-lg hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2;
}

.quick-action-btn:hover .text-gray-700 {
    @apply text-blue-700;
}

.quick-action-btn:hover .text-gray-500 {
    @apply text-blue-600;
}

.quick-action-btn:active {
    @apply transform scale-95;
}
</style>

<!-- ===================== Quick Search & File Location Modal ===================== -->
<div id="qs-modal" class="fixed inset-0 z-[1000] hidden">
    <div class="absolute inset-0 bg-black/50" data-qs-close></div>
    <div class="relative mx-auto mt-16 w-full max-w-2xl px-4">
        <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700">
                <h3 class="text-white font-semibold flex items-center gap-2">
                    <i data-lucide="map-pin" class="h-5 w-5"></i> Quick Search &amp; File Location
                </h3>
                <button type="button" data-qs-close class="text-white/80 hover:text-white">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="p-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Enter File Number</label>
                <div class="flex gap-2">
                    <input id="qs-input" type="text" readonly autocomplete="off"
                        placeholder="Click “Select” to choose a file number"
                        class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm uppercase cursor-pointer focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <button type="button" id="qs-pick"
                        class="inline-flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-900 whitespace-nowrap">
                        <i data-lucide="folder-search" class="h-4 w-4"></i><span>Select</span>
                    </button>
                    <button type="button" id="qs-btn"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        <i data-lucide="search" class="h-4 w-4"></i><span>Search</span>
                    </button>
                </div>
                <p class="mt-2 text-xs text-gray-500">Every search returns a clear outcome and next action.</p>
                <div id="qs-result" class="mt-5 hidden"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const RESOLVE_URL = "{{ route('create-file-tracker.quick-search.resolve') }}";
    const SLIP_URL    = "{{ route('create-file-tracker.slip') }}";
    const FR_URL      = "{{ route('create-file-tracker.file-request') }}";
    const UPDATE_URL  = "{{ route('create-file-tracker.quick-search.update-status') }}";
    const CSRF        = "{{ csrf_token() }}";

    const STATUS_OPTIONS = [
        ['IN_TRANSIT', 'In Transit'],
        ['IN_ARCHIVE', 'In Archive'],
        ['IN_POOL_OFFICE', 'In Pool Office'],
        ['FILE_NOT_FOUND', 'File Not Found'],
        ['REFER_TO_ORIGINAL_REGISTRY', 'Refer to Original Registry'],
    ];

    const modal  = document.getElementById('qs-modal');
    const openTriggers = document.querySelectorAll('.js-qs-open');
    const input  = document.getElementById('qs-input');
    const btn    = document.getElementById('qs-btn');
    const pickBtn= document.getElementById('qs-pick');
    const result = document.getElementById('qs-result');
    if (!modal) return;

    function pickFileNumber() {
        if (typeof GlobalFileNoModal === 'undefined') { search(); return; }
        GlobalFileNoModal.open({
            callback: function (data) {
                const fileNumber = (data.fileNumber || '').toString().trim();
                if (!fileNumber) return;
                input.value = fileNumber;
                search();
            }
        });
    }

    const STATUS_META = {
        IN_TRANSIT:                 { label: 'In Transit',                 cls: 'bg-amber-100 text-amber-800 border-amber-300',  icon: 'truck' },
        IN_ARCHIVE:                 { label: 'In Archive',                 cls: 'bg-green-100 text-green-800 border-green-300',  icon: 'archive' },
        IN_POOL_OFFICE:             { label: 'In Pool Office',             cls: 'bg-blue-100 text-blue-800 border-blue-300',    icon: 'folder-search' },
        FILE_NOT_FOUND:             { label: 'File Not Found',             cls: 'bg-red-100 text-red-800 border-red-300',       icon: 'alert-triangle' },
        REFER_TO_ORIGINAL_REGISTRY: { label: 'Refer to Original Registry', cls: 'bg-gray-100 text-gray-800 border-gray-300',    icon: 'corner-up-right' },
    };

    const esc = v => (v == null ? '' : String(v)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const row = (l, v) => v ? `<div class="flex justify-between gap-4 py-2 border-b border-gray-100 last:border-0">
        <span class="text-xs font-medium text-gray-500">${esc(l)}</span>
        <span class="text-sm text-gray-800 text-right">${esc(v)}</span></div>` : '';

    function openModal()  { modal.classList.remove('hidden'); setTimeout(() => input.focus(), 50); if (window.lucide) lucide.createIcons(); }
    function closeModal() { modal.classList.add('hidden'); result.classList.add('hidden'); result.innerHTML=''; input.value=''; }

    function actionButtons(d) {
        const out = [];
        const fno = encodeURIComponent(d.file_number);
        if (d.status === 'IN_TRANSIT' && d.file_tracker_id) {
            out.push(`<a href="/create-file-tracker/${d.file_tracker_id}/request-sheet" target="_blank"
                class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                <i data-lucide="printer" class="h-4 w-4"></i> Print Tracking Confirmation Slip</a>`);
        } else if (d.slip_variant) {
            const labels = { tracking_sheet:'Print Request Sheet', missing:'Print Missing File Slip', refer_registry:'Print Refer-to-Registry Slip' };
            out.push(`<a href="${SLIP_URL}?file_number=${fno}&variant=${d.slip_variant}" target="_blank"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                <i data-lucide="printer" class="h-4 w-4"></i> ${labels[d.slip_variant] || 'Print Slip'}</a>`);
        }
        if (d.can_send_fr) {
            out.push(`<button type="button" data-fr class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <i data-lucide="send" class="h-4 w-4"></i> Send File Search Request to SCB Monitor</button>`);
        }
        return out.join('');
    }

    function render(d) {
        const meta = STATUS_META[d.status] || { label:d.status, cls:'bg-gray-100 text-gray-800 border-gray-300', icon:'file' };
        result.innerHTML = `
            <div class="rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <div class="text-lg font-bold text-gray-900">${esc(d.file_number)}</div>
                        <div class="text-sm text-gray-500">${esc(d.file_title || '—')}</div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold ${meta.cls}">
                        <i data-lucide="${meta.icon}" class="h-3.5 w-3.5"></i> ${esc(meta.label)}
                        ${d.manual ? '<span title="Manually set" class="ml-1 text-[10px] font-bold opacity-70">(manual)</span>' : ''}</span>
                </div>
                <div class="px-5 py-3">
                    ${row('Registry', d.registry)}
                    ${row('Rack / Shelf', d.rack_shelf)}
                    ${row('Current Location', d.current_location)}
                    ${row('Receiving Officer', d.receiving_officer_name)}
                    ${row('Duration with holder', d.duration_with_holder)}
                    ${row('Tracking ID', d.tracking_id)}
                    <div class="mt-3 rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3">
                        <div class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Next Action</div>
                        <div class="text-sm text-indigo-900 mt-0.5">${esc(d.next_action)}</div>
                    </div>

                    <details class="mt-3 rounded-lg border border-gray-200">
                        <summary class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-gray-600 flex items-center gap-2">
                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Update Location Status
                        </summary>
                        <div class="px-4 pb-4 pt-1 space-y-2">
                            <select data-us-status class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                ${STATUS_OPTIONS.map(o => `<option value="${o[0]}" ${o[0]===d.status?'selected':''}>${o[1]}</option>`).join('')}
                            </select>
                            <input data-us-loc type="text" placeholder="Location / note (optional)" value="${esc(d.current_location || '')}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <button type="button" data-us-save class="inline-flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">
                                <i data-lucide="save" class="h-4 w-4"></i> Save Status
                            </button>
                            <div data-us-msg class="text-xs"></div>
                        </div>
                    </details>
                </div>
                <div class="px-5 py-4 border-t border-gray-100 flex flex-wrap gap-2">${actionButtons(d)}</div>
            </div>`;
        result.classList.remove('hidden');
        const frBtn = result.querySelector('[data-fr]');
        if (frBtn) frBtn.addEventListener('click', () => sendFR(d, frBtn));
        const saveBtn = result.querySelector('[data-us-save]');
        if (saveBtn) saveBtn.addEventListener('click', () => updateStatus(d, result.querySelector('[data-us-status]').value, result.querySelector('[data-us-loc]').value, saveBtn));
        if (window.lucide) lucide.createIcons();
    }

    async function updateStatus(d, status, location, saveBtn) {
        const msg = result.querySelector('[data-us-msg]');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Saving…';
        if (window.lucide) lucide.createIcons();
        try {
            const res = await fetch(UPDATE_URL, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                body: JSON.stringify({ file_number: d.file_number, status, current_location: location }),
            });
            const json = await res.json();
            if (json.success) { render(json.data); }   // re-render with updated (manual) status
            else if (msg) { msg.className = 'text-xs text-red-600'; msg.textContent = json.message || 'Could not update.'; saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> Save Status'; if (window.lucide) lucide.createIcons(); }
        } catch (e) {
            if (msg) { msg.className = 'text-xs text-red-600'; msg.textContent = 'Network error — please try again.'; }
            saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> Save Status';
            if (window.lucide) lucide.createIcons();
        }
    }

    function renderError(msg) {
        result.innerHTML = `<div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">${esc(msg)}</div>`;
        result.classList.remove('hidden');
    }

    async function search() {
        const q = input.value.trim();
        if (!q) { input.focus(); return; }
        btn.disabled = true; result.classList.add('hidden');
        try {
            const res = await fetch(`${RESOLVE_URL}?query=${encodeURIComponent(q)}`, { headers:{ 'Accept':'application/json' } });
            const json = await res.json();
            json.success ? render(json.data) : renderError(json.message || 'Search failed.');
        } catch (e) { renderError('Network error — please try again.'); }
        finally { btn.disabled = false; }
    }

    async function sendFR(d, frBtn, force = false) {
        frBtn.disabled = true;
        frBtn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Sending…';
        if (window.lucide) lucide.createIcons();
        try {
            const res = await fetch(FR_URL, {
                method:'POST',
                headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                body: JSON.stringify({ file_number:d.file_number, file_title:d.file_title, current_location:d.current_location, resolved_status:d.status, force: force ? 1 : 0 }),
            });
            const json = await res.json();
            if (json.success) {
                frBtn.outerHTML = `<span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-800">
                    <i data-lucide="check" class="h-4 w-4"></i> File Request ${esc(json.data?.request_no || '')} sent to SCB Monitors</span>`;
            } else if (json.duplicate) {
                const ex = json.existing || {};
                frBtn.disabled = false; frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Send File Search Request to SCB Monitor';
                const ok = confirm(
                    'This file has already been requested.\n\n' +
                    'Requested by: ' + (ex.requester_name || '—') +
                    (ex.request_no ? '\nRequest: ' + ex.request_no : '') +
                    '\nStatus: ' + (ex.status || '') + (ex.requested_at ? ' (' + ex.requested_at + ')' : '') +
                    '\n\nIt has already been sent to SCB and is awaiting a response.\n\nDo you want to request it anyway?'
                );
                if (ok) { sendFR(d, frBtn, true); return; }
            } else {
                frBtn.disabled = false; frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Retry Send';
                alert(json.message || 'Could not send the request.');
            }
            if (window.lucide) lucide.createIcons();
        } catch (e) {
            frBtn.disabled = false; frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Retry Send';
            alert('Network error — please try again.');
        }
    }

    openTriggers.forEach(el => el.addEventListener('click', openModal));
    btn.addEventListener('click', search);
    if (pickBtn) pickBtn.addEventListener('click', pickFileNumber);
    input.addEventListener('click', pickFileNumber);   // read-only: open picker instead of typing
    modal.querySelectorAll('[data-qs-close]').forEach(el => el.addEventListener('click', closeModal));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });

    // Allow deep-link auto-open: /create-file-tracker?quicksearch=1
    if (new URLSearchParams(window.location.search).get('quicksearch') === '1') openModal();
})();
</script>

@include('create_file_tracker_page.partials.fr-modal')

{{-- Live toast when an SCB Monitor responds (Found / Not Found) to a File Request you raised --}}
<script>
(function () {
    const LIST_URL = "{{ url('notifications/api') }}";
    const seen = new Set();
    let primed = false;

    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function toast(title, body, found) {
        const cont = document.getElementById('page-toast-container');
        if (!cont) return;
        const accent = found ? '#16a34a' : '#dc2626';
        const el = document.createElement('div');
        el.className = 'pointer-events-auto w-80 max-w-[90vw] bg-white rounded-xl shadow-lg border px-4 py-3 flex gap-3 items-start';
        el.style.borderColor = accent + '55';
        el.innerHTML = `
            <div style="color:${accent};margin-top:1px;"><i data-lucide="${found ? 'check-circle' : 'alert-triangle'}" class="h-5 w-5"></i></div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-gray-900">${esc(title)}</div>
                <div class="text-xs text-gray-600 mt-0.5">${esc(body)}</div>
            </div>`;
        cont.appendChild(el);
        if (window.lucide) lucide.createIcons();
        setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 9000);
    }

    async function poll() {
        try {
            const res = await fetch(`${LIST_URL}?status=unread&perPage=30`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const json = await res.json();
            const items = (json && json.data && json.data.items) || [];
            for (const n of items) {
                if (n.type !== 'file_search_request') continue;
                if (!/found/i.test(n.title)) continue;  // FR responses only, not the initial FR
                if (seen.has(n.id)) continue;
                seen.add(n.id);
                if (primed) toast(n.title, n.body || '', /:\s*found/i.test(n.title));
            }
            primed = true;  // first pass primes existing unread without toasting
        } catch (e) { /* silent */ }
    }

    poll();
    setInterval(poll, 30000);
})();
</script>
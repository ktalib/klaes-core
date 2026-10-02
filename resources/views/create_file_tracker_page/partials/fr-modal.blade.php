<!-- ===================== Send File (Search) Request (direct) Modal ===================== -->
<div id="fr-modal" class="fixed inset-0 z-[1000] hidden">
    <div class="absolute inset-0 bg-black/50" data-fr-close></div>
    <div class="relative mx-auto mt-16 w-full max-w-lg px-4">
        <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700">
                <h3 class="text-white font-semibold flex items-center gap-2">
                    <i data-lucide="send" class="h-5 w-5"></i> Send File Search Request to SCB Monitors
                </h3>
                <button type="button" data-fr-close class="text-white/80 hover:text-white">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-xs text-gray-500">Routes a physical-search request to all SCB Monitors via the mobile app and email.</p>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">File Number <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <input id="fr-file-number" type="text" readonly autocomplete="off"
                            class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm uppercase cursor-pointer"
                            placeholder="Click “Select” to choose a file number">
                        <button type="button" id="fr-pick"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900 whitespace-nowrap">
                            <i data-lucide="folder-search" class="h-4 w-4"></i> Select
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">File Title</label>
                    <input id="fr-file-title" type="text" readonly tabindex="-1"
                        class="w-full rounded-lg border border-gray-200 bg-gray-100 text-gray-500 px-3 py-2.5 text-sm cursor-not-allowed"
                        placeholder="Auto-filled from file number">
                </div>
                <div id="fr-feedback" class="hidden"></div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" data-fr-close class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200">Cancel</button>
                <button type="button" id="fr-send" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                    <i data-lucide="send" class="h-4 w-4"></i> Send Request
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const FR_URL      = "{{ route('create-file-tracker.file-request') }}";
    const RESOLVE_URL = "{{ route('create-file-tracker.quick-search.resolve') }}";
    const CSRF        = "{{ csrf_token() }}";

    const modal    = document.getElementById('fr-modal');
    const triggers = document.querySelectorAll('.js-fr-open');
    const fno      = document.getElementById('fr-file-number');
    const ftitle   = document.getElementById('fr-file-title');
    const pickBtn  = document.getElementById('fr-pick');
    const sendBtn  = document.getElementById('fr-send');
    const feedback = document.getElementById('fr-feedback');
    if (!modal) return;

    // Resolved metadata captured silently (location/status power the SCB Monitor email).
    let resolved = { current_location: null, status: null };

    const esc = v => (v == null ? '' : String(v)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function openModal()  { modal.classList.remove('hidden'); feedback.classList.add('hidden'); feedback.innerHTML=''; if (window.lucide) lucide.createIcons(); }
    function closeModal() { modal.classList.add('hidden'); fno.value=''; ftitle.value=''; resolved = { current_location:null, status:null }; }

    function note(type, msg) {
        const cls = type === 'ok' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700';
        feedback.className = `rounded-lg border px-3 py-2 text-sm ${cls}`;
        feedback.innerHTML = esc(msg);
        feedback.classList.remove('hidden');
    }

    // Resolve a file number to backfill its title + (silent) expected location.
    async function resolveFile(fileNumber) {
        try {
            const res = await fetch(`${RESOLVE_URL}?query=${encodeURIComponent(fileNumber)}`, { headers:{ 'Accept':'application/json' } });
            const json = await res.json();
            if (json.success) {
                resolved.current_location = json.data.current_location || null;
                resolved.status = json.data.status || null;
                if (!ftitle.value && json.data.file_title) ftitle.value = json.data.file_title;
            }
        } catch (e) { /* non-fatal — FR can still be sent */ }
    }

    function pickFileNumber() {
        if (typeof GlobalFileNoModal === 'undefined') {
            note('err', 'File number selector is not available on this page.');
            return;
        }
        GlobalFileNoModal.open({
            callback: function (data) {
                const fileNumber = (data.fileNumber || '').toString().trim();
                if (!fileNumber) return;
                fno.value = fileNumber;
                ftitle.value = (data.file_title || data.file_name || '').toString().trim();
                resolveFile(fileNumber);
            }
        });
    }

    // Render the duplicate warning with a "Request Anyway" action.
    function noteDuplicate(fileNumber, ex) {
        feedback.className = 'rounded-lg border border-amber-300 bg-amber-50 px-3 py-3 text-sm';
        feedback.innerHTML = `
            <div class="flex items-start gap-2 text-amber-800">
                <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5 shrink-0"></i>
                <div>
                    <div class="font-semibold">This file has already been requested.</div>
                    <div class="text-xs mt-0.5">Requested by <span class="font-semibold">${esc(ex.requester_name || '—')}</span>
                        ${ex.request_no ? '· ' + esc(ex.request_no) : ''} · ${esc(ex.status || '')}${ex.requested_at ? ' · ' + esc(ex.requested_at) : ''}.
                        It has already been sent to SCB and is awaiting a response.</div>
                </div>
            </div>
            <div class="mt-2">
                <button type="button" id="fr-anyway" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                    <i data-lucide="send" class="h-3.5 w-3.5"></i> Request Anyway</button>
            </div>`;
        feedback.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
        document.getElementById('fr-anyway').addEventListener('click', () => send(true));
    }

    async function send(force = false) {
        const fileNumber = fno.value.trim();
        if (!fileNumber) { note('err', 'Please select a file number first.'); return; }
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Sending…';
        if (window.lucide) lucide.createIcons();
        try {
            if (resolved.current_location === null) await resolveFile(fileNumber);
            const res = await fetch(FR_URL, {
                method:'POST',
                headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                body: JSON.stringify({
                    file_number: fileNumber,
                    file_title: ftitle.value.trim(),
                    current_location: resolved.current_location || '',
                    resolved_status: resolved.status || 'MANUAL',
                    force: force ? 1 : 0,
                }),
            });
            const json = await res.json();
            if (json.success) {
                note('ok', `File Request ${json.data?.request_no || ''} sent to SCB Monitors.`);
                fno.value=''; ftitle.value=''; resolved = { current_location:null, status:null };
            } else if (json.duplicate) {
                noteDuplicate(fileNumber, json.existing || {});
            } else {
                note('err', json.message || 'Could not send the request.');
            }
        } catch (e) { note('err', 'Network error — please try again.'); }
        finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Send Request';
            if (window.lucide) lucide.createIcons();
        }
    }

    triggers.forEach(el => el.addEventListener('click', openModal));
    pickBtn.addEventListener('click', pickFileNumber);
    fno.addEventListener('click', pickFileNumber);
    sendBtn.addEventListener('click', () => send());
    modal.querySelectorAll('[data-fr-close]').forEach(el => el.addEventListener('click', closeModal));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
})();
</script>

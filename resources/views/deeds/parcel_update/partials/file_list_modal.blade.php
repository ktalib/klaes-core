{{-- Shared file-number list modal (S/N + search). Opened by any element with class
     pu-newfiles-open and data-files='[...]', optional data-from and data-title.
     Used by the SPU/APU New FileNo(s) column and the Decommissioned Files Related File cell. --}}
@once
@push('scripts')
<div id="pu-newfiles-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="pu-newfiles-title">
    <div class="flex max-h-[85vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h3 id="pu-newfiles-title" class="text-sm font-black uppercase tracking-wide text-slate-800">New FileNo(s)</h3>
                <p id="pu-newfiles-sub" class="text-xs text-slate-500"></p>
            </div>
            <button type="button" class="pu-newfiles-close rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">&times;</button>
        </div>
        <div class="border-b border-slate-100 px-5 py-3">
            <input id="pu-newfiles-search" type="search" placeholder="Search file number or title…"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-3">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-white text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr><th class="w-16 py-2">S/N</th><th class="py-2 pr-4">File No</th><th id="pu-newfiles-title-col" class="py-2">File Title</th></tr>
                </thead>
                <tbody id="pu-newfiles-rows" class="divide-y divide-slate-50 font-mono font-bold text-emerald-700"></tbody>
            </table>
            <p id="pu-newfiles-none" class="hidden py-6 text-center text-xs text-slate-400">No file number or title matches.</p>
        </div>
        <div class="flex justify-end border-t border-slate-100 px-5 py-3">
            <button type="button" class="pu-newfiles-close rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200">Close</button>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('pu-newfiles-modal');
    var rows = document.getElementById('pu-newfiles-rows');
    var search = document.getElementById('pu-newfiles-search');
    var none = document.getElementById('pu-newfiles-none');
    var files = [];      // [{f: file no, t: title}]
    var hasTitles = false;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render() {
        var q = search.value.trim().toUpperCase();
        var list = q ? files.filter(function (x) { return (x.f + ' ' + x.t).toUpperCase().indexOf(q) !== -1; }) : files;
        // S/N follows the order shown, so a filtered list still counts 1..N.
        rows.innerHTML = list.map(function (x, i) {
            return '<tr><td class="py-1.5 font-sans text-xs font-normal text-slate-400">' + (i + 1) + '</td>'
                + '<td class="py-1.5 pr-4 whitespace-nowrap">' + esc(x.f) + '</td>'
                + (hasTitles ? '<td class="py-1.5 font-sans text-xs font-semibold text-slate-700">' + (x.t ? esc(x.t) : '<span class="text-slate-300">&mdash;</span>') + '</td>' : '')
                + '</tr>';
        }).join('');
        none.classList.toggle('hidden', list.length > 0);
    }

    function open(btn) {
        var raw;
        try { raw = JSON.parse(btn.getAttribute('data-files') || '[]'); } catch (e) { raw = []; }
        // Items are plain file numbers, or [file no, title] pairs (New FileNo(s) column).
        files = (Array.isArray(raw) ? raw : []).map(function (x) {
            return Array.isArray(x) ? { f: String(x[0] || ''), t: String(x[1] || '') } : { f: String(x || ''), t: '' };
        });
        hasTitles = files.some(function (x) { return x.t !== ''; });
        document.getElementById('pu-newfiles-title-col').classList.toggle('hidden', !hasTitles);
        var from = btn.getAttribute('data-from') || '';
        document.getElementById('pu-newfiles-title').textContent = btn.getAttribute('data-title') || 'New FileNo(s)';
        document.getElementById('pu-newfiles-sub').textContent =
            files.length + ' file number' + (files.length === 1 ? '' : 's') + (from ? ' from ' + from : '');
        search.value = '';
        render();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        search.focus();
    }

    function close() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Delegated: rows are redrawn by DataTables / pagination, so no per-button binding.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.pu-newfiles-open');
        if (btn) { e.preventDefault(); e.stopPropagation(); open(btn); return; }
        if (e.target === modal || e.target.closest('.pu-newfiles-close')) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
    search.addEventListener('input', render);
})();
</script>
@endpush
@endonce

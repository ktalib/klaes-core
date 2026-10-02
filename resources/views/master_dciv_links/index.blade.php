@extends('layouts.app')

@section('page-title') {{ __($pageTitle ?? 'DCIV Master Link Table') }} @endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 text-slate-900">
    @include('admin.header')

    <div class="max-w-7xl mx-auto px-6 lg:px-10 pt-5 pb-10 space-y-4">

        <!-- Header -->
        <div class="bg-gradient-to-r from-white via-rose-50 to-rose-100/60 border border-rose-100 rounded-2xl px-5 py-3 shadow-sm flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-rose-900 leading-tight">DCIV Master Link Table</h1>
                <p class="text-xs text-rose-500">DCIV / LPCC files linked to their related Land, SLTR and ST files.</p>
            </div>
            <div class="text-right shrink-0">
                <p class="text-[0.6rem] uppercase tracking-widest text-rose-500">Total DCIV Files</p>
                <p class="text-lg font-semibold text-slate-800 leading-none">{{ number_format($total ?? 0) }}</p>
            </div>
        </div>

        <!-- Filters + table card -->
        <div class="bg-white rounded-3xl shadow-xl p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                    <input id="mdl-search" type="text"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-300 focus:border-rose-400"
                        placeholder="DCIV no, related no, title, reason" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Related Type</label>
                    <select id="mdl-type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-300">
                        <option value="">All</option>
                        <option value="Land">Land</option>
                        <option value="SLTR">SLTR</option>
                        <option value="ST">ST</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-sm" id="mdl-table">
                    <thead class="bg-slate-50 text-slate-700 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left w-10">S/N</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">DCIV FileNo</th>
                            <th class="px-3 py-2 text-left">DCIV Title</th>
                            <th class="px-3 py-2 text-left">Related FileNo</th>
                            <th class="px-3 py-2 text-left">Reason</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Date Created</th>
                        </tr>
                    </thead>
                    <tbody id="mdl-tbody" class="divide-y divide-slate-100">
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-slate-600" id="mdl-summary">Showing -- of -- results</p>
                <div class="flex items-center gap-3">
                    <button id="mdl-prev" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm disabled:opacity-50" disabled>Prev</button>
                    <span id="mdl-page" class="text-sm font-semibold text-slate-700">--</span>
                    <button id="mdl-next" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm disabled:opacity-50" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Related Files Modal -->
<div id="rf-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-rose-50">
            <h3 id="rf-modal-title" class="text-sm font-semibold text-rose-900"></h3>
            <button id="rf-modal-close" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-3 px-5 py-2 bg-slate-50 border-b border-slate-100">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">FileNo</span>
            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 text-right">Related File Title</span>
        </div>
        <div id="rf-modal-body" class="px-5 py-2 max-h-80 overflow-y-auto divide-y divide-slate-100"></div>
    </div>
</div>

<script>
(function () {
    const dataUrl = @json(route('master-dciv-links.data'));
    const state = { page: 1, perPage: 50, search: '', type: '' };
    let debounce = null;

    const tbody = document.getElementById('mdl-tbody');
    const summary = document.getElementById('mdl-summary');
    const pageLabel = document.getElementById('mdl-page');
    const prevBtn = document.getElementById('mdl-prev');
    const nextBtn = document.getElementById('mdl-next');
    const searchInput = document.getElementById('mdl-search');
    const typeSelect = document.getElementById('mdl-type');

    function esc(v) {
        if (v === null || v === undefined) return '';
        return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    const typeClasses = {
        'Land':  'bg-emerald-50 text-emerald-700 border border-emerald-200',
        'SLTR':  'bg-violet-50 text-violet-700 border border-violet-200',
        'ST':    'bg-amber-50 text-amber-700 border border-amber-200',
        'Other': 'bg-slate-100 text-slate-600 border border-slate-200',
    };

    function filePill(f) {
        const cls = typeClasses[f.type] || typeClasses['Other'];
        return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${cls} whitespace-nowrap">${esc(f.file_number || '')}</span>`;
    }

    function relatedFilesCell(relatedFiles, uid) {
        if (!relatedFiles || relatedFiles.length === 0) {
            return '<span class="text-slate-400">-</span>';
        }

        if (relatedFiles.length === 1) {
            return filePill(relatedFiles[0]);
        }

        // Multiple related files — show first + count button that opens a modal
        const first = relatedFiles[0];
        const extraCount = relatedFiles.length - 1;
        const encoded = encodeURIComponent(JSON.stringify(relatedFiles));

        return `<div class="flex items-center gap-1.5 flex-wrap">
            ${filePill(first)}
            <button type="button" data-related-files="${encoded}"
                class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 cursor-pointer">
                +${extraCount}
            </button>
        </div>`;
    }

    // Modal elements
    const modal = document.getElementById('rf-modal');
    const modalTitle = document.getElementById('rf-modal-title');
    const modalBody = document.getElementById('rf-modal-body');
    const modalClose = document.getElementById('rf-modal-close');

    function openRelatedModal(relatedFiles, dcivNo) {
        modalTitle.textContent = 'Related Files — ' + (dcivNo || '');
        modalBody.innerHTML = relatedFiles.map(f => `
            <div class="flex items-center justify-between gap-3 py-2.5 border-b border-slate-100 last:border-0">
                <div>${filePill(f)}</div>
                <div class="text-sm text-slate-600 text-right">${f.title ? esc(f.title) : '<span class="text-slate-400 text-xs">No title</span>'}</div>
            </div>
        `).join('');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    modalClose.addEventListener('click', function () {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    });

    // Delegated click handler on tbody rows to open modal
    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-related-files]');
        if (!btn) return;
        const relatedFiles = JSON.parse(decodeURIComponent(btn.dataset.relatedFiles));
        const row = btn.closest('tr');
        const dcivCell = row ? row.querySelector('td:nth-child(2) span') : null;
        openRelatedModal(relatedFiles, dcivCell ? dcivCell.textContent : '');
    });

    async function load() {
        tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Loading...</td></tr>';
        const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
        if (state.search.trim() !== '') params.set('search', state.search.trim());
        if (state.type !== '') params.set('type', state.type);

        try {
            const res = await fetch(dataUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            const rows = json.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">No links found.</td></tr>';
            } else {
                let counter = (state.page - 1) * state.perPage;
                tbody.innerHTML = rows.map((r) => {
                    counter++;
                    const uid = 'rf_' + r.dciv_file_number.replace(/[^a-z0-9]/gi, '_') + '_' + counter;
                    return `<tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 text-slate-500">${counter}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">${esc(r.dciv_file_number)}</span>
                        </td>
                        <td class="px-3 py-2 text-slate-700">${r.dciv_file_title ? esc(r.dciv_file_title) : '<span class="text-slate-400">-</span>'}</td>
                        <td class="px-3 py-2">${relatedFilesCell(r.related_files, uid)}</td>
                        <td class="px-3 py-2 text-slate-600 max-w-xs whitespace-normal break-words">${r.dciv_reason ? esc(r.dciv_reason) : '<span class="text-slate-400">-</span>'}</td>
                        <td class="px-3 py-2 text-slate-500 whitespace-nowrap">${r.created_at ? esc(String(r.created_at).substring(0, 10)) : '-'}</td>
                    </tr>`;
                }).join('');
            }

            const meta = json.meta || {};
            const total = meta.total || 0;
            const lastPage = meta.last_page || 1;
            const start = total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
            const end = Math.min(start + state.perPage - 1, total);
            summary.textContent = `Showing ${start.toLocaleString()} to ${end.toLocaleString()} of ${total.toLocaleString()} results`;
            pageLabel.textContent = `Page ${state.page} of ${lastPage}`;
            prevBtn.disabled = state.page <= 1;
            nextBtn.disabled = state.page >= lastPage;
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-red-500">Error loading links.</td></tr>';
        }
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(function () {
            state.search = searchInput.value;
            state.page = 1;
            load();
        }, 300);
    });

    typeSelect.addEventListener('change', function () {
        state.type = typeSelect.value;
        state.page = 1;
        load();
    });

    prevBtn.addEventListener('click', function () { if (state.page > 1) { state.page--; load(); } });
    nextBtn.addEventListener('click', function () { state.page++; load(); });

    load();
})();
</script>
@endsection

@extends('layouts.app')

@section('page-title') {{ __($pageTitle ?? 'Related File Numbers') }} @endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 text-slate-900">
    @include('admin.header')

    <div class="max-w-7xl mx-auto px-6 lg:px-10 pt-5 pb-10 space-y-4">

        <!-- Header -->
        <div class="bg-gradient-to-r from-white via-orange-50 to-orange-100/60 border border-orange-100 rounded-2xl px-5 py-3 shadow-sm flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-orange-900 leading-tight">Related File Numbers</h1>
            </div>
            <div class="text-right shrink-0">
                <p class="text-[0.6rem] uppercase tracking-widest text-orange-500">Total</p>
                <p class="text-lg font-semibold text-slate-800 leading-none">{{ number_format($totals->total_rows ?? 0) }}</p>
            </div>
        </div>

        <!-- Filters + table card -->
        <div class="bg-white rounded-3xl shadow-lg border border-slate-200 p-6 space-y-6">
            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-3">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">Search</label>
                    <input id="rfn-search" type="text"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-orange-300 focus:border-orange-400"
                        placeholder="related_fileno, file_number, title, party, comment" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">Transaction Type</label>
                    <select id="rfn-comment-type" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-orange-300">
                        <option value="">All</option>
                        <option value="kangis">KANGIS Recertification</option>
                        <option value="mlpp">Land &amp; Physical Planning Recertification</option>
                        <option value="merger">Merger</option>
                        <option value="subdivision">Subdivision</option>
                        <option value="change_of_purpose">Change of Purpose</option>
                        <option value="none">Untyped</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full min-w-[1380px] text-sm" id="rfn-table">
                    <thead class="bg-slate-50/80 text-slate-600 text-[11px] font-semibold uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">S/N</th>
                            <th class="px-3 py-2 text-left">Prop ID</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Related File No</th>
                            <th class="px-3 py-2 text-left">Related Title</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Parent File</th>
                            <th class="px-3 py-2 text-left">Parent Title</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Transaction Type</th>
                            <th class="px-3 py-2 text-left">Location</th>
                            <th class="px-3 py-2 text-left">Source</th>
                            <th class="px-3 py-2 text-left">Comment</th>
                        </tr>
                    </thead>
                    <tbody id="rfn-tbody" class="divide-y divide-slate-100 text-[13px] text-slate-700"></tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-slate-600">
                <div id="rfn-summary">Loading…</div>
                <div class="flex items-center gap-2">
                    <button id="rfn-prev" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                    <span id="rfn-page" class="font-medium text-slate-700">1 / 1</span>
                    <button id="rfn-next" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                    <select id="rfn-per-page" class="border border-slate-300 rounded-lg px-2 py-1 text-sm">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const tbody   = document.getElementById('rfn-tbody');
    const summary = document.getElementById('rfn-summary');
    const pageLbl = document.getElementById('rfn-page');
    const prevBtn = document.getElementById('rfn-prev');
    const nextBtn = document.getElementById('rfn-next');
    const search  = document.getElementById('rfn-search');
    const cType   = document.getElementById('rfn-comment-type');
    const perSel  = document.getElementById('rfn-per-page');

    let page = 1;
    let lastPage = 1;
    const endpoint = "{{ route('related-file-number.records') }}";

    const e = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const textOrDash = (val, dash = '\u2014') => {
        const text = String(val ?? '').replace(/\s+/g, ' ').trim();
        return text ? text : dash;
    };

    const titleCase = (val, dash = '\u2014') => {
        const base = textOrDash(val, '');
        if (!base) return dash;
        return base
            .toLowerCase()
            .replace(/\b([a-z])/g, (m) => m.toUpperCase());
    };

    const upperCase = (val, dash = '\u2014') => {
        const base = textOrDash(val, '');
        if (!base) return dash;
        return base.toUpperCase();
    };

    const commentClass = (c) => {
        if (!c) return '';
        if (c.startsWith('KANGIS')) return 'text-orange-700 font-medium';
        if (c.startsWith('MINISTRY')) return 'text-amber-700 font-medium';
        return '';
    };

    const fileNumberCell = (val) => {
        const normalized = upperCase(val);
        const v = e(normalized);
        // Visually mark KANGIS-style related file numbers in orange
        if (normalized !== '\u2014' && /^(KNML|MLKN|KNGP|KN\s)/i.test(normalized)) {
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-md bg-orange-50 text-orange-700 border border-orange-200 text-[11px] font-semibold whitespace-nowrap">${v}</span>`;
        }
        return `<span class="font-semibold tracking-wide text-slate-700 whitespace-nowrap">${v}</span>`;
    };

    const typeBadge = (t) => {
        if (!t) return '<span class="text-slate-400 text-xs">-</span>';
        const map = {
            'KANGIS Recertification':                    'bg-orange-50 text-orange-700 border-orange-200',
            'Ministry of Land & Physical Planning Recertification': 'bg-amber-50 text-amber-700 border-amber-200',
            'Merger':                                    'bg-purple-50 text-purple-700 border-purple-200',
            'Subdivision':                               'bg-yellow-50 text-yellow-700 border-yellow-200',
            'Change of Purpose':                         'bg-blue-50 text-blue-700 border-blue-200',
        };
        const cls = map[t] || 'bg-slate-50 text-slate-700 border-slate-200';
        return `<span class="px-2 py-0.5 rounded-full text-[0.65rem] uppercase tracking-wide border ${cls}">${e(t)}</span>`;
    };

    const render = (rows, meta) => {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="px-3 py-10 text-center text-slate-400">No results.</td></tr>';
            return;
        }
        const startSn = ((meta.page || 1) - 1) * (meta.per_page || 1);
        tbody.innerHTML = rows.map((r, idx) => `
            <tr class="align-top odd:bg-white even:bg-slate-50/40 hover:bg-orange-50/30 transition-colors">
                <td class="px-3 py-3 text-xs font-semibold text-slate-400">${startSn + idx + 1}</td>
                <td class="px-3 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 font-mono text-[11px] font-semibold text-slate-600">${e(textOrDash(r.prop_id))}</span>
                </td>
                <td class="px-3 py-2 align-top whitespace-nowrap">${fileNumberCell(r.related_fileno)}</td>
                <td class="px-3 py-3 text-slate-700 leading-snug">${e(titleCase(r.related_file_title || r.file_title || r.party_2))}</td>
                <td class="px-3 py-3 whitespace-nowrap">${fileNumberCell(r.file_number)}</td>
                <td class="px-3 py-3 text-slate-700 leading-snug">${e(titleCase(r.file_title || r.party_2))}</td>
                <td class="px-3 py-2 align-top whitespace-nowrap">${typeBadge(r.transaction_type)}</td>
                <td class="px-3 py-3 text-slate-600 max-w-xs truncate" title="${e(textOrDash(r.location, ''))}">${e(titleCase(r.location))}</td>
                <td class="px-3 py-3 whitespace-nowrap">
                    <span class="text-[0.65rem] uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">${e(textOrDash(r.source_table).replace(/_/g, ' '))}</span>
                </td>
                <td class="px-3 py-3 ${commentClass(r.comment)}">${e(textOrDash(r.comment))}</td>
            </tr>
        `).join('');
    };

    const load = async () => {
        tbody.innerHTML = '<tr><td colspan="10" class="px-3 py-10 text-center text-slate-400">Loading…</td></tr>';
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', perSel.value);
        if (search.value.trim()) url.searchParams.set('search', search.value.trim());
        if (cType.value) url.searchParams.set('comment_type', cType.value);

        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            const meta = json.meta || {};
            render(json.data || [], meta);
            lastPage = meta.last_page || 1;
            pageLbl.textContent = `${meta.page || 1} / ${lastPage}`;
            const from = ((meta.page - 1) * meta.per_page) + 1;
            const to = Math.min(meta.page * meta.per_page, meta.total);
            summary.textContent = meta.total ? `Showing ${from.toLocaleString()}–${to.toLocaleString()} of ${meta.total.toLocaleString()}` : 'No results.';
            prevBtn.disabled = (meta.page || 1) <= 1;
            nextBtn.disabled = (meta.page || 1) >= lastPage;
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="10" class="px-3 py-10 text-center text-red-500">Failed to load results.</td></tr>';
        }
    };

    // Debounce search
    let t;
    search.addEventListener('input', () => {
        clearTimeout(t);
        t = setTimeout(() => { page = 1; load(); }, 300);
    });
    cType.addEventListener('change', () => { page = 1; load(); });
    perSel.addEventListener('change', () => { page = 1; load(); });
    prevBtn.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    nextBtn.addEventListener('click', () => { if (page < lastPage) { page++; load(); } });

    load();
})();
</script>
@endsection

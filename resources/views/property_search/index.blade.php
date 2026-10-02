@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-2.0.7/datatables.min.css">
    <style>
        .source-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .source-badge-fh {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .source-badge-cofo {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .source-badge-pra {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .source-badge-deed {
            background: #ede9fe;
            color: #5b21b6;
            border-color: #ddd6fe;
        }

        .txn-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.2rem 0.65rem;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .txn-badge-default { background: #f3f4f6; color: #374151; border-color: #e5e7eb; }
        .txn-badge-transfer { background: #dbeafe; color: #1e3a8a; border-color: #bfdbfe; }
        .txn-badge-assignment { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
        .txn-badge-cofo { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .txn-badge-mortgage { background: #ffedd5; color: #9a3412; border-color: #fed7aa; }
        .txn-badge-lease { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .txn-badge-surrender { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .txn-badge-revoke { background: #fce7f3; color: #9d174d; border-color: #fbcfe8; }

        /* ── Stats Grid ───────────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1.25rem;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #d1d5db;
            border-radius: 0.625rem;
            padding: 1.1rem 1.25rem 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 10px rgba(0,0,0,.04);
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            position: relative;
            overflow: hidden;
            transition: box-shadow .15s;
        }
        .stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.10); }

        .stat-card-blue    { border-left-color: #3b82f6; }
        .stat-card-violet  { border-left-color: #8b5cf6; }
        .stat-card-emerald { border-left-color: #10b981; }
        .stat-card-amber   { border-left-color: #f59e0b; }

        .stat-icon {
            position: absolute;
            top: 0.9rem;
            right: 1rem;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon-blue    { background: #eff6ff; color: #3b82f6; }
        .stat-icon-violet  { background: #f5f3ff; color: #8b5cf6; }
        .stat-icon-emerald { background: #ecfdf5; color: #10b981; }
        .stat-icon-amber   { background: #fffbeb; color: #f59e0b; }

        .stat-label {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }

        .stat-value {
            font-size: 2rem;
            line-height: 1.1;
            color: #0f172a;
            font-weight: 800;
            margin-top: 0.2rem;
        }

        .stat-sub {
            margin-top: 0.25rem;
            color: #6b7280;
            font-size: 0.73rem;
            line-height: 1.4;
        }

        .top-types {
            margin-top: 0.4rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .timeline-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .timeline-badge .timeline-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 9999px;
            background: currentColor;
            display: inline-flex;
        }

        .timeline-badge-muted {
            background: #f3f4f6;
            color: #6b7280;
            border-color: #e5e7eb;
        }

        .timeline-badge-info {
            background: #e0f2fe;
            color: #0369a1;
            border-color: #bae6fd;
        }

        .timeline-badge-warn {
            background: #fef3c7;
            color: #b45309;
            border-color: #fde68a;
        }

        .timeline-badge-strong {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fecaca;
        }

        .file-number-link {
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .file-number-link:hover {
            text-decoration: underline;
        }

        /* Keep DataTables controls aligned and prevent vertical pagination buttons */
        #property-search-table_wrapper .dataTables_length,
        #property-search-table_wrapper .dataTables_filter,
        #property-search-table_wrapper .dataTables_info,
        #property-search-table_wrapper .dataTables_paginate {
            margin-top: 0.75rem;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            color: #374151;
        }

        #property-search-table_wrapper .dataTables_filter {
            text-align: right;
        }

        #property-search-table_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.35rem 0.55rem;
            margin-left: 0.5rem;
            min-width: 220px;
        }

        #property-search-table_wrapper .dataTables_paginate {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
        }

        #property-search-table_wrapper .dataTables_paginate .paginate_button,
        #property-search-table_wrapper .dt-paging .dt-paging-button {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.6rem !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem;
            background: #ffffff !important;
            color: #374151 !important;
            margin: 0 !important;
            line-height: 1 !important;
        }

        #property-search-table_wrapper .dataTables_paginate .paginate_button.current,
        #property-search-table_wrapper .dt-paging .dt-paging-button.current {
            background: #1d4ed8 !important;
            border-color: #1d4ed8 !important;
            color: #ffffff !important;
        }

        #property-search-table_wrapper .dataTables_paginate .paginate_button:hover,
        #property-search-table_wrapper .dt-paging .dt-paging-button:hover {
            background: #eff6ff !important;
            border-color: #93c5fd !important;
            color: #1e3a8a !important;
        }

        #property-search-table_wrapper .dataTables_scroll {
            overflow: auto;
        }

        /* ── Table Improvements ─────────────────────────────────────────── */
        .ps-table-wrap {
            border: 1px solid #e5e7eb;
            border-radius: 0.625rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.05), 0 2px 8px rgba(0,0,0,.04);
        }

        #property-search-table {
            border-collapse: collapse;
            width: 100% !important;
        }

        #property-search-table thead tr {
            background: #f8fafc;
            border-bottom: 2px solid #e5e7eb;
        }

        #property-search-table thead th {
            padding: 0.7rem 0.9rem;
            font-size: 0.68rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            white-space: nowrap;
            vertical-align: middle;
        }

        #property-search-table tbody td {
            padding: 0.65rem 0.9rem;
            font-size: 0.82rem;
            color: #1e293b;
            vertical-align: middle;
            white-space: nowrap;
            border-bottom: 1px solid #f1f5f9;
        }

        /* Long-text columns – ellipsis on overflow */
        #property-search-table td.col-party,
        #property-search-table td.col-location {
            max-width: 190px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #property-search-table tbody tr:hover td {
            background: #f8faff;
        }

        #property-search-table tbody tr:last-child td {
            border-bottom: none;
        }

        .ps-table-wrap .dataTables_wrapper {
            padding: 0.75rem 1rem 1rem;
        }

        .ps-table-wrap .dataTables_filter {
            margin-bottom: 0.5rem;
        }

        .ps-table-wrap .dataTables_info,
        .ps-table-wrap .dataTables_paginate {
            margin-top: 0.75rem;
        }
    </style>
@endpush

@section('page-title', $PageTitle ?? 'Property Records')

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="max-w-7xl mx-auto px-6 py-12 space-y-8">
            <header class="space-y-2">
                <h1 class="text-2xl font-semibold text-gray-900">{{ $PageTitle ?? 'Property Records' }}</h1>
                <p class="text-gray-600">
                    Unified view of property transactions from File History, CofO, PRA, and Deed Registrations.
                </p>
            </header>

            <section class="stats-grid" id="property-dashboard-stats">

                {{-- Card 1: Total Records --}}
                <article class="stat-card stat-card-blue">
                    <span class="stat-icon stat-icon-blue">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="17" height="17">
                            <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/>
                        </svg>
                    </span>
                    <p class="stat-label">Total Records</p>
                    <p class="stat-value" id="stat-total-records">—</p>
                    <p class="stat-sub">Latest record per Prop ID / File</p>
                </article>

                {{-- Card 2: With Timeline --}}
                <article class="stat-card stat-card-violet">
                    <span class="stat-icon stat-icon-violet">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="17" height="17">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <p class="stat-label">Records With Timeline</p>
                    <p class="stat-value" id="stat-multi">—</p>
                    <p class="stat-sub">Groups with more than 1 transaction</p>
                </article>

                {{-- Card 3: Single Records --}}
                <article class="stat-card stat-card-emerald">
                    <span class="stat-icon stat-icon-emerald">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="17" height="17">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <p class="stat-label">Single Records</p>
                    <p class="stat-value" id="stat-single">—</p>
                    <p class="stat-sub">Groups with only 1 transaction</p>
                </article>

                {{-- Card 4: Top Transaction Types (collapsible) --}}
                <article class="stat-card stat-card-amber">
                    <span class="stat-icon stat-icon-amber">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="17" height="17">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                        </svg>
                    </span>

                    <button type="button" id="txn-types-toggle"
                        class="stat-label d-flex align-items-center gap-1 w-100 text-start border-0 bg-transparent p-0"
                        style="display:flex;align-items:center;gap:0.35rem;cursor:pointer;text-transform:uppercase;letter-spacing:0.06em;font-size:0.7rem;font-weight:700;color:#6b7280;background:none;border:none;padding:0;"
                        aria-expanded="false" aria-controls="txn-types-body">
                        Top Transaction Types
                        <svg id="txn-types-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                            width="13" height="13" style="margin-left:auto;flex-shrink:0;transition:transform .2s;transform:rotate(-90deg);">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>

                    <div id="txn-types-body" style="overflow:hidden;transition:max-height .25s ease, opacity .2s ease;max-height:0;opacity:0;">
                        <div class="top-types" id="stat-top-types">
                            <span class="txn-badge txn-badge-default">Loading...</span>
                        </div>
                        <p class="stat-sub" id="stat-source-breakdown" style="margin-top:0.45rem;">Loading source distribution...</p>
                    </div>
                </article>

            </section>

            <div class="ps-table-wrap">
                <div class="overflow-x-auto">
                    <table id="property-search-table" class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th>File Number</th>
                                <th>Party 1</th>
                                <th>Party 2</th>
                                <th>Transaction Type</th>
                                <th>Land Use</th>
                                <th>Registration</th>
                                <th>Transaction Date</th>
                                <th>Location</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="9" class="p-6 text-center text-sm text-gray-500">Loading records...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.datatables.net/v/bs5/dt-2.0.7/datatables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery) return;

            const timelineBaseUrl = "{{ route('property-search.timeline') }}";
            const statsUrl = "{{ route('property-search.stats') }}";

            const sourceBadge = (source) => {
                const map = {
                    'File History': 'source-badge-fh',
                    'CofO': 'source-badge-cofo',
                    'PRA': 'source-badge-pra',
                    'Deed Registration': 'source-badge-deed',
                };
                const cls = map[source] || 'source-badge-fh';
                return `<span class="source-badge ${cls}">${source || 'N/A'}</span>`;
            };

            const landUseBadge = (data) => {
                if (!data || data === 'N/A' || data === '-') {
                    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-gray-100 text-gray-500 border border-gray-200">N/A</span>';
                }
                const raw = data.toString().trim().toUpperCase();
                const normMap = {
                    'COM': 'COMMERCIAL', 'RES': 'RESIDENTIAL', 'IND': 'INDUSTRIAL',
                    'MIX': 'MIXED USE', 'PUB': 'PUBLIC', 'AGR': 'AGRICULTURAL',
                    'EDU': 'EDUCATIONAL', 'REL': 'RELIGIOUS'
                };
                const label = normMap[raw] || raw;
                const palette = {
                    'COMMERCIAL': 'bg-amber-50 text-amber-700 border-amber-200',
                    'RESIDENTIAL': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'INDUSTRIAL': 'bg-slate-50 text-slate-700 border-slate-200',
                    'MIXED USE': 'bg-purple-50 text-purple-700 border-purple-200',
                    'PUBLIC': 'bg-blue-50 text-blue-700 border-blue-200',
                    'AGRICULTURAL': 'bg-lime-50 text-lime-700 border-lime-200',
                    'EDUCATIONAL': 'bg-cyan-50 text-cyan-700 border-cyan-200',
                    'RELIGIOUS': 'bg-rose-50 text-rose-700 border-rose-200'
                };
                const cls = palette[label] || 'bg-gray-50 text-gray-600 border-gray-200';
                return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase border ${cls}">${label}</span>`;
            };

            const escapeHtml = (text) => {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };

            const getTxnTypeClass = (type) => {
                const t = String(type || '').toUpperCase();
                if (t.includes('TRANSFER')) return 'txn-badge-transfer';
                if (t.includes('ASSIGN')) return 'txn-badge-assignment';
                if (t.includes('CERTIFICATE OF OCCUPANCY') || t.includes('COFO') || t.includes('C OF O')) return 'txn-badge-cofo';
                if (t.includes('MORTGAGE')) return 'txn-badge-mortgage';
                if (t.includes('LEASE')) return 'txn-badge-lease';
                if (t.includes('SURRENDER')) return 'txn-badge-surrender';
                if (t.includes('REVOK')) return 'txn-badge-revoke';
                return 'txn-badge-default';
            };

            const renderTopTypes = (types) => {
                const container = document.getElementById('stat-top-types');
                if (!container) return;
                const list = Array.isArray(types) ? types : [];
                if (!list.length) {
                    container.innerHTML = '<span class="txn-badge txn-badge-default">No Data</span>';
                    return;
                }

                container.innerHTML = list.map((item) => {
                    const label = escapeHtml(item.transaction_type || 'N/A');
                    const count = Number(item.total || 0).toLocaleString();
                    const badgeClass = getTxnTypeClass(item.transaction_type);
                    return `<span class="txn-badge ${badgeClass}">${label} (${count})</span>`;
                }).join('');
            };

            const loadStats = async () => {
                try {
                    const res = await fetch(statsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const json = await res.json();
                    const payload = json?.data || {};

                    document.getElementById('stat-total-records').textContent = Number(payload.total_records || 0).toLocaleString();
                    document.getElementById('stat-multi').textContent = Number(payload.multi_timeline_records || 0).toLocaleString();
                    document.getElementById('stat-single').textContent = Number(payload.single_timeline_records || 0).toLocaleString();

                    renderTopTypes(payload.top_transaction_types || []);

                    const src = payload.source_counts || {};
                    const breakdown = [
                        `File History: ${Number(src.file_history || 0).toLocaleString()}`,
                        `CofO: ${Number(src.cofo || 0).toLocaleString()}`,
                        `PRA: ${Number(src.pra || 0).toLocaleString()}`,
                        `Deed Reg: ${Number(src.deed_registration || 0).toLocaleString()}`,
                    ].join(' | ');

                    const breakdownEl = document.getElementById('stat-source-breakdown');
                    if (breakdownEl) breakdownEl.textContent = breakdown;
                } catch (_error) {
                    const breakdownEl = document.getElementById('stat-source-breakdown');
                    if (breakdownEl) breakdownEl.textContent = 'Unable to load stats.';
                }
            };

            $('#property-search-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('property-search.data') }}",
                },
                pageLength: 25,
                lengthChange: false,
                order: [[6, 'desc']],
                columns: [
                    {
                        data: 'file_number',
                        render: (data, type, row) => {
                            const fn = escapeHtml(data || 'N/A');
                            const timelineCount = Number.isFinite(row.timeline_count) ? row.timeline_count : 0;
                            const params = row.prop_id
                                ? `prop_id=${encodeURIComponent(row.prop_id)}&file_number=${encodeURIComponent(data || '')}`
                                : `file_number=${encodeURIComponent(data || '')}`;
                            const url = `${timelineBaseUrl}?${params}`;

                            let toneClass = 'timeline-badge-info';
                            if (timelineCount === 0) toneClass = 'timeline-badge-muted';
                            else if (timelineCount >= 6 && timelineCount <= 15) toneClass = 'timeline-badge-warn';
                            else if (timelineCount > 15) toneClass = 'timeline-badge-strong';

                            return `
                                <div class="space-y-1">
                                    <a href="${url}" class="file-number-link">${fn}</a>
                                    <div class="mt-1">
                                        <a href="${url}" class="inline-flex hover:opacity-80 transition-opacity">
                                            <span class="timeline-badge ${toneClass}">
                                                <span class="timeline-dot"></span>
                                                Timeline (${timelineCount})
                                            </span>
                                        </a>
                                    </div>
                                </div>
                            `;
                        }
                    },
                    { data: 'party_1', createdCell: (td) => td.classList.add('col-party'), render: (d) => `<span title="${escapeHtml(d||'')}">${escapeHtml(d || '-')}</span>` },
                    { data: 'party_2', createdCell: (td) => td.classList.add('col-party'), render: (d) => `<span title="${escapeHtml(d||'')}">${escapeHtml(d || '-')}</span>` },
                    {
                        data: 'transaction_type',
                        render: (d) => {
                            const text = escapeHtml(d || 'N/A');
                            const cls = getTxnTypeClass(d || '');
                            return `<span class="txn-badge ${cls}">${text}</span>`;
                        }
                    },
                    { data: 'land_use', render: (d) => landUseBadge(d) },
                    { data: 'registration', render: (d) => escapeHtml(d || 'N/A') },
                    { data: 'transaction_date', render: (d) => escapeHtml(d || 'N/A') },
                    { data: 'location', createdCell: (td) => td.classList.add('col-location'), render: (d) => `<span title="${escapeHtml(d||'')}">${escapeHtml(d || '-')}</span>` },
                    {
                        data: 'source_table',
                        orderable: false,
                        render: (d) => sourceBadge(d)
                    },
                ],
                language: {
                    search: 'Search records:',
                    paginate: { next: 'Next', previous: 'Previous' },
                    processing: 'Loading records...',
                    zeroRecords: 'No matching records found.',
                }
            });

            loadStats();

            // ── Collapsible Top Transaction Types card ─────────────────────
            const txnToggle  = document.getElementById('txn-types-toggle');
            const txnBody    = document.getElementById('txn-types-body');
            const txnChevron = document.getElementById('txn-types-chevron');

            if (txnToggle && txnBody) {
                txnToggle.addEventListener('click', () => {
                    const isOpen = txnToggle.getAttribute('aria-expanded') === 'true';
                    if (isOpen) {
                        // Collapse: lock current height then animate to 0
                        txnBody.style.maxHeight = txnBody.scrollHeight + 'px';
                        requestAnimationFrame(() => {
                            txnBody.style.maxHeight = '0';
                            txnBody.style.opacity   = '0';
                        });
                        txnToggle.setAttribute('aria-expanded', 'false');
                        txnChevron.style.transform = 'rotate(-90deg)';
                    } else {
                        // Expand
                        txnBody.style.maxHeight = txnBody.scrollHeight + 'px';
                        txnBody.style.opacity   = '1';
                        txnToggle.setAttribute('aria-expanded', 'true');
                        txnChevron.style.transform = 'rotate(0deg)';
                        // After transition, free the max-height so content can grow
                        txnBody.addEventListener('transitionend', () => {
                            if (txnToggle.getAttribute('aria-expanded') === 'true') {
                                txnBody.style.maxHeight = '300px';
                            }
                        }, { once: true });
                    }
                });
            }
        });
    </script>
@endpush

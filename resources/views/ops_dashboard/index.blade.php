@extends('layouts.app')

@section('page-title')
    Occupancy Permit (OP)
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header', ['PageTitle' => 'Occupancy Permit (OP)', 'PageDescription' => 'Overview of OPs, PRA records, deeds, and change-of-name applications.'])
    <div class="p-6 space-y-6">

    {{-- Date filter --}}
    <div class="flex justify-end">
        <div class="flex items-center gap-2 flex-wrap">
            <input type="date" id="filter_from" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none" placeholder="From">
            <input type="date" id="filter_to"   class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none" placeholder="To">
            <button id="btn_apply_filter" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-1.5 rounded-lg flex items-center gap-1.5 transition">
                <i data-lucide="filter" class="w-4 h-4"></i> Apply
            </button>
            <button id="btn_clear_filter" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold px-4 py-1.5 rounded-lg flex items-center gap-1.5 transition">
                <i data-lucide="x" class="w-4 h-4"></i> Clear
            </button>
        </div>
    </div>

    {{-- ① Stat Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4" id="stat-cards">
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 shrink-0">
                <i data-lucide="file-badge" class="w-5 h-5"></i>
            </span>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">Total OPs Captured</div>
                <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="stat_total_ops">—</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-sky-100 text-sky-600 shrink-0">
                <i data-lucide="hand-coins" class="w-5 h-5"></i>
            </span>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">Direct Allocation</div>
                <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="stat_direct_allocation">—</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-orange-100 text-orange-600 shrink-0">
                <i data-lucide="users" class="w-5 h-5"></i>
            </span>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">Resettlement</div>
                <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="stat_resettlement">—</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-100 text-emerald-600 shrink-0">
                <i data-lucide="file-check-2" class="w-5 h-5"></i>
            </span>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">Change of Name</div>
                <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="stat_op_applications">—</div>
            </div>
        </div>
        {{-- Linked to MLS — hidden --}}
        {{-- Change of Name — hidden --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-violet-100 text-violet-600 shrink-0">
                <i data-lucide="database" class="w-5 h-5"></i>
            </span>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider">PRA Records</div>
                <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="stat_pra_records">—</div>
            </div>
        </div>
    </div>

    {{-- ② Charts — hidden for now --}}
    {{-- <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-700 mb-4 flex items-center gap-2">
                <i data-lucide="pie-chart" class="w-4 h-4 text-indigo-500"></i>
                Land Use Distribution
            </h2>
            <div class="relative" style="height:260px">
                <canvas id="chart_land_use"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-700 mb-4 flex items-center gap-2">
                <i data-lucide="bar-chart-horizontal" class="w-4 h-4 text-indigo-500"></i>
                OP Type Breakdown
            </h2>
            <div class="relative" style="height:260px">
                <canvas id="chart_op_type"></canvas>
            </div>
        </div>
    </div> --}}

    {{-- ③ Tabbed DataTable --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        {{-- Tab headers --}}
        {{-- Tab buttons — PRA Records, Change of Name, Deeds (New) hidden for now --}}
        <div class="border-b border-slate-200 px-5 pt-4 flex items-center gap-1 flex-wrap" id="ops-tabs">
            <button class="ops-tab-btn active-tab px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 border-transparent transition"
                    data-tab="all_ops">All OPs</button>
            <button class="ops-tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 border-transparent transition hidden"
                    data-tab="pra">PRA Records</button>
            <button class="ops-tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 border-transparent transition hidden"
                    data-tab="change_of_name">Change of Name</button>
            <button class="ops-tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 border-transparent transition hidden"
                    data-tab="deeds">Deeds (New)</button>
        </div>

        <div class="p-4">
            {{-- All OPs --}}
            <div id="tab_all_ops" class="ops-tab-content">
                <table id="dt_all_ops" class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-100">
                            <th class="px-3 py-2">FileNo</th>
                            <th class="px-3 py-2">OP Type</th>
                            <th class="px-3 py-2">OP Serial No</th>
                            <th class="px-3 py-2">Registration Particulars</th>
                            <th class="px-3 py-2">Party 1</th>
                            <th class="px-3 py-2">Party 2</th>
                            <th class="px-3 py-2">Property Description</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div id="tab_pra" class="ops-tab-content hidden">
                <table id="dt_pra" class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-100">
                            <th class="px-3 py-2">FileNo</th>
                            <th class="px-3 py-2">Transaction Type</th>
                            <th class="px-3 py-2">OP Type</th>
                            <th class="px-3 py-2">OP Serial No</th>
                            <th class="px-3 py-2">Grantor</th>
                            <th class="px-3 py-2">Grantee</th>
                            <th class="px-3 py-2">LGA</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div id="tab_change_of_name" class="ops-tab-content hidden">
                <table id="dt_change_of_name" class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-100">
                            <th class="px-3 py-2">FileNo</th>
                            <th class="px-3 py-2">Party 1</th>
                            <th class="px-3 py-2">Party 2</th>
                            <th class="px-3 py-2">OP Type</th>
                            <th class="px-3 py-2">OP Serial No</th>
                            <th class="px-3 py-2">Land Use</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div id="tab_deeds" class="ops-tab-content hidden">
                <table id="dt_deeds" class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-100">
                            <th class="px-3 py-2">Temp File No</th>
                            <th class="px-3 py-2">Instrument Type</th>
                            <th class="px-3 py-2">Reg No</th>
                            <th class="px-3 py-2">Deeds Date</th>
                            <th class="px-3 py-2">Grantor</th>
                            <th class="px-3 py-2">Grantee</th>
                            <th class="px-3 py-2">Captured</th>
                            <th class="px-3 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    </div>{{-- /.p-6 --}}
    @include('admin.footer')
</div>{{-- /.flex-1 --}}
@endsection

@push('styles')
<style>
    .ops-tab-btn {
        color: #64748b;
        background: transparent;
        border-color: transparent;
    }
    .ops-tab-btn:hover {
        color: #4f46e5;
        background: #eef2ff;
    }
    .ops-tab-btn.active-tab {
        color: #4f46e5;
        background: #fff;
        border-color: #e2e8f0 #e2e8f0 #fff;
        font-weight: 700;
    }
    /* Stacked file cell */
    .file-cell-temp  { font-family: ui-monospace, monospace; font-size: 11px; color: #94a3b8; }
    .file-cell-mls   { font-weight: 700; font-size: 13px; color: #1e293b; }
    .file-cell-empty { color: #cbd5e1; font-size: 11px; }
    .file-cell-temp-link {
        font-family: ui-monospace, monospace; font-size: 11px;
        color: #6366f1; text-decoration: underline; text-underline-offset: 2px;
        cursor: pointer; background: none; border: none; padding: 0;
    }
    .file-cell-temp-link:hover { color: #4338ca; }

    /* DataTables tweaks */
    table.dataTable thead th {
        background: #f8fafc;
        font-weight: 600;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-bottom: 1px solid #e2e8f0;
    }
    table.dataTable tbody tr:hover td { background: #f1f5f9; }
    table.dataTable tbody td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: #4f46e5 !important; color: #fff !important; border-color: #4f46e5 !important; border-radius: 6px;
    }
    .dataTables_wrapper .dataTables_filter input { border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 10px; font-size: 13px; }
    .dataTables_wrapper .dataTables_length select { border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 6px; }
</style>
@endpush

@push('scripts')
@php
    $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
        ->map(fn ($role) => trim($role))
        ->filter();
    $canUpdateOpFromDashboard = true;
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/property-timeline-modal.js') }}"></script>
<script>
$(function () {
    'use strict';
    const canUpdateOpFromDashboard = @json($canUpdateOpFromDashboard);
    const opCaptureEditUrlTemplate = @json(url('lands-one-stop-shop/applications/op-resettlement/__ID__/capture-edit'));

    /* ── helpers ── */
    const PALETTE = ['#6366f1','#10b981','#f59e0b','#3b82f6','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#84cc16'];

    function getFilters() {
        return {
            from: $('#filter_from').val() || '',
            to:   $('#filter_to').val()   || '',
        };
    }

    function stackedFileCell(r) {
        const mls  = r.mls_file_number;
        const temp = r.temp_fileno;
        const hasMls  = mls  && mls  !== 'N/A' && mls  !== '';
        const hasTemp = temp && temp !== 'N/A' && temp !== '';
        const isCoN   = r.is_change_of_name == 1 || r.is_change_of_name === true;
        const propId  = r.prop_id          || '';
        const capId   = r.source_capture_id || '';
        const praId   = r.pra_id           || '';
        const mlsEsc  = (hasMls  ? mls  : '').replace(/"/g, '&quot;');
        const tempEsc = (hasTemp ? temp : '').replace(/"/g, '&quot;');
        const newParty = (r.new_party_name || '').replace(/"/g, '&quot;');
        const link = hasTemp
            ? `<button class="file-cell-temp-link js-op-details" data-prop-id="${propId}" data-capture-id="${capId}" data-pra-id="${praId}" data-temp="${tempEsc}" data-mls="${mlsEsc}" data-new-party="${newParty}">${temp}</button>`
            : '';

        // Timeline badge with count (below file number, like PRA)
        const fileNo = String(r.mls_file_number || r.temp_fileno || '').trim();
        const count = parseInt(r.timeline_count) || 1;
        const timelineBadge = propId
            ? `<div class="mt-1"><button onclick="openPropertyTimeline('${String(propId).replace(/'/g, "\\'")}'${fileNo ? ", '" + fileNo.replace(/'/g, "\\'") + "'" : ''})" class="timeline-badge timeline-tier-1" style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;color:#1e40af;background:#eff6ff;border:1px solid #bfdbfe;cursor:pointer"><i class="fas fa-clock-rotate-left" style="font-size:10px;margin-right:4px"></i>Timeline (${count})</button></div>`
            : '';

        // Stack MLS + TEMP only for Change of Name records
        if (isCoN && hasMls && hasTemp) return `<div class="file-cell-mls">${mls}</div>${link}${timelineBadge}`;
        if (hasMls)  return `<div class="file-cell-mls">${mls}</div>${timelineBadge}`;
        if (hasTemp) return `<div class="file-cell-mls">${temp}</div>${timelineBadge}`;
        return '<div class="file-cell-empty">—</div>';
    }

    function toTitleCase(val) {
        if (!val) return '—';
        return val.replace(/\w\S*/g, w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase());
    }

    /**
     * Has this record already become a Transfer of Title?
     *
     * A matched OP is no longer a permit awaiting anything — the transfer has been
     * written, and the union collapses the pair to one row per property, so the row
     * that survives IS the Transfer of Title. Reading instrument_type rather than
     * op_type is the point: op_type still holds "Resettlement"/"Direct Allocation"
     * on a transferred record, because it describes how the parcel was ORIGINALLY
     * granted and that never stops being true.
     *
     * Matched loosely: the data carries "Transfer of Title (OP)" and the stray
     * capitalisation "Transfer Of Title (OP)" (122 rows), and an exact compare would
     * silently mislabel every one of them as a permit.
     */
    function isTransferOfTitle(row) {
        return String((row && row.instrument_type) || '').toLowerCase().includes('transfer of title');
    }

    /**
     * One label per grant type, however it was stored.
     *
     * op_type was captured by several screens over several years and the same answer
     * exists in two spellings — "Resettlement" (19,126 rows) and "OP Resettlement"
     * (4,284), "Direct Allocation" (9,337) and "OP Direct Allocation" (222). They mean
     * the same thing, and showing both makes the column read as four grant types when
     * there are two.
     *
     * The bare form wins: the column is already headed OP TYPE, so the prefix repeats
     * the header. This is the same rule the OSS applications page applies to its own
     * sub-label (preg_replace('/^op\\s+/i', '', $src)), so the two screens agree.
     *
     * Display only. The stored values are untouched, so sorting and the server-side
     * search still key off whatever each row actually holds.
     */
    function normalizeOpType(val) {
        const text = String(val || '').trim().replace(/\s+/g, ' ');
        if (!text) return '';
        return text.replace(/^op\s+/i, '');
    }

    function fmtOpType(val, type, row) {
        // A transferred record is labelled for what it now is, and nothing else. The
        // original grant type is deliberately NOT shown underneath: this column answers
        // one question per row, and a second line under the badge reads as a second
        // type rather than as history. op_type is not even read on this path.
        if (isTransferOfTitle(row)) {
            return '<span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-violet-100 text-violet-700">Transfer of Title</span>';
        }

        const label = normalizeOpType(val);
        if (!label) return '<span class="text-slate-300">—</span>';
        const cls = label.toLowerCase().includes('resettlement')
            ? 'bg-orange-100 text-orange-700'
            : 'bg-sky-100 text-sky-700';
        return `<span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold ${cls}">${label}</span>`;
    }

    function buildCaptureEditIdentifier(r) {
        const praId = String(r.pra_id || '').trim();
        const id = String(r.id || '').trim();
        const capId = String(r.source_capture_id || '').trim();

        // PRA-based rows (Change of Name / standalone PRA): use pra-{praId}
        if (praId && /^\d+$/.test(praId)) return `pra-${praId}`;
        // fileNumber-based rows: use fn.id
        if (id && /^\d+$/.test(id)) return id;
        // instrument_capture with no fileNumber: use ic-{icId}
        if (capId && /^\d+$/.test(capId)) return `ic-${capId}`;
        return '';
    }

    function renderAllOpsActionCell(r) {
        if (!canUpdateOpFromDashboard) {
            return '<span class="text-slate-300">—</span>';
        }

        const rowIdentifier = buildCaptureEditIdentifier(r);
        if (!rowIdentifier) {
            return '<span class="text-slate-300">—</span>';
        }

        const url = opCaptureEditUrlTemplate.replace('__ID__', encodeURIComponent(rowIdentifier));

        // The action names the record it opens. Offering "Update OP" on a transferred
        // record invites an officer to go looking for permit fields on a transfer.
        const isToT = isTransferOfTitle(r);
        const label = isToT ? 'Update ToT' : 'Update OP';
        const tone  = isToT
            ? 'text-violet-600 hover:text-violet-700 hover:bg-violet-50'
            : 'text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50';

        return `
            <div class="flex items-center justify-center gap-1">
                <a href="${url}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold ${tone} transition">
                    <i class="fas fa-file-pen w-3 h-3"></i>
                    <span>${label}</span>
                </a>
            </div>
        `;
    }

    /* ── charts storage ── */
    let chartLandUse = null;
    let chartOpType  = null;

    /* ── 1. Stats ── */
    function loadStats() {
        const f = getFilters();
        $.get('{{ route("ops-dashboard.stats") }}', f, function (data) {
            $('#stat_total_ops').text(Number(data.total_ops).toLocaleString());
            $('#stat_direct_allocation').text(Number(data.direct_allocation).toLocaleString());
            $('#stat_resettlement').text(Number(data.resettlement).toLocaleString());
            $('#stat_op_applications').text(Number(data.op_applications).toLocaleString());
            $('#stat_linked_to_mls').text(Number(data.linked_to_mls).toLocaleString());
            $('#stat_change_of_name').text(Number(data.change_of_name).toLocaleString());
            $('#stat_pra_records').text(Number(data.pra_records).toLocaleString());
        });
    }

    /* ── 2. Charts ── */
    function buildChart(canvas, type, labels, data, title) {
        return new Chart(canvas, {
            type,
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: type === 'pie' ? PALETTE : PALETTE[0],
                    borderColor: type === 'bar' ? PALETTE[0] : '#fff',
                    borderWidth: type === 'pie' ? 2 : 0,
                    borderRadius: type === 'bar' ? 4 : 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: type === 'bar' ? 'y' : 'x',
                plugins: {
                    legend: { display: type === 'pie', position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                    title:  { display: false },
                    tooltip: { callbacks: {
                        label: ctx => ` ${ctx.formattedValue}`,
                    }},
                },
                scales: type === 'bar' ? {
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
                } : {},
            },
        });
    }

    function loadCharts() {
        const f = getFilters();

        $.get('{{ route("ops-dashboard.chart-land-use") }}', f, function (rows) {
            if (chartLandUse) chartLandUse.destroy();
            chartLandUse = buildChart(
                document.getElementById('chart_land_use'),
                'pie',
                rows.map(r => r.label),
                rows.map(r => r.total),
                'Land Use',
            );
        });

        $.get('{{ route("ops-dashboard.chart-op-type") }}', f, function (rows) {
            if (chartOpType) chartOpType.destroy();
            chartOpType = buildChart(
                document.getElementById('chart_op_type'),
                'bar',
                rows.map(r => r.label),
                rows.map(r => r.total),
                'OP Types',
            );
        });
    }

    /* ── 3. DataTables ── */
    let dtAllOps     = null;
    let dtPra        = null;
    let dtChangeOfName = null;
    let dtDeeds      = null;

    function mkAjax(url) {
        return {
            url,
            type: 'GET',
            data: d => Object.assign(d, getFilters()),
        };
    }

    function initAllOps() {
        dtAllOps = $('#dt_all_ops').DataTable({
            serverSide: true,
            processing: true,
            ajax: mkAjax('{{ route("ops-dashboard.table-all-ops") }}'),
            columns: [
                { data: null, name: 'temp_fileno', render: r => stackedFileCell(r) },
                { data: 'op_type', name: 'op_type', render: fmtOpType },
                { data: 'op_serial_number', name: 'op_serial_number', defaultContent: '—' },
                { data: 'registration_number', name: 'registration_number', defaultContent: '—' },
                { data: 'grantor', name: 'grantor', render: v => toTitleCase(v) },
                { data: 'allottee', name: 'allottee', render: v => toTitleCase(v) },
                { data: 'property_description', name: 'property_description', render: v => toTitleCase(v) },
                { data: 'created_at', name: 'created_at' },
                { data: null, name: 'actions', orderable: false, searchable: false, render: r => renderAllOpsActionCell(r) },
            ],
            dom: '<"flex items-center justify-between mb-3"<"flex items-center gap-2"lB>f>rtip',
            buttons: [
                { extend: 'csv',   text: '<i class="lucide lucide-download inline w-3.5 h-3.5 mr-1"></i>CSV',   className: 'btn-dt-export' },
                { extend: 'excel', text: '<i class="lucide lucide-table inline w-3.5 h-3.5 mr-1"></i>Excel', className: 'btn-dt-export' },
                { extend: 'pdf',   text: '<i class="lucide lucide-file-text inline w-3.5 h-3.5 mr-1"></i>PDF',  className: 'btn-dt-export' },
            ],
            pageLength: 15,
            order: [[7, 'desc']],
        });
    }

    function initPra() {
        dtPra = $('#dt_pra').DataTable({
            serverSide: true,
            processing: true,
            ajax: mkAjax('{{ route("ops-dashboard.table-pra") }}'),
            columns: [
                { data: null, name: 'temp_fileno', render: r => stackedFileCell(r) },
                { data: 'transaction_type', name: 'transaction_type', defaultContent: '—' },
                { data: 'op_type', name: 'op_type', render: fmtOpType },
                { data: 'op_serial_number', name: 'op_serial_number', defaultContent: '—' },
                { data: 'grantor', name: 'grantor', render: v => toTitleCase(v) },
                { data: 'grantee', name: 'grantee', render: v => toTitleCase(v) },
                { data: 'lga', name: 'lga', render: v => toTitleCase(v) },
                { data: 'created_at', name: 'created_at' },
                { data: null, name: 'actions', orderable: false, searchable: false, render: r => renderAllOpsActionCell(r) },
            ],
            dom: '<"flex items-center justify-between mb-3"<"flex items-center gap-2"lB>f>rtip',
            buttons: [
                { extend: 'csv',   text: 'CSV',   className: 'btn-dt-export' },
                { extend: 'excel', text: 'Excel', className: 'btn-dt-export' },
                { extend: 'pdf',   text: 'PDF',   className: 'btn-dt-export' },
            ],
            pageLength: 15,
            order: [[7, 'desc']],
        });
    }

    function initChangeOfName() {
        dtChangeOfName = $('#dt_change_of_name').DataTable({
            serverSide: true,
            processing: true,
            ajax: mkAjax('{{ route("ops-dashboard.table-change-of-name") }}'),
            columns: [
                { data: null, name: 'temp_fileno', render: r => stackedFileCell(r) },
                { data: 'party_1', name: 'party_1', render: v => toTitleCase(v) },
                { data: 'party_2', name: 'party_2', render: v => toTitleCase(v) },
                { data: 'op_type', name: 'op_type', render: fmtOpType },
                { data: 'op_serial_number', name: 'op_serial_number', defaultContent: '—' },
                { data: 'land_use', name: 'land_use', defaultContent: '—' },
                { data: 'created_at', name: 'created_at' },
                { data: null, name: 'actions', orderable: false, searchable: false, render: r => renderAllOpsActionCell(r) },
            ],
            dom: '<"flex items-center justify-between mb-3"<"flex items-center gap-2"lB>f>rtip',
            buttons: [
                { extend: 'csv',   text: 'CSV',   className: 'btn-dt-export' },
                { extend: 'excel', text: 'Excel', className: 'btn-dt-export' },
                { extend: 'pdf',   text: 'PDF',   className: 'btn-dt-export' },
            ],
            pageLength: 15,
            order: [[6, 'desc']],
        });
    }

    function initDeeds() {
        dtDeeds = $('#dt_deeds').DataTable({
            serverSide: true,
            processing: true,
            ajax: mkAjax('{{ route("ops-dashboard.table-deeds") }}'),
            columns: [
                { data: null, name: 'temp_fileno', defaultContent: '—',
                  render: function(d, t, row) {
                    var v = row.temp_fileno;
                    var html = v ? '<span class="font-mono text-xs text-slate-700">' + $('<span>').text(v).html() + '</span>' : '—';
                    var propId = (row.prop_id || '').toString().trim();
                    if (propId) {
                        var fileNo = (v || '').toString().trim();
                        var count = parseInt(row.timeline_count) || 1;
                        var args = "'" + propId.replace(/'/g, "\\'") + "'";
                        if (fileNo) args += ", '" + fileNo.replace(/'/g, "\\'") + "'";
                        html += '<div class="mt-1"><button onclick="openPropertyTimeline(' + args + ')" class="timeline-badge timeline-tier-1" style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;color:#1e40af;background:#eff6ff;border:1px solid #bfdbfe;cursor:pointer"><i class="fas fa-clock-rotate-left" style="font-size:10px;margin-right:4px"></i>Timeline (' + count + ')</button></div>';
                    }
                    return html;
                  } },
                { data: 'instrument_type', name: 'instrument_type', defaultContent: '—' },
                { data: 'reg_number', name: 'reg_number', defaultContent: '—' },
                { data: 'deeds_date', name: 'deeds_date', defaultContent: '—' },
                { data: 'grantor', name: 'grantor', render: v => toTitleCase(v) },
                { data: 'grantee', name: 'grantee', render: v => toTitleCase(v) },
                { data: 'created_at', name: 'created_at' },
                { data: null, name: 'actions', orderable: false, searchable: false, render: r => renderAllOpsActionCell(r) },
            ],
            dom: '<"flex items-center justify-between mb-3"<"flex items-center gap-2"lB>f>rtip',
            buttons: [
                { extend: 'csv',   text: 'CSV',   className: 'btn-dt-export' },
                { extend: 'excel', text: 'Excel', className: 'btn-dt-export' },
                { extend: 'pdf',   text: 'PDF',   className: 'btn-dt-export' },
            ],
            pageLength: 15,
            order: [[6, 'desc']],
        });
    }

    /* ── Tabs ── */
    const tabInited = { all_ops: false, pra: false, change_of_name: false, deeds: false };

    $('#ops-tabs').on('click', '.ops-tab-btn', function () {
        const tab = $(this).data('tab');
        $('.ops-tab-btn').removeClass('active-tab');
        $(this).addClass('active-tab');
        $('.ops-tab-content').addClass('hidden');
        $(`#tab_${tab}`).removeClass('hidden');

        // lazy-init DataTable on first visit
        if (!tabInited[tab]) {
            tabInited[tab] = true;
            if (tab === 'all_ops')       initAllOps();
            if (tab === 'pra')           initPra();
            if (tab === 'change_of_name') initChangeOfName();
            if (tab === 'deeds')         initDeeds();
        }
    });

    /* ── Filter buttons ── */
    $('#btn_apply_filter').on('click', function () {
        loadStats();
        loadCharts();
        // reload whichever tables have been inited
        if (dtAllOps)      dtAllOps.ajax.reload();
        if (dtPra)         dtPra.ajax.reload();
        if (dtChangeOfName) dtChangeOfName.ajax.reload();
        if (dtDeeds)       dtDeeds.ajax.reload();
    });

    $('#btn_clear_filter').on('click', function () {
        $('#filter_from, #filter_to').val('');
        $('#btn_apply_filter').trigger('click');
    });

    /* ── Bootstrap export button styles ── */
    $(document).on('draw.dt', function () {
        $('.btn-dt-export').addClass(
            'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition mr-1'
        );
    });

    /* ── Initial load ── */
    loadStats();
    loadCharts();
    initAllOps();   // first tab is always visible — init immediately

    /* ── OP Details modal ── */
    async function showOpDetails(propId, captureId, praId, tempFileno, mlsFileno, newPartyName) {
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const qs = new URLSearchParams();
            if (propId)    qs.set('prop_id', String(propId));
            if (captureId) qs.set('source_capture_id', String(captureId));
            if (praId)     qs.set('pra_id', String(praId));
            const res  = await fetch(`/mls-fileno/temp-file-details?${qs.toString()}`, { headers: { 'Accept': 'application/json' } });
            const body = await res.json();
            if (!res.ok || !body || body.success !== true || !body.data) throw new Error((body && body.message) || 'Details not found.');
            const row = body.data;
            const esc = v => String(v ?? '—').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
            const tt  = v => { const s = String(v ?? '').trim(); if (!s) return '—'; return s.replace(/\w\S*/g, w => w[0].toUpperCase() + w.slice(1).toLowerCase()); };
            const isChangeOfName = !!String(praId || '').trim();
            const tempParty1 = tt(row.party_1_name);
            const tempParty2 = tt(row.party_2_name);
            const newAppParty1 = isChangeOfName ? tt(row.party_2_name) : tempParty1;
            const newAppParty2 = isChangeOfName ? tt(newPartyName || '') : tempParty2;
            const html = `
                <div class="text-left text-sm font-sans">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                      <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-sky-600 text-white font-extrabold text-sm">1</span>
                        <span class="text-xs font-extrabold uppercase tracking-widest text-sky-700">Occupancy Permit</span>
                      </div>
                      <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <div><div class="text-[10px] text-slate-400">Temp File No</div><div class="font-mono font-bold text-slate-800 text-xs">${esc(row.temp_fileno||tempFileno)}</div></div>
                        <div><div class="text-[10px] text-slate-400">OP Type</div><div class="font-semibold text-orange-700 text-xs">${esc(tt(row.op_type))}</div></div>
                        <div><div class="text-[10px] text-slate-400">OP Serial No</div><div class="font-bold text-slate-800 text-xs">${esc(row.op_serial_number)}</div></div>
                        <div><div class="text-[10px] text-slate-400">Registration No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.registration_number)}</div></div>
                        <div><div class="text-[10px] text-slate-400">Party 1</div><div class="font-semibold text-slate-800 text-xs">${esc(tempParty1)}</div></div>
                        <div><div class="text-[10px] text-slate-400">Party 2</div><div class="font-semibold text-slate-800 text-xs">${esc(tempParty2)}</div></div>
                        <div class="col-span-2"><div class="text-[10px] text-slate-400">Property</div><div class="font-semibold text-slate-800 text-xs leading-snug">${esc(tt(row.property_description||row.property_location||''))}</div></div>
                      </div>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                      <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-600 text-white font-extrabold text-sm">2</span>
                        <span class="text-xs font-extrabold uppercase tracking-widest text-emerald-700">New Application</span>
                      </div>
                      <div class="mb-3"><div class="text-[10px] text-slate-400">New MLPP File No</div><div class="font-mono font-extrabold text-emerald-800 text-sm">${esc(mlsFileno||row.mlsFNo||'—')}</div></div>
                      <div class="mb-3"><div class="text-[10px] text-slate-400">OP Serial No</div><div class="font-bold text-emerald-800 text-xs">${esc(row.op_serial_number)}</div></div>
                      <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                                                <div><div class="text-[10px] text-slate-400">Party 1</div><div class="font-semibold text-slate-800 text-xs">${esc(newAppParty1)}</div></div>
                                                <div><div class="text-[10px] text-slate-400">Party 2</div><div class="font-semibold text-slate-800 text-xs">${esc(newAppParty2)}</div></div>
                        <div><div class="text-[10px] text-slate-400">Land Use</div><div class="font-semibold text-slate-800 text-xs">${esc(tt(row.land_use))}</div></div>
                        <div><div class="text-[10px] text-slate-400">TP No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.tp_no)}</div></div>
                        <div><div class="text-[10px] text-slate-400">Plot No</div><div class="font-semibold text-slate-800 text-xs">${esc(row.plot_number)}</div></div>
                        <div><div class="text-[10px] text-slate-400">LGA</div><div class="font-semibold text-slate-800 text-xs">${esc(tt(row.lga))}</div></div>
                      </div>
                    </div>
                  </div>
                </div>`;
            Swal.fire({ icon: 'info', title: `Occupancy Permit (OP) Details (${esc(row.temp_fileno||tempFileno||'—')})`, html, width: 900, confirmButtonText: 'Close' });
        } catch (err) {
            Swal.fire('Error', err.message || 'Unable to load OP details.', 'error');
        }
    }

    $('#dt_all_ops, #dt_pra, #dt_change_of_name, #dt_deeds').on('click', '.js-op-details', function () {
        const btn = $(this);
        showOpDetails(
            btn.data('prop-id')   || '',
            btn.data('capture-id')|| '',
            btn.data('pra-id')    || '',
            btn.data('temp')      || '',
            btn.data('mls')       || '',
            btn.data('new-party') || ''
        );
    });
    tabInited.all_ops = true;

    // re-init Lucide icons after render
    if (typeof lucide !== 'undefined') lucide.createIcons();
});


</script>
@endpush

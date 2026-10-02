@extends('layouts.app')

@section('page-title')
    Letter of Grant / RofO
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header', ['PageTitle' => 'Letter of Grant / RofO', 'PageDescription' => 'Overview of Letter of Grant and Right of Occupancy (RofO) records.'])
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

    {{-- Stat Card --}}
    <div class="max-w-xs" id="stat-cards">
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm hover:shadow-md transition-shadow">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 shrink-0">
                <i data-lucide="scroll" class="w-5 h-5"></i>
            </span>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Total Records</div>
                <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="stat_total_records">—</div>
            </div>
        </div>
    </div>

    {{-- DataTable --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="border-b border-slate-200 px-5 pt-4 flex items-center gap-1 flex-wrap">
            <button class="px-4 py-2 text-sm font-bold rounded-t-lg border border-b-0 text-indigo-600 bg-white" style="border-color: #e2e8f0 #e2e8f0 #fff;">All Records</button>
        </div>
        <div class="p-4">
            <table id="dt_rofo" class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500 border-b border-slate-100">
                        <th class="px-3 py-2">File No</th>
                        <th class="px-3 py-2">Instrument Type</th>
                        <th class="px-3 py-2">Party 2</th>
                        <th class="px-3 py-2">Reg No</th>
                        <th class="px-3 py-2">Land Use</th>
                        <th class="px-3 py-2">LGA</th>
                        <th class="px-3 py-2">Property Description</th>
                        <th class="px-3 py-2">Transaction Date</th>
                        <th class="px-3 py-2">Date Captured</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    </div>
    @include('admin.footer')
</div>
@endsection

@push('styles')
<style>
    .file-cell-primary { font-weight: 700; font-size: 13px; color: #1e293b; white-space: nowrap; }
    .file-cell-secondary { font-family: ui-monospace, monospace; font-size: 11px; color: #94a3b8; white-space: nowrap; }
    .badge-instrument, .badge-landuse {
        display: inline-flex; align-items: center; padding: 2px 10px;
        border-radius: 9999px; font-size: 11px; font-weight: 600; white-space: nowrap; line-height: 1.6;
    }
    .badge-rofo   { background: #dbeafe; color: #1e40af; }
    .badge-other  { background: #f1f5f9; color: #475569; }
    .badge-residential { background: #dbeafe; color: #1e40af; }
    .badge-commercial  { background: #fef3c7; color: #92400e; }
    .badge-industrial  { background: #ede9fe; color: #5b21b6; }
    .badge-agricultural { background: #d1fae5; color: #065f46; }

    table.dataTable thead th {
        background: #f8fafc; font-weight: 600; font-size: 11px; color: #64748b;
        text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #e2e8f0;
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
<script>
$(function () {
    'use strict';

    function getFilters() {
        return { from: $('#filter_from').val() || '', to: $('#filter_to').val() || '' };
    }

    function toTitle(v) {
        if (!v || !v.trim()) return '—';
        return v.replace(/\w\S*/g, w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase());
    }

    function fileCell(row) {
        var mls = (row.mlsFNo || '').trim(),
            kg  = (row.kangisFileNo || '').trim(),
            nk  = (row.NewKANGISFileno || '').trim(),
            fn  = (row.fileno || '').trim();
        var primary = mls || nk || kg || fn;
        if (!primary) return '<span style="color:#cbd5e1;font-size:11px">—</span>';
        var html = '<div class="file-cell-primary">' + $('<span>').text(primary).html() + '</div>';
        var secondary = (mls && kg) ? kg : ((mls && nk) ? nk : '');
        if (secondary) html += '<div class="file-cell-secondary">' + $('<span>').text(secondary).html() + '</div>';

        // Timeline badge with count below file number
        var propId = (row.prop_id || '').toString().trim();
        if (propId) {
            var fileNo = (mls || fn || '').trim();
            var count = parseInt(row.timeline_count) || 1;
            var args = "'" + propId.replace(/'/g, "\\'") + "'";
            if (fileNo) args += ", '" + fileNo.replace(/'/g, "\\'") + "'";
            html += '<div class="mt-1"><button onclick="openPropertyTimeline(' + args + ')" style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;color:#1e40af;background:#eff6ff;border:1px solid #bfdbfe;cursor:pointer"><i data-lucide="clock" style="width:12px;height:12px;margin-right:4px"></i>Timeline (' + count + ')</button></div>';
        }
        return html;
    }

    function instrBadge(v) {
        if (!v || !v.trim()) return '<span class="badge-instrument badge-other">—</span>';
        var u = v.trim().toUpperCase(), cls = 'badge-other';
        if (/R[- .]?OF[- .]?O|RIGHT OF OCCUPANCY/.test(u)) cls = 'badge-rofo';
        return '<span class="badge-instrument ' + cls + '">' + $('<span>').text(v.trim()).html() + '</span>';
    }

    function luBadge(v) {
        if (!v || !v.trim()) return '<span class="badge-landuse badge-other">—</span>';
        var u = v.trim().toUpperCase(), cls = 'badge-other';
        if (u.indexOf('RESIDENTIAL') !== -1) cls = 'badge-residential';
        else if (u.indexOf('COMMERCIAL') !== -1) cls = 'badge-commercial';
        else if (u.indexOf('INDUSTRIAL') !== -1) cls = 'badge-industrial';
        else if (u.indexOf('AGRICULT') !== -1) cls = 'badge-agricultural';
        return '<span class="badge-landuse ' + cls + '">' + $('<span>').text(v.trim()).html() + '</span>';
    }

    /* Stats */
    function loadStats() {
        $.get('{{ route("rofo-staging.stats") }}', getFilters(), function (d) {
            $('#stat_total_records').text(Number(d.total_records).toLocaleString());
        });
    }

    /* DataTable — column order must match <thead> exactly */
    var dt = $('#dt_rofo').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '{{ route("rofo-staging.table-all") }}',
            type: 'GET',
            data: function (d) { Object.assign(d, getFilters()); }
        },
        columns: [
            { data: null,                   name: 'mlsFNo',               orderable: true,  searchable: true,  render: function(d,t,row){ return fileCell(row); } },
            { data: 'instrument_type',      name: 'instrument_type',      orderable: true,  searchable: false, render: function(v){ return instrBadge(v); } },
            { data: 'party_2',              name: 'party_2',              orderable: true,  searchable: false, render: function(v){ return toTitle(v); } },
            { data: 'reg_no',               name: 'reg_no',               orderable: true,  searchable: false },
            { data: 'land_use',             name: 'land_use',             orderable: true,  searchable: false, render: function(v){ return luBadge(v); } },
            { data: 'lgsaOrCity',           name: 'lgsaOrCity',           orderable: true,  searchable: false, render: function(v){ return toTitle(v); } },
            { data: 'property_description', name: 'property_description', orderable: true,  searchable: false, render: function(v){ return toTitle(v); } },
            { data: 'transaction_date',     name: 'transaction_date',     orderable: true,  searchable: false },
            {
                data: 'created_at',
                name: 'created_at',
                orderable: true,
                searchable: false,
                render: function(data, type, row) {
                    if (type === 'sort' || type === 'type') {
                        return row.created_at_sort || '';
                    }
                    return data || '—';
                }
            },
        ],
        order: [[8, 'desc']],
        pageLength: 15,
        dom: '<"flex items-center justify-between mb-3"<"flex items-center gap-2"lB>f>rtip',
        buttons: [
            { extend: 'csv',   text: 'CSV',   className: 'btn-dt-export' },
            { extend: 'excel', text: 'Excel', className: 'btn-dt-export' },
            { extend: 'pdf',   text: 'PDF',   className: 'btn-dt-export' },
        ],
    });

    /* Filters */
    $('#btn_apply_filter').on('click', function () { loadStats(); dt.ajax.reload(); });
    $('#btn_clear_filter').on('click', function () { $('#filter_from, #filter_to').val(''); loadStats(); dt.ajax.reload(); });

    /* Export button styles */
    $(document).on('draw.dt', function () {
        $('.btn-dt-export').addClass('inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition mr-1');
    });

    loadStats();
    if (typeof lucide !== 'undefined') lucide.createIcons();

    /* Re-init Lucide icons after each DataTable draw (for action buttons) */
    dt.on('draw.dt', function () {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
});
</script>
<script src="{{ asset('js/property-timeline-modal.js') }}"></script>
@endpush

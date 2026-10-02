@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Report' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Report</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription ?? '' }}</p>
            </div>
            <div class="flex gap-2">
                <button id="btn-export-pdf"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">
                    <i data-lucide="file-down" class="h-4 w-4 text-red-500"></i> Export PDF
                </button>
                <a id="btn-export-csv" href="{{ url('special-assignment/report/export-csv') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg">
                    <i data-lucide="table-2" class="h-4 w-4"></i> Export CSV
                </a>
            </div>
        </div>

        {{-- Year picker --}}
        <div class="flex items-center gap-3 mb-6">
            <label class="text-sm text-gray-600 font-medium">Year:</label>
            <select id="year-picker" class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:border-[rgb(186,191,12)]">
                @for($y = date('Y'); $y >= 2022; $y--)
                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-1">Land Records</p>
                <p id="stat-land" class="text-2xl font-bold" style="color:rgb(186,191,12)">—</p>
                <p class="text-xs text-gray-400 mt-1">Total registered</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-1">Notices Served</p>
                <p id="stat-notices" class="text-2xl font-bold text-blue-600">—</p>
                <p class="text-xs text-gray-400 mt-1">1st &amp; 2nd serve</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-1">Change of Purpose Sheets Issued</p>
                <p id="stat-certs" class="text-2xl font-bold text-green-600">—</p>
                <p class="text-xs text-gray-400 mt-1">Total issued</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 mb-1">Pending Actions</p>
                <p id="stat-pending" class="text-2xl font-bold text-amber-500">—</p>
                <p class="text-xs text-gray-400 mt-1">Across all sections</p>
            </div>
        </div>

        {{-- Charts --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Monthly Notice Activity</h3>
                <canvas id="chart-monthly" height="200"></canvas>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Application Status Breakdown</h3>
                <canvas id="chart-status" height="200"></canvas>
            </div>
        </div>

        {{-- Activity Log Table --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" id="print-table-wrap">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Detailed Activity Log</h2>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-500">From</label>
                    <input type="date" id="filter-from" class="text-xs border border-gray-200 rounded-md px-2 py-1 outline-none">
                    <label class="text-xs text-gray-500">To</label>
                    <input type="date" id="filter-to" class="text-xs border border-gray-200 rounded-md px-2 py-1 outline-none">
                    <button id="btn-apply-filter" class="px-3 py-1 text-xs bg-[rgb(186,191,12)] text-white rounded-md hover:opacity-90">Apply</button>
                </div>
            </div>
            <div class="p-4">
                <table id="report-table" class="w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Section</th>
                            <th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2">Officer</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
    @media print {
        body * { visibility:hidden; }
        #print-table-wrap, #print-table-wrap * { visibility:visible; }
        #print-table-wrap { position:absolute; left:0; top:0; width:100%; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const REPORT_DATA_URL = '{{ route("special-assignment.report.data") }}';

let monthlyChart, statusChart;

const STATUS_COLORS_LOG = {
    open          : 'bg-blue-100 text-blue-700',
    in_progress   : 'bg-amber-100 text-amber-700',
    approved      : 'bg-green-100 text-green-700',
    certificate_issued: 'bg-purple-100 text-purple-700',
    closed        : 'bg-gray-100 text-gray-500',
    served        : 'bg-green-100 text-green-700',
    pending       : 'bg-amber-100 text-amber-700',
    issued        : 'bg-green-100 text-green-700',
};

$(document).ready(function () {
    // ── Charts init ────────────────────────────────────────────────────────
    monthlyChart = new Chart(document.getElementById('chart-monthly'), {
        type: 'bar',
        data: {
            labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            datasets: [{
                label: 'Notices Issued',
                data: new Array(12).fill(0),
                backgroundColor: 'rgba(186,191,12,.7)',
                borderColor: 'rgb(186,191,12)',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: { responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }
    });

    statusChart = new Chart(document.getElementById('chart-status'), {
        type: 'doughnut',
        data: {
            labels: ['Open', 'In Progress', 'Approved', 'Cert Issued'],
            datasets: [{ data:[0,0,0,0], backgroundColor:['#3b82f6','#f59e0b','#22c55e','#a855f7'] }]
        },
        options: { responsive:true, plugins:{ legend:{ position:'right' } } }
    });

    // ── DataTable ──────────────────────────────────────────────────────────
    const reportTable = $('#report-table').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: REPORT_DATA_URL,
            data: d => ({ ...d, table:1, from: $('#filter-from').val(), to: $('#filter-to').val() })
        },
        columns: [
            { data:'DT_RowIndex', orderable:false, searchable:false },
            { data:'section' },
            { data:'file_number' },
            { data:'description', render: d => d ? `<span title="${d}">${d.length>50?d.slice(0,50)+'…':d}</span>` : '—' },
            { data:'officer' },
            { data:'date' },
            { data:'status', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS_LOG[d]||'bg-gray-100 text-gray-600'}">${d||'—'}</span>` },
        ],
    });

    // ── Load summary + charts ──────────────────────────────────────────────
    function loadData(year) {
        fetch(`${REPORT_DATA_URL}?year=${year}`).then(r=>r.json()).then(d=>{
            document.getElementById('stat-land').textContent    = d.summary.land_records    ?? '—';
            document.getElementById('stat-notices').textContent = d.summary.notices_served  ?? '—';
            document.getElementById('stat-certs').textContent   = d.summary.certificates    ?? '—';
            document.getElementById('stat-pending').textContent = d.summary.pending         ?? '—';
            monthlyChart.data.datasets[0].data = d.monthly;
            monthlyChart.update();
            statusChart.data.datasets[0].data = d.status_counts;
            statusChart.update();
        }).catch(()=>{});
    }

    loadData($('#year-picker').val());
    $('#year-picker').on('change', function() { loadData(this.value); });

    // ── Date filter ────────────────────────────────────────────────────────
    document.getElementById('btn-apply-filter').addEventListener('click', () => reportTable.ajax.reload());

    // ── Export PDF (print) ─────────────────────────────────────────────────
    document.getElementById('btn-export-pdf').addEventListener('click', () => window.print());
});
</script>
@endsection

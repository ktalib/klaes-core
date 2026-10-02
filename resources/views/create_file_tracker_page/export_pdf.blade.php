<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; background: #fff; padding: 14px 16px; }

    /* ── Watermark ── */
    .watermark {
        position: fixed;
        top: 50%; left: 50%;
        margin-top: -90px; margin-left: -90px;
        width: 180px; height: 180px;
        opacity: 0.07;
    }

    /* ── Ministry Header ── */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .header-table td { padding: 0; vertical-align: middle; }
    .header-table .td-logo { width: 90px; text-align: center; }
    .header-table .td-logo img { width: 78px; height: 78px; }
    .header-table .td-text { text-align: center; }
    .h-title { font-size: 22px; font-weight: bold; text-transform: uppercase; color: #000; }
    .h-sub   { font-size: 15px; font-weight: bold; text-transform: uppercase; color: #000; margin-top: 4px; }
    .h-dept  { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #000; margin-top: 4px; }
    .header-divider { border: none; border-top: 2px solid #1a1a1a; margin: 10px 0 12px; }

    /* ── Report Meta ── */
    .report-title { font-size: 11px; font-weight: bold; color: #1e293b; margin-bottom: 2px; }
    .report-sub   { font-size: 8px; color: #64748b; margin-bottom: 8px; }

    /* ── Data Table ── */
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table thead tr { background: #166534; color: #fff; }
    .data-table thead th { padding: 6px 7px; text-align: left; font-size: 8px; font-weight: 700; white-space: nowrap; }
    .data-table tbody tr:nth-child(even) { background: #f0fdf4; }
    .data-table tbody tr:nth-child(odd)  { background: #fff; }
    .data-table tbody td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; font-size: 8px; vertical-align: top; }

    .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7px; font-weight: 700; }
    .badge-high   { background: #fee2e2; color: #b91c1c; }
    .badge-medium { background: #fef9c3; color: #92400e; }
    .badge-low    { background: #dcfce7; color: #166534; }

    /* ── Footer ── */
    .footer-table { width: 100%; border-collapse: collapse; margin-top: 12px; border-top: 1px solid #cbd5e1; }
    .footer-table td { font-size: 7.5px; color: #64748b; padding-top: 5px; }
    .footer-table .f-right { text-align: right; }

    .no-data { text-align: center; padding: 40px; color: #94a3b8; font-size: 11px; }
</style>
</head>
<body>

{{-- Watermark --}}
<img class="watermark" src="{{ public_path('assets/logo/Nigerian-Coat-of-Arms.png') }}" alt="">

{{-- Ministry Header --}}
<table class="header-table">
    <tr>
        <td class="td-logo">
            <img src="{{ public_path('assets/logo/ministry1.jpg') }}" alt="Coat of Arms">
        </td>
        <td class="td-text">
            <div class="h-title">Kano State Government</div>
            <div class="h-sub">Ministry of Land and Physical Planning</div>
            <div class="h-dept">{{ $module ? strtoupper($module) . ' Department' : 'File Tracking Report' }}</div>
        </td>
        <td class="td-logo">
            <img src="{{ public_path('assets/logo/ministry2.jpeg') }}" alt="Ministry Seal">
        </td>
    </tr>
</table>
<hr class="header-divider">

{{-- Report Meta --}}
<div class="report-title">File Tracker Export Report{{ $module ? ' — ' . strtoupper($module) : '' }}</div>
<div class="report-sub">Generated: {{ $generated }} &nbsp;|&nbsp; Period: {{ $dateFrom ?? 'All' }} to {{ $dateTo ?? 'All' }} &nbsp;|&nbsp; Total Records: {{ number_format($totalCount) }}</div>

@if($totalCount === 0)
    <div class="no-data">No file tracker records found for the selected date range.</div>
@else
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>File Number</th>
            <th>File Title</th>
            <th>Priority</th>
            <th>Origin Registry</th>
            <th>Destination</th>
            <th>Receiving Officer</th>
            <th>Log In</th>
            <th>Log Out</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        {!! $tableRows !!}
    </tbody>
</table>
@endif

<table class="footer-table">
    <tr>
        <td>Kano State Ministry of Land &amp; Physical Planning &mdash; File Tracker System</td>
        <td class="f-right">Printed: {{ $generated }}</td>
    </tr>
</table>

</body>
</html>

{{--
    Approver-only preview of the report an Online Legal Search request would
    release. Standalone page (no staff chrome) so it reads exactly like the
    document the requester will receive. Nothing here is sent to anyone —
    delivery happens on approval, by email.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Preview — {{ $searchRequest->request_no }} · {{ $searchRequest->file_number }}</title>
  <style>
    body { background-color: #525659; font-family: Arial, sans-serif; margin: 0; padding: 10px; display: flex; flex-direction: column; align-items: center; }
    .action-bar { width: 11in; max-width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; color: #e2e8f0; font-size: 13px; }
    .action-bar a, .action-bar button { font-size: 13px; font-weight: 600; padding: 9px 16px; border-radius: 8px; border: 0; cursor: pointer; text-decoration: none; }
    .btn-light { background: #e2e8f0; color: #0f172a; }
    .btn-primary { background: #2563eb; color: #fff; }
    .notice { width: 11in; max-width: 100%; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; box-sizing: border-box; }
    .empty { width: 520px; max-width: 100%; background: #fff; border-radius: 14px; padding: 24px; text-align: center; color: #b91c1c; font-weight: 600; }

    .page { background-color: #fff; width: 11in; min-height: 8.5in; padding: 0.2in 0.4in; position: relative; box-sizing: border-box; display: flex; flex-direction: column; overflow: hidden; }
    .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); white-space: nowrap; font-size: 52px; font-weight: 900; letter-spacing: 2px; color: rgba(185,28,28,.16); text-transform: uppercase; text-align: center; pointer-events: none; z-index: 0; }
    header { width: 100%; border-bottom: 2px solid #000; padding-bottom: 2px; margin-bottom: 6px; position: relative; z-index: 1; }
    .header-top { display: flex; justify-content: space-between; align-items: center; }
    .logo-box img { width: 75px; height: auto; }
    .title-block { text-align: center; flex: 1; }
    .title-block h1 { font-size: 19px; color: #007a33; margin: 0; font-weight: bold; }
    .title-block h2 { font-size: 15px; margin: 2px 0; font-weight: bold; }
    .title-block h3 { font-size: 13px; margin: 0; text-decoration: underline; font-weight: bold; }
    .date-line { text-align: right; font-size: 13px; font-weight: bold; margin-top: 2px; position: relative; z-index: 1; }
    .section-label { border: 1px solid #000; display: inline-block; padding: 1px 6px; font-weight: bold; font-size: 11px; margin: 3px 0; background: #fff; position: relative; z-index: 1; }
    .prop-details { font-size: 12px; margin-bottom: 5px; border-collapse: collapse; width: 100%; table-layout: fixed; position: relative; z-index: 1; }
    .prop-details td { padding: 1px 0; vertical-align: top; }
    .bold-lbl-left { font-weight: bold; width: 125px; }
    .bold-lbl-right { font-weight: bold; width: 110px; padding-left: 35px; }
    .transaction-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; margin-bottom: 6px; position: relative; z-index: 1; }
    .transaction-table th, .transaction-table td { padding: 4px; vertical-align: top; border-bottom: .5px solid #eee; word-wrap: break-word; }
    .transaction-table .header-row th { font-weight: bold; border-bottom: 1.5px solid #000; text-align: left; }
    .table-end-notice { text-align: center; font-size: 9.5px; font-weight: bold; font-style: italic; margin: 6px 0 8px; }
    .remarks-container { border-top: 1px solid #000; width: 100%; padding-top: 8px; margin-top: 10px; display: flex; align-items: flex-start; position: relative; z-index: 1; }
    .remarks-label { border: 1px solid #000; padding: 2px 8px; font-weight: bold; font-size: 13px; margin-right: 12px; flex-shrink: 0; }
    .remarks-text { color: #0f766e; font-size: 13px; font-weight: bold; margin: 0; }
    .disclaimer-nb { font-size: 9.5px; font-style: italic; font-weight: bold; margin: 8px 0 4px; color: #333; position: relative; z-index: 1; }
  </style>
</head>
<body>

<div class="action-bar">
  <button onclick="window.close()" class="btn-light">Close preview</button>
  <span>{{ $searchRequest->request_no }} · {{ $searchRequest->requester_email }}</span>
  <button onclick="window.print()" class="btn-primary">Print</button>
</div>

<div class="notice">
  <strong>Preview only — not yet released.</strong>
  This is the report that will be emailed to {{ $searchRequest->requester_email }} if you approve request {{ $searchRequest->request_no }}.
  Nothing has been sent.
</div>

@if(!$report)
  <div class="empty">The report for file <strong>{{ $searchRequest->file_number }}</strong> could not be generated. Approving this request will fail until the underlying record is fixed.</div>
@else
  @php
    $rows = $report['rows'] ?? [];
    $uniqueFileNumbers = collect($rows)->pluck('file_no')->filter(fn($fn) => $fn && $fn !== '-')->unique();
    $showFileNo = $uniqueFileNumbers->count() > 1;
  @endphp

  <div class="page">
    <div class="watermark" aria-hidden="true">Preview &nbsp;&bull;&nbsp; Not Approved</div>

    <header>
      <div class="header-top">
        <div class="logo-box"><img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="" onerror="this.style.visibility='hidden'"></div>
        <div class="title-block">
          <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
          <h2>LEGAL SEARCH REPORT</h2>
          <h3>Online Pay-per Search</h3>
        </div>
        <div class="logo-box"><img src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="" onerror="this.style.visibility='hidden'"></div>
      </div>
      <div class="date-line">{{ $report['date_line'] ?? 'Date: ' . now()->format('F j, Y') }}</div>
    </header>

    <div class="section-label">Property Details</div>
    <table class="prop-details">
      <tr>
        <td class="bold-lbl-left">File Number:</td><td><strong>{{ $report['file_number'] ?: '-' }}</strong></td>
        <td class="bold-lbl-right">District/LGA:</td><td><strong>{{ $report['district_lga'] ?: '-' }}</strong></td>
      </tr>
      <tr>
        <td class="bold-lbl-left">File Title:</td><td><strong>{{ $report['file_title'] ?: '-' }}</strong></td>
        <td class="bold-lbl-right">Plot No:</td><td><strong>{{ $report['plot_no'] ?: '-' }}</strong></td>
      </tr>
      <tr>
        <td class="bold-lbl-left">Land Use:</td><td><strong>{{ $report['land_use'] ?: '-' }}</strong></td>
        <td class="bold-lbl-right">Size:</td><td><strong>{{ $report['size'] ?: '-' }}</strong></td>
      </tr>
      <tr>
        <td class="bold-lbl-left">Plot Description:</td><td><strong>{{ $report['plot_description'] ?: '-' }}</strong></td>
        <td class="bold-lbl-right">TP No:</td><td><strong>{{ $report['tpno'] ?: '-' }}</strong></td>
      </tr>
    </table>

    <div class="section-label">File History</div>
    <table class="transaction-table">
      <thead>
        <tr class="header-row">
          <th style="width:3%">S/N</th>
          @if($showFileNo)<th style="width:9%">File No</th>@endif
          <th style="width:{{ $showFileNo ? '18%' : '27%' }}">Instrument/Transaction Type</th>
          <th style="width:13%">Party 1</th>
          <th style="width:13%">Party 2</th>
          <th style="width:10%">Party 3</th>
          <th style="width:10%">Reg. Time/Date</th>
          <th style="width:9%">Particulars</th>
          <th style="width:5%">Caveat</th>
          <th style="width:10%">Comments</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          <tr>
            <td>{{ $row['sn'] ?? $loop->iteration }}</td>
            @if($showFileNo)<td>{{ $row['file_no'] ?? '-' }}</td>@endif
            <td>{{ $row['instrument_type'] ?? '-' }}</td>
            <td>{{ $row['grantor'] ?? '-' }}</td>
            <td>{{ $row['grantee'] ?? '-' }}</td>
            <td>{{ $row['party_3'] ?? '-' }}</td>
            <td>
              @if(!empty($row['reg_time']))<strong>{{ $row['reg_time'] }}</strong><br />@else-<br />@endif
              {{ $row['reg_date'] ?? '-' }}
            </td>
            <td>{{ $row['reg_no'] ?? '0/0/0' }}</td>
            <td>{{ $row['caveat'] ?? '-' }}</td>
            <td>{{ $row['comments'] ?? '-' }}</td>
          </tr>
        @empty
          <tr><td colspan="{{ $showFileNo ? 10 : 9 }}" style="text-align:center;padding:14px;">No transactions on record for this file.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="table-end-notice">*** END OF TRANSACTION HISTORY ***</div>

    <div class="remarks-container">
      <div class="remarks-label">Remarks</div>
      <p class="remarks-text">
        {{ $report['caveat_note'] ?: (($report['is_caveated'] ?? false)
            ? 'A caveat exists on this title. Please consult the Ministry before proceeding.'
            : 'Based on our available records, the title is free from encumbrances.') }}
      </p>
    </div>

    <p class="disclaimer-nb">
      N.B: This search report is deduced based on the available records from the file and does not represent any document in possession of any body.
      @if(!empty($report['remarks'])) {{ $report['remarks'] }} @endif
    </p>
  </div>
@endif

</body>
</html>

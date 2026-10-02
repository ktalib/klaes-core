<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plot Extension Application - {{ $record->file_number ?? '' }}</title>
  <style>
    @page { size: A4 portrait; margin: 1.5cm 2cm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.5; color: #000; background: #fff; }
    .page { max-width: 17cm; margin: 0 auto; padding-top: 20px; }

    .doc-header { display: flex; align-items: flex-start; justify-content: space-between; padding-bottom: 10px; border-bottom: 2.5px double #000; margin-bottom: 15px; }
    .header-logo { width: 65px; height: 65px; object-fit: contain; flex-shrink: 0; }
    .header-center { flex: 1; text-align: center; padding: 0 10px; min-width: 0; }
    .ministry-name { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; line-height: 1.2; white-space: nowrap; }
    .ministry-address { font-size: 8.5pt; margin-top: 3px; color: #333; }
    .doc-title-box { display: inline-block; border: 1.5px solid #000; padding: 4px 14px; margin-top: 7px; font-size: 11pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }

    .memo-to { font-weight: bold; text-decoration: underline; font-size: 11.5pt; margin-bottom: 12px; text-transform: uppercase; }
    .para { margin-bottom: 10px; text-align: justify; }
    .dimension-table { margin: 6px 0 10px 25px; }
    .dimension-row { display: flex; align-items: baseline; font-size: 10.5pt; margin-bottom: 3px; }
    .dim-num { min-width: 30px; }
    .dim-val { min-width: 140px; font-weight: bold; }
    .dim-count { font-weight: bold; }
    .rec-block { margin: 8px 0; }

    .sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; margin-top: 10px; margin-bottom: 4px; }
    .sig-row { display: flex; align-items: flex-end; margin-bottom: 6px; font-size: 10.5pt; }
    .sig-label { white-space: nowrap; margin-right: 6px; }
    .sig-dots { flex: 1; border-bottom: 1px solid #000; height: 18px; }
    .sig-right-label { font-weight: bold; margin-top: 3px; font-size: 11pt; }
    .section-heading { font-weight: bold; text-transform: uppercase; text-decoration: underline; margin: 14px 0 5px; font-size: 11pt; }
    .section-body { margin-bottom: 8px; }

    .doc-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1.5px solid #000; padding-top: 10px; margin-top: 28px; }
    .footer-logo { height: 80%; width: 100px; object-fit: contain; }

    .pg-blank { display:inline-block; min-width:50px; border-bottom:1px solid #000; vertical-align:bottom; }
    .pg-ref { display:inline-block; min-width:35px; border-bottom:1px solid #000; vertical-align:bottom; font-weight:bold; text-align:center; }
    .letterhead-space { height: 110px; border-bottom: 1px dashed #bbb; margin-bottom: 18px; font-size: 8pt; color: #ccc; font-style: italic; font-family: Arial, sans-serif; padding-top: 4px; }
    @media print { .letterhead-space { border-bottom: none; color: transparent; } }
    .print-btn { display: block; margin: 20px auto; padding: 8px 24px; font-size: 11pt; cursor: pointer; background: #1a5276; color: white; border: none; border-radius: 4px; font-family: Arial, sans-serif; }
    @media print {
      body { background: #fff; }
      .no-print { display: none !important; }
      .page { margin: 0; max-width: 100%; }
    }
  </style>
</head>
<body>
<div class="page">

  {{-- LETTERHEAD SPACE --}}
  <div class="letterhead-space">[ Letterhead ]</div>

  {{-- MEMO TO --}}
  <div class="memo-to">PERMANENT SECRETARY</div>

  {{-- BODY --}}
  <div class="para">
    At page <strong>{{ $record->page ?? '1' }}</strong> is an application for Plot Extension in respect of the property covered by
    <strong>{{ $record->file_number ?? '...............' }}</strong>
    situated at <strong>{{ $record->location ?? '...............' }}</strong>
    by the title holder: <strong>{{ $record->applicant_name ?? '...............' }}</strong>.
  </div>

  <div class="para">
    At page <span class="pg-ref">{{ $record->page_2 ?? '' }}</span> for the planning views, where the State Planning Authority at page <span class="pg-ref">{{ $record->page_3 ?? '' }}</span> recommended the application for Extension in view of the fact that, the site is adequately accessible and conforms with the existing land use of the area.
  </div>

  <div class="para">
    The recommended site plan at page <span class="pg-ref">{{ $record->page_4 ?? '' }}</span> and back cover has the following dimension:-
  </div>

  <div class="dimension-table">
    @if(isset($record->plotSizes) && $record->plotSizes->count())
      @foreach($record->plotSizes as $i => $size)
      <div class="dimension-row">
        <span class="dim-num">{{ ['i.','ii.','iii.','iv.','v.','vi.'][$i] ?? ($i+1).'.' }}</span>
        <span class="dim-val">{{ $size->length ?? '.......' }}m x {{ $size->width ?? '.......' }}m</span>
      </div>
      @endforeach
    @else
      <div class="dimension-row"><span class="dim-num">i.</span><span class="dim-val">..............m x ..............m</span></div>
    @endif
  </div>

  <div class="para">
    The assessed value of the extended plot is
    <strong>&#x20A6;{{ isset($record->development_value) ? number_format($record->development_value, 2) : '.................' }}</strong>
    and all applicable fees have been paid by the applicant vide receipts at back cover.
  </div>

  <div class="para">
    In view of the above, you may wish to recommend the following for the <strong>Honourable Commissioner:-</strong>
  </div>

  <div class="rec-block">
    <div class="para">
      I. <strong>CONSIDER AND APPROVE</strong> the extension in respect of
      <strong>{{ $record->file_number ?? '...............' }}</strong>
      over the piece of land situated at
      <strong>{{ $record->location ?? '...............' }}</strong>
      in favour of <strong>{{ $record->applicant_name ?? '...............' }}</strong>.
    </div>
    <div class="para">
      II. Endorse the recommended layout plan No. <strong>{{ $record->layout_plan_no ?? 'PL/KAMMA/........../.........' }}</strong> at back cover.
    </div>
  </div>

  {{-- DIRECTOR LAND SIGNATURES --}}
  <div class="sig-grid">
    <div>
      <div class="sig-row"><span class="sig-label">Name:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Rank:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div>
    </div>
    <div>
      <div class="sig-row"><span class="sig-label">Counter Sign:</span><span class="sig-dots"></span></div>
      <div class="sig-right-label">Director Land</div>
      <div class="sig-row" style="margin-top:16px;"><span class="sig-label">Date:</span><span class="sig-dots"></span></div>
    </div>
  </div>

  {{-- HONOURABLE COMMISSIONER --}}
  <div class="section-heading">HONOURABLE COMMISSIONER</div>
  <div class="section-body">The application is hereby recommended for your kind approval please.</div>
  <div class="sig-grid">
    <div>
      <div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div>
    </div>
    <div>
      <div class="sig-right-label">Permanent Secretary</div>
    </div>
  </div>

  {{-- PERMANENT SECRETARY --}}
  <div class="section-heading">PERMANENT SECRETARY</div>
  <div class="section-body">The application is hereby Approved / Not Approved.</div>
  <div class="sig-grid">
    <div>
      <div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div>
    </div>
    <div>
      <div class="sig-right-label">Honourable Commissioner</div>
    </div>
  </div>

  {{-- FOOTER --}}
  <div class="doc-footer">
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" class="footer-logo" alt="KLAES">
    <img src="http://app.klaes.ng/assets/logo/las.jpg" class="footer-logo" alt="LAS">
  </div>

</div>
<button class="print-btn no-print" onclick="window.print()">Print Document</button>
</body>
</html>

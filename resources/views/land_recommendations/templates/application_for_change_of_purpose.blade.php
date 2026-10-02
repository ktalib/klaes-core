<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change of Purpose - {{ $record->file_number ?? '' }}</title>
  <style>
    @page { size: A4 portrait; margin: 1.5cm 2cm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.5; color: #000; background: #fff; }
    .page { max-width: 17cm; margin: 0 auto; padding-top: 20px; }
    .memo-to { font-weight: bold; text-decoration: underline; font-size: 11.5pt; margin-bottom: 12px; text-transform: uppercase; }
    .para { margin-bottom: 10px; text-align: justify; }
    .rec-list { margin: 6px 0 10px 20px; list-style-type: decimal; }
    .rec-list li { margin-bottom: 5px; }
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
    @media print { body { background: #fff; } .no-print { display: none !important; } .page { margin: 0; max-width: 100%; } }
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
    At page <strong>{{ $record->page ?? '1' }}</strong> is an application for change of purpose
    {{ $record->purpose_description ? 'to <strong>'.$record->purpose_description.'</strong>' : '' }}
    of property covered by Statutory Right of Occupancy No.
    <strong>{{ $record->file_number ?? '...............' }}</strong>
    over <strong>A PIECE OF LAND</strong> situated at
    <strong>{{ $record->location ?? '...............' }}</strong>,
    submitted by the title holder; <strong>{{ $record->applicant_name ?? '...............' }}</strong>.
  </div>

  <div class="para">
    The application was referred to the Physical Planning Department of the Ministry for planning
    recommendations, where the Department at page <span class="pg-ref">{{ $record->page_2 ?? '' }}</span>
    recommended the application for change of purpose
    {{ $record->purpose_description ? 'to <strong>'.$record->purpose_description.'</strong>' : '' }}
    in view of the fact that the site under application satisfies the minimum requirement and is suitable
    for the purpose applied for.
  </div>

  <div class="para">
    The applicant at page <span class="pg-ref">{{ $record->page_3 ?? '' }}</span> was conveyed the conditions
    for the recommendation which he accepted vide letter at page <span class="pg-ref">{{ $record->page_4 ?? '' }}</span> herein.
  </div>

  <div class="para">
    The recommended and endorsed site plan at page <span class="pg-ref">{{ $record->page_5 ?? '' }}</span>
    and back cover has the following dimensions:
  </div>

  @if($record->dimensions_text ?? null)
  <div class="para" style="margin-left:25px; font-style:italic;">
    {{ $record->dimensions_text }}
  </div>
  @endif

  <div class="para">
    The term of the grant is <strong>{{ $record->term ?? '99' }} years</strong>.
  </div>

  <div class="para">
    In view of the above, you may kindly wish to recommend the following for <strong>approval</strong> of the Honourable Commissioner:
  </div>

  <ol class="rec-list">
    <li>
      Consider and Approve the application for change of purpose
      {{ $record->purpose_description ? 'to <strong>'.$record->purpose_description.'</strong>' : '' }}
      of Statutory Right of Occupancy No. <strong>{{ $record->file_number ?? '...............' }}</strong>
      over <strong>A PIECE OF LAND</strong> situated at
      <strong>{{ $record->location ?? '...............' }}</strong>
      in favour of: <strong>{{ $record->applicant_name ?? '...............' }}</strong>.
    </li>
  </ol>

  {{-- DIRECTOR LAND SIGNATURES --}}
  <div class="sig-grid">
    <div>
      <div class="sig-row"><span class="sig-label">Name:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Rank:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Signature:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div>
    </div>
    <div>
      <div class="sig-row"><span class="sig-label">Counter Sign:</span><span class="sig-dots"></span></div>
      <div class="sig-right-label">Director Land</div>
      <div class="sig-row" style="margin-top:16px;"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div>
    </div>
  </div>

  {{-- HONOURABLE COMMISSIONER --}}
  <div class="section-heading">Honourable Commissioner</div>
  <div class="section-body">The application is hereby recommended for your kind approval, please.</div>
  <div class="sig-grid">
    <div>
      <div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div>
    </div>
    <div><div class="sig-right-label">Permanent Secretary</div></div>
  </div>

  {{-- PERMANENT SECRETARY --}}
  <div class="section-heading">PERMANENT SECRETARY</div>
  <div class="section-body">The application is hereby <strong>APPROVED/NOT APPROVED.</strong></div>
  <div class="sig-grid">
    <div>
      <div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div>
      <div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div>
    </div>
    <div><div class="sig-right-label">Honourable Commissioner</div></div>
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

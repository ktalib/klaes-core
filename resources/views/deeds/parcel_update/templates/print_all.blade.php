<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Parcel Update Templates — Print All</title>
  <style>
    @page { size: A4 portrait; margin: 1.5cm 2cm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.5; color: #000; background: #fff; }

    /* ── each template occupies one page ── */
    .doc-page { page-break-after: always; padding: 20px 0 0; max-width: 17cm; margin: 0 auto; }
    .doc-page:last-child { page-break-after: avoid; }

    /* ── on-screen separator between pages ── */
    @media screen {
      body { background: #e8edf2; padding: 20px; }
      .doc-page {
        background: #fff;
        width: 210mm;
        min-height: 297mm;
        margin: 0 auto 40px;
        padding: 20px 2cm;
        box-shadow: 0 4px 18px rgba(0,0,0,0.18);
      }
    }

    /* ── HEADER ── */
    .doc-header { display: flex; align-items: flex-start; justify-content: space-between; padding-bottom: 10px; border-bottom: 2.5px double #000; margin-bottom: 15px; }
    .header-logo { width: 65px; height: 65px; object-fit: contain; flex-shrink: 0; }
    .header-center { flex: 1; text-align: center; padding: 0 10px; min-width: 0; }
    .ministry-name { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; line-height: 1.2; white-space: nowrap; }
    .ministry-address { font-size: 8.5pt; margin-top: 3px; color: #333; }
    .doc-title-box { display: inline-block; border: 1.5px solid #000; padding: 4px 14px; margin-top: 7px; font-size: 11pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }

    /* ── MEMO BODY ── */
    .memo-to { font-weight: bold; text-decoration: underline; font-size: 11.5pt; margin-bottom: 12px; text-transform: uppercase; }
    .para { margin-bottom: 10px; text-align: justify; }
    .rec-list { margin: 6px 0 10px 20px; list-style-type: decimal; }
    .rec-list li { margin-bottom: 5px; }
    .rec-block { margin: 8px 0; }
    .dimension-table { margin: 6px 0 10px 25px; }
    .dimension-row { display: flex; align-items: baseline; font-size: 10.5pt; margin-bottom: 3px; }
    .dim-num { min-width: 30px; }
    .dim-val { min-width: 140px; font-weight: bold; }
    .dim-count { font-weight: bold; }

    /* ── SIGNATURES ── */
    .sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; margin-top: 10px; margin-bottom: 4px; }
    .sig-row { display: flex; align-items: flex-end; margin-bottom: 6px; font-size: 10.5pt; }
    .sig-label { white-space: nowrap; margin-right: 6px; }
    .sig-dots { flex: 1; border-bottom: 1px solid #000; height: 18px; }
    .sig-right-label { font-weight: bold; margin-top: 3px; font-size: 11pt; }
    .section-heading { font-weight: bold; text-transform: uppercase; text-decoration: underline; margin: 14px 0 5px; font-size: 11pt; }
    .section-body { margin-bottom: 8px; }

    /* ── LETTERHEAD SPACE ── */
    .letterhead-space { height: 110px; border-bottom: 1px dashed #bbb; margin-bottom: 18px; font-size: 8pt; color: #ccc; font-style: italic; font-family: Arial, sans-serif; padding-top: 4px; }
    @media print { .letterhead-space { border-bottom: none; color: transparent; } }

    /* ── FOOTER ── */
    .doc-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1.5px solid #000; padding-top: 10px; margin-top: 28px; }
    .footer-logo { height: 80%; width: 100px; object-fit: contain; }

    /* ── PRINT BUTTON (screen only) ── */
    .print-bar { position: fixed; bottom: 20px; right: 20px; z-index: 999; display: flex; gap: 10px; }
    .print-btn { padding: 10px 22px; font-size: 12pt; cursor: pointer; background: #1a5276; color: white; border: none; border-radius: 6px; font-family: Arial, sans-serif; box-shadow: 0 3px 10px rgba(0,0,0,0.3); }
    .print-btn:hover { background: #154360; }
    .close-btn { padding: 10px 16px; font-size: 12pt; cursor: pointer; background: #555; color: white; border: none; border-radius: 6px; font-family: Arial, sans-serif; box-shadow: 0 3px 10px rgba(0,0,0,0.3); }
    @media print { .print-bar { display: none !important; } }
  </style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════════
     PAGE 1 — PLOT MERGER
══════════════════════════════════════════════════════════════ --}}
@php $record = $merger; @endphp
<div class="doc-page">
  <div class="letterhead-space">[ Letterhead ]</div>
  <div class="memo-to">PERMANENT SECRETARY</div>
  <div class="para">At page 1 is an application for Plot Merger in respect of the property covered by <strong>{{ $record->file_no }}</strong> over a piece of land situated at {{ $record->house_no ? $record->house_no.', ' : '' }}{{ $record->street_name }}, {{ $record->district }}, {{ $record->lga }} by the title holder: <strong>{{ $record->applicant_name }}</strong>.</div>
  <div class="para">The application was forwarded to the State Planning Authority (KAMMA) for planning advice; consequently the Authority via its memo at page &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; recommended the application for the merger of the said plots into <strong>{{ $record->num_plots }}no(s). Plot(s)</strong>, in view of the fact that the merged plot is independently accessible and conforms with the existing land use of the area.</div>
  <div class="para">The recommended site plan at page &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and back cover has the following dimension:-</div>
  <div class="dimension-table">
    @foreach($record->plotSizes as $i => $size)
    <div class="dimension-row"><span class="dim-num">{{ ['i.','ii.','iii.','iv.','v.'][$i] ?? ($i+1).'.' }}</span><span class="dim-val">{{ $size->length }}m x {{ $size->width }}m</span><span class="dim-count">&mdash; {{ $size->count }}No.</span></div>
    @endforeach
  </div>
  <div class="para">In view of the above, you may wish to recommend the following for the <strong>Honourable Commissioner:-</strong></div>
  <div class="rec-block">
    <div class="para">I. <strong>CONSIDER AND APPROVE</strong> the merger in respect of <strong>{{ $record->file_no }}</strong> over a piece of land situated at {{ $record->street_name }}, {{ $record->district }} into <strong>{{ $record->num_plots }}no(s). Plot(s)</strong> in favour of <strong>{{ $record->applicant_name }}</strong>.</div>
    <div class="para">II. Endorse the recommended layout plan No. <strong>{{ $record->knupda_remarks }}</strong> at back cover.</div>
  </div>
  <div class="sig-grid">
    <div><div class="sig-row"><span class="sig-label">Name:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Rank:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div>
    <div><div class="sig-row"><span class="sig-label">Counter Sign:</span><span class="sig-dots"></span></div><div class="sig-right-label">Director Land</div><div class="sig-row" style="margin-top:16px;"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div>
  </div>
  <div class="section-heading">HONOURABLE COMMISSIONER</div>
  <div class="section-body">The application is hereby recommended for your kind approval please.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div><div><div class="sig-right-label">Permanent Secretary</div></div></div>
  <div class="section-heading">PERMANENT SECRETARY</div>
  <div class="section-body">The application is hereby Approved / Not Approved.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div><div><div class="sig-right-label">Honourable Commissioner</div></div></div>
  <div class="doc-footer">
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" class="footer-logo" alt="KLAES">
    <img src="http://app.klaes.ng/assets/logo/las.jpg" class="footer-logo" alt="LAS">
  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     PAGE 2 — PLOT SUBDIVISION
══════════════════════════════════════════════════════════════ --}}
@php $record = $subdivision; @endphp
<div class="doc-page">
  <div class="letterhead-space">[ Letterhead ]</div>
  <div class="memo-to">PERMANENT SECRETARY</div>
  <div class="para">At page 1 is an application for private layout in respect of the property covered by <strong>{{ $record->file_no }}</strong> over a piece of land situated at {{ $record->street_name }}, {{ $record->district }}, {{ $record->lga }} by the title holder: <strong>{{ $record->applicant_name }}</strong>.</div>
  <div class="para">The application was forwarded to the State Planning Authority for planning advice; consequently the Authority via its memo at page &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; recommended the application for the subdivision of the property into <strong>{{ $record->num_plots }}nos. Portions</strong>, in view of the fact that, each portion is independently accessible and conforms with the existing land use of the area.</div>
  <div class="para">The recommended site plan at page &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and back cover has the following dimension:-</div>
  <div class="dimension-table">
    @foreach($record->plotSizes as $i => $size)
    <div class="dimension-row"><span class="dim-num">{{ ['i.','ii.','iii.','iv.','v.'][$i] ?? ($i+1).'.' }}</span><span class="dim-val">{{ $size->length }}m x {{ $size->width }}m</span><span class="dim-count">&mdash; {{ $size->count }}No.</span></div>
    @endforeach
  </div>
  <div class="para">In view of the above, you may wish to recommend the following for the <strong>Honourable Commissioner:-</strong></div>
  <div class="rec-block">
    <div class="para">I. <strong>CONSIDER AND APPROVE</strong> the sub-division in respect <strong>{{ $record->file_no }}</strong> over a piece of land situated at {{ $record->street_name }}, {{ $record->district }} into <strong>{{ $record->num_plots }}nos. Portions</strong> in favour of <strong>{{ $record->applicant_name }}</strong>.</div>
    <div class="para">II. Endorse the recommended layout plan No. <strong>{{ $record->knupda_remarks }}</strong> at back cover.</div>
  </div>
  <div class="sig-grid">
    <div><div class="sig-row"><span class="sig-label">Name:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Rank:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div>
    <div><div class="sig-row"><span class="sig-label">Counter Sign:</span><span class="sig-dots"></span></div><div class="sig-right-label">Director Land</div><div class="sig-row" style="margin-top:16px;"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div>
  </div>
  <div class="section-heading">HONOURABLE COMMISSIONER</div>
  <div class="section-body">The application is hereby recommended for your kind approval please.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div><div><div class="sig-right-label">Permanent Secretary</div></div></div>
  <div class="section-heading">PERMANENT SECRETARY</div>
  <div class="section-body">The application is hereby Approved / Not Approved.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div><div><div class="sig-right-label">Honourable Commissioner</div></div></div>
  <div class="doc-footer">
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" class="footer-logo" alt="KLAES">
    <img src="http://app.klaes.ng/assets/logo/las.jpg" class="footer-logo" alt="LAS">
  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     PAGE 3 — STATUTORY ROO (RESIDENTIAL)
══════════════════════════════════════════════════════════════ --}}
@php $record = $rooResidential; @endphp
<div class="doc-page">
  <div class="letterhead-space">[ Letterhead ]</div>
  <div class="memo-to">PERMANENT SECRETARY</div>
  <div class="para">At page 1 is an application for statutory Right of Occupancy over GP No. <strong>{{ $record->plot_number }}</strong> at <strong>{{ $record->location }}</strong> by <strong>{{ $record->applicant_name }}</strong>.</div>
  <div class="para">The applicant acquired the GP No. <strong>{{ $record->plot_number }}</strong> through the monetization policy of Government. The applicant has settled all appropriate payments as per clearance letter at page &nbsp;&nbsp;&nbsp;&nbsp; where it is confirmed that, the beneficiary has paid the sum of <strong>&#x20A6;{{ number_format($record->premium, 2) }}</strong> ({{ $record->premium_words }}) (as purchase price) with receipt No. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; copy attached a, b, c. In addition, the applicant has paid the sum of <strong>&#x20A6;{{ number_format($record->preparation_fees, 2) }}</strong> ({{ $record->preparation_fees_words }}) being payment for 5% purchasing price of GP vide page &nbsp;&nbsp;&nbsp;&nbsp; and triplicate receipt at back cover.</div>
  <div class="para">The term of the grant is <strong>{{ $record->term }} years</strong>.</div>
  <div class="para">In view of the above, you may wish to recommend this application to the Honourable Commissioner for approval.</div>
  <ol class="rec-list"><li>Grant statutory Right of Occupancy No. <strong>{{ $record->file_number }}</strong> in respect of GP No. <strong>{{ $record->plot_number }}</strong> situated at <strong>{{ $record->location }}</strong> in favour of <strong>{{ $record->applicant_name }}</strong>.</li></ol>
  <div class="sig-grid">
    <div><div class="sig-row"><span class="sig-label">Name:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Rank:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Signature:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div></div>
    <div><div class="sig-row"><span class="sig-label">Counter Sign:</span><span class="sig-dots"></span></div><div class="sig-right-label">Director Land</div><div class="sig-row" style="margin-top:16px;"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div></div>
  </div>
  <div class="section-heading">Honorable Commissioner</div>
  <div class="section-body">The application is hereby recommended for your kind approval, please.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div></div><div><div class="sig-right-label">Permanent Secretary</div></div></div>
  <div class="section-heading">PERMANENT SECRETARY</div>
  <div class="section-body">The application is hereby <strong>APPROVED/NOT APPROVED.</strong></div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div></div><div><div class="sig-right-label">Honorable Commissioner</div></div></div>
  <div class="doc-footer">
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" class="footer-logo" alt="KLAES">
    <img src="http://app.klaes.ng/assets/logo/las.jpg" class="footer-logo" alt="LAS">
  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     PAGE 4 — STATUTORY ROO (COMMERCIAL)
══════════════════════════════════════════════════════════════ --}}
@php $record = $rooCommercial; @endphp
<div class="doc-page">
  <div class="letterhead-space">[ Letterhead ]</div>
  <div class="memo-to">PERMANENT SECRETARY</div>
  <div class="para">At page 1 is an application for Statutory Right of Occupancy No. <strong>{{ $record->file_number }}</strong> over <strong>A PIECE OF LAND</strong> situated at <strong>{{ $record->location }}</strong>, {{ $record->lga }} Local Government Area of Kano State, in favour of <strong>{{ $record->applicant_name }}</strong>.</div>
  <div class="para">The application was forwarded to the Lands Department for scrutiny. The applicant has satisfied all statutory requirements including payment of all prescribed fees and charges as per the clearance documents attached. The applicant has paid the sum of <strong>&#x20A6;{{ number_format($record->premium, 2) }}</strong> ({{ $record->premium_words }}) being the assessed value of the land.</div>
  <div class="para">The applicant has also paid the processing fee of <strong>&#x20A6;{{ number_format($record->preparation_fees, 2) }}</strong> as confirmed by the receipt copies at back cover.</div>
  <div class="para">The term of the grant is <strong>{{ $record->term }} years</strong> for <strong>{{ $record->purpose_of_clause }}</strong>.</div>
  <div class="para">In view of the above, you may wish to recommend this application to the Honourable Commissioner for approval.</div>
  <ol class="rec-list"><li>Grant Statutory Right of Occupancy No. <strong>{{ $record->file_number }}</strong> over a Piece of Land situated at <strong>{{ $record->location }}</strong> in favour of <strong>{{ $record->applicant_name }}</strong>.</li></ol>
  <div class="sig-grid">
    <div><div class="sig-row"><span class="sig-label">Name:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Rank:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Signature:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div></div>
    <div><div class="sig-row"><span class="sig-label">Counter Sign:</span><span class="sig-dots"></span></div><div class="sig-right-label">Director Land</div><div class="sig-row" style="margin-top:16px;"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div></div>
  </div>
  <div class="section-heading">Honorable Commissioner</div>
  <div class="section-body">The application is hereby recommended for your kind approval, please.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div></div><div><div class="sig-right-label">Permanent Secretary</div></div></div>
  <div class="section-heading">PERMANENT SECRETARY</div>
  <div class="section-body">The application is hereby <strong>APPROVED/NOT APPROVED.</strong></div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span>&nbsp;{{ date('Y') }}</div></div><div><div class="sig-right-label">Honorable Commissioner</div></div></div>
  <div class="doc-footer">
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" class="footer-logo" alt="KLAES">
    <img src="http://app.klaes.ng/assets/logo/las.jpg" class="footer-logo" alt="LAS">
  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     PAGE 5 — TEMPORARY FILE NUMBER
══════════════════════════════════════════════════════════════ --}}
@php $record = $tempFile; @endphp
<div class="doc-page">
  <div class="letterhead-space">[ Letterhead ]</div>
  <div class="memo-to">PERMANENT SECRETARY</div>
  <div class="para">At page 1 is an application for temporary file No. <strong>{{ $record->file_no }}</strong> over Plot No. <strong>{{ $record->plot_no }}</strong> part of <strong>{{ $record->file_title }}</strong> situated at {{ $record->house_no ? $record->house_no.', ' : '' }}<strong>{{ $record->street_name }}{{ $record->district ? ', '.$record->district : '' }}</strong> by the applicant herein; <strong>{{ $record->applicant_name }}</strong>.</div>
  <div class="para">The application was forwarded to Cadastral Department for verification and available records at Cadastral Department vide page &nbsp;&nbsp;&nbsp;&nbsp; indicate that, as per their record Plot No. <strong>{{ $record->plot_no }}</strong> part of <strong>{{ $record->file_title }}</strong> within <strong>{{ $record->street_name }}</strong> is open. In addition, the file was referred to Deed Department and vide the report at page &nbsp;&nbsp;&nbsp;&nbsp; the title No. <strong>{{ $record->file_no }}</strong> is at Letter of Grant Stage.</div>
  <div class="para">The applicant has complied with and fulfilled the requirements for temporary file application.</div>
  <div class="para">In view of the above you may kindly recommend the following for the approval of the Honourable Commissioner:-</div>
  <div class="rec-block"><div class="para"><strong>Approve</strong> the application for temporary file No. <strong>{{ $record->file_no }}</strong> over Plot No. <strong>{{ $record->plot_no }}</strong> part of <strong>{{ $record->file_title }}</strong> situated at <strong>{{ $record->street_name }}{{ $record->district ? ', '.$record->district : '' }}</strong> in favour of <strong>{{ $record->applicant_name }}</strong>.</div></div>
  <div class="sig-grid">
    <div><div class="sig-row"><span class="sig-label">Name:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Rank:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div>
    <div><div class="sig-row"><span class="sig-label">Counter Sign:</span><span class="sig-dots"></span></div><div class="sig-right-label">Director Land</div><div class="sig-row" style="margin-top:16px;"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div>
  </div>
  <div class="section-heading">HONOURABLE COMMISSIONER</div>
  <div class="section-body">The application is hereby recommended for your kind approval please.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div><div><div class="sig-right-label">Permanent Secretary</div></div></div>
  <div class="section-heading">PERMANENT SECRETARY</div>
  <div class="section-body">The application is hereby approved / Not approved.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div><div><div class="sig-right-label">Honourable Commissioner</div></div></div>
  <div class="doc-footer">
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" class="footer-logo" alt="KLAES">
    <img src="http://app.klaes.ng/assets/logo/las.jpg" class="footer-logo" alt="LAS">
  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     PAGE 6 — PLOT EXTENSION
══════════════════════════════════════════════════════════════ --}}
@php $record = $extension; @endphp
<div class="doc-page">
  <div class="letterhead-space">[ Letterhead ]</div>
  <div class="memo-to">PERMANENT SECRETARY</div>
  <div class="para">At page 1 is an application for Plot Extension in respect of the property covered by <strong>{{ $record->file_no }}</strong> situated at {{ $record->house_no ? $record->house_no.', ' : '' }}<strong>{{ $record->street_name }}{{ $record->district ? ', '.$record->district : '' }}, {{ $record->lga }}</strong> by the title holder: <strong>{{ $record->applicant_name }}</strong>.</div>
  <div class="para">At page &nbsp;&nbsp;&nbsp;&nbsp; for the planning views, where the State Planning Authority at page &nbsp;&nbsp;&nbsp;&nbsp; recommended the application for Extension in view of the fact that, the site is adequately accessible and conforms with the existing land use of the area.</div>
  <div class="para">The recommended site plan at page &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and back cover has the following dimension:-</div>
  <div class="dimension-table">
    @foreach($record->plotSizes as $i => $size)
    <div class="dimension-row"><span class="dim-num">{{ ['i.','ii.','iii.'][$i] ?? ($i+1).'.' }}</span><span class="dim-val">{{ $size->length }}m x {{ $size->width }}m</span><span class="dim-count">&mdash; {{ $size->count }}No.</span></div>
    @endforeach
  </div>
  <div class="para">The assessed value of the extended plot is <strong>&#x20A6;{{ number_format($record->land_value, 2) }}</strong> and all applicable fees have been paid by the applicant vide receipts at back cover.</div>
  <div class="para">In view of the above, you may wish to recommend the following for the <strong>Honourable Commissioner:-</strong></div>
  <div class="rec-block">
    <div class="para">I. <strong>CONSIDER AND APPROVE</strong> the extension in respect of <strong>{{ $record->file_no }}</strong> over the piece of land situated at {{ $record->street_name }}{{ $record->district ? ', '.$record->district : '' }} in favour of <strong>{{ $record->applicant_name }}</strong>.</div>
    <div class="para">II. Endorse the recommended layout plan No. <strong>{{ $record->knupda_remarks }}</strong> at back cover.</div>
  </div>
  <div class="sig-grid">
    <div><div class="sig-row"><span class="sig-label">Name:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Rank:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div>
    <div><div class="sig-row"><span class="sig-label">Counter Sign:</span><span class="sig-dots"></span></div><div class="sig-right-label">Director Land</div><div class="sig-row" style="margin-top:16px;"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div>
  </div>
  <div class="section-heading">HONOURABLE COMMISSIONER</div>
  <div class="section-body">The application is hereby recommended for your kind approval please.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div><div><div class="sig-right-label">Permanent Secretary</div></div></div>
  <div class="section-heading">PERMANENT SECRETARY</div>
  <div class="section-body">The application is hereby Approved / Not Approved.</div>
  <div class="sig-grid"><div><div class="sig-row"><span class="sig-label">Sign:</span><span class="sig-dots"></span></div><div class="sig-row"><span class="sig-label">Date:</span><span class="sig-dots"></span></div></div><div><div class="sig-right-label">Honourable Commissioner</div></div></div>
  <div class="doc-footer">
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" class="footer-logo" alt="KLAES">
    <img src="http://app.klaes.ng/assets/logo/las.jpg" class="footer-logo" alt="LAS">
  </div>
</div>

{{-- ── FLOATING PRINT / CLOSE BUTTONS ── --}}
<div class="print-bar">
  <button class="close-btn" onclick="window.close()">&#x2715; Close</button>
  <button class="print-btn" onclick="window.print()">&#128438; Print All 6 Pages</button>
</div>

<script>
  // Auto-open print dialog when opened via "Print All" button
  if (window.opener) {
    window.addEventListener('load', () => setTimeout(() => window.print(), 800));
  }
</script>
</body>
</html>

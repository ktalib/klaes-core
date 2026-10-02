<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kano State – Batch RofO Print ({{ $records->count() }} records)</title>
    <style>
        :root { --gov-green: #006b3f; }
        body {
            background-color: #d3d3d3;
            display: flex;
            flex-direction: column;
            align-items: center;
            line-height: 3;
            font-size: 20pt;
            padding: 20px 0;
            margin: 0;
            font-family: "Times New Roman", Times, serif;
        }
        .page-container {
            background-color: #fff;
            width: 210mm;
            height: 297mm;
            box-shadow: 0 0 30px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
            box-sizing: border-box;
        }
        /* Same security paper as the single RofO print — see the note there. Kept
           identical on purpose: a batch is the same letter, many times. */
        .page-container { --security-bg-opacity: 1; }
        /* The security paper — see the note in rofo_print.blade.php. OFF here too:
           drop the `display:none` to put it back. */
        .security-bg {
            display: none;
            position: absolute; top:0; left:0; width:100%; height:100%;
            opacity: var(--security-bg-opacity); pointer-events: none;
            background-image: url("{{ asset('assets/letterhead/rofo-security-paper.jpg') }}");
            background-size: cover; background-position: center; background-repeat: no-repeat;
            z-index:0;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .content-wrapper { flex:1; margin:6mm 8mm 0 8mm; position:relative; z-index:1; display:flex; flex-direction:column; }
        /* Slimmed from 45px, in step with the single RofO print — the width it gives
           back goes into the boxes below. */
        .ornate-border {
            border: 32px solid transparent;
            border-image-source: url("http://app.klaes.ng/storage/template_frames/land.png");
            border-image-slice: 160; border-image-repeat: round; border-image-width: 32px;
            margin: 6mm 8mm -4mm 8mm;
        }
        .simple-margin { margin: 20mm; }
        .inner-content { padding:12px 30px 8px 30px; flex:1; box-sizing:border-box; font-size:14.5px; line-height:1.35; display:flex; flex-direction:column; }
        .row { display:flex; margin-bottom:3px; font-weight:bold; align-items:baseline; }
        .title-center { text-align:center; color:#c90202!important; font-size:15px; font-weight:bold; text-decoration:underline; margin:5px 0; }
        /* Width moved into the free-text "To:" box so a long address wraps less.
           1.2fr / 1.3fr is the measured limit once the dotted rules opposite are
           trimmed — at 1.3fr the right box hits its min-content width and its
           labels break across two lines. See the fuller note in
           rofo_print.blade.php. */
        .ref-grid { display:grid; grid-template-columns:1.2fr 1.3fr; gap:15px; margin:8px 0; }
        .bordered-section { border:2px solid #000; padding:10px 12px; margin-top:5px; background:#fff; height:100%; box-sizing:border-box; }
        /* One grid for the whole details box, so all four dotted rules start and end
           on the same x instead of each beginning where its own label ran out. See
           the fuller note in rofo_print.blade.php. */
        .ref-details { display:grid; grid-template-columns:max-content minmax(120px,1fr); column-gap:6px; row-gap:10px; align-items:baseline; }
        .ref-details .ref-label { font-weight:bold; white-space:nowrap; }
        .ref-details .inline-data { min-width:0; margin-left:0; margin-right:0; }
        /* margin-right, not a space in the markup: .sub-item-line is a flex
           container and drops whitespace between its children. See rofo_print. */
        .inline-data { display:inline-block; border-bottom:1px dotted #000; min-width:45px; margin-left:5px; margin-right:5px; font-weight:normal; color:#000; padding-bottom:1px; }
        .conditions-list-fixed p { margin:4px 0; text-align:justify; line-height:1.4; }
        .condition-item { margin-bottom:8px; }
        .sub-item { margin-left:20px; margin-top:2px; }
        .sub-item-line { display:flex; align-items:baseline; margin-bottom:2px; }
        .sub-item-label { min-width:20px; margin-right:5px; }
        .signature-block { margin-top:auto; display:flex; justify-content:space-between; padding:0 40px 20px 40px; text-align:center; font-weight:bold; align-items:flex-end; }
        .signature-block > div { display:flex; flex-direction:column; align-items:center; }
        .security-line-container {
            position:relative; width:280px; height:15px; margin-bottom:1px; overflow:hidden; background:transparent;
        }
        .security-line-container::after {
            content:"Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning ";
            position:absolute; top:-1px; left:0; width:100%; font-size:3px; font-weight:900; letter-spacing:-0.65px; word-spacing:-3.2px;
            color:#000; white-space:nowrap; overflow:hidden; text-transform:uppercase; pointer-events:none; z-index:2;
            font-family:'Arial Narrow','Helvetica Condensed','Courier New',monospace; text-align:center; line-height:1;
            text-shadow:0 0.5px 0 #666;
        }
        .date-line { width:150px; border-top:2px solid #000; padding-top:0; margin-top:0; height:1px; margin-bottom:8px; }
        .footer-barcode-area { height:22mm; padding:0 22mm 4mm 22mm; display:flex; justify-content:space-between; align-items:flex-end; z-index:5; position:relative; }
        /* Page 2 */
        .applicant-address-block { display:flex; border:1px solid #000; margin-bottom:15px; min-height:100px; }
        .left-commissioner { padding:10px; width:50%; border-right:1px solid #000; display:flex; flex-direction:column; justify-content:flex-end; }
        .right-address { padding:10px; width:50%; }
        .address-line-box { height:20px; border-bottom:1px dotted #000; display:flex; align-items:center; margin-top:8px; }
        .fee-table { width:100%; border-collapse:collapse; margin:10px 0; font-size:11px; }
        .fee-table th, .fee-table td { border:1px solid #000; padding:4px; }
        .note-box { border:1px solid #000; padding:10px; margin-top:20px; font-weight:bold; font-size:11px; }
        .signature-row { display:flex; justify-content:space-between; margin-top:60px; }
        .signature-item { border-top:1px solid #000; width:45%; text-align:center; padding-top:5px; }
        .signature-item-date { border-top:1px solid #000; width:30%; text-align:center; padding-top:5px; }
        /* Only the ORIGINAL copy carries colour; the Duplicate and Triplicate are
           office copies and print black & white. Desaturating the wrapper (rather
           than each image) is what takes the ornate frame with it — the frame is a
           border-image, which no img rule can reach — and the coloured text rules
           are overridden to black because grayscale alone leaves red and blue as
           mid-greys. */
        /* The wrapper-level grayscale is gone: the coat of arms and the ministry
           badge must carry colour on every copy, and a CSS filter cannot be undone
           on a descendant. The ornate frame prints in colour on the office copies
           as a consequence. Kept in step with rofo_print.blade.php. */
        /* The security paper is a sibling of .content-wrapper and is not part of
           the letter's identity, so office copies still print on plain paper. */
        .copy-bw .security-bg { filter:grayscale(100%); -webkit-filter:grayscale(100%); }
        .copy-bw .title-center { color:#000!important; }
        /* No .version-label override: the copy label prints in the header red. */
        /* Batch print button */
        .print-btn-container { position:fixed; top:20px; right:20px; z-index:1000; display:flex; gap:10px; }
        .print-btn { padding:12px 24px; background-color:#006b3f; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold; box-shadow:0 2px 8px rgba(0,0,0,0.2); font-size:14px; }
        .print-btn:hover { background-color:#004d2c; }
        @media print {
            @page { size:A4; margin:0!important; }
            body { background:none!important; padding:0!important; margin:0!important; -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
            .page-container { box-shadow:none!important; margin:0!important; height:100vh!important; page-break-after:always!important; }
            .page-container:last-of-type { page-break-after:auto!important; }
            .print-btn-container { display:none!important; }
        }
    </style>
</head>
<body>
<div class="print-btn-container no-print">
    <button class="print-btn" onclick="window.print()">&#128438; Print All ({{ $records->count() }} RofOs)</button>
    <button class="print-btn" style="background:#334155;" onclick="window.close()">Close</button>
</div>

@php
    // Each copy keeps its own colour (red / blue / green), as in
    // rofo_print.blade.php — they are no longer blacked out on the office copies.
    $versionColors = ['Original'=>'#ff0000','Duplicate'=>'#0000ff','Triplicate'=>'#008000'];
    $versions      = ['Original','Duplicate','Triplicate'];
    $categories    = [
        ['label'=>'Agriculture',           'fee'=>10000],
        ['label'=>'Residential',           'fee'=>20000,'extra'=>'+'],
        ['label'=>'i. Very High Density',  'fee'=>20000,'extra'=>'+','is_sub'=>true],
        ['label'=>'ii. High Density',      'fee'=>20000,'extra'=>'+','is_sub'=>true],
        ['label'=>'iii. Medium Density',   'fee'=>20000,'extra'=>'+','is_sub'=>true],
        ['label'=>'iv. Low Density',       'fee'=>25000,'extra'=>'+','is_sub'=>true],
        ['label'=>'Commercial',            'fee'=>10000],
        ['label'=>'Industrial',            'fee'=>10000],
    ];
    $isFirstRecord = true;
@endphp

@foreach($records as $recommendation)
@php
    $securityCode      = $securityCodes[$recommendation->id] ?? null;
    $recordBreakStyle  = $isFirstRecord ? '' : 'page-break-before: always;';
    $isFirstRecord     = false;
@endphp

@foreach($versions as $vIdx => $version)
@php
    // Only the ORIGINAL copy prints in colour; the office copies go out B&W.
    $isBwCopy = $version !== 'Original';
@endphp
{{-- ═══════════════  PAGE 1 ═══════════════ --}}
<div class="page-container{{ $isBwCopy ? ' copy-bw' : '' }}" style="{{ ($vIdx === 0) ? $recordBreakStyle : 'page-break-before: always;' }}">
    <div class="security-bg"></div>
    <div class="content-wrapper ornate-border">
        <div class="inner-content" style="position:relative;">
            {{-- Version + security code top-right --}}
            <div style="position:absolute;top:10px;right:10px;text-align:right;font-weight:bold;font-size:16px;letter-spacing:0.35em;z-index:2;">
                <span class="version-label" style="color:{{ $versionColors[$version] }};text-transform:uppercase;">{{ $version }}</span>
                @if($securityCode)
                    @php $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code); @endphp
                    <div style="display:flex;justify-content:flex-end;margin-top:4px;">
                        <div style="display:inline-flex;align-items:center;gap:4px;letter-spacing:normal;">
                            <span style="line-height:1;color:#334155;display:inline-flex;flex-direction:column;align-items:center;font-weight:900;font-family:Arial,sans-serif;">
                                <span style="border-bottom:1.5px solid #334155;padding-bottom:1px;font-size:8px;">{{ $sc['alphabet'] }}</span>
                                <span style="padding-top:1px;font-size:8px;">{{ $sc['digits_start'] }}</span>
                            </span>
                            <span style="font-size:13px;font-weight:900;letter-spacing:0.1em;color:#334155;font-family:'Courier New',monospace;">{{ $sc['digits_end'] }}</span>
                        </div>
                    </div>
                @endif
            </div>
            {{-- Header --}}
            <div style="position:relative;margin-bottom:4px;margin-top:10px;min-height:110px;">
                <div style="text-align:center;">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" alt="Nigeria Seal" style="width:100px;height:auto;display:inline-block;" />
                </div>
                {{-- Signed KLAES-Q1 token, matching the single-RofO template; falls back
                     to the legacy payload when QR signing is not configured. --}}
                @php
                    $qrData = document_qr_token('ROFO', $recommendation->id ?? null, [
                        'source_table' => 'land_recommendations',
                        'file_number'  => $recommendation->file_number ?? null,
                        'tracking_id'  => $recommendation->tracking_id ?? null,
                    ]) ?: trim((string) ($recommendation->tracking_id ?? $recommendation->file_number));
                @endphp
                <img src="{{ qr_data_uri($qrData, 150) }}" alt="QR" style="position:absolute;left:40px;top:50%;transform:translateY(-50%);width:55px;height:55px;" />
            </div>
            <div style="text-align:center;margin-bottom:8px;">
                <div style="display:inline-block;border:2px solid #000;border-radius:8px;padding:3px;background:#fff;">
                    <div style="background:#662631;padding:8px 16px;border-radius:5px;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
                        <p style="font-weight:bold;color:#fff;text-transform:uppercase;font-size:15px;margin:0;letter-spacing:0.5px;">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</p>
                    </div>
                </div>
            </div>
            <div class="ref-grid">
                <div>
                    <div class="bordered-section" style="padding:20px 15px;">
                        <div class="row" style="margin-bottom:25px;align-items:flex-end;">
                            <span style="font-weight:bold;font-size:16px;margin-right:8px;white-space:nowrap;">To:</span>
                            <span class="inline-data" style="flex:1;text-align:left;border-bottom:1.5px dotted #000;font-size:16px;padding-bottom:2px;line-height:1;">{{ $recommendation->applicant_name }}</span>
                        </div>
                        <div class="row" style="margin-bottom:10px;align-items:flex-end;">
                            <span style="width:32px;display:inline-block;"></span>
                            <span class="inline-data" style="flex:1;text-align:left;border-bottom:1.5px dotted #000;font-size:16px;padding-bottom:2px;min-height:20px;line-height:1;">{{ $recommendation->applicant_address }}</span>
                        </div>
                    </div>
                </div>
                @php
                    // Strip a leading plot-number token (legacy prefix) and force
                    // uppercase so mixed-case legacy locations print consistently.
                    $printLocation = trim((string) ($recommendation->location ?? ''));
                    $plotNo = trim((string) ($recommendation->plot_number ?? ''));
                    if ($plotNo !== '' && $printLocation !== '') {
                        $printLocation = preg_replace('/^' . preg_quote($plotNo, '/') . '\s*[-\/]?\s*/i', '', $printLocation);
                    }
                    $printLocation = trim(preg_replace('/\s+/', ' ', $printLocation));
                    if ($printLocation !== '') {
                        $printLocation = mb_strtoupper($printLocation, 'UTF-8');
                    }

                    // Every application type derives from an existing file, and those cite
                    // the parent file number in place of the plan number on the
                    // "as per plan No." line. A plain Direct / Conversion record has no old
                    // file number, so it keeps the layout plan number.
                    $oldFileNumber = trim((string) ($recommendation->old_file_number ?? ''));
                    $layoutPlanNo  = trim((string) ($recommendation->layout_plan_no ?? ''));
                    $planNoRef     = $oldFileNumber !== '' ? $oldFileNumber : $layoutPlanNo;

                    // PLOT/PLAN No. always prints whatever is on the record: both parts when
                    // present, otherwise whichever one exists (blank when neither does).
                    $plotPlanNo = implode(' / ', array_filter([$plotNo, $layoutPlanNo], fn ($v) => $v !== ''));
                @endphp
                {{-- Label / rule pairs in one grid — all four rules on the same x. The
                     per-row min-widths that used to size them made the rules ragged and
                     set this box's floor width; both go with them. --}}
                <div>
                    <div class="bordered-section ref-details">
                        <span class="ref-label">R of O No:</span>
                        <span class="inline-data">{{ $recommendation->file_number }}</span>

                        <span class="ref-label">PLOT/PLAN No:</span>
                        <span class="inline-data">{{ $plotPlanNo }}</span>

                        <span class="ref-label">LOCATION:</span>
                        <span class="inline-data">{{ $printLocation }}</span>

                        <span class="ref-label">DATE OF ISSUE:</span>
                        <span class="inline-data">{{ optional($recommendation->date_issued)->format('Y-m-d') }}</span>
                    </div>
                </div>
            </div>
            <div class="title-center">TERMS OF OFFER OF GRANT/CONVEYANCE OF APPROVAL</div>
            <div class="conditions-list-fixed">
                <p class="condition-item">
                    {{-- The applicant's own application date, and nothing else —
                         it used to print created_at, the day the record was captured.
                         Blank when there is none. The year is 'Y' to match the single
                         print, which this letter has to read identically to; it was
                         'y' here and came out as "26". --}}
                    With reference to your application dated
                    <span class="inline-data" style="min-width:80px">{{ optional($recommendation->application_date)->format('jS F') }}</span>
                    <span class="inline-data" style="min-width:30px">{{ optional($recommendation->application_date)->format('Y') }}</span>,
                    I am directed to inform you that the Governor of Kano State has approved the grant of a Right of Occupancy to you over piece of land/plot No
                    <span class="inline-data" style="min-width:40px">{{ $recommendation->plot_number }}</span>
                    situated at <span class="inline-data" style="min-width:150px">{{ $printLocation }}</span>
                    as per plan No. <span class="inline-data" style="min-width:50px">{{ $planNoRef }}</span> on following the conditions:
                </p>
                <div class="condition-item">
                    <strong>1. Payment of:</strong>
                    <div class="sub-item">
                        <div class="sub-item-line"><span class="sub-item-label">(a)</span> Ground Rent N<span class="inline-data" style="min-width:80px">{{ $recommendation->ground_rent_label }}</span> P.H.P.A. (Revisable after every 5 years)</div>
                        @php $devIsText = filled($recommendation->development_charge) && !is_numeric($recommendation->development_charge); @endphp
                        <div class="sub-item-line"><span class="sub-item-label">(b)</span> Development Charges @unless($devIsText) N @endunless<span class="inline-data" style="min-width:80px">{{ is_numeric($recommendation->development_charge) ? number_format($recommendation->development_charge, 2) : ($recommendation->development_charge ?: '0.00') }}</span> (Payable once)</div>
                        <div class="sub-item-line"><span class="sub-item-label">(c)</span> Survey/Processing fees N<span class="inline-data" style="min-width:80px">{{ number_format($recommendation->survey_fees, 2) }}</span></div>
                    </div>
                </div>
                <div class="condition-item">
                    <div class="sub-item" style="margin-left:0;">
                        <div class="sub-item-line"><strong style="margin-right:8px;">2.</strong><span class="sub-item-label">(a)</span> Term: <span class="inline-data" style="min-width:40px">{{ $recommendation->term }}</span> years.</div>
                        <div class="sub-item-line" style="padding-left:18px;"><span class="sub-item-label">(b)</span> Purpose: <span class="inline-data" style="min-width:120px">{{ $recommendation->purpose_of_clause }}</span></div>
                        <div class="sub-item-line" style="padding-left:18px;"><span class="sub-item-label">(c)</span> Improvement Value: N<span class="inline-data" style="min-width:80px">{{ number_format($recommendation->development_value, 2) }}</span> within&nbsp;<span class="inline-data" style="min-width:8px">{{ $recommendation->development_period }}</span>&nbsp;years</div>
                    </div>
                </div>
                <p class="condition-item"><strong>3.</strong> Not to alienate the Right of Occupation in part or whole without written consent of the Governor.</p>
                <p class="condition-item"><strong>4.</strong> To be responsible for development/maintenance of drainage, landscaping and frontage beautification.</p>
                <p class="condition-item"><strong>5.</strong> Not to erect or permit to be erected on the subject land any building or development except in accordance with plans and specifications approved by the State Planning Authority in the case of urban areas or this ministry in the case of rural areas.</p>
                <p class="condition-item"><strong>6.</strong> To complete development of the land within <span class="inline-data" style="min-width:30px">{{ $recommendation->development_period }}</span> years.</p>
                <p class="condition-item"><strong>7.</strong> For Petrol Stations, 33 1/2 percent of annual rental is payable to the Government.</p>
                <p class="condition-item"><strong>8.</strong> The duplicate &amp; triplicate copies of the letter of Grant must be returned duly accepted with the required fees to enable production of C OF O, otherwise the offer lapses.</p>
            </div>
            <br>
            <div class="signature-block">
                <div><div class="security-line-container"></div><div>HONOURABLE COMMISSIONER</div></div>
                <div><div class="security-line-container"></div><div style="margin-top:5px;">DATE</div></div>
            </div>
        </div>
    </div>
    <div class="footer-barcode-area">
        <div style="visibility:visible;opacity:0.0001;display:flex;align-items:flex-end;gap:10px;">
            <div style="font-size:7px;transform:rotate(-90deg)">©NSPM</div>
        </div>
    </div>
</div>

{{-- ═══════════════  PAGE 2 ═══════════════ --}}
<div class="page-container{{ $isBwCopy ? ' copy-bw' : '' }}" style="page-break-before: always;">
    <div class="security-bg"></div>
    <div class="content-wrapper simple-margin">
        <div class="inner-content">
            <div class="applicant-address-block">
                <div class="left-commissioner">
                    <div style="text-align:center;">The Honourable Commissioner<br/>Ministry of Land and Physical Planning</div>
                </div>
                <div class="right-address">
                    <div class="address-line-box" style="margin-top:0;"><span class="inline-data" style="width:100%;border:none;min-width:auto;font-weight:bold;">{{ $recommendation->applicant_address }}</span></div>
                    <div class="address-line-box"><span class="inline-data" style="width:100%;border:none;min-width:auto"></span></div>
                    <div class="address-line-box"><span class="inline-data" style="width:100%;border:none;min-width:auto"></span></div>
                    <br/>
                    Date: <span class="inline-data" style="min-width:200px">{{ $recommendation->application_date ? $recommendation->application_date->format('Y-m-d') : '' }}</span><br/>
                    <div style="height:10px;"></div>
                    <center>Applicant's Address</center>
                </div>
            </div>
            <h3 style="text-align:center;text-decoration:underline;margin:10px 0;">ACCEPTANCE LETTER</h3>
            <p>With reference to the Offer of Grant, I hereby accept the terms and conditions of the grant of the Right of Occupancy as conveyed to me by your overleaf quoted letter.</p>
            <p>I will submit my building plan to you for approval before I commence any improvement on the Site, and on completion of the improvement, I will get your completion Certificate before occupation of the building. I forwarded herewith:</p>
            @php
                $selectedCategory = $recommendation->rofo_land_use_category ?? $recommendation->land_use;
                $totalSurvey = 0; $totalDev = 0;
            @endphp
            <table class="fee-table">
                <tr><th>Land Use</th><th>Survey Fees (N)</th><th>Dev. Charge (N)</th></tr>
                @foreach($categories as $cat)
                    @php
                        $normCat = $cat['label'];
                        if (!empty($cat['is_sub'])) { $normCat = str_replace(['i. ','ii. ','iii. ','iv. '],'Residential - ',$cat['label']); }
                        $isMatch = $selectedCategory && (strcasecmp($selectedCategory,$cat['label'])===0 || strcasecmp($selectedCategory,$normCat)===0 || stripos($selectedCategory,$cat['label'])!==false || stripos($normCat,$selectedCategory)!==false);
                        $surveyVal = $isMatch ? ($recommendation->rofo_survey_fees ?? 0) : null;
                        $devVal    = $isMatch ? ($recommendation->rofo_dev_charge   ?? 0) : null;
                        if ($isMatch) { $totalSurvey += (float)$surveyVal; $totalDev += (float)$devVal; }
                    @endphp
                    <tr>
                        <td style="{{ !empty($cat['is_sub']) ? 'padding-left:20px;' : 'font-weight:bold;' }}">{{ $cat['label'] }}</td>
                        <td style="text-align:right;">@if($isMatch)<strong>{{ number_format($surveyVal,2) }}</strong>@else{{ number_format($cat['fee'],2) }}{{ $cat['extra'] ?? '' }}@endif</td>
                        <td style="text-align:right;">{{ $isMatch ? number_format($devVal,2) : '' }}</td>
                    </tr>
                @endforeach
                <tr><td><strong>TOTAL</strong></td><td>N <span>{{ number_format($totalSurvey+$totalDev,2) }}</span></td><td></td></tr>
            </table>
            <p style="margin-top:10px;"><span style="display:inline-block;width:14px;height:14px;border:1.5px solid #000;vertical-align:middle;margin-right:6px;"></span> I require the Director Survey to carry out the land survey for me</p>
            <p><span style="display:inline-block;width:14px;height:14px;border:1.5px solid #000;vertical-align:middle;margin-right:6px;"></span> I require a licensed Surveyor to carry out the land survey for me</p>
            <div class="note-box">
                NOTE: APPLICANT TO RETAIN ORIGINAL AND RETURN 2 COPIES AFTER SIGNING.<br/><br/>
                THIS R OF O IS SUBJECT TO VERIFICATION BEFORE ANY STATUTORY PAYMENTS TO REVENUE DEPARTMENT.
            </div>
            <div class="signature-row">
                <div class="signature-item">APPLICANT'S SIGNATURE</div>
                <div class="signature-item-date">DATE</div>
            </div>
            <br>
            <div style="display:flex;align-items:center;justify-content:flex-end;margin-top:16px;padding-top:6px;border-top:1px solid #ccc;">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" style="height:35px;width:auto;object-fit:contain;">
            </div>
        </div>
    </div>
    <div class="footer-barcode-area"></div>
</div>
@endforeach {{-- end versions --}}
@endforeach {{-- end records --}}

<script>
    // Auto-print when window opens; parent window logs after user confirms
    window.addEventListener('load', function () {
        window.print();
    });
    window.addEventListener('afterprint', function () {
        // Notify opener to show the "confirm & log" step
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({ type: 'rofo_batch_printed' }, '*');
        }
    });
</script>
</body>
</html>

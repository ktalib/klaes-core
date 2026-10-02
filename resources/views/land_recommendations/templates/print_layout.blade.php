<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Recommendation for Grant of Statutory Right of Occupancy</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 1.4cm 1.6cm 1.5cm;
    }

    body {
      font-family: "Times New Roman", Times, serif;
      font-size: 9.8pt;
      line-height: 1.28;
      color: black;
      margin: 0;
      padding: 0;
    }

    .ctc-watermark-bg {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-45deg);
      font-size: 50pt;
      color: rgba(255, 0, 0, 0.05);
      font-weight: bold;
      z-index: 0;
      white-space: nowrap;
      pointer-events: none;
      text-transform: uppercase;
      letter-spacing: 2px;
    }

    .container {
      max-width: 18.8cm;
      margin: 0 auto;
      position: relative;
    }

    .top-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 0.3cm;
    }

    .qr {
      width: 65px;
      height: 65px;
    }

    .coat {
      text-align: center;
      flex: 1;
    }

    .coat img {
      height: 80px;
    }

    .serial {
      border: 1px solid black;
      padding: 4px 8px;
      min-width: 110px;
      margin-top: 4px;
      text-align: center;
    }

    .serial-code-wrap {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      justify-content: center;
    }

    .serial-fraction {
      line-height: 1;
      color: #1e293b;
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      font-weight: 900;
    }

    .serial-fraction .frac-top {
      border-bottom: 1.5px solid #1e293b;
      padding-bottom: 1px;
      font-size: 8px;
    }

    .serial-fraction .frac-bot {
      padding-top: 1px;
      font-size: 8px;
    }

    .serial-digits {
      font-size: 13px;
      font-weight: 900;
      letter-spacing: 0.1em;
      color: #1e293b;
      font-family: 'Courier New', monospace;
    }

    .ministry {
      font-size: 15pt;
      font-weight: bold;
      text-transform: uppercase;
      text-align: center;
      margin: 0.1cm 0 0.05cm;
      letter-spacing: 0.4px;
    }

    .address {
      font-size: 9pt;
      text-align: center;
      margin-bottom: 0.45cm;
    }

    .title-bar {
      display: flex;
      align-items: center;
      margin: 0.6cm 0 0.3cm;
    }

    .title-bar hr {
      flex: 1;
      border: none;
      border-top: 1px solid black;
      margin: 0;
    }

    .title-green {
      background: #006400;
      color: white;
      padding: 0.1cm 0.5cm;
      font-size: 9.5pt;
      font-weight: bold;
      text-transform: uppercase;
      line-height: 1.1;
      white-space: nowrap;
      text-align: center;
    }

    .subtitle {
      font-weight: bold;
      text-transform: uppercase;
      font-size: 9.5pt;
      margin: 0.3cm 0 0.15cm;
      text-align: center;
    }

    .field {
      border-bottom: 1px solid black;
      display: inline-block;
      min-width: 160px;
      min-height: 1.3em;
      margin: 0 3px;
      font-weight: bold;
      vertical-align: bottom;
      padding-bottom: 1px;
    }

    .field-row {
      display: flex;
      align-items: flex-start;
      margin-bottom: 0.12cm;
    }

    .field-label {
        white-space: nowrap;
        margin-right: 5px;
    }

    .paired {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 0.12cm;
    }

    .paired > div {
      display: flex;
      align-items: flex-end;
    }

    .paired > div.paired-narrow {
      width: 38%;
    }

    .paired > div.paired-wide {
      width: 60%;
    }

    .full-line {
      border-bottom: 1px solid black;
      margin: 0.15cm 0;
    }

    .reasons {
      min-height: 2.2cm;
      border-bottom: 1px solid black;
      margin: 0.4cm 0 0.7cm;
      font-weight: bold;
    }

    .sig-row {
      display: flex;
      justify-content: space-between;
      margin: 1.1cm 0 0.35cm;
      font-size: 9.5pt;
    }

    .sig-label {
      min-width: 110px;
      display: inline-block;
      text-align: center;
    }

    .sig-line {
      border-bottom: 1px solid black;
      width: 180px;
      display: inline-block;
    }

    .approval-bar {
      display: flex;
      align-items: center;
      margin: 0.9cm 0 0.35cm;
    }

    .approval-bar hr {
      flex: 1;
      border: none;
      border-top: 1px solid black;
      margin: 0;
    }

    .approval-green {
      background: #006400;
      color: white;
      padding: 0.1cm 0.5cm;
      font-size: 9.5pt;
      font-weight: bold;
      text-transform: uppercase;
      line-height: 1.1;
      white-space: nowrap;
      text-align: center;
    }

    .red-text {
      color: #b00000;
      font-weight: bold;
      margin: 0.8cm 0 0.5cm;
      font-size: 10pt;
    }

    .camscanner {
      font-size: 8.5pt;
      color: #666;
      text-align: right;
      margin-top: 0.8cm;
    }

    @media print {
      .no-print { 
        display: none !important; 
      }
      body {
        font-size: 9.8pt !important;
      }
      .title-green, .approval-green {
        background: #006400 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      /* ...but never on a proof, which prints black on white. Declared after the
         rule above so it wins on equal specificity. */
      .white-copy .title-green, .white-copy .approval-green {
        background: #fff !important;
        color: #000 !important;
      }
    }

    /* ── White Copy: the proof sheet ────────────────────────────────────────
       A draft on ordinary white paper, in black and white, for an officer to read
       against the record before an official copy is run off. The rules here exist
       to keep it from being mistaken for one: the arms, the QR, the serial and the
       signature lines are removed in the markup, and the mark below says what the
       sheet is — centred across the head of the page, large enough to be the first
       thing read off it, plain black so it survives a monochrome printer.

       The head keeps the height it has on the official document so the text below
       begins in the same place. A proof that reflows is a proof of a different
       document. */
    .white-copy-head {
      display: block;
      text-align: center;
      min-height: 65px;
    }

    .white-copy-mark {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 24pt;
      font-weight: 900;
      line-height: 1.1;
      letter-spacing: 0.24em;
      text-transform: uppercase;
      color: #000;
    }

    .white-copy-note {
      margin-top: 2px;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 6.5pt;
      font-weight: bold;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: #333;
    }

    /* Black and white throughout: the proof goes through whatever printer is
       nearest, on ordinary paper, and a green banner reproduced in grey reads as a
       smudge where the official document reads as a heading. The banners keep their
       shape — same padding, same block — so the page below them does not move; only
       the ink changes, to black on white with a rule around it.

       Set element by element rather than with one grayscale filter on the page: a
       filter applies to the whole subtree with no way for a descendant to opt out,
       and it would leave the greens and reds as mid-greys rather than as black. */
    .white-copy .title-green,
    .white-copy .approval-green {
      background: #fff !important;
      color: #000 !important;
      border: 1px solid #000;
    }

    .white-copy .red-text {
      color: #000 !important;
    }

    /* The room the signature rows occupied, so the page below is unchanged. */
    .white-copy-sig-gap {
      display: block;
      text-align: center;
      font-size: 6.5pt;
      font-style: italic;
      color: #6b7280;
      padding-top: 0.55cm;
    }

    .print-button {
      display: block;
      margin: 2rem auto;
      padding: 0.8rem 1.5rem;
      font-size: 1rem;
      cursor: pointer;
      background-color: #006400;
      color: white;
      border: none;
      border-radius: 4px;
      font-family: Arial, sans-serif;
    }

    .print-button:hover {
      background-color: #004d00;
    }
  </style>
</head>
@php
    // The proof sheet. Shared by LandRecommendationController::printWhiteCopy();
    // no query string switches it on, so an official print URL cannot become a
    // proof and a proof URL cannot become an official print.
    $isWhiteCopy = !empty($isWhiteCopy);

    $requestedStatus = request('status', 'Original');
    $isCTCBatch = request('isCTC') == 1;
    // 'Office' is the Duplicate and Triplicate alone — run 2 of a split print,
    // once the plain paper has replaced the security stock in the tray.
    $printVersions = ($requestedStatus === 'Batch')
        ? ['Original', 'Duplicate', 'Triplicate']
        : (($requestedStatus === 'Office') ? ['Duplicate', 'Triplicate'] : [$requestedStatus]);

    // A White Copy is one copy, whatever the URL asks for. It is not an Original, a
    // Duplicate or a Triplicate, and three proofs of one document are three copies
    // of the same reading.
    if ($isWhiteCopy) {
        $printVersions = ['White Copy'];
    }

    $versionColors = [
        'Original' => '#ff0000',
        'Duplicate' => '#0000ff',
        'Triplicate' => '#008000',
        'CTC' => '#ff0000'
    ];

    // Never for a proof. The serial is the document's official number: minting one
    // here would put a real serial on a sheet that is not an issued copy — and
    // because this template mints it as it renders, merely opening a proof would
    // have spent one.
    $securityCode = null;
    $sc = null;
    if (!$isWhiteCopy) {
        $securityCode = app(\App\Services\SecurityCodeService::class)->getOrGenerateForDocument(
            (string) ($recommendation->file_number ?? ($recommendation->id ?? '')),
            (int) $recommendation->id,
            'Lands ROFO'
        );
        $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code);
    }
@endphp

@foreach($printVersions as $index => $version)
<div class="container{{ $isWhiteCopy ? ' white-copy' : '' }}" style="{{ $index > 0 ? 'page-break-before: always;' : '' }}">
    @if($isCTCBatch)
        <div class="ctc-watermark-bg">CERTIFIED TRUE COPY</div>
    @endif


  {{-- The head of the sheet. On a proof the arms, the QR and the serial box all
       come off — the arms are the State's, the QR resolves to a verifiable record
       and the serial is the document's official number — and the block says what
       the sheet is instead. It keeps the same height either way, so the document
       below starts on the same line of the page as it will on the official print. --}}
  @if($isWhiteCopy)
    <div class="top-header white-copy-head">
      <div class="white-copy-mark">White Copy</div>
      <div class="white-copy-note">Proof for vetting — not an official document</div>
    </div>
  @else
  <div class="top-header">
    @php
        $qrData = json_encode([ 'tracking_id' => $recommendation->tracking_id]);
        $qrUrl = qr_data_uri($qrData, 65);
    @endphp
    <img src="{{ $qrUrl }}" alt="QR Code" class="qr">

    <div class="coat">
      <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" alt="Coat of Arms of Nigeria">
    </div>

    <div class="serial">
        @if($version !== 'Original')
            <span style="color: {{ $versionColors[$version] ?? '#ff0000' }}; font-weight: bold; font-size: 9pt;">
                {{ strtoupper($version) }}
            </span><br>
        @endif
        <div style="font-weight: bold; text-transform: uppercase; border-bottom: 1px solid black; padding-bottom: 3px; margin-bottom: 4px; font-size: 9px;">Serial No:</div>
        <div class="serial-code-wrap">
            <span class="serial-fraction">
              <span class="frac-top">{{ $sc['alphabet'] }}</span>
              <span class="frac-bot">{{ $sc['digits_start'] }}</span>
            </span>
            <span class="serial-digits">{{ $sc['digits_end'] }}</span>
        </div>
    </div>
  </div>
  @endif

  <div class="ministry">MINISTRY OF LAND AND PHYSICAL PLANNING</div>
  <div class="address">No. 2 Dr. Bala Moh'd Road, Nassarawa GRA, Kano PMB 3038 Kano-Nigeria.</div>

  <div class="title-bar">
    <hr />
    <div class="title-green">
      RECOMMENDATION FOR GRANT OF<br>STATUTORY RIGHT OF OCCUPANCY
    </div>
    <hr />
  </div>

  <div class="subtitle">CONDITIONS FOR APPLICATION</div>

  @php
      // Plot No. is already shown in its own field on this template ($recommendation->display_location
      // strips it from the front of location). Legacy district/street text is also inconsistently
      // cased (e.g. "HOTORO Nasarawa Kano State" mixes full caps with title case), so normalize to
      // a consistent Title Case for display here too.
      $normalizedLocation = $recommendation->display_location;
      if ($normalizedLocation !== '') {
          $normalizedLocation = mb_convert_case(mb_strtolower($normalizedLocation, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
      }
  @endphp

  <div class="field-row">Name of Applicant: <span class="field" style="width:450px;">{{ $recommendation->applicant_name }}</span></div>

  <div class="paired">
    <div class="field-row paired-narrow"><span class="field-label">(a) File Ref. No.:</span> <span class="field" style="flex:1;">{{ $recommendation->file_number }}</span></div>
    <div class="field-row paired-wide"><span class="field-label">(b) Landuse/Purpose Clause:</span> <span class="field" style="flex:1;">{{ $recommendation->landuse_purpose }}</span></div>
  </div>

  <div class="paired">
    <div class="field-row paired-wide"><span class="field-label">(c) Location:</span> <span class="field" style="flex:1;">{{ $normalizedLocation }}</span></div>
    <div class="field-row paired-narrow"><span class="field-label">(d) Plot No.:</span> <span class="field" style="flex:1;">{{ $recommendation->plot_number }}</span></div>
  </div>

  @php
      // Applications derived from an existing file (Plot Subdivision / Plot Merger /
      // Change of Purpose) cite the parent file number as the Plan No. in the approval
      // block below; the Layout Plan No. above always shows the layout plan itself.
      $oldFileNumber = trim((string) ($recommendation->old_file_number ?? ''));
      $planNoRef     = $oldFileNumber !== '' ? $oldFileNumber : $recommendation->layout_plan_no;
  @endphp
  <div class="field-row">(e) Layout Plan No.: <span class="field" style="width:450px;">{{ $recommendation->layout_plan_no }}</span></div>

  <div class="field-row">Term: <span class="field" style="width:530px;">{{ $recommendation->term }}</span></div>

  <div class="field-row">value for proposed development N: <span class="field" style="width:430px;">{{ number_format($recommendation->development_value, 2) }}</span></div>

  <div class="field-row">Time for completion of proposed development: <span class="field" style="width:360px;">{{ $recommendation->development_period_label }}</span></div>

  <div class="field-row">Annual Ground Rent: <span class="field" style="width:450px;">{{ $recommendation->ground_rent_label }}</span></div>

  <div class="field-row">Development Charge (if any): <span class="field" style="width:400px;">{{ $recommendation->development_charge ? (is_numeric($recommendation->development_charge) ? number_format($recommendation->development_charge, 2) : $recommendation->development_charge) : 'NIL' }}</span></div>

  <div class="field-row">Survey and processing charges: <span class="field" style="width:390px;">{{ number_format($recommendation->survey_fees, 2) }}</span></div>

 
  <div style="margin:0.6cm 0 0.2cm;">
    The Director of Land recommends/does not recommend the application for the following reasons:
  </div>

  <div class="reasons">{{ $recommendation->recommendation }}</div>

  {{-- Signature lines come off the proof. A blank rule over a name and a date is
       exactly what can be signed and passed off as the document itself, which is
       the one misuse this stage exists to prevent. The space is left behind so the
       page still breaks where the official document breaks. --}}
  @if($isWhiteCopy)
  <div class="sig-row white-copy-sig-gap">Signature block omitted — white copy for proofreading only</div>
  @else
  <div class="sig-row">
    <div><span class="sig-label">Director Land</span> <span class="sig-line"></span></div>
    <div><span class="sig-label">Date</span> <span class="sig-line"></span></div>
  </div>
  @endif

  <div class="approval-bar">
    <hr />
    <div class="approval-green">
      APPROVAL FOR GRANT OF<br>STATUTORY RIGHT OF OCCUPANCY
    </div>
    <hr />
  </div>

  <div class="field-row" style="margin-bottom:0.4cm;">
    I recommend/do not recommend the application for a Grant over Plot No.: <span class="field" style="flex:1;">{{ $recommendation->plot_number }}</span>
  </div>

  <div class="paired" style="margin-bottom:0.6cm;">
    <div class="field-row"><span class="field-label">Plan No.:</span> <span class="field" style="flex:1;">{{ $planNoRef }}</span></div>
    <div class="field-row" style="justify-content: flex-end;">
      <span class="field-label">Location:</span> <span class="field" style="flex:1;">{{ $normalizedLocation }}</span>
    </div>
  </div>

  @if($isWhiteCopy)
  <div class="sig-row white-copy-sig-gap">Signature block omitted — white copy for proofreading only</div>
  @else
  <div class="sig-row">
    <div>
      <span class="sig-line"></span>
      <br>
      <span class="sig-label">Permanent Secretary</span> 
    </div>
    <div>
      <span class="sig-line"></span>
      <br>
      <span class="sig-label">Date</span>
    </div>
  </div>
  @endif

  {{-- Off the proof, with the signature blocks it belongs to. It is the line the
       Commissioner's signature acts on — a sheet carrying the declaration and a
       blank rule beneath it is a document waiting to be executed, which is exactly
       what a proof must not look like. There is nothing to proofread in it either:
       it is fixed text, identical on every letter. --}}
  @unless($isWhiteCopy)
  <div class="red-text">
    The Grant of Occupancy is hereby APPROVED/NOT APPROVED
  </div>
  @endunless

  @if($isWhiteCopy)
  <div class="sig-row white-copy-sig-gap">Signature block omitted — white copy for proofreading only</div>
  @else
  <div class="sig-row">
    <div>
      <span class="sig-line"></span>
      <br>
      <span class="sig-label">Honourable Commissioner</span>
    </div>
    <div>
      <span class="sig-line"></span>
      <br>
      <span class="sig-label">Date</span>
    </div>
  </div>
  @endif

</div>

{{-- Page 2: acknowledgement / collection sheet. Not on a proof: the sheet is the
     half the applicant signs and returns on collection, and there is nothing on it
     to proofread that is not already on page 1. A white copy is one sheet. --}}
@unless($isWhiteCopy)
@include('land_recommendations.templates._ack_sheet')
@endunless
@endforeach

<button class="no-print print-button" onclick="window.print()">
  Print (Total Pages: {{ count($printVersions) }})
</button>

</body>
</html>

</body>
</html>
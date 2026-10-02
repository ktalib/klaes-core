{{-- Acknowledgement sheet — rendered twice after the Land Recommendation print: a
     FILE COPY for the registry, then the ORIGINAL for the applicant, distinguished by watermark.
     All selectors are scoped under .ack-page so they do not collide with print_layout's styles. --}}
<style>
  .ack-page {
    page-break-before: always;
    /* each copy must stay on one sheet — a split leaves the signatures and the
       footer logo stranded on a spill page */
    page-break-inside: avoid;
    break-inside: avoid;
    position: relative;
    /* Kept just inside the A4 printable height (29.7cm less print_layout's
       1.4/1.5cm @page margins) so the bottom-right footer logo is never sliced
       across the page break. */
    min-height: 24.8cm;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
  }
  
  /* Diagonal copy watermark. Sits behind the content and must survive the print
     dialog's "background graphics" default, hence print-color-adjust. */
  .ack-page .ack-watermark {
    position: absolute; 
    /* Anchored to a fixed offset from the top of the sheet and stretched full
       width, rather than centred on the box with top/left:50%. The box is taller
       than the printable page on the OSS layout, so a 50% anchor pushed the text
       past the page edge and it printed clipped. */
    top: 105mm;
    left: 0;
    right: 0;
    text-align: center;
    transform: rotate(-45deg);
    font-size: 52pt;
    font-weight: bold;
    letter-spacing: 3px;
    text-transform: uppercase;
    white-space: nowrap;
    color: rgba(200, 0, 0, 0.10);
    z-index: 0;
    pointer-events: none;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  /* Everything else stacks above the watermark. */
  .ack-page > *:not(.ack-watermark) {
    position: relative;
    z-index: 1;
  }

  /* The sheet travels on its own once it is detached from the letter, so it has
     to say which file it belongs to. Same treatment as the Consent
     acknowledgement: label in normal weight, the number itself bold and ruled.

     inline-flex, not flex: block-level it would run the full width of the sheet
     and leave a short file number under a rule stretching to the right margin.
     Shrink-wrapping keeps the underline to the text, with min-width so a very
     short number still gets a sensible rule. */
  .ack-page .ack-file-no {
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    min-width: 5.5cm;
    font-size: 13.5px;
    font-weight: bold;
    text-transform: uppercase;
    border-bottom: 1px solid #000;
    padding-bottom: 4px;
    margin-bottom: 12px;
  }

  .ack-page .ack-file-no .ack-file-no-label {
    font-weight: normal;
    text-transform: none;
  }

  .ack-page .ack-copy-tag {
    text-align: right;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-top: -10px;
    margin-bottom: 10px;
  }

  .ack-page .header {
    position: relative;
    text-align: center;
    min-height: 92px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    margin-bottom: 16px;
    /* leave room for the logos and the QR block on either side of the titles */
    padding: 0 160px;
  }

  .ack-page .header-logo {
    position: absolute;
    top: 0;
    right: 0;
    width: 70px;
    height: 70px;
    object-fit: contain;
  }

  .ack-page .header-logo-left {
    position: absolute;
    top: 0;
    left: 0;
    width: 70px;
    height: 70px;
    object-fit: contain;
  }

  .ack-page .header-title {
    font-size: 17px;
    font-weight: bold;
    text-decoration: underline;
    text-transform: uppercase;
    margin-bottom: 6px;
  }

  .ack-page .header-subtitle {
    font-size: 15px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .ack-page .content-body p {
    font-size: 14px;
    line-height: 1.35;
    margin-bottom: 10px;
    text-align: justify;
  }

  .ack-page .content-body a {
    color: #0066cc;
    text-decoration: underline;
  }

  /* QR sits in the header, immediately left of the KANGIS logo. */
  .ack-page .ack-qr {
    position: absolute;
    top: 0;
    right: 78px;
    width: 72px;
    text-align: center;
  }

  .ack-page .ack-qr img {
    width: 70px;
    height: 70px;
    /* keep the code crisp on paper — printers must not down-sample it */
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .ack-page .contact-section {
    margin-top: 14px;
    margin-bottom: 16px;
    font-size: 14px;
    line-height: 1.3;
  }

  .ack-page .contact-section h3 {
    font-size: 15px;
    font-weight: bold;
    margin-bottom: 8px;
  }

  .ack-page .contact-address {
    margin-bottom: 4px;
  }

  .ack-page .acknowledgment-title {
    font-size: 15px;
    font-weight: 500;
    margin-top: 16px;
    margin-bottom: 20px;
  }

  .ack-page .form-group {
    margin-bottom: 34px;
    display: flex;
    align-items: flex-end;
    font-size: 14.5px;
  }

  .ack-page .form-label {
    font-weight: 500;
    white-space: nowrap;
    margin-right: 2px;
  }

  .ack-page .dotted-line {
    flex-grow: 1;
    border-bottom: 1px dotted #000;
    margin-left: 2px;
    height: 1.2em;
  }

  .ack-page .dotted-line.short {
    max-width: 320px;
  }

  .ack-page .dotted-line.medium {
    max-width: 420px;
  }

  .ack-page .multiline-spacer {
    margin-top: 26px;
  }

  .ack-page .form-row {
    display: flex;
    gap: 40px;
    margin-bottom: 34px;
  }

  .ack-page .form-row .form-group {
    flex: 1;
    margin-bottom: 0;
  }

  .ack-page .signature-block {
    margin-top: 40px;
  }

  .ack-page .signature-block-title {
    font-weight: bold;
    font-size: 14.5px;
    margin-bottom: 28px;
    padding-bottom: 6px;
    border-bottom: 1px solid #000;
  }

  .ack-page .footer {
    position: absolute;
    bottom: 4px;
    right: 0;
    line-height: 0;   /* no descender gap under the logo, which clipped it */
  }

  .ack-page .footer img {
    height: 40px;
    width: auto;
    max-width: none;
    object-fit: contain;
    display: block;
  }
</style>

{{-- Same payload/service as the QR on page 1 of print_layout, so a scan of any
     page resolves to the same application. Computed once, shared by both copies. --}}
@php
  // The Land prints pass $recommendation; the OSS print (lands_one_stop_shop) passes
  // $record and names the file column file_ref. Without this fallback the OSS copy
  // encodes an empty payload.
  $ackRecord     = $recommendation ?? $record ?? null;
  $ackTrackingId = $ackRecord->tracking_id ?? null;
  // sltr_number covers the SLTR recommendation, which has neither of the other two.
  $ackFileNumber = $ackRecord->file_number ?? $ackRecord->file_ref ?? $ackRecord->sltr_number ?? null;
  $ackQrPayload  = json_encode(array_filter([
      'tracking_id' => $ackTrackingId,
      'file_number' => $ackFileNumber,
  ]));
  $ackQrUrl = qr_data_uri($ackQrPayload, 200);

  // Defaults to the registry copy + the applicant's copy. Callers that only need
  // one sheet pass e.g. ['ackCopies' => ['Original' => '']].
  $ackCopies = $ackCopies ?? ['File Copy' => 'file-copy', 'Original' => ''];
@endphp

@foreach ($ackCopies as $ackCopyLabel => $ackCopyClass)
<div class="ack-page">

  <div class="ack-watermark {{ $ackCopyClass }}">{{ $ackCopyLabel }}</div>

  <!-- Top Header -->
  <header class="header">
    <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Kano State Ministry Logo" class="header-logo-left">
    <img src="http://app.klaes.ng/assets/logo/ministry2.png" alt="Kano State Ministry Logo" class="header-logo">
    <div class="ack-qr">
      <img src="{{ $ackQrUrl }}" alt="Application tracking QR code">
    </div>
    <h1 class="header-title">Right of Occupancy</h1>
    <h2 class="header-subtitle">Collection of Letter of Grant</h2>
  </header>

  <div class="ack-copy-tag">{{ $ackCopyLabel }}</div>

  {{-- $ackFileNumber is resolved above and already falls back to sltr_number;
       it was only ever fed to the QR. Hidden when blank rather than printing a
       bare rule with nothing on it. --}}
  @if (trim((string) $ackFileNumber) !== '')
    <div class="ack-file-no">
      <span class="ack-file-no-label">File No.:</span>
      <span>{{ $ackFileNumber }}</span>
    </div>
  @endif

  <!-- Paragraphs -->
  <section class="content-body">
    <p>
      Please note that you may be invited later for an Interview via Phone, SMS, WhatsApp or Email to provide additional information and documentation where necessary. You can always check the status of your application via our website [<a href="https://land.gov.ng" target="_blank">https://land.gov.ng</a>] or Contact the Ministry of Land Customer Service Desk via Phone: +234 (0)700 000 0000, SMS, WhatsApp, or you can visit the Land Customer Service Desk.
    </p>

    <p>
      You can track the progress of your application using the QR code on this page
    </p>

    <p>
      Please keep the original acknowledgement letter in a safe place for future reference. It is one of the requirements for the collection of your new digital Right of Occupancy.
    </p>
  </section>

  <!-- Contact Details -->
  <section class="contact-section">
    <h3>Contact Information:</h3>
    <div class="contact-address">
      Land Customer Service<br>
      2 Dr. Bala Muhammad Way,<br>
      Nassarawa G.R.A., Kano, Nigeria<br>
      Tel: +234 (0)900 000 0000 | Email: support@kangis.gov.ng<br>
      Website: <a href="https://Land.gov.ng" target="_blank">https://Land.gov.ng</a>
    </div>
  </section>

  <!-- Acknowledgment Title -->
  <div class="acknowledgment-title">
    Original copy of Right of Occupancy was collected by me
  </div>

  <!-- Fillable Form Fields -->
  {{-- class is ack-form-fields, NOT form-container: the OSS print template
       (lands_one_stop_shop/partials/print_recommendation) styles .form-container as
       its 210x296mm page card — shadow, padding, overflow:hidden, position:relative.
       Reusing that name turned this section into a second page-sized card and made
       the footer logo anchor to it, so the logo printed clipped. --}}
  <section class="ack-form-fields">

    <div class="form-group">
      <span class="form-label">Name</span>
      <div class="dotted-line short"></div>
    </div>

    <div class="form-group">
      <span class="form-label">Address:</span>
      <div class="dotted-line short"></div>
    </div>

    <!-- Second Line for Address -->
    <div class="form-group multiline-spacer">
      <div class="dotted-line medium"></div>
    </div>

    <div class="form-group">
      <span class="form-label">Phone No:</span>
      <div class="dotted-line medium"></div>
    </div>

    <!-- Recipient Signature -->
    <div class="form-row signature-block">
      <div class="form-group">
        <span class="form-label">Signature</span>
        <div class="dotted-line short"></div>
      </div>
      <div class="form-group">
        <span class="form-label">Date</span>
        <div class="dotted-line short"></div>
      </div>
    </div>

    <!-- Witness Signature -->
    <div class="signature-block">
      <div class="signature-block-title">Witness</div>

      <div class="form-group">
        <span class="form-label">Name</span>
        <div class="dotted-line short"></div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <span class="form-label">Signature</span>
          <div class="dotted-line short"></div>
        </div>
        <div class="form-group">
          <span class="form-label">Date</span>
          <div class="dotted-line short"></div>
        </div>
      </div>
    </div>

  </section>

</div>
@endforeach

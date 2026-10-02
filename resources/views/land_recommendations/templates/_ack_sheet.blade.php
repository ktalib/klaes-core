{{-- Acknowledgement sheet — rendered twice after the Land Recommendation print: a
     FILE COPY for the registry, then the ORIGINAL for the applicant, distinguished by watermark.
     All selectors are scoped under .ack-page so they do not collide with print_layout's styles. --}}
<style>
  .ack-page {
    /* Column flexbox purely so the footer logo can be pushed to the bottom with
       margin-top:auto instead of being absolutely positioned. Absolute placement
       took the logo out of flow, so on any copy whose content ran long it printed
       on top of the witness Date line. */
    display: flex;
    flex-direction: column;
    page-break-before: always;
    /* each copy must stay on one sheet — a split leaves the signatures and the
       footer logo stranded on a spill page */
    page-break-inside: avoid;
    break-inside: avoid;
    position: relative;
    /* No min-height. It used to be 24.8cm to bottom-anchor the absolutely
       positioned logo, but the sheet's own content already fills a page on the
       OSS layout (whose card adds padding of its own), so forcing the box taller
       than what remains pushed the footer onto a second, otherwise blank page.
       Content height plus margin-top:auto on the footer covers both cases. */
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

  .ack-page .ack-copy-tag {
    text-align: right;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-top: -10px;
    margin-bottom: 10px;
  }

  /* File No. on the acknowledgement sheets. Page 1 carries it as the File Ref.
     No.; these two pages travel separately once the sheet is signed and handed
     over, so each has to say which application it belongs to on its own. */
  /* inline-flex, not flex: as a block-level flexbox the rule ran the full width
     of the sheet, so a short file number sat under a line stretching to the
     right margin. Shrink-wrapping keeps the underline to the text. */
  .ack-page .ack-file-no {
    display: inline-flex;
    /* .ack-page is a flex column, which would blockify this back to full width */
    align-self: flex-start;
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

  /* The vertical rhythm below is deliberately tight. The sheet's content ran a
     few centimetres past the printable page, which pushed the footer logo onto a
     second, otherwise blank sheet; these margins were trimmed to pull the whole
     copy — logo included — back onto one page. Loosen them and it spills again. */
  .ack-page .contact-section {
    margin-top: 10px;
    margin-bottom: 12px;
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
    margin-top: 12px;
    margin-bottom: 14px;
  }

  .ack-page .form-group {
    margin-bottom: 24px;
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
    margin-top: 18px;
  }

  .ack-page .form-row {
    display: flex;
    gap: 40px;
    margin-bottom: 24px;
  }

  .ack-page .form-row .form-group {
    flex: 1;
    margin-bottom: 0;
  }

  .ack-page .signature-block {
    margin-top: 24px;
  }

  .ack-page .signature-block-title {
    font-weight: bold;
    font-size: 14.5px;
    margin-bottom: 20px;
    padding-bottom: 6px;
    border-bottom: 1px solid #000;
  }

  /* In flow at the end of the sheet: margin-top:auto drops it to the bottom when
     the content is short, and it is simply pushed down when the content is long,
     so it can never land on top of the witness signature block. */
  .ack-page .footer {
    margin-top: auto;
    align-self: flex-end;
    padding-top: 10px;
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
  $ackFileNumber = $ackRecord->file_number ?? $ackRecord->file_ref ?? null;
  $ackQrPayload  = json_encode(array_filter([
      'tracking_id' => $ackTrackingId,
      'file_number' => $ackFileNumber,
  ]));
  $ackQrUrl = qr_data_uri($ackQrPayload, 200);
@endphp

@foreach (['File Copy' => 'file-copy', 'Original' => ''] as $ackCopyLabel => $ackCopyClass)
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

  @if(trim((string) $ackFileNumber) !== '')
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

  <!-- Footer -->
  <footer class="footer">
    {{-- logo.png, not Klase.png — the latter 404s and rendered as a broken-image
         icon on every acknowledgement sheet. This is the same file page 1 uses. --}}
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo">
  </footer>

</div>
@endforeach

{{-- Acknowledgement sheet — page 2 of the Assignment consent letter, signed by
     the applicant when the original consent is handed over.

     Adapted from land_recommendations/templates/_ack_sheet.blade.php. The
     layout, the tight vertical rhythm and the footer-in-flow trick are carried
     over verbatim; only the document-specific wording differs, because this
     sheet acknowledges collection of a Consent letter, not of a Right of
     Occupancy. All selectors stay scoped under .ack-page so they cannot collide
     with the letter's own styles on page 1. --}}
<style>
  .ack-page {
    /* Column flexbox purely so the footer logo can be pushed to the bottom with
       margin-top:auto instead of being absolutely positioned. Absolute placement
       took the logo out of flow, so on any copy whose content ran long it printed
       on top of the witness Date line. */
    display: flex;
    flex-direction: column;
    page-break-before: always;
    /* the sheet must stay on one page — a split leaves the signatures and the
       footer logo stranded on a spill page */
    page-break-inside: avoid;
    break-inside: avoid;
    position: relative;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
    color: #000;
    box-sizing: border-box;
  }

  /* On screen the letter's page 1 is a white A4 card on grey; this sheet has to
     match it or page 2 renders as bare text on the grey backdrop. In print the
     card styling is dropped and the sheet is the page. */
  @media screen {
    .ack-page {
      width: 210mm;
      min-height: 297mm;
      margin: 20px auto 0;
      padding: 1.5cm 2.4cm;
      background: #fff;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }
  }

  @media print {
    .ack-page {
      width: auto;
      margin: 0;
      padding: 1.2cm 2.4cm 1.5cm 2.4cm;
      background: none;
      box-shadow: none;
    }
  }

  /* Diagonal copy watermark. Sits behind the content and must survive the print
     dialog's "background graphics" default, hence print-color-adjust. */
  .ack-page .ack-watermark {
    position: absolute;
    /* Anchored to a fixed offset from the top of the sheet and stretched full
       width, rather than centred on the box with top/left:50%. */
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

  /* File No. on the acknowledgement sheet. Page 1 carries it as the Our Ref.;
     this page travels separately once the sheet is signed and handed over, so
     it has to say which file it belongs to on its own. */
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
    margin: 0 0 6px;
  }

  .ack-page .header-subtitle {
    font-size: 15px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 0;
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

  /* QR sits in the header, immediately left of the ministry logo. */
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

@php
  /* Same payload as the QR on page 1, so a scan of either page of the letter
     resolves to the same file. $qrValue is built in the parent template, which
     has already applied verify_url and the tracking-id fallback.
     Drawn server-side as a data URI rather than by the client-side library used
     on page 1: this page is the one that gets photocopied and filed, and a data
     URI cannot fail to load. */
  $ackQrUrl = qr_data_uri($qrValue, 200);
  $ackFileNumber = $application->file_number;
@endphp

<div class="ack-page">

  <div class="ack-watermark">File Copy</div>

  <!-- Top Header -->
  <header class="header">
    <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Kano State Ministry Logo" class="header-logo-left">
    <img src="{{ asset('assets/logo/ministry2.png') }}" alt="Kano State Ministry Logo" class="header-logo">
    <div class="ack-qr">
      <img src="{{ $ackQrUrl }}" alt="Consent tracking QR code">
    </div>
    <h1 class="header-title">Consent to Assign</h1>
    <h2 class="header-subtitle">Acknowledgement for Collection of Consent Letter</h2>
  </header>

  <div class="ack-copy-tag">File Copy</div>

  @if (trim((string) $ackFileNumber) !== '')
    <div class="ack-file-no">
      <span class="ack-file-no-label">File No.:</span>
      <span>{{ $ackFileNumber }}</span>
    </div>
  @endif

  <!-- Paragraphs -->
  <section class="content-body">
    <p>
      Please note that you may be invited later for an Interview via Phone, SMS, WhatsApp or Email to provide
      additional information and documentation where necessary. You can always check the status of your application
      via our website [<a href="https://land.gov.ng" target="_blank">https://land.gov.ng</a>] or Contact the Ministry
      of Land Customer Service Desk via Phone: +234 (0)700 000 0000, SMS, WhatsApp, or you can visit the Land Customer
      Service Desk.
    </p>

    <p>
      You can track the progress of your application using the QR code on this page
    </p>

    <p>
      Please keep the original consent letter in a safe place for future reference. It is one of the requirements for
      the registration of your Deed of Assignment.
    </p>
  </section>

  <!-- Contact Details -->
  <section class="contact-section">
    <h3>Contact Information:</h3>
    <div class="contact-address">
      Deeds Department<br>
      2 Dr. Bala Muhammad Way,<br>
      Nassarawa G.R.A., Kano, Nigeria<br>
      Tel: +234 (0)900 000 0000 | Email: support@kangis.gov.ng<br>
      Website: <a href="https://Land.gov.ng" target="_blank">https://Land.gov.ng</a>
    </div>
  </section>

  <!-- Acknowledgment Title -->
  <div class="acknowledgment-title">
    Original copy of acknowledgement Consent Letter was collected by me
  </div>

  <!-- Fillable Form Fields -->
  {{-- class is ack-form-fields, NOT form-container: that name is a page card in
       the OSS print template and reusing it turned this section into a second
       page-sized card, which made the footer logo print clipped. --}}
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
         icon on every acknowledgement sheet. --}}
    <img src="{{ asset('storage/upload/logo/logo.png') }}" alt="KLAES Logo">
  </footer>

</div>

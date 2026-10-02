<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kano State - Recommendation Form</title>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f0f0f0;
        /* Column, not row: the acknowledgement sheet is a second page and must
           stack under page 1, not sit beside it. */
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        padding: 20px;
        color: #000;
      }

      .form-container {
        width: 210mm;
        /* Fixed, not min-height: with a floor the box could grow past the sheet on
           content-heavy records and spill the footer logos onto a page of their own,
           which turned the 2-page print into 3. Capping it keeps page 1 to exactly
           one sheet without touching the layout inside (296mm, not 297mm — a box
           exactly equal to the page makes Chrome emit a trailing blank page). */
        height: 296mm;
        overflow: hidden;
        background: white;
        padding: 10mm 15mm;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        position: relative;
      }

      /* Page 2 (the acknowledgement sheet partial). It normally inherits its
         margins from @page, but this template uses @page margin: 0 and pads the
         page box instead, so match .form-container or the sheet bleeds to the
         paper edge.

         The partial sets min-height: 25.5cm assuming a content-box under @page
         margins. Here `* { box-sizing: border-box }` applies, so that 25.5cm
         would swallow the padding, the box would grow past the sheet, and the
         absolutely-positioned footer logo would land on a third page. Pin the
         height to one sheet instead (296mm, not 297mm — a box exactly equal to
         the page makes Chrome emit a trailing blank page). */
      .ack-page {
        width: 210mm;
        height: 296mm;
        min-height: 0;
        overflow: hidden;
        background: white;
        padding: 10mm 15mm;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        page-break-after: avoid;
      }

      /* The partial pins its footer logo with bottom/right: 0, which resolves
         against the padding box — fine under @page margins, but here it would sit
         flush against the paper edge and the printer would slice the logo. Inset
         it to the page padding.

         `body` prefix is load-bearing: the partial's own `.ack-page .footer` rule
         has identical specificity and ships in a <style> inside the body, so it
         comes later in source order and would otherwise win this tie. */
      body .ack-page .footer {
        bottom: 10mm;
        right: 15mm;
        line-height: 0;
      }

      body .ack-page .footer img {
        height: 40px;
        width: auto;
        max-width: none;
        display: block;
      }

      /* Branding Elements */
      .qr-block {
        position: absolute;
        top: 10mm;
        left: 15mm;
        text-align: center;
      }
      .qr-block canvas,
      .qr-block img {
        width: 50px;
        height: 50px;
        display: block;
      }
      .qr-label {
        font-size: 8px;
        font-weight: bold;
        margin-top: 2px;
        color: #444;
      }

      .serial-block {
        position: absolute;
        top: 10mm;
        right: 15mm;
        border: 1px solid black;
        padding: 4px 8px;
        min-width: 110px;
        text-align: center;
        background: white;
        z-index: 20;
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

      .header-logo {
        text-align: center;
        margin-bottom: 5px;
      }
      .header-logo img {
        width: 80px;
        height: auto;
      }

      /* Header Box */
       .main-heading {
            text-align: center;
            border: 2px solid #050505;
            padding: 4px;
            margin-bottom: 8px;
        }

        .main-heading h1 {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #333;
        }
      
      .ministry-box h1 {
        font-size: 16px;
        text-transform: uppercase;
        margin: 0;
      }
      .oss-label {
        font-size: 11px;
        font-weight: bold;
        color: #ea1b1b;
        letter-spacing: 3px;
        text-transform: uppercase;
      }
      .document-title {
        border: 1.5px solid #000;
        padding: 12px 20px;
        font-size: 19px;
        font-weight: 900;
        text-transform: uppercase;
        line-height: 1.2;
      }

      .sub-heading {
        text-align: center;
        font-size: 15px;
        font-weight: bold;
        text-transform: uppercase;
        text-decoration: underline;
        margin: 15px 0;
      }

      /* Form Layout */
      .form-content {
        font-size: 13.5px;
        line-height: 1.5;
      }
      .field-row {
        display: flex;
        margin-bottom: 8px;
        align-items: baseline;
      }
      .field-label {
        font-weight: bold;
        margin-right: 8px;
        white-space: nowrap;
      }
      .field-value {
        flex-grow: 1;
        text-transform: uppercase;
        padding-left: 5px;
      }

      .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        column-gap: 40px;
        width: 100%;
      }

      /* Recommendation Sections */
      .section-block {
        margin-top: 15px;
      }
      .section-title {
        font-weight: bold;
        text-transform: uppercase;
        font-size: 13px;
        margin-bottom: 5px;
      }
      .reason-text {
        margin-left: 25px;
        margin-bottom: 15px;
      }

      /* ── White Copy: the proof sheet ───────────────────────────
         Centred across the head of the page, large enough to be the first thing
         read off the sheet, plain black so it survives a monochrome printer. The
         block keeps the height the arms and serial occupied so the form below
         begins where it begins on the official print. */
      /* Black and white throughout. This document is already almost entirely black
         on white; the OSS label is the one thing carrying colour, and a proof goes
         through whatever printer is nearest on ordinary paper. */
      .form-container.white-copy .oss-label,
      .form-container.white-copy .status-options {
        color: #000 !important;
      }

      .white-copy-head {
        text-align: center;
        min-height: 70px;
        padding-top: 6px;
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

      .white-copy-sig-gap {
        display: block;
        text-align: center;
        font-size: 7pt;
        font-style: italic;
        color: #6b7280;
        padding-top: 14px;
      }

      .signature-row {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
      }
      .sig-line-container {
        width: 45%;
      }
      .sig-line {
        border-bottom: 1px solid #000;
        height: 20px;
        margin-bottom: 5px;
      }
      .sig-label {
        text-align: center;
        font-weight: bold;
        font-size: 12px;
      }

      /* Final Approval Row — the grant clause and both options read as one line. */
      .status-options {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 10px;
      }

      /* Footer */
      .footer-banner {
        text-align: center;
        font-size: 14px;
        font-weight: bold;
        border: 1px solid #000;
        padding: 6px;
        margin-top: 25px;
      }
      .footer-logos {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
      }

      /* On a proof the signature blocks are gone, so the page runs short and the
         logos ride up under the ministry banner with the rest of the sheet empty
         beneath them.

         Pinned to the foot of the page rather than pushed down by a margin. The
         margin was a guess at how much content the proof happened to lose, and the
         answer differs per record — a long recommendation loses less. .form-container
         is a fixed 296mm sheet with position: relative, so the logos can simply be
         told where they belong: the bottom, inset to the same 15mm/10mm padding the
         page uses, whatever the letter above them does.

         Scoped to the proof: the official print fills its page and its spacing is
         not this sheet's to change. */
      .form-container.white-copy .footer-logos {
        position: absolute;
        left: 15mm;
        right: 15mm;
        bottom: 10mm;
        margin-top: 0;
      }
      .footer-logos img {
        height: 35px;
        width: auto;
      }

      @media print {
        body {
          background: white;
          padding: 0;
          /* Block, not flex — browsers ignore page-break-before on flex items,
             which is what merged the two pages into one sheet. */
          display: block;
          gap: 0;
        }
        .form-container {
          box-shadow: none;
          width: 100%;
          border: none;
        }
        .ack-page {
          width: 100%;
          box-shadow: none;
        }
        @page {
          size: A4;
          margin: 0;
        }
      }

      {{-- The downloaded OSS White Copy (DomPDF 2.x). DomPDF has no flexbox, no
           grid and no box-sizing: border-box, so the sheet rules above misfire
           there — a 210mm box plus its 15mm side padding ran off the right edge,
           and a 296mm box plus its vertical padding spilled onto a second page.
           Here the page margins do the job the padding did, the container takes
           its size from the page, and the flex/grid rows become tables and floats
           DomPDF can lay out. --}}
      @if(!empty($isPdfDownload))
      /* The margin lives on body, not @page: the @page { margin: 0 } in the
         print block above is still picked up by DomPDF and zeroed the page
         margins, running the text into the paper edge. */
      @page {
        size: A4 portrait;
        margin: 0;
      }
      html {
        margin: 0;
        padding: 0;
        background: #fff;
      }
      body {
        width: auto;
        height: auto;
        margin: 0;
        padding: 12mm 18mm 12mm 18mm;
        background: #fff;
        display: block;
        overflow: visible;
      }
      .form-container,
      .form-container.white-copy {
        width: auto;
        height: auto;
        margin: 0;
        padding: 0;
        box-shadow: none;
        overflow: visible;
        position: static;
      }
      .white-copy-head {
        min-height: 0;
        padding-top: 0;
        margin-bottom: 10px;
      }
      .field-row {
        display: block;
        margin-bottom: 6px;
      }
      .field-label,
      .field-value {
        display: inline;
      }
      .info-grid {
        display: table;
        width: 100%;
        table-layout: fixed;
      }
      .info-grid > div {
        display: table-cell;
        width: 50%;
        vertical-align: top;
      }
      .signature-row {
        display: block;
      }
      /* Held to the foot of the sheet, inset to the same margins as body. */
      .form-container .footer-logos,
      .form-container.white-copy .footer-logos {
        position: fixed;
        left: 18mm;
        right: 18mm;
        bottom: 12mm;
        height: 35px;
        margin-top: 0;
        display: block;
      }
      .footer-logos img.logo-left {
        float: left;
      }
      .footer-logos img.logo-right {
        float: right;
      }
      @endif
    </style>
  </head>
  <body>
    <div class="form-container{{ !empty($isWhiteCopy) ? ' white-copy' : '' }}">
      @php
          // The location line: DISTRICT, LGA.
          //
          // Addresses arrive as "240, KUYAN TA INNA, KUMBOTSO, KANO STATE" — plot,
          // district, LGA, state — and the letter wants the middle two. The plot is
          // already printed in a field of its own, and the state is the same for
          // every file in this registry, so neither earns a place on the line.
          //
          // The district is the first part left and the LGA is the last: taking the
          // ends rather than a fixed count keeps a compound district whole, so
          // "U/RIMI, DANSUDU, WANGARA, TOFA" reads "U/RIMI, TOFA" and not a name cut
          // off in the middle. Where only one part survives it stands alone.
          $cleanLocation = function($loc) {
              if (!$loc) return '';

              $parts = array_values(array_filter(
                  array_map('trim', explode(',', $loc)),
                  static fn($part) => $part !== ''
              ));
              if (!$parts) return '';

              // "PLOT 3004", the commercial "PLOT C-651" / "PLOT TGS-189", or just
              // the bare number. The digit is what makes it a plot reference rather
              // than a place: one address opens "PLOT KIDIDDIFAWA WESTERN BY PASS",
              // where the word belongs to the name and stripping it would throw the
              // district away.
              $isPlotReference = static function (string $part): bool {
                  if (preg_match('/^PLOT\b/i', $part) === 1) {
                      return preg_match('/[0-9]/', $part) === 1;
                  }

                  return preg_match('/^[0-9]+[A-Za-z]?$/', $part) === 1;
              };
              if (count($parts) > 1 && $isPlotReference($parts[0])) {
                  array_shift($parts);
              }

              // "KANO" or anything ending in "STATE". Dropped only while something
              // else remains — a location that is nothing but the state still has
              // to print something.
              $isState = static function (string $part): bool {
                  return preg_match('/^KANO$/i', $part) === 1
                      || preg_match('/\bSTATE$/i', $part) === 1;
              };
              if (count($parts) > 1 && $isState($parts[count($parts) - 1])) {
                  array_pop($parts);
              }

              if (count($parts) === 1) {
                  return $parts[0];
              }

              return $parts[0] . ', ' . $parts[count($parts) - 1];
          };

          // The recommendation's Serial No. It is minted here, on first print, and
          // keyed by file number alone (document_id 0) so the three controllers
          // that render this template all resolve to the same row. This is NOT
          // $record->rofo_serial_no — that column holds the RofO's security paper
          // code, which a recommendation never carries.
          // The proof sheet — shared by
          // LandRecommendationController::printWhiteCopy(). Never minted for one:
          // the serial is the document's official number and this template mints it
          // as it renders, so merely opening a proof would otherwise spend a real
          // one.
          $isWhiteCopy = !empty($isWhiteCopy);

          $securityCode = null;
          $sc = null;
          if (!$isWhiteCopy) {
              $securityCode = app(\App\Services\SecurityCodeService::class)->getOrGenerateForDocument(
                  (string) ($record->file_ref ?? ''),
                  0,
                  'OSS Recomm'
              );
              $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code);
          }
      @endphp
      {{-- The QR resolves to a verifiable record, the serial is the document's
           official number and the arms are the State's, so a proof carries none of
           them. It says what the sheet is instead. --}}
      @if($isWhiteCopy)
      <div class="white-copy-head">
        <div class="white-copy-mark">White Copy</div>
        <div class="white-copy-note">Proof for vetting — not an official document</div>
      </div>
      @else
      @if(!empty($record->tracking_id))
      <div class="qr-block">
        <div id="qr-code"></div>
      </div>
      @endif

      <div class="serial-block">
        <div style="font-weight: bold; text-transform: uppercase; border-bottom: 1px solid black; padding-bottom: 3px; margin-bottom: 4px; font-size: 9px;">Serial No:</div>
        <div class="serial-code-wrap">
            <span class="serial-fraction">
              <span class="frac-top">{{ $sc['alphabet'] }}</span>
              <span class="frac-bot">{{ $sc['digits_start'] }}</span>
            </span>
            <span class="serial-digits">{{ $sc['digits_end'] }}</span>
        </div>
      </div>

      <div class="header-logo">
        <img
          src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg"
          alt="Coat of Arms"
        />
      </div>
      @endif

      <div class="main-heading">
            <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <h2 style="font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #333;">
                RECOMMENDATION FOR GRANT OF STATUTORY<br>RIGHT OF OCCUPANCY
            </h2>
            <br>
            <p class="oss-label" style="color:#ea1b1b">LAND ONE STOP SHOP</p>
        </div>


      <div class="sub-heading">Conditions for Application</div>

      <div class="form-content">
        <div class="field-row">
          <span class="field-label">1. Name Of Applicant:</span>
          <span class="field-value">{{ $record->applicant_name }}</span>
        </div>

        <div class="info-grid">
          <div>
            <div class="field-row">
              <span class="field-label">2. (a) File Ref No:</span>
              <span class="field-value">{{ $record->file_ref }}</span>
            </div>
            <div class="field-row" style="padding-left: 17px">
              <span class="field-label">(c) Location:</span>
              <span class="field-value">{{ $cleanLocation($record->location) }}</span>
            </div>
          </div>
          <div>
            <div class="field-row">
              <span class="field-label">(b) Purpose Of Clause:</span>
              <span class="field-value">{{ $record->purpose }}</span>
            </div>
            <div class="field-row">
              <span class="field-label">(d) Plot No:</span>
              <span class="field-value">{{ $record->plot_no }}</span>
            </div>
          </div>
        </div>

        <div class="field-row" style="padding-left: 17px">
          <span class="field-label">(e) Layout Plan No.:</span>
          <span class="field-value">{{ $record->plan_no }}</span>
        </div>

        <div class="field-row">
          <span class="field-label">3. Term:</span>
          <span class="field-value">{{ $record->term }} years</span>
        </div>
        <div class="field-row">
          <span class="field-label">4. Value For Proposed Development:</span>
          <span class="field-value">{{ $record->dev_value }}</span>
        </div>
        <div class="field-row">
          <span class="field-label"
            >5. Time for the Completion of proposed development:</span
          >
          <span class="field-value">{{ $record->completion_time }}</span>
        </div>
        <div class="field-row">
          <span class="field-label">6. Annual Ground Rent(phpa):</span>
          <span class="field-value">{{ $record->ground_rent }}</span>
        </div>
        <div class="field-row">
          <span class="field-label">7. Development Charge (If any):</span>
          <span class="field-value">{{ $record->dev_charge }}</span>
        </div>
        <div class="field-row">
          <span class="field-label">8. Survey And Processing Charges:</span>
          <span class="field-value">{{ $record->survey_charges }}</span>
        </div>

        <div class="section-block">
          <div class="section-title">
            9. The Director of Land recommends/does not recommend the
            application for the following reasons,
          </div>
          <div class="reason-text">
            {{ $record->director_reasons }}
          </div>
          {{-- Signature lines come off the proof: a blank rule over an office
               name is what can be signed and passed off as the document itself.
               The room they took is left behind so the page still breaks where it
               breaks on the official print. --}}
          @if($isWhiteCopy)
          <div class="signature-row white-copy-sig-gap">
            Signature block omitted — white copy for proofreading only
          </div>
          @else
          <div class="signature-row">
            <div class="sig-line-container">
              <div style="margin-top: 10px; text-align: left">
                Sign: ________________________________
              </div>
              <div class="sig-label">Director Land</div>
            </div>
            <div style="width: 40%">
             <div style="margin-top: 10px; text-align: left">
                Date: {{ $record->director_date ?: '________________________' }}
              </div>
            </div>
          </div>
          @endif
        </div>

        <div class="section-block">
          <div class="section-title">
            10. RECOMMENDATION BY THE PERMANENT SECRETARY
          </div>
          <div class="reason-text">
            I recommend/do not recommend the Application for a Grant of Right of
            Occupancy over Plot No: {{ $record->ps_plot ?: $record->plot_no }} Location: {{ $cleanLocation($record->ps_location ?: $record->location) }}
          </div>
          @if($isWhiteCopy)
          <div class="signature-row white-copy-sig-gap">
            Signature block omitted — white copy for proofreading only
          </div>
          @else
          <div class="signature-row">
            <div class="sig-line-container">
              <div style="margin-top: 10px; text-align: left">
                Sign: ________________________________
              </div>
              <div class="sig-label">Permanent Secretary</div>
            </div>
            <div style="width: 40%">
              <div style="margin-top: 10px; text-align: left">
                Date: {{ $record->ps_date ?: '________________________' }}
              </div>
            </div>
          </div>
          @endif
        </div>

        <div class="section-block">
          <div class="section-title">
            11. APPROVAL BY THE HONOURABLE COMMISSIONER
          </div>
          {{-- Off the proof, with the signature block it belongs to: it is the line
               the Commissioner's signature acts on. --}}
          @unless($isWhiteCopy)
          <p class="status-options">
            The Grant of Right of Occupancy is hereby
            @if($record->approval_status === 'approved')
              <u>APPROVED</u><span style="color: #999;">/NOT APPROVED</span>
            @elseif($record->approval_status === 'not_approved')
              <span style="color: #999;">APPROVED/</span><u>NOT APPROVED</u>
            @else
              APPROVED/NOT APPROVED
            @endif
          </p>
          @endunless
<br>
          {{-- Same shape as the Permanent Secretary row in section 10: signature
               line with the office name under it, Date alongside on the right. --}}
          @if($isWhiteCopy)
          <div class="signature-row white-copy-sig-gap">
            Signature block omitted — white copy for proofreading only
          </div>
          @else
          <div class="signature-row">
            <div class="sig-line-container">
              <div style="margin-top: 10px; text-align: left">
                Sign: ________________________________
              </div>
              <div class="sig-label">
                @if(!empty($record->commissioner_name))
                  {{ $record->commissioner_name }}<br>
                @endif
                The Honourable Commissioner of Land
              </div>
            </div>
            <div style="width: 40%">
              <div style="margin-top: 10px; text-align: left">
                Date: {{ $record->commissioner_date ?: '________________________' }}
              </div>
            </div>
          </div>
          @endif
        </div>
      </div>

      <div class="footer-banner">
        Kano State Ministry of Land and Physical Planning
      </div>
      <div class="footer-logos">
        <img
          class="logo-left"
          src="http://app.klaes.ng/assets/logo/Left_Logo.png"
          alt="KLAES Logo"
        />
        <img class="logo-right" src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="LAS Logo" />
      </div>
    </div>

    {{-- Page 2: "Right of Occupancy — Collection of Letter of Grant", the same
         acknowledgement sheet the Land Recommendation print carries. The partial
         is fully static (no view variables) and scopes its CSS under .ack-page. --}}
    @unless($isWhiteCopy)
    @include('land_recommendations.templates._ack_sheet')
    @endunless


    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
      window.onload = function () {
        var trackingId = @json($record->tracking_id ?? '');
        if (trackingId) {
          new QRCode(document.getElementById("qr-code"), {
            text: trackingId,
            width: 50,
            height: 50,
            correctLevel: QRCode.CorrectLevel.M
          });
        }
        setTimeout(function () { window.print(); }, 600);
      };
    </script>
  </body>
</html>

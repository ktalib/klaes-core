@php
    // The proof sheet — shared by LandRecommendationController::printWhiteCopy().
    $isWhiteCopy = !empty($isWhiteCopy);

    // Never minted for a proof: the serial is the document's official number, and
    // this template mints it as it renders, so merely opening a proof would
    // otherwise have spent a real one.
    $securityCode = null;
    $sc = null;
    if (!$isWhiteCopy) {
        $securityCode = app(\App\Services\SecurityCodeService::class)->getOrGenerateForDocument(
            (string) ($recommendation->file_number ?? ($recommendation->id ?? '')),
            (int) $recommendation->id,
            'Land Conversion'
        );
        $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code);
    }
@endphp
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Kano State Land Form - Conversion Recommendation</title>
    <style>
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* ── White Copy: the proof sheet ────────────────────────────────────
         Centred across the head of the page, large enough to be the first thing
         read off the sheet, plain black so it survives a monochrome printer. */
      /* Black and white throughout. The document is drawn in the ministry green on
         cream; a proof goes through whatever printer is nearest, on ordinary paper,
         and green reproduced in grey reads as a smudge where the real thing reads
         as a heading.

         Declared element by element rather than as one grayscale filter on the
         page: a filter applies to the whole subtree with no way for a descendant to
         opt out, and it would leave the greens as mid-greys rather than as black.
         The banners keep their shape — same padding, same block — so nothing below
         them moves; only the ink changes. */
      .a4-page.white-copy {
        background-color: #fff !important;
        color: #000 !important;
      }

      .a4-page.white-copy [class*="bg-[#1a4731]"],
      .a4-page.white-copy .full-width-green {
        background-color: #fff !important;
        color: #000 !important;
        border: 1px solid #000;
      }

      /* The hairline under each banner is part of the green rule above it. */
      .a4-page.white-copy .h-0\.5 {
        background-color: #000 !important;
      }

      .a4-page.white-copy [class*="text-[#1a4731]"],
      .a4-page.white-copy [class*="text-red"] {
        color: #000 !important;
      }

      .a4-page.white-copy [class*="border-[#1a4731]"] {
        border-color: #000 !important;
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

      @media print {
        html, body {
          height: auto !important;
          margin: 0 !important;
          padding: 0 !important;
          background-color: #fdfcf0 !important;
        }
        @page {
          size: A4;
          margin: 0;
        }
        .no-print {
          display: none !important;
        }
        .a4-page {
          width: 210mm !important;
          height: 296mm !important; /* Slightly less than 297mm to prevent overflow loops */
          padding: 10mm !important;
          margin: 0 !important;
          border: none !important;
          box-shadow: none !important;
          background-color: #fdfcf0 !important;
          background-color: #fdfcf0 !important;
        }
      }

      .dotted-line {
        border-bottom: 1px dashed #1a4731;
        flex-grow: 1;
        height: auto;
        margin-left: 4px;
        position: relative;
        top: -4px;
        display: block;
      }

      .full-width-green {
        background-color: #1a4731;
        margin-left: -24px;
        margin-right: -24px;
        padding-left: 24px;
        padding-right: 24px;
        padding-top: 8px;
        padding-bottom: 8px;
        width: calc(100% + 48px);
        box-sizing: border-box;
      }

      .value-text {
        color: #000;
        font-family: serif;
        font-weight: bold;
        position: relative;
        top: -2px;
        padding: 0 5px;
        margin-bottom: 2px;
        display: inline-block;
        line-height: 1.3;
      }

      .serial-box {
        border: 1px solid #1a4731;
        padding: 4px 8px;
        min-width: 110px;
        margin-top: 4px;
        font-size: 11px;
      }

      .serial-box .serial-label {
        font-weight: bold;
        text-transform: uppercase;
        border-bottom: 1px solid #1a4731;
        padding-bottom: 3px;
        margin-bottom: 4px;
        font-size: 11px;
        color: #1a4731;
      }

      .serial-code-wrap {
        display: inline-flex;
        align-items: center;
        gap: 4px;
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
    </style>
  </head>
  <body class="bg-gray-100 flex flex-col items-center font-serif p-2">
    <div class="no-print mb-4 flex gap-4">
        <button onclick="window.print()" class="bg-[#1a4731] text-white px-8 py-2 rounded-full font-bold shadow-lg hover:bg-[#0f2e1f]">
          Print Record
        </button>
        <a href="{{ route('land-recommendations.index') }}" class="bg-white text-slate-700 border border-slate-200 px-8 py-2 rounded-full font-bold shadow hover:bg-slate-50">
          Back to List
        </a>
    </div>

    <div class="a4-page{{ $isWhiteCopy ? ' white-copy' : '' }} bg-[#fdfcf0] w-[210mm] h-[297mm] p-8 border border-gray-400 shadow-2xl relative text-[#1a4731] flex flex-col box-border overflow-hidden">
      <div class="h-full flex flex-col">
        <!-- Header -->
        {{-- The QR resolves to a verifiable record and the serial is the document's
             official number, so a proof carries neither. The mark takes their place,
             centred across the head of the sheet; the block keeps its height so the
             form below begins where it begins on the official print. --}}
        @if($isWhiteCopy)
        <div class="flex flex-col items-center justify-center mb-1 px-2" style="min-height: 3.5rem;">
          <div class="white-copy-mark">White Copy</div>
          <div class="white-copy-note">Proof for vetting — not an official document</div>
        </div>
        @else
        <div class="flex justify-between items-start mb-1 px-2">
          <div class="w-14 h-14 border border-[#1a4731] flex items-center justify-center bg-white p-1">
            <img src="{{ qr_data_uri($recommendation->file_number, 100) }}" alt="QR" class="w-full h-full grayscale">
          </div>
          <div class="text-right flex flex-col items-end gap-1">
         
            <div class="serial-box">
              <div class="serial-label">Serial No:</div>
              <div class="serial-code-wrap">
                <span class="serial-fraction">
                  <span class="frac-top">{{ $sc['alphabet'] }}</span>
                  <span class="frac-bot">{{ $sc['digits_start'] }}</span>
                </span>
                <span class="serial-digits">{{ $sc['digits_end'] }}</span>
              </div>
            </div>
          </div>
        </div>
        @endif

        <div class="border-2 border-[#1a4731] pt-4 pb-3 px-5 flex flex-col flex-grow box-border relative overflow-hidden">
          <!-- Main Banner -->
          <div class="flex items-center gap-4 mb-1 shrink-0">
            {{-- The ministry crest is a mark of an issued document, so it comes off
                 the proof with the arms, the QR and the serial. The column keeps its
                 width so the banner beside it stays where it is. --}}
            <div class="w-16">
              @unless($isWhiteCopy)
              <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" class="w-14 mx-auto" alt="Logo" onerror="this.src='http://app.klaes.ng/assets/logo/ministry1.jpg'">
              @endunless
            </div>
            <div class="bg-[#1a4731] text-white text-center py-1.5 px-4 flex-1 relative" style="border-top-left-radius: 25px">
              <h1 class="font-bold text-[16px] uppercase tracking-tighter leading-tight">
                Recommendation for the Conversion of <br />
                Customary Title to Statutory Right of Occupancy
              </h1>
              <div class="absolute -bottom-2 left-0 w-full h-0.5 bg-[#1a4731]"></div>
            </div>
          </div>

          <!-- Address Block -->
          <div class="mb-1 text-[13px] font-bold leading-tight text-[#1a4731] shrink-0">
            <p>Honourable Commissioner</p>
            <p class="italic text-[#2e6349]">Ministry of Land and Physical Planning</p>
            <p>Kano State.</p>
          </div>

          <!-- Application Details (Section 3 mappings) -->
          <div class="text-black space-y-1 text-[13px] mb-1 shrink-0">
            <div class="flex items-end">
              <span>Page
                <span class="border-b border-black px-2 min-w-[30px] inline-block text-center font-bold">
                    {{ $recommendation->page }}
                </span>
              </span>
              <span class="ml-4 italic">Application by:</span>
              <div class="dotted-line">
                  <span class="value-text">{{ strtoupper($recommendation->applicant_name) }}</span>
              </div>
            </div>
            <div class="ml-20 flex items-end">
              <span class="italic">for the Right of Occupancy</span>
              <div class="dotted-line">
                  <span class="value-text">{{ $recommendation->file_number }}</span>
              </div>
            </div>
            <div class="flex items-end">
              <div class="ml-20 dotted-line">
                  <span class="value-text">{{ normalizeLocationText($recommendation->location) }} @if($recommendation->plot_number) (PLOT: {{ $recommendation->plot_number }}) @endif</span>
              </div>
            </div>
            <div class="ml-20 flex items-end">
              <span class="italic">for the purpose of</span>
              <div class="dotted-line">
                  <span class="value-text">{{ $recommendation->landuse_purpose }}</span>
              </div>
            </div>
            <div class="pt-1 text-[13px] leading-relaxed flex items-start gap-2">
              <div class="flex items-center shrink-0 gap-1">
                <span>Page</span>
                <span class="border-b border-black px-2 min-w-[30px] inline-block text-center font-bold">
                    {{ $recommendation->page_survey_report }}
                </span>
                <span class="ml-2 italic whitespace-nowrap">Survey Report</span>
              </div>
              <div class="flex-grow text-black">
                <span class="border-b border-dashed border-[#1a4731] font-bold px-1 inline text-black">
                    {{ $recommendation->survey_report }}
                </span>
              </div>
            </div>
          </div>

          <!-- Planning Recommendation -->
          <div class="mb-0 text-black shrink-0">
            <p class="text-center font-bold text-[13px] mb-0.5 uppercase tracking-wide">
              PLANNING RECOMMENDATION (IF ANY)
            </p>
            {{-- Physical Planning's comment, captured on the Page Number details card
                 beside the page it is read off. It was printing `recommendation` — the
                 officer's own recommendation text, a different field on a different
                 card — so the letter carried the wrong sentence here. Blank when there
                 is none: this line is "if any", and "NONE PROVIDED" printed on a letter
                 reads as a finding rather than an empty box. --}}
            <div class="space-y-2 px-2 py-1 border border-slate-200 rounded-lg min-h-[24px] text-[13px]">
                {{ $recommendation->physical_planning_comment ?? '' }}
            </div>
          </div>

          {{-- Terms Table. Each row is its own grid, so the label column is a fixed
               width rather than `auto` — auto would size each row to its own label and
               the five rule lines would start at five different places. That width has
               to clear the longest label, "Time of Erection:", which at 100px wrapped
               to two lines: "Time of" sat above the row and, because the row is
               items-end, "Erection:" dropped to the baseline beside (e) with the rule
               line beside only that. whitespace-nowrap holds every label to one line,
               so none of them can break a row again. --}}
          <div class="text-black text-[13px] space-y-0.5 mb-1 shrink-0">
            <p class="font-bold underline italic">
              The grant of Right of Occupancy is recommended on the terms set out as follows:
            </p>
            <div class="grid grid-cols-[20px_128px_1fr] items-end px-2">
              <span>(a)</span>
              <span class="italic font-bold whitespace-nowrap">Rents:</span>
              <div class="border-b border-black h-4 px-2">
                  ₦ {{ $recommendation->ground_rent_label }}
              </div>
            </div>
            <div class="grid grid-cols-[20px_128px_1fr] items-end px-2">
              <span>(b)</span>
              <span class="italic font-bold whitespace-nowrap">Terms:</span>
              <div class="border-b border-black h-4 px-2">
                  {{ $recommendation->term ?? '99' }} YEARS
              </div>
            </div>
            <div class="grid grid-cols-[20px_128px_1fr] items-end px-2">
              <span>(c)</span>
              <span class="italic font-bold whitespace-nowrap">Improvement:</span>
              <div class="border-b border-black h-4 px-2">
                  {{ $recommendation->improvement }}
              </div>
            </div>
            <div class="grid grid-cols-[20px_128px_1fr] items-end px-2">
              <span>(d)</span>
              <span class="italic font-bold whitespace-nowrap">Revision Period:</span>
              <div class="border-b border-black h-4 px-2">
                  {{ $recommendation->revision_period }}
              </div>
            </div>
            <div class="grid grid-cols-[20px_128px_1fr] items-end px-2">
              <span>(e)</span>
              <span class="italic font-bold whitespace-nowrap">Time of Erection:</span>
              <div class="border-b border-black h-4 px-2">
                  {{ $recommendation->time_of_erection }}
              </div>
            </div>
          </div>

          <!-- Recommendation Footer -->
          <p class="text-black text-[13px] italic mb-1 shrink-0">
            You may wish to approve this application on the terms set out above
            and subject to Survey Report at page <span class="inline-block w-8 border-b border-black text-center font-bold">{{ $recommendation->page_survey_report }}</span> and recommendation of the <br /> Urban Planning Board/ Physical Planning
            Department at page
            <span class="inline-block w-16 border-b border-black text-center font-bold">{{ $recommendation->page_2 }}</span>.
          </p>

          {{-- Signature lines come off the proof: a blank rule over a name is what
               can be signed and passed off as the document itself. The room they
               took is left behind so the page still breaks where it breaks on the
               official print. --}}
          @if($isWhiteCopy)
          <div class="mt-11 mb-1 shrink-0 text-center text-[9px] italic text-slate-500">
            Signature block omitted — white copy for proofreading only
          </div>
          @else
          <div class="flex justify-between text-center text-[13px] mt-11 mb-1 px-4 text-black shrink-0">
            <div class="w-40 border-t border-black pt-1 font-bold">Rank</div>
            <div class="w-40 border-t border-black pt-1 font-bold">Director Land</div>
          </div>
          @endif

          <!-- Approval Banner -->
          <div class="full-width-green text-white text-center py-1.5 mb-1 shrink-0">
            <h2 class="font-bold text-[14px] uppercase tracking-tight">
              APPROVAL FOR THE CONVERSION OF CUSTOMARY <br />
              TITLE TO STATUTORY RIGHT OF OCCUPANCY
            </h2>
            <div class="absolute -bottom-2 left-0 w-full h-0.5 bg-[#1a4731]"></div>
          </div>

          <!-- Approval Details -->
          <div class="text-black text-[13px] space-y-2 mb-1 shrink-0">
            <div class="flex items-end">
              <span>I recommend/do not recommend the application for a Grant over
                Plot No.:</span>
              <div class="dotted-line">
                  <span class="value-text">{{ $recommendation->plot_number ?? '____' }}</span>
              </div>
            </div>
            <div class="flex items-end gap-4">
              <span>Plan No.</span>
              <div class="dotted-line">
                   <span class="value-text">{{ $recommendation->layout_plan_no ?? '____' }}</span>
              </div>
              <span>Location</span>
              <div class="dotted-line w-2/3">
                  <span class="value-text">{{ normalizeLocationText($recommendation->location) }}</span>
              </div>
            </div>
          </div>

          <!-- Permanent Secretary Signature -->
          @if($isWhiteCopy)
          <div class="mt-10 shrink-0 text-center text-[9px] italic text-slate-500">
            Signature block omitted — white copy for proofreading only
          </div>
          @else
          <div class="mt-10 shrink-0">
            <div class="flex justify-between text-[13px] text-black">
              <div class="w-56 border-t border-black text-center pt-1 font-bold uppercase">
                Permanent Secretary
              </div>
              <div class="w-24 border-t border-black text-center pt-1 font-bold uppercase">
                Date
              </div>
            </div>
          </div>
          @endif

          <!-- Status / Approval Text -->
          {{-- Off the proof, with the signature blocks it belongs to: it is the line
               the Commissioner's signature acts on, and there is nothing in it to
               proofread — it is the same fixed text on every document. --}}
          @unless($isWhiteCopy)
          <p class="text-red-600 font-bold text-[13px] text-left tracking-tighter mt-10 mb-0 shrink-0">
            The Grant of Occupancy is hereby APPROVED/NOT APPROVED
          </p>
          @endunless

          <!-- Honourable Commissioner Signature -->
          @if($isWhiteCopy)
          <div class="mt-10 shrink-0 text-center text-[9px] italic text-slate-500">
            Signature block omitted — white copy for proofreading only
          </div>
          @else
          <div class="mt-10 shrink-0">
            <div class="flex justify-between text-[13px] text-black">
              <div class="w-56 border-t border-black text-center pt-1 font-bold uppercase">
                Honourable Commissioner
              </div>
              <div class="w-24 border-t border-black text-center pt-1 font-bold uppercase">
                Date
              </div>
            </div>
          </div>
          @endif

          {{-- The KLAES logo. On an official run it arrives on the acknowledgement
               sheet behind this page; the proof leaves that sheet off, so it sits at
               the foot of the proof itself — the same system mark the RofO's back
               page carries. It is not an official mark: it says which system
               produced the sheet, which is worth knowing on a draft. --}}
          @if($isWhiteCopy)
          <div class="mt-auto pt-2 flex items-center justify-end border-t border-black/20 shrink-0">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo"
                 style="height: 26px; width: auto; object-fit: contain;">
          </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Page 2: acknowledgement / collection sheet.
         Wrapped so it inherits this template's cream background and gets page
         padding (this layout uses @page { margin: 0 }); the partial's own
         .ack-page carries the page-break so it starts on a fresh sheet. --}}
    <style>
      .ack-print-wrap { background-color: #fdfcf0; }
      .ack-print-wrap .ack-page {
        padding: 10mm;
        box-sizing: border-box;
        min-height: 285mm;
      }
      /* Inset the footer so the logo sits inside the corner, not flush to the
         physical paper edge (this layout uses @page { margin: 0 }). */
      .ack-print-wrap .ack-page .footer {
        bottom: 12mm;
        right: 12mm;
      }
      @media print {
        .ack-print-wrap { background-color: #fdfcf0 !important; }
      }
    </style>
    {{-- Not on a proof: the acknowledgement sheet is the half the applicant signs
         and returns on collection, and there is nothing on it to proofread that is
         not already on page 1. A white copy is one sheet. --}}
    @unless($isWhiteCopy)
    <div class="ack-print-wrap">
      @include('land_recommendations.templates._ack_sheet')
    </div>
    @endunless

    <script>
      // Auto-open the print dialog once the page (and images) have loaded.
      window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
      });

      // Log the print after the dialog closes — never for a proof, which has not
      // been printed in the sense print_logs records.
      @unless($isWhiteCopy)
      window.onafterprint = function () {
        fetch('{{ route("land-recommendations.log-print", $recommendation->id) }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        })
        .then(response => response.json())
        .then(data => console.log('Print logged:', data))
        .catch(error => console.error('Error logging print:', error));
      };
      @endunless
    </script>
  </body>
</html>

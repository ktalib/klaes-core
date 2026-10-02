<!DOCTYPE html>
<html lang="en">
{{--
 | SLTR's own copy of programmes/print_cofo_complete (cloned 2026-10-02).
 |
 | The COMPLETE SLTR Certificate of Occupancy — one two-sided document.
 |
 |     page 1 (front)  the C of O front page      KLAES / ST
 |     page 2 (back)   the TDP                    KANGIS / GIS
 |
 | The front page markup and its print styles are the SAME partials the standalone front-page
 | view uses, so the front of the complete certificate cannot drift away from the front page
 | printed on its own. Only the back page is new here.
 |
 | Expects: $cofo, $tdp, $tdpUrl, $tdpIsPdf
 --}}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLTR Certificate of Occupancy — Complete</title>
    <style>
@include('sltr_cofo.partials.print_styles')

        /* ---- Back page (TDP) ------------------------------------------------------
           A4 to match the front, and forced onto its own sheet. The TDP is scanned or
           plotted artwork of unpredictable proportions, so it is fitted inside the page
           rather than stretched: a survey plan printed out of proportion is not a survey
           plan. */
        .tdp-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 10mm;
            box-sizing: border-box;
            page-break-before: always;
            break-before: page;
            display: flex;
            flex-direction: column;
        }

        .tdp-page img,
        .tdp-page embed,
        .tdp-page object {
            display: block;
            width: 100%;
            height: 277mm;
            object-fit: contain;
            border: 0;
        }

        .tdp-fallback {
            border: 1px dashed #999;
            padding: 12mm;
            font-family: "Times New Roman", serif;
            font-size: 11pt;
            text-align: center;
            color: #333;
        }

        .tdp-fallback a { color: #0645ad; }

        /* On screen only — a hint that this is the two-sided document, never printed. */
        .screen-only { display: block; }

        @media print {
            .screen-only { display: none !important; }
        }
    </style>
</head>
<body>

<div class="screen-only" style="max-width:210mm;margin:8px auto;padding:8px 12px;font-family:sans-serif;font-size:12px;color:#334155;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;">
    <strong>Complete Certificate of Occupancy</strong> —
    page 1 is the front page, page 2 is the TDP
    ({{ $tdp->original_name ?: basename($tdp->file_path) }}).
    Print double-sided to produce the certificate.
</div>

{{-- ---- PAGE 1: FRONT ------------------------------------------------------------- --}}
@include('sltr_cofo.partials.front_page_body')

{{-- ---- PAGE 2: BACK (TDP) --------------------------------------------------------- --}}
<div class="tdp-page">
    @if ($tdpIsPdf)
        {{-- A PDF TDP is embedded rather than converted. Browsers print an embedded PDF
             inconsistently, so the link below is the reliable route when the embed is
             refused — better than silently printing a blank back page. --}}
        <embed src="{{ $tdpUrl }}" type="application/pdf">
        <div class="tdp-fallback screen-only">
            {{ __('If the TDP does not appear above, open it directly:') }}
            <a href="{{ $tdpUrl }}" target="_blank">{{ $tdp->original_name ?: __('TDP') }}</a>
        </div>
    @else
        <img src="{{ $tdpUrl }}" alt="{{ __('TDP — back page of the Certificate of Occupancy') }}">
    @endif
</div>

<script>
    // Printing is what this page is for, so offer the dialog on arrival. Guarded because a
    // PDF embed can still be loading when the document fires ready.
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 600);
    });
</script>

</body>
</html>

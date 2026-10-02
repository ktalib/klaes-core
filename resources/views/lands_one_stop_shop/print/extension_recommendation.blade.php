{{--
    Plot Extension memo to the Permanent Secretary / Honourable Commissioner.

    Composed the way duplex/print/recommendation.blade.php composes its memo - that
    is the sheet the Ministry accepted, so this one follows it: the page GROWS rather
    than clipping at 297mm, the parcel sizes the application recorded are printed
    (the controller already loads them; the sheet simply never used them), and the
    footer marks are absolute URLs so they survive being printed from a host that is
    not this app.
--}}
@php
    use App\Support\ParcelSizeSummary;

    // Sizes as the memo reads them: "1,500 + 2,000 m2". Empty when the application
    // captured none, and the sheet then reads exactly as it did before.
    $sizes = ParcelSizeSummary::of($record->plotSizes ?? []);

    // Where the land is. plot_extension_applications does carry `location`, so it leads,
    // and the district and LGA stand behind it.
    $situated = Str::upper((string) ($record->location ?: $record->district ?: $record->lga));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plot Extension Recommendation - KLAES GIS</title>
    <style>
        :root {
            --page-color: #fdf6e3;
            --accent-red: #cc0000;
        }
        * { box-sizing: border-box; }
        body {
            background-color: #525659;
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            display: flex;
            justify-content: center;
        }
        .a4-page {
            background-color: var(--page-color);
            width: 210mm;
            min-height: 297mm;
            display: flex;
            position: relative;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .left-sidebar { width: 60px; flex-shrink: 0; }
        .main-container { flex: 1; display: flex; flex-direction: column; padding: 20px 40px; }
        .header-block { height: 100px; margin-bottom: 5px; }
        .addressee { font-weight: bold; text-decoration: underline; margin-top: 15px; margin-bottom: 15px; font-size: 1.1em; }
        .body-paragraph { text-align: justify; line-height: 1.4; margin-bottom: 15px; }
        .point-block { margin-top: 15px; margin-bottom: 10px; font-weight: bold; }
        .signature-field-container { display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; }
        .sig-row { display: flex; justify-content: space-between; align-items: flex-end; gap: 30px; }
        .sig-item { display: flex; align-items: flex-end; width: 45%; }
        .line-label { font-weight: bold; white-space: nowrap; margin-right: 8px; }
        .input-line { border: none; background: transparent; border-bottom: 1px solid #000; font-size: 1em; font-family: inherit; flex-grow: 1; padding-bottom: 2px; }
        .approval-section { border-top: 1px solid black; padding-top: 15px; margin-top: 25px; padding-bottom: 20px; }
        .approval-section .sig-row { justify-content: flex-start; gap: 10px; }
        .approval-section .sig-item { width: auto; flex-grow: 1; }
        .approval-section .input-line { width: 150px; flex-grow: 0; }
        .red-tick { color: var(--accent-red); font-size: 1.4em; margin-left: 10px; vertical-align: middle; }
        .bold-caps { font-weight: bold; text-transform: uppercase; }
        .red-text { color: var(--accent-red); font-weight: bold; }
        @media print {
            body { background: none; margin: 0; padding: 0; }
            .a4-page { box-shadow: none; margin: 0; border: none; }
            @page { size: A4; margin: 0; }
            /* A logo that vanishes on paper is worse than none. */
            img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="a4-page">
        <div class="left-sidebar"></div>
        <div class="main-container">
            <div class="header-block"></div>
            <div class="addressee">PERMANENT SECRETARY.</div>
            <div class="body-paragraph">
                At Page 1 is an application for <span class="bold-caps">PLOT EXTENSION</span> over a piece of land situated <span class="bold-caps">{{ $situated }}</span> covered by Certificate of occupancy no. <span class="bold-caps">{{ Str::upper($record->file_no) }}</span> in favour of <span class="bold-caps">{{ Str::upper($record->applicant_name) }}.</span>
            </div>
            <div class="body-paragraph">
                The application seeks to extend the existing boundaries of the plot{{ $sizes['phrase'] }}. Verification at Cadastral Department reveals that the proposed extension is feasible and does not infringe on neighboring titles.
            </div>
            <div class="body-paragraph">
                In view of the above, you may kindly wish to recommend this application for Plot Extension to <span class="bold-caps">Honourable Commissioner</span> for approval of:
            </div>
            <div class="point-block">
                a.) Extension of the boundary of plot no. <span class="bold-caps">{{ Str::upper($record->plot_no) }}</span>{{ $sizes['phrase'] }} as per the attached plan, situated at {{ $situated }} in favor of {{ Str::upper($record->applicant_name) }}.
            </div>
            <div class="signature-field-container">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Name:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Counter Sign:</span> <input type="text" class="input-line"></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Rank:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Director Land:</span> <input type="text" class="input-line"></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <span class="red-text" style="border-bottom: 1px solid black; flex-grow: 1; min-width: 150px;"></span></div>
                </div>
            </div>
            <div class="addressee">HONOURABLE COMMISSIONER:</div>
            <div class="body-paragraph">The application is hereby recommended for your kind approval, please.</div>
            <div class="signature-field-container" style="margin-bottom: 10px;">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <span class="red-text" style="border-bottom: 1px solid black; width: 150px;"></span></div>
                </div>
                <div class="sig-row"><div class="sig-item"><span class="line-label">Permanent Secretary.</span></div></div>
            </div>
            <div class="approval-section">
                <div class="addressee" style="margin-top: 0;">PERMANENT SECRETARY.</div>
                <div class="body-paragraph">The application is hereby Approved/<span style="text-decoration: line-through;">Not Approved:</span> <span class="red-tick"></span></div>
                <div class="signature-field-container" style="margin-bottom: 10px;">
                    <div class="sig-row">
                        <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                        <div class="sig-item"><span class="line-label">Date:</span> <span class="red-text" style="border-bottom: 1px solid black; width: 150px;"></span></div>
                    </div>
                    <div class="sig-row"><div class="sig-item"><span class="line-label">Honourable Commissioner.</span></div></div>
                </div>
            </div>
            <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #000; padding-top: 10px; padding-bottom: 20px;">
                {{-- Absolute URLs, as on the duplex memo and the conveyance letter, and
                     for the same reason: these sheets are printed from hosts that are
                     not always this app. KLAES left, LAnd ADmin right. --}}
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" style="height: 58px; width: auto; object-fit: contain;">
                <img src="http://app.klaes.ng/assets/logo/Left_Logo.png" alt="LAnd ADmin Enterprise System" style="height: 58px; width: auto; object-fit: contain;">
            </div>
        </div>
    </div>
</body>
</html>

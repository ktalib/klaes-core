@php
    /**
     * Internal memo to the Permanent Secretary, following the ministry's
     * APPLICATIONS template (docs/templates/parcels_templates/APPLICATIONS_1.png).
     *
     * printApplication() and printAcknowledgement() both render this sheet, so the
     * label variables are defaulted here rather than assumed.
     */
    $landUse    = $landUseLabel ?? ($record->land_use ?: '');
    $newPurpose = $newPurposeLabel ?? ($record->purpose ?: '');
    $holder     = $record->applicant_name ?: '';
    $situatedAt = $record->location ?: trim(implode(', ', array_filter([$record->district ?? null, $record->lga ?? null])));
    $dots       = '..........................';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change of Purpose Application - KLAES GIS</title>
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
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .left-sidebar { width: 60px; flex-shrink: 0; }
        .main-container { flex: 1; display: flex; flex-direction: column; padding: 20px 40px; }
        /* Blank block: the memo is printed on the ministry's letterhead paper. */
        .header-block { height: 100px; margin-bottom: 5px; display: flex; justify-content: flex-end; align-items: flex-start; }
        .addressee { font-weight: bold; text-decoration: underline; margin-top: 15px; margin-bottom: 15px; font-size: 1.1em; }
        .body-paragraph { text-align: justify; line-height: 1.4; margin-bottom: 15px; }
        .dimension-list { margin: 0 0 15px 40px; line-height: 1.6; }
        .dimension-list div { font-weight: bold; }
        .point-block { margin-top: 15px; margin-bottom: 10px; }
        .point-block .lead { font-weight: bold; }
        .signature-field-container { display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; }
        .sig-row { display: flex; justify-content: space-between; align-items: flex-end; gap: 30px; }
        .sig-item { display: flex; align-items: flex-end; width: 45%; }
        .line-label { font-weight: bold; white-space: nowrap; margin-right: 8px; }
        .input-line { border: none; background: transparent; border-bottom: 1px solid #000; font-size: 1em; font-family: inherit; flex-grow: 1; padding-bottom: 2px; }
        .approval-section { border-top: 1px solid black; padding-top: 15px; margin-top: 25px; padding-bottom: 20px; }
        .approval-section .sig-row { justify-content: flex-start; gap: 10px; }
        .approval-section .sig-item { width: auto; flex-grow: 1; }
        .approval-section .input-line { width: 150px; flex-grow: 0; }
        .bold-caps { font-weight: bold; text-transform: uppercase; }
        .red-text { color: var(--accent-red); font-weight: bold; }
        .print-button {
            position: fixed; top: 16px; right: 16px; z-index: 50;
            background: #2563eb; color: #fff; border: none; cursor: pointer;
            padding: 8px 20px; border-radius: 8px; font-family: sans-serif;
            font-size: 10pt; font-weight: 600; box-shadow: 0 2px 6px rgba(0,0,0,0.4);
        }
        @media print {
            body { background: none; margin: 0; padding: 0; }
            .a4-page { box-shadow: none; margin: 0; border: none; }
            .print-button, .no-print { display: none !important; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-button no-print">Print</button>

    <div class="a4-page">
        <div class="left-sidebar"></div>
        <div class="main-container">
            <div class="header-block">
                <img src="{{ qr_data_uri('COP-' . $record->id, 110) }}"
                     alt="QR Code" style="width: 70px; height: 70px; object-fit: contain;">
            </div>

            <div class="addressee">PERMANENT SECRETARY.</div>

            <div class="body-paragraph">
                At page {{ $dots }} is an application for <span class="bold-caps">change of purpose</span>
                @if($landUse && $newPurpose)
                    from <span class="bold-caps">{{ $landUse }}</span> to <span class="bold-caps">{{ $newPurpose }}</span>
                @endif
                in respect of the property covered by Right of Occupancy no.
                <span class="bold-caps">{{ $record->file_no }}</span> over a piece of land situated at
                <span class="bold-caps">{{ $situatedAt }}</span>
                @if($record->plot_no) (Plot No. <span class="bold-caps">{{ $record->plot_no }}</span>) @endif
                by the title holder: <span class="bold-caps">{{ $holder }}</span>.
            </div>

            <div class="body-paragraph">
                The application was forwarded to the State Planning Authority for planning advice; consequently
                the Authority via its memo at page {{ $dots }} recommended the application for the change of
                purpose of the property
                @if($landUse && $newPurpose)
                    from <span class="bold-caps">{{ $landUse }}</span> to <span class="bold-caps">{{ $newPurpose }}</span>
                @endif
                in view of the fact that the property is independently accessible and conforms with the existing
                land use of the area.
            </div>

            <div class="body-paragraph" style="margin-bottom: 8px;">
                The recommended site plan
                @if($record->plan_no) No. <span class="bold-caps">{{ $record->plan_no }}</span> @endif
                at page {{ $dots }} and back cover has the following dimension:-
            </div>
            <div class="dimension-list">
                <div>A. {{ $dots }} m x {{ $dots }} m</div>
                <div>B. {{ $dots }} m x {{ $dots }} m</div>
            </div>

            <div class="body-paragraph">
                In view of the above, you may wish to recommend the following for the
                <span style="font-weight: bold;">Honorable Commissioner</span>:-
            </div>

            @php
                // One file, but a holding of several plots: the slip says how many,
                // because approving it approves a new number for each of them.
                $plots = max(1, (int) ($plotCount ?? 1));
                $isBatch = $plots > 1;
            @endphp

            <div class="point-block">
                I. <span class="lead">Approve</span> the change of purpose
                @if($landUse && $newPurpose)
                    from <span class="bold-caps">{{ $landUse }}</span> to <span class="bold-caps">{{ $newPurpose }}</span> use
                @endif
                in respect of Right of Occupancy No. <span class="bold-caps">{{ $record->file_no }}</span>
                @if($isBatch)
                    , covering <span class="bold-caps">{{ $plots }}</span> plots,
                @endif
                over a piece of land situated at <span class="bold-caps">{{ $situatedAt }}</span>
                in favour of <span class="bold-caps">{{ $holder }}</span>.

            </div>

            @if($isBatch)
                <div class="point-block">
                    II. <span class="lead">Endorse</span> the issuance of a new file number for each of the
                    <span class="bold-caps">{{ $plots }}</span> plots, in place of
                    <span class="bold-caps">{{ $record->file_no }}</span>, at back cover.
                </div>
            @elseif(!empty($newFileNo))
                <div class="point-block">
                    II. <span class="lead">Endorse</span> the issuance of the new file number
                    <span class="bold-caps">{{ $newFileNo }}</span> in place of
                    <span class="bold-caps">{{ $record->file_no }}</span> at back cover.
                </div>
            @endif

            <div class="signature-field-container">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Name:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Counter Sign:</span> <input type="text" class="input-line"></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Rank:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Director Land</span></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <span style="border-bottom: 1px solid black; flex-grow: 1; min-width: 150px;"></span></div>
                </div>
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Date:</span> <input type="text" class="input-line"></div>
                </div>
            </div>

            <div class="addressee">HONOURABLE COMMISSIONER</div>
            <div class="body-paragraph">The application is hereby recommended for your kind approval please.</div>
            <div class="signature-field-container" style="margin-bottom: 10px;">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <span style="border-bottom: 1px solid black; width: 150px;"></span></div>
                </div>
                <div class="sig-row"><div class="sig-item"><span class="line-label">Permanent Secretary</span></div></div>
            </div>

            <div class="approval-section">
                <div class="addressee" style="margin-top: 0;">PERMANENT SECRETARY</div>
                <div class="body-paragraph">The application is hereby Approved / Not Approved.</div>
                <div class="signature-field-container" style="margin-bottom: 10px;">
                    <div class="sig-row">
                        <div class="sig-item"><span class="line-label">Sign:</span> <input type="text" class="input-line"></div>
                        <div class="sig-item"><span class="line-label">Date:</span> <span style="border-bottom: 1px solid black; width: 150px;"></span></div>
                    </div>
                    <div class="sig-row"><div class="sig-item"><span class="line-label">Honourable Commissioner</span></div></div>
                </div>
            </div>

            <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #000; padding-top: 10px; padding-bottom: 20px;">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" style="width: 100px; height: 100px; object-fit: contain;">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="LAS Logo" style="width: 100px; height: 100px; object-fit: contain;">
            </div>
        </div>
    </div>
</body>
</html>

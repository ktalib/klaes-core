<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plot Extension Application - KLAES GIS</title>
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
            height: 297mm;
            display: flex;
            position: relative;
            overflow: hidden;
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
        @media print {
            body { background: none; margin: 0; padding: 0; }
            .a4-page { box-shadow: none; margin: 0; border: none; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="a4-page">
        <div class="left-sidebar"></div>
        <div class="main-container">
            <div class="header-block"></div>
            <div class="addressee">DIRECTOR LANDS.</div>
            <div class="body-paragraph">
                This is an application for <span class="bold-caps">PLOT EXTENSION</span> over a piece of land situated <span class="bold-caps">{{ Str::upper($record->location) }}</span> covered by Certificate of occupancy no. <span class="bold-caps">{{ Str::upper($record->file_no) }}</span> in favour of <span class="bold-caps">{{ Str::upper($record->applicant_name) }}.</span>
            </div>
            <div class="body-paragraph">
                The application seeks to extend the existing boundaries of the plot as described in the application.
            </div>
            <div class="body-paragraph">
                In view of the above, please process this application for Plot Extension and forward for necessary recommendation:
            </div>
            <div class="point-block">
                a.) Extension of plot no. <span class="bold-caps">{{ Str::upper($record->plot_no) }}</span> situated at {{ Str::upper($record->location) }} in favor of {{ Str::upper($record->applicant_name) }}.
            </div>
            <div class="signature-field-container">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Applicant Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <span class="red-text" style="border-bottom: 1px solid black; flex-grow: 1; min-width: 150px;"></span></div>
                </div>
            </div>
            <div class="addressee">FOR OFFICIAL USE:</div>
            <div class="body-paragraph">The application has been received and verified.</div>
            <div class="signature-field-container" style="margin-bottom: 10px;">
                <div class="sig-row">
                    <div class="sig-item"><span class="line-label">Officer Sign:</span> <input type="text" class="input-line"></div>
                    <div class="sig-item"><span class="line-label">Date:</span> <span class="red-text" style="border-bottom: 1px solid black; width: 150px;"></span></div>
                </div>
            </div>
            <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #000; padding-top: 10px; padding-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img src="{{ asset('assets/logo/klaes.png') }}" alt="KLAES Logo" style="width: 100px; height: 100px; object-fit: contain;">
                   
                </div>
                <img src="{{ asset('assets/logo/las.jpeg') }}" alt="LAS Logo" style="width: 100px; height: 100px; object-fit: contain;">
            </div>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kano State Land and Physical Planning Minutes</title>
    <style>
        :root {
            --primary-green: #2e7d32; /* Kept for red text reference, removed from lines */
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

        /* Empty Sidebar for Letterhead Margin - Green line removed */
        .left-sidebar {
            width: 60px;
            /* border-right: 2px solid var(--primary-green); */ /* REMOVED */
            flex-shrink: 0;
        }

        .main-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px 40px;
        }

        /* Empty Header Block for Letterhead Top - Green line removed */
        .header-block {
            height: 100px; 
            margin-bottom: 5px;
            /* border-bottom: 2px solid var(--primary-green); */ /* REMOVED */
        }

        .addressee {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 15px;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .body-paragraph {
            text-align: justify;
            line-height: 1.4;
            margin-bottom: 15px;
        }

        .point-block {
            margin-top: 15px;
            margin-bottom: 10px;
            font-weight: bold;
        }

        /* Signature Section Fix: Labels beside lines */
        .signature-field-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
        }

        .sig-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 30px;
        }

        .sig-item {
            display: flex;
            align-items: flex-end;
            width: 45%;
        }

        .line-label {
            font-weight: bold;
            white-space: nowrap;
            margin-right: 8px;
        }

        .input-line { 
            border: none; 
            background: transparent; 
            border-bottom: 1px dotted #000; 
            font-size: 1em; 
            font-family: inherit; 
            flex-grow: 1;
            padding-bottom: 2px;
        }

        /* Approval Section Fix: Matching Image Alignment */
        .approval-section {
            /* border-top: 3px solid var(--primary-green); */ /* REMOVED GREEN LINE */
            border-top: 1px solid black; /* ADDED BLACK THIN LINE */
            padding-top: 15px;
            margin-top: 25px; /* Added margin to separate, remove auto push */
            padding-bottom: 20px;
        }

        .approval-section .sig-row {
            justify-content: flex-start; /* Corrected to match the image, not spaced-out */
            gap: 10px;
        }

        .approval-section .sig-item {
            width: auto; /* Adjust width based on content, not fixed 45% */
            flex-grow: 1;
        }

        .approval-section .input-line {
            width: 150px; /* Specific length for input lines like the image */
            flex-grow: 0;
        }

        .red-tick {
            color: var(--accent-red);
            font-size: 1.4em;
            margin-left: 10px;
            vertical-align: middle;
        }

        .bold-caps { font-weight: bold; text-transform: uppercase; }
        .red-text { color: var(--accent-red); font-weight: bold; }

        .print-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-bottom: 5px;
        }
        .print-footer .footer-logo {
            width: auto;
            object-fit: contain;
        }
        .print-footer .footer-logo.left { height: 40px; }
        .print-footer .footer-logo.right { height: 32px; }

        @media print {
            .no-print { display: none !important; }
            body { background: none; margin: 0; padding: 0; }
            .a4-page { box-shadow: none; margin: 0; border: none; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
        <button onclick="window.print()" style="background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); display: flex; align-items: center; gap: 8px; font-family: sans-serif;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Document
        </button>
    </div>

    <div class="a4-page">
        <div class="left-sidebar"></div>

        <div class="main-container">
            <div class="header-block"></div>

            <div class="addressee">PERMANENT SECRETARY.</div>

            <div class="body-paragraph">
                At Page 1 is an application for temporary file over a piece of land situated <span class="bold-caps">{{ Str::upper($record->address) }}</span> covered by Certificate of occupancy no. <span class="bold-caps">{{ Str::upper($record->file_no) }}</span> in favour of <span class="bold-caps">{{ Str::upper($record->applicant_name) }}.</span>
            </div>

            <div class="body-paragraph">
                Verification at Cadastral Department revealed that the file in question is sent to Deeds Department for fees as per report at page 10 and index card at back cover. Further verification at Deeds Department also confirmed that the title under application is free from encumbrance as per report at page 10. The applicant has complied and fulfilled the requirement for temporary file application.
            </div>

            <div class="body-paragraph">
                In view of the above, you may kindly wish to recommend this application for Temporary file to <span class="bold-caps">Honourable Commissioner</span> for approval of:
            </div>

            <div class="point-block">
                a.) Commissioning of temporary file no. <span class="bold-caps">{{ Str::upper($record->file_no) }}(T)</span> over a piece of land situated {{ Str::upper($record->address) }} in favor of {{ Str::upper($record->applicant_name) }}.
            </div>

            <div class="signature-field-container">
                <div class="sig-row">
                    <div class="sig-item">
                        <span class="line-label">Name:</span> 
                        <input type="text" class="input-line" value="{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}">
                    </div>
                    <div class="sig-item">
                        <span class="line-label">Counter Sign:</span> 
                        <input type="text" class="input-line">
                    </div>
                </div>

                <div class="sig-row">
                    <div class="sig-item">
                        <span class="line-label">Rank:</span> 
                        <input type="text" class="input-line" value="">
                    </div>
                    <div class="sig-item">
                        <span class="line-label">Director Deeds:</span> 
                        <input type="text" class="input-line">
                    </div>
                </div>

                <div class="sig-row">
                    <div class="sig-item">
                        <span class="line-label">Sign:</span> 
                        <input type="text" class="input-line">
                    </div>
                    <div class="sig-item">
                        <span class="line-label">Date:</span> 
                        <span class="red-text" style="border-bottom: 1px solid black; flex-grow: 1; min-width: 150px;"></span>
                    </div>
                </div>
            </div>

            <div class="addressee">HONOURABLE COMMISSIONER:</div>
            
            <div class="body-paragraph">
                The application is hereby recommended for your kind approval, please.
            </div>

               <div class="signature-field-container" style="margin-bottom: 10px;">
                    <div class="sig-row">
                        <div class="sig-item">
                            <span class="line-label">Sign:</span> <input type="text" class="input-line">
                        </div>
                        <div class="sig-item">
                            <span class="line-label">Date:</span>
                            <span class="red-text" style="border-bottom: 1px solid black; width: 150px;"></span>
                        </div>
                    </div>
                    <div class="sig-row">
                        <div class="sig-item" style="justify-content: flex-start; gap: 5;">
                            <span class="line-label">Permanent Secretary.</span>
                        </div>
                    </div>
                </div>

            <div class="approval-section">
                <div class="addressee" style="margin-top: 0;">PERMANENT SECRETARY.</div>

                <div class="body-paragraph">
                    The application is hereby Approved/<span style="text-decoration: line-through;">Not Approved:</span> <span class="red-tick"></span>
                </div>

                <div class="signature-field-container" style="margin-bottom: 10px;">
                    <div class="sig-row">
                        <div class="sig-item">
                            <span class="line-label">Sign:</span> <input type="text" class="input-line">
                        </div>
                        <div class="sig-item">
                            <span class="line-label">Date:</span>
                            <span class="red-text" style="border-bottom: 1px solid black; width: 150px;"></span>
                        </div>
                    </div>
                    <div class="sig-row">
                        <div class="sig-item" style="justify-content: flex-start; gap: 0;">
                            <span class="line-label">Honourable Commissioner.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="print-footer">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Left Logo" class="footer-logo left">
                <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Right Logo" class="footer-logo right">
            </div>

        </div>
    </div>

</body>
</html>
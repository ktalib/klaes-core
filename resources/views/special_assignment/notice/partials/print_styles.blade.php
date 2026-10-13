{{-- Print styles for the notice letter (single and batch print). --}}
    <style>
        /* Printer margins as set by the office: top 0.92in, left 0.43in, right and bottom 0. */
        @page { size: A4; margin: 0.92in 0 0 0.43in; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #f2f2f2; font-family: "Times New Roman", Times, serif; color: #000; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; padding: 25mm; position: relative; }

        .header { position: relative; height: 68mm; }
        .address { position: absolute; top: 35mm; left: 0; width: 95mm; }
        .address-line { min-height: 7mm; border-bottom: 1px dotted #555; font-size: 12pt; line-height: 7mm; padding-left: 1mm;
                        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: uppercase; }
        .date { position: absolute; top: 8mm; right: 5mm; font-size: 12pt; white-space: nowrap; }
        .date-line { display: inline-block; min-width: 43mm; border-bottom: 1px dotted #555; margin-left: 3mm; vertical-align: middle; text-align: center; }
        .ref { position: absolute; top: 16mm; right: 5mm; font-size: 10pt; white-space: nowrap; }

        .salutation { font-size: 12pt; margin-top: 1mm; margin-bottom: 8mm; }

        .main-heading { text-align: center; margin-bottom: 7mm; }
        .main-heading .reminder { display: block; font-weight: bold; text-decoration: underline; font-size: 14pt; margin-bottom: 7mm; }
        .main-heading .subject { display: block; font-weight: bold; text-decoration: underline; font-size: 13pt; text-transform: uppercase; }

        .content { font-size: 12pt; line-height: 1.55; text-align: left; }
        .content p { margin: 0 0 8mm 0; }
        .content p:first-child { text-align: justify; }

        .closing { margin-top: 25mm; font-size: 12pt; line-height: 1.45; }
        .signature { margin-top: 13mm; font-size: 12pt; line-height: 1.3; }
        .signature .name { font-weight: bold; }
        .signature .for { font-weight: bold; }

        .no-print { text-align: center; padding: 12px; }
        .no-print button { padding: 9px 22px; font-size: 13px; font-weight: bold; cursor: pointer; background: rgb(186,191,12); color: #fff; border: 0; border-radius: 8px; }

        /* Batch print: one notice per sheet, a gap between them on screen. */
        .page + .page { margin-top: 10mm; }

        @media print {
            .page + .page { margin-top: 0; page-break-before: always; break-before: page; }
            body { background: #fff; }
            /* Fill what the @page margins leave rather than a full A4, or the
               signature block spills onto a second sheet. */
            .page { margin: 0; width: auto; min-height: 0; box-shadow: none; page-break-inside: avoid; }
            .no-print { display: none !important; }
        }
    </style>

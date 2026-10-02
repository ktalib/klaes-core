{{-- Print styles for the ST C of O front page.
     Extracted verbatim from print_cofo_front_page.blade.php so the front page and the
     complete (front + TDP) certificate share one stylesheet instead of drifting apart.
     Not edited -- if this needs changing, it changes for both documents, which is the point. --}}
        /* Page setup for printing - REDUCED MARGINS */
        /* https://i.ibb.co/NnQ8Gz3v/C-of-O-page-0001.jpg */
        @page {
            size: A4;
            margin: 0; /* Remove margins for full-page background */
        }

        /* Base document styling */
        body {
            font-family: "Times New Roman", serif;
            margin: 0;
            padding: 0;
            line-height: 1.1;
            font-size: 10pt;
            text-align: justify;
            width: 100%;
            box-sizing: border-box;
            background-color: white;
            /* Full page background */
            
            background-size: 100% 100%;
            background-repeat: no-repeat;
            background-position: center;
            min-height: 297mm; /* A4 height */
        }

        /* Main certificate container */
        .certificate-container {
            width: 210mm; /* A4 width */
            min-height: 297mm; /* A4 height */
            position: relative;
            margin: 0 auto;
            padding: 45mm 20mm 40mm 20mm; /* Maintain increased top padding */
            box-sizing: border-box;
        }

        /* Header section with title and file info */
        .header-container {
            display: flex;
            justify-content: center;
            position: relative;
            margin-bottom: 3mm; /* REDUCED from 8mm to 3mm */
            padding-top: 0; /* Removed padding */
        }

        /* Header text content */
        .header-content {
            text-align: center;
            flex: 1;
            max-width: 70%;
        }

        /* Main title styling */
        .header h1 {
            font-size: 14pt;
            margin: 0;
            text-transform: uppercase;
           
            font-weight: normal;
        }

        /* File number and type styling */
        .file-info {
            text-align: center;
            margin: 1mm 0; /* Increased margin */
            font-size: 10pt;
        }

        /* Passport photo section */
        .passport-section {
            position: absolute;
            right: -1mm;
            top: -18mm; /* Adjusted to match new padding */
            width: 25mm;
        }

        /* Passport photo placeholder */
        .passport-slot {
            width: 23mm;
            height: 30mm;
            position: relative;
            background-color: rgb(255, 255, 255);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            border: 0.5px solid #eaeaea;
        }

        /* Passport photo label */
        /* .passport-slot::after {
            position: absolute;
            bottom: -12px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 6pt;
            color: #666;
        } */

        /* Main content area */
        .main-content {
            margin-top: 2mm; /* REDUCED from 5mm to 2mm */
        }

        /* Certificate holder information */
        .certify-text {
            margin: 1mm 0; /* REDUCED from 2mm to 1mm */
            font-size: 10pt;
        }

        /* Holder name styling */
        .holder-name {
            font-weight: bold;
            margin-left: 5mm;
        }

        /* Holder address styling */
        .holder-address {
            margin-left: 5mm;
        }

        /* Terms and conditions section */
        .terms {
            margin: 2mm 0; /* Increased margin */
            font-size: 9pt;
        }

        /* Remove default margins */
        .terms p,
        .terms ol {
            margin: 0;
            padding: 0;
        }

        /* Ordered list indentation */
        .terms ol {
            padding-left: 4mm;
        }

        /* List item spacing */
        .terms ol li {
            margin-bottom: 0;
        }

        /* Date section styling */
        .date-section {
            font-weight: bold;
            text-align: center;
            margin: 2mm 0; /* Reduced margin to bring content closer */
            font-size: 9pt;
            width: 100%;
        }

        /* Signature section */
        .signature-section {
            text-align: right;
            margin: 2mm 0 0 auto; /* Reduced top margin to move content up */
            width: fit-content;
        }

        /* Signature line */
        .signature-line {
            border-top: 1px solid #000;
            width: 50mm;
            margin-left: auto;
            margin-top: 2mm; /* Increased margin */
        }

        /* Signature name */
        .signature-name {
            margin: 0;
            padding: 1mm;
            font-size: 10pt;
        }

        /* Signature title */
        .signature-title {
            margin: 0;
            padding: 0;
            font-style: italic;
            font-size: 9pt;
        }

        /* Print controls container */
        .controls {
            text-align: center;
            margin: 2mm 0;
            padding: 10px;
        }

        /* Print button styling */
        .print-btn {
            background-color: #004080;
            color: white;
            border: none;
            padding: 8px 16px;
            font-size: 11pt;
            cursor: pointer;
            margin-bottom: 5mm;
            border-radius: 4px;
        }

        .print-btn:hover {
            background-color: #003060;
        }

        /* Print-specific styles */
        @media print {
            .controls {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
                
                background-size: 100% 100%;
                background-repeat: no-repeat;
                background-position: center;
            }
            .certificate-container {
                height: 297mm;
                min-height: 297mm;
                padding: 45mm 20mm 40mm 20mm; /* Maintain increased top padding */
                margin: 0;
                box-shadow: none;
            }
            
            /* Additional print optimizations*/
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        /* Screen-specific styles */
        @media screen {
            body {
                background: white;
                padding: 20px;
            }
            .certificate-container { 
                /* box-shadow: 0 0 10px rgba(0,0,0,0.1); */
              
                background-size: 100% 100%;
                background-repeat: no-repeat;
                background-position: center;
            }
        }

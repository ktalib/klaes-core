<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compensation Valuation - Field Sheet</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        @media print {
            body { background-color: white; }
            .no-print { display: none; }
            @page {
                size: portrait;
                margin: 0;
            }
        }

        .container {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background-color: white;
            box-sizing: border-box;
            padding: 8mm;
            position: relative;
            overflow: hidden;
        }

        .header {
            text-align: center;
            margin-bottom: 5px;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            text-decoration: underline;
        }

        .project-info {
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 2px 0;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 0;
            height: 245mm; 
            border: 1px solid #000;
        }

        .quadrant {
            border: 1px solid #000;
            padding: 12px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .quadrant-header {
            margin-bottom: 8px;
        }

        .label {
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 3px;
            display: block;
        }

        .input-line {
            border-bottom: 1px solid #000;
            height: 22px;
            margin-bottom: 8px;
            width: 100%;
        }

        .description-area {
            flex-grow: 1;
            border: 1px dashed #eee;
            margin: 5px 0;
            position: relative;
        }
        
        .description-area::after {
            content: "SKETCH / FIELD NOTES";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #f9f9f9;
            font-size: 20px;
            font-weight: bold;
            z-index: 0;
        }

        .bottom-fields {
            margin-top: auto;
        }

        .field-row {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .field-label {
            min-width: 90px;
            font-weight: bold;
        }

        .field-line {
            flex-grow: 1;
            border-bottom: 1px solid #000;
            height: 18px;
        }

        .footer-info {
            position: absolute;
            bottom: 5mm;
            left: 8mm;
            right: 8mm;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #333;
            border-top: 1px solid #000;
            padding-top: 3px;
        }

        .no-print-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            color: white;
            font-size: 13px;
        }
        .btn-print { background: #0d9488; }
        .btn-back { background: #64748b; }

    </style>
</head>
<body>

<div class="no-print-bar no-print">
    <a href="{{ route('valuation-compensations.projects.index') }}" class="btn btn-back">Back</a>
    <button onclick="window.print()" class="btn btn-print">Print Field Sheet</button>
</div>

<div class="container">
    <div class="header">
        <h1>COMPENSATION VALUATION - FIELD SHEET</h1>
    </div>

    <div class="project-info">
        <span><strong>PROJECT:</strong> {{ $project->project_name }}</span>
        <span><strong>CODE:</strong> {{ $project->project_code }}</span>
        <span><strong>FILE NO:</strong> {{ $project->project_fileno }}</span>
    </div>

    <div class="grid-container">
        @for($i = 1; $i <= 4; $i++)
        <div class="quadrant">
            <div class="quadrant-header">
                <span class="label">Name of Owner</span>
                <div class="input-line"></div>
                
                <span class="label">Property Description</span>
                <div class="input-line"></div>
            </div>

            <div class="description-area"></div>

            <div class="bottom-fields">
                <div class="field-row">
                    <span class="field-label">Account Number</span>
                    <div class="field-line"></div>
                </div>
                <div class="field-row">
                    <span class="field-label">Bank Name</span>
                    <div class="field-line"></div>
                </div>
                <div class="field-row">
                    <span class="field-label">Phone Number</span>
                    <div class="field-line"></div>
                </div>
            </div>
            
            <div style="position: absolute; top: 8px; right: 12px; font-size: 10px; font-weight: bold; color: #000; border: 1px solid #000; padding: 1px 4px;">
                BOX {{ $i }}
            </div>
        </div>
        @endfor
    </div>

    <div class="footer-info">
       {{-- FOOTER LOGOS --}}
       <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Left Logo" style="width: 100px; height: 50px;">

    </div>
</div>

</body>
</html>

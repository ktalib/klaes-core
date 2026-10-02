<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }} - {{ $fileNumber }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f0f0;
        }

        .page {
            width: 210mm;
            height: 297mm;
            padding: 2mm 10mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .sheet {
            height: 148.5mm;
            padding-bottom: 2mm;
            padding-top: 2mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .header {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 4px;
            text-transform: uppercase;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        td {
            border: 1px solid black;
            padding: 4px 8px;
            font-size: 10px;
            height: 14px;
        }

        .label {
            width: 30%;
            font-weight: bold;
            background-color: #f9fafb;
        }

        .status-banner {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            margin: 8px 0;
            padding: 6px;
            border: 1px solid #1a1a1a;
            text-transform: uppercase;
        }

        .banner-missing { background: #fee2e2; color: #b91c1c; }
        .banner-refer   { background: #fef9c3; color: #92400e; }
        .banner-archive { background: #dcfce7; color: #166534; }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            padding-bottom: 2px;
        }

        .sig-box { width: 40%; text-align: center; }
        .sig-line { border-top: 1px solid #1a73e8; margin-top: 25px; margin-bottom: 2px; }
        .sig-text { font-size: 9px; line-height: 1.1; }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 5px;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .print-btn:hover { background: #b91c1c; }

        .cutoff-line {
            width: 100%;
            border-top: 1px dashed #6b7280;
            text-align: center;
            position: relative;
            margin-top: 10px;
            color: #6b7280;
            font-size: 10px;
        }

        .cutoff-line span {
            position: relative;
            top: -7px;
            background: white;
            padding: 0 10px;
        }

        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; }
            .print-btn { display: none; }
        }
    </style>
</head>

<body>

    <button class="print-btn" onclick="window.print()">Print Slip</button>

    <div class="page">
        <div class="sheet">
            <!-- Ministry document header -->
            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px;">
                <img src="http://app.klaes.ng/assets/logo/ministry2.png" alt="Ministry" style="height:58px; width:58px; object-fit:contain;">
                <div style="flex:1; text-align:center;">
                    <div style="font-size:13px; font-weight:bold; text-transform:uppercase; color:#000;">Kano State Government</div>
                    <div style="font-size:11px; font-weight:bold; color:#000;">Ministry of Land &amp; Physical Planning</div>
                </div>
                <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Coat of Arms" style="height:58px; width:58px; object-fit:contain;">
            </div>

            <div class="header">KLAES FILE TRACKING REQUEST SHEET</div>

            @php
                // The remark should reflect the action the slip represents, not the raw
                // lookup status. A "refer_registry" slip means SCB could not find the file
                // in archive/pool, so the front desk is told to refer to the original registry.
                $variantRemarks = [
                    'refer_registry' => 'Refer to Original Registry',
                ];
                $remarkLabel = $variantRemarks[$variant] ?? trim(str_replace('_', ' ', $statusLabel));
                $remark = $reason ? ($remarkLabel . ' — ' . $reason) : $remarkLabel;
            @endphp

            <table>
                <tr>
                    <td class="label">File Number</td>
                    <td style="font-weight: bold; font-size: 11px;">{{ $fileNumber }}</td>
                </tr>
                <tr>
                    <td class="label">File Title</td>
                    <td>{{ $fileTitle }}</td>
                </tr>
                <tr>
                    <td class="label">Registry</td>
                    <td>{{ $registry }}</td>
                </tr>
                <tr>
                    <td class="label">Location</td>
                    <td>{{ $location }}</td>
                </tr>
                <tr>
                    <td class="label" style="height: 50px; vertical-align: top;">Remark(s)</td>
                    <td style="vertical-align: top;">{{ $remark ?: '—' }}</td>
                </tr>
            </table>

            <div style="text-align: center; font-size: 11px; margin: 12px 0; color: #374151;">
                <strong>Generated by:</strong> {{ auth()->user()->name ?? 'System' }}
                <strong>on</strong> {{ now()->format('d/m/Y H:i') }}
            </div>

            <div class="signatures">
                <div class="sig-box">
                    <div class="sig-line" style="margin-top: 5px;"></div>
                    <div class="sig-text"><strong>File Searcher</strong><br>Date: ____________</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-text">Signature and Date<br><strong>Director Lands</strong></div>
                </div>
            </div>

            <div class="footer">
                @if(\Illuminate\Support\Facades\Storage::disk('public')->exists('upload/logo/logo.png'))
                    <img src="{{ asset('storage/upload/logo/logo.png') }}" alt="Left Logo" style="height: 48px; object-fit: contain;">
                @else
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Left Logo" style="height: 48px; object-fit: contain;">
                @endif
                <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Right Logo" style="height: 32px; object-fit: contain;">
            </div>
        </div>

        <div class="cutoff-line">
            <span>✂------------------ Cut Here ------------------✂</span>
        </div>
    </div>

</body>

</html>

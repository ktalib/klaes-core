<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLAES File Request Sheet - {{ $tracker->file_number }}</title>
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
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
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

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            padding-bottom: 2px;
        }

        .sig-box {
            width: 40%;
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid #1a73e8;
            margin-top: 25px;
            margin-bottom: 2px;
        }

        .sig-text {
            font-size: 9px;
            line-height: 1.1;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 5px;
        }

        @media print {
            body {
                background: none;
            }

            .page {
                margin: 0;
                box-shadow: none;
            }

            .print-btn {
                display: none;
            }
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
            transition: background 0.2s;
        }

        .print-btn:hover {
            background: #b91c1c;
        }

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
    </style>
</head>

<body>

    <button class="print-btn" onclick="window.print()">Print Request Sheet</button>

    <div class="page">
        <!-- SINGLE HALF-PAGE SHEET -->
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

            <table>
                <tr>
                    <td class="label">File Number</td>
                    <td style="font-weight: bold; font-size: 11px;">{{ $tracker->file_number }}</td>
                </tr>
                <tr>
                    <td class="label">File Title</td>
                    <td>{{ $tracker->file_title }}</td>
                </tr>
                <tr>
                    <td class="label">File Request Priority</td>
                    <td>
                        @php
                            $prio = strtoupper($tracker->priority ?? 'LOW');
                        @endphp
                        <span style="{{ $prio === 'HIGH' ? 'font-weight: bold; color: #dc2626;' : 'color: #9ca3af;' }}">High</span> / 
                        <span style="{{ $prio === 'MEDIUM' ? 'font-weight: bold; color: #d97706;' : 'color: #9ca3af;' }}">Medium</span> / 
                        <span style="{{ $prio === 'LOW' ? 'font-weight: bold; color: #16a34a;' : 'color: #9ca3af;' }}">Low</span>
                    </td>
                </tr>
                <tr>
                    <td class="label">File Request Type</td>
                    <td>
                        @php
                            $requestType = strtoupper($tracker->file_request_type ?? '');
                        @endphp
                        <span style="{{ $requestType === 'MANUAL' ? 'font-weight: bold; color: #1d4ed8;' : 'color: #9ca3af;' }}">Manual Request</span> /
                        <span style="{{ $requestType === 'SUBMITTED' ? 'font-weight: bold; color: #1d4ed8;' : 'color: #9ca3af;' }}">Submitted Request</span>
                    </td>
                </tr>
                <tr>
                    <td class="label">Registry (Origin)</td>
                    <td>{{ $tracker->origin_office_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Requester Department</td>
                    <td>{{ $tracker->department ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Requester Director</td>
                    <td>{{ $tracker->requester_director_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Requester Office</td>
                    <td>{{ $tracker->receiving_office_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">File Requester</td>
                    <td>
                        {{-- Requester's passport photo beside their name. Printed sheets
                             need an absolute URL, which UserPhoto already returns. --}}
                        <span style="display:inline-flex;align-items:center;gap:8px;">
                            @if(!empty($officerPhoto))
                                <img src="{{ $officerPhoto }}" alt=""
                                    style="width:44px;height:44px;border-radius:6px;object-fit:cover;border:1px solid #cbd5e1;">
                            @endif
                            <span>{{ $tracker->receiving_officer_name ?? '—' }}</span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="label">Officer's Rank</td>
                    <td>{{ $officerRank ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Date Requested</td>
                    <td>{{ $tracker->date_requested ? $tracker->date_requested->format('d/m/Y H:i') : ($tracker->date_created ? $tracker->date_created->format('d/m/Y H:i') : '—') }}</td>
                </tr>
                <tr>
                    <td class="label">Date Logged</td>
                    <td>{{ $tracker->assignment_accepted_at ? $tracker->assignment_accepted_at->format('d/m/Y H:i') : ($tracker->created_at ? $tracker->created_at->format('d/m/Y H:i') : '—') }}</td>
                </tr>
                <tr>
                    <td class="label" style="height: 50px; vertical-align: top;">Remark(s)</td>
                    <td style="vertical-align: top;">{{ $tracker->notes ?? $tracker->description ?? '—' }}</td>
                </tr>
            </table>

            <!-- Position A: Generated by Section -->
            <div style="text-align: center; font-size: 11px; margin-top: 15px; margin-bottom: 15px; color: #374151;">
                <strong>Generated by:</strong> {{ $tracker->created_by_name ?? 'System' }} <strong>on</strong> {{ $tracker->date_created ? $tracker->date_created->format('d/m/Y H:i') : ($tracker->created_at ? $tracker->created_at->format('d/m/Y H:i') : '') }}
            </div>

            <div class="signatures">
                <div class="sig-box">
                    <div style="font-weight: bold; font-size: 10px; min-height: 14px; text-transform: uppercase;">
                        {{ $tracker->receiving_officer_name ?? '—' }}
                    </div>
                    <div class="sig-line" style="margin-top: 5px;"></div>
                    <div class="sig-text">
                        <strong>Requester</strong><br>
                        Date: {{ $tracker->date_created ? $tracker->date_created->format('d/m/Y H:i') : ($tracker->created_at ? $tracker->created_at->format('d/m/Y H:i') : '') }}
                    </div>
                </div>
                @php
                    $sigModule = strtolower($tracker->module ?? '');
                    $sigDirector = in_array($sigModule, ['kangis', 'new_kangis'], true)
                        ? 'Director GIS'
                        : ($sigModule === 'sltr' ? 'Director SLTR' : 'Director Lands');
                @endphp
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-text">
                        Signature and Date<br>
                        <strong>{{ $sigDirector }}</strong>
                    </div>
                </div>
            </div>

            <div class="footer">
                <!-- Left Logo -->
                @if(\Illuminate\Support\Facades\Storage::disk('public')->exists('upload/logo/logo.png'))
                    <img src="{{ asset('storage/upload/logo/logo.png') }}" alt="Left Logo" style="height: 48px; object-fit: contain;">
                @else
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Left Logo" style="height: 48px; object-fit: contain;">
                @endif

                <!-- Right Logo -->
                <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Right Logo" style="height: 32px; object-fit: contain;">
            </div>
        </div>

        <!-- Cutoff Line -->
        <div class="cutoff-line">
            <span>✂------------------ Cut Here ------------------✂</span>
        </div>
    </div>

</body>

</html>

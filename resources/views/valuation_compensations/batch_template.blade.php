<!DOCTYPE html>
<html lang="en">
    {{-- ikkdt --}}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Compensation Valuation - {{ $project->project_name ?? 'Project' }}</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 1400px;
            background-color: white;
            padding: 40px;
            border: 2px solid #00008B;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .logo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .logo-header img {
            height: 100px;
            object-contain: contain;
        }

        h1 {
            text-align: center;
            text-decoration: underline;
            font-size: 28px;
            margin-top: 10px;
            margin-bottom: 25px;
            text-transform: uppercase;
        }

        .header-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            line-height: 1.8;
            font-size: 16px;
        }

        .underline-field {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 150px;
            padding-left: 5px;
            font-weight: bold;
        }

        .location-banner {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            text-transform: uppercase;
            border-top: 2px solid rgba(255, 0, 0, 0);
            border-bottom: 2px solid rgba(255, 0, 0, 0);
            padding: 15px 0;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 40px;
        }

        th, td {
            border: 1px solid black;
            padding: 8px 4px;
            text-align: center;
            font-size: 12px;
            word-wrap: break-word;
        }

        th {
            background-color: #f8fafc;
            height: 40px;
            text-transform: uppercase;
            font-size: 10px;
        }

        .footer-signatures {
            margin-top: 40px;
        }

        .sig-row {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .sig-line {
            flex: 1;
            border-top: 1px solid black;
            padding-top: 8px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 12px;
        }

        .perm-sec-wrap {
            display: flex;
            justify-content: center;
        }

        .logo-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .logo-footer img {
            height: 40px;
            width: auto;
            max-width: 120px;
            object-contain: contain;
        }

        .generation-info {
            font-size: 10px;
            color: #666;
            margin-top: 10px;
            text-align: center;
            font-style: italic;
        }

        .data-table {
            /* Nudge the itemized table down so the first sheet's content sits
               lower and the empty band below it is reduced. Only affects the
               space before the first row (sheet 1); the header that repeats on
               later sheets stays flush at the top. */
            margin-top: 60px;
            margin-bottom: 0;
        }

        /* Totals + signatures share the main table as one closing tbody so the
           column headers repeat above them on whichever sheet they land on.
           Kept together so the block never splits across two sheets. */
        .closing-block {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Blank row that leaves signing room above the signature lines. */
        .sig-spacer td {
            border: none;
            height: 45px;
        }

        .sig-cell {
            border: none;
            border-top: 1px solid #000;
            padding-top: 8px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 12px;
            vertical-align: bottom;
        }

        .sig-gap {
            border: none;
        }

        .footer-cell {
            border: none;
            vertical-align: middle;
            padding-top: 20px;
        }

        .footer-logo {
            height: 40px;
            width: auto;
            max-width: 120px;
            object-fit: contain;
        }

        /* Invisible spacer row used to force a page break after a fixed
           number of rows. Renders as a zero-height, borderless row on
           screen and triggers a new sheet when printing. */
        .page-break-row td {
            border: none !important;
            padding: 0 !important;
            height: 0 !important;
            line-height: 0;
            font-size: 0;
        }

        /* PRINT SETTINGS */
        @media print {
            @page {
                size: landscape;
                margin: 0.5cm;
            }

            body {
                background-color: white;
                padding: 0;
            }

            /* NOTE: the container must stay a normal block in print.
               A flex container is treated as one unbreakable box, so
               page-break rules inside the table would be ignored and
               nothing would flow onto a second sheet. */
            .container {
                width: 100%;
                max-width: none;
                box-shadow: none;
                border: 2px solid #00008B;
                padding: 15px;
                display: block;
            }

            .footer-wrap {
                padding-top: 10px;
                page-break-inside: avoid;
            }

            /* Tighten vertical spacing in print so a short report keeps
               the table and signatures together on one sheet. */
            table {
                margin-bottom: 15px;
            }
            .footer-signatures {
                margin-top: 20px;
            }
            .logo-footer {
                margin-top: 20px;
                padding-top: 10px;
            }

            .no-print {
                display: none;
            }

            /* Repeat the column headers at the top of every printed sheet */
            thead {
                display: table-header-group;
            }

            /* Never split a single row across two sheets */
            tbody tr {
                page-break-inside: avoid;
            }

            /* Force a new sheet after the fixed row count */
            .page-break-row {
                page-break-after: always;
                break-after: page;
            }

            /* Keep group headers with the rows that follow them */
            .grp-header {
                page-break-after: avoid;
            }

            /* Keep the closing totals together on one sheet */
            .total-row {
                page-break-inside: avoid;
            }
            .grand-total-row {
                page-break-before: avoid;
            }
        }

        .no-print-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
             display: none;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            items-center: center;
            gap: 8px;
            border: none;
            transition: all 0.2s;
        }

        .btn-print { background: #0d9488; color: white; }
        .btn-back { background: #64748b; color: white; }
    </style>
</head>
<body>

<div class="no-print-bar no-print">
    <a href="{{ route('valuation-compensations.index') }}" class="btn btn-back">
        <span>Back to List</span>
    </a>
    <button onclick="window.print();" class="btn btn-print">
        <span>Print Batch Report</span>
    </button>
</div>

<div class="container">
    <div class="logo-header">
        <img src="http://app.klaes.ng/assets/logo/ministry2.png" alt="Header Left">
         <h1>COMPENSATION VALUATION ({{ $project->project_fileno ?? 'N/A' }})</h1>
        <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Header Right">
    </div>

   

    <div class="header-meta">
        <div>
            @php
                $addressedTo = ($project && $project->addressed_to) 
                    ? $project->addressed_to 
                    : "The Honourable Commissioner\nMinistry of Land and Physical\nPlanning, Kano State";
                $addrLines = explode("\n", $addressedTo);
            @endphp
            To: <span class="underline-field">{{ $addrLines[0] ?? '' }}</span><br>
            @foreach(array_slice($addrLines, 1) as $line)
                <span style="margin-left: 25px;"></span><span class="underline-field">{{ $line }}</span><br>
            @endforeach
        </div>
        <div style="text-align: right;">
            Our Ref: <span class="underline-field">{{ $project->our_reference ?? ($project->project_fileno ?? 'N/A') }}</span><br>
            Your Ref: <span class="underline-field">{{ $project->your_reference ?? 'N/A' }}</span><br>
            Date: <span class="underline-field">{{ now()->format('jS F, Y') }}</span>
        </div>
    </div>

    <div class="location-banner">
        @php
            $projLocation = "";
            if ($project->project_name) $projLocation .= $project->project_name;
            
            $addr = "";
            if ($project->street) $addr .= $project->street;
            if ($project->district) $addr .= ($addr ? ", " : "") . $project->district;
            if ($project->lga) $addr .= ($addr ? ", " : "") . $project->lga;
            if ($project->state) $addr .= ($addr ? ", " : "") . $project->state . " State";
            
            if ($addr) {
                $projLocation .= ($projLocation ? " - " : "") . $addr;
            }
        @endphp
        LOCATION SCOPE: <span style="text-decoration: underline;">{{ $projLocation ?: ($records->first()->location ?? 'N/A') }}</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">S/N</th>
                <th style="width: 14%;">Name of Owner</th>
                <th style="width: 11%;">Type of Building</th>
                <th style="width: 6%;">No. of Building</th>
                <th style="width: 7%;">Area Covered in M<sup>2</sup></th>
                <th style="width: 8%;">Rate of Cost ₦</th>
                <th style="width: 12%;">Amount of Compensation ₦</th>
                <th style="width: 9%;">Bank Name</th>
                <th style="width: 9%;">Account Number</th>
                <th style="width: 8%;">Phone Number</th>
                <th style="width: 6%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php
                $projectWorkers = $project->workers()->with('user')->get()->keyBy('worker_code');

                // Running worker counter -> letters A, B, C ... (Excel-style beyond Z).
                $workerSeq = 0;
                $numToLetters = function ($n) {
                    $s = '';
                    while ($n > 0) { $n--; $s = chr(65 + ($n % 26)) . $s; $n = intdiv($n, 26); }
                    return $s;
                };

                // Group by sub-project first
                $recordsBySubProject = $records->groupBy('sub_project_id');
                $totalValuation = 0;

                // Fixed number of table rows per printed sheet. Change this
                // one value to fit more/fewer rows on each page.
                $rowsPerPage   = $rowsPerPage ?? 7;
                $printRowCount = 0;
            @endphp

            @foreach($recordsBySubProject as $subProjectId => $subProjectRecords)
                @php
                    $subProject = $subProjectRecords->first()->subProject;
                    $subProjectName = $subProject ? $subProject->name : 'General';
                    $subProjectTotal = 0;
                @endphp

                <!-- Sub-Project Header Row (only when a sub-project is set) -->
                @if($subProject)
                <tr class="grp-header" style="border-top: 1px solid #000;">
                    <td colspan="1" style="font-weight: bold;"></td>
                    <td colspan="10" style="text-align: left; padding-left: 15px; font-weight: bold; text-transform: uppercase; background-color: #f8fafc;">
                        {{ $subProjectName }}
                    </td>
                </tr>
                @php $printRowCount++; @endphp
                @endif

                @php
                    // Group by worker within this sub-project
                    $recordsByWorker = $subProjectRecords->groupBy('worker_id');
                @endphp

                @foreach($recordsByWorker as $workerId => $workerRecords)
                    @php
                        $worker = $workerRecords->first()->worker;
                        $workerName = ($worker && $worker->user) ? $worker->user->first_name : 'Unknown';
                        $workerCode = $worker ? $worker->worker_code : 'N/A';
                        $workerTotal = 0;

                        // Worker letter (A, B, C ...) and per-worker owner counter (A1, A2 ...).
                        $workerSeq++;
                        $workerLetter = $numToLetters($workerSeq);
                        $ownerSeq = 0;
                    @endphp

                    <!-- Worker Header Row -->
                    <tr class="grp-header" style="border-bottom: 1px solid #000;">
                        <td colspan="1" style="font-weight: bold; text-align: center;">{{ $workerLetter }}</td>
                        <td colspan="10" style="text-align: left; padding-left: 15px; font-weight: bold; text-transform: uppercase; font-size: 10px; color: #475569;">
                            WORKER: {{ $workerName }} ({{ $workerCode }})
                        </td>
                    </tr>
                    @php $printRowCount++; @endphp

                    @php
                        // Group by owner within this worker
                        $recordsByOwner = $workerRecords->groupBy('owner_name');
                    @endphp

                    @foreach($recordsByOwner as $ownerName => $ownerRecords)
                        @php
                            $ownerSubTotal = 0;
                            $ownerRecordCount = count($ownerRecords);
                        @endphp

                        @foreach($ownerRecords as $record)
                            @php
                                // Parse compensated_items — supports JSON (new) and legacy string (old)
                                $buildings = [];
                                $subItems = [];
                                $isJsonFormat = false;
                                if ($record->compensated_items) {
                                    $decoded = json_decode($record->compensated_items, true);
                                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['buildings'])) {
                                        $isJsonFormat = true;
                                        $buildings = $decoded['buildings'] ?? [];
                                        $subItems  = $decoded['items'] ?? [];
                                    } else {
                                        // Legacy string format
                                        $legacyItems = explode(', ', $record->compensated_items);
                                        foreach ($legacyItems as $itemStr) {
                                            if (strpos($itemStr, '[') === 0) continue;
                                            if (strpos($itemStr, ' (₦') !== false) {
                                                $parts = explode(' (₦', $itemStr);
                                                $name  = trim($parts[0]);
                                                $amt   = (float) str_replace([')', ','], '', $parts[1]);
                                                $subItems[] = ['name' => $name, 'amount' => $amt];
                                            }
                                        }
                                    }
                                }

                                $itemsSum = collect($subItems)->sum(fn ($s) => (float) ($s['amount'] ?? 0));

                                // Record total = buildings + items for the JSON format (compensation_amount
                                // there is the buildings portion only). Legacy records keep the stored
                                // compensation_amount, which already represents the full total.
                                if ($isJsonFormat) {
                                    $buildingsSum = collect($buildings)->sum(fn ($b) => (float) ($b['amount'] ?? 0));
                                    $recordTotal = $buildingsSum + $itemsSum;
                                } else {
                                    $recordTotal = (float) $record->compensation_amount;
                                }
                                $ownerSubTotal += $recordTotal;

                                $mainAmount = max(0, (float) $record->compensation_amount - $itemsSum);
                            @endphp

                            @php
                                // Build the ordered list of rows to render for this record.
                                // Rule: a building line is only shown when its building type
                                // is entered. Compensated items are always shown. The owner's
                                // identity (S/N, name, account, phone, remarks) is attached to
                                // whichever row ends up first, so it is never lost even when the
                                // building row is skipped.
                                $displayRows = [];

                                if (count($buildings) > 0) {
                                    foreach ($buildings as $building) {
                                        if (trim($building['building_type'] ?? '') === '') continue;
                                        $displayRows[] = [
                                            'kind'   => 'building',
                                            'type'   => $building['building_type'],
                                            'count'  => 1,
                                            'area'   => ($building['area'] ?? 0) > 0 ? number_format($building['area'], 2) : 'Allow',
                                            'rate'   => number_format($building['rate'] ?? 0, 2),
                                            'amount' => number_format($building['amount'] ?? 0, 2),
                                        ];
                                    }
                                } elseif (trim($record->building_type ?? '') !== '') {
                                    $displayRows[] = [
                                        'kind'   => 'building',
                                        'type'   => $record->building_type,
                                        'count'  => $record->building_count,
                                        'area'   => $record->area_covered > 0 ? number_format($record->area_covered, 2) : 'Allow',
                                        'rate'   => number_format($record->rate_of_cost, 2),
                                        'amount' => number_format($mainAmount, 2),
                                    ];
                                }

                                foreach ($subItems as $sub) {
                                    // Show the entered measurement (area m² / volume m³, or linear
                                    // length) when available; only fall back to "Allow" for older
                                    // records that never stored measurements.
                                    $itemMeasure = (float) ($sub['measure'] ?? 0);
                                    $itemLength  = (float) ($sub['length'] ?? 0);
                                    $itemRate    = (float) ($sub['rate'] ?? 0);

                                    if ($itemMeasure > 0) {
                                        $itemArea = number_format($itemMeasure, 2);
                                    } elseif ($itemLength > 0) {
                                        $itemArea = number_format($itemLength, 2);
                                    } else {
                                        $itemArea = 'Allow';
                                    }

                                    $displayRows[] = [
                                        'kind'   => 'item',
                                        'type'   => $sub['name'],
                                        'count'  => 1,
                                        'area'   => $itemArea,
                                        'rate'   => $itemRate > 0 ? number_format($itemRate, 2) : number_format($sub['amount'], 2),
                                        'amount' => number_format($sub['amount'], 2),
                                    ];
                                }
                            @endphp

                            @foreach($displayRows as $rIdx => $drow)
                                @php
                                    $isFirst = ($rIdx === 0);
                                    $isItem  = ($drow['kind'] === 'item');
                                    // The first visible row acts as the owner's main row; any
                                    // following row (extra building or item) is bulleted/indented.
                                    $bulleted = !$isFirst;
                                @endphp
                                <tr @if($isItem && !$isFirst) style="font-size: 9px; color: #444; font-style: italic;" @else style="font-size: 9px;" @endif>
                                    <td style="font-size: 9px;">{{ $isFirst ? $workerLetter . (++$ownerSeq) : '' }}</td>
                                    <td style="text-align: left; padding-left: 8px; font-size: 9px;">{{ $isFirst ? $record->owner_name : '' }}</td>
                                    <td style="text-align: left; padding-left: {{ $bulleted ? '20px' : '8px' }}; font-size: 9px;">{{ $bulleted ? '• ' : '' }}{{ $drow['type'] }}</td>
                                    <td style="font-size: 9px;">{{ $drow['count'] }}</td>
                                    <td style="font-size: 9px;">{{ $drow['area'] }}</td>
                                    <td style="text-align: right; padding-right: 8px; font-size: 9px;">{{ $drow['rate'] }}</td>
                                    <td style="text-align: right; padding-right: 8px; font-size: 9px;">{{ $drow['amount'] }}</td>
                                    <td style="text-align: left; padding-left: 8px; font-size: 9px;">{{ $isFirst ? $record->bank_name : '' }}</td>
                                    <td style="font-size: 9px;">{{ $isFirst ? $record->account_number : '' }}</td>
                                    <td style="font-size: 9px;">{{ $isFirst ? $record->phone_number : '' }}</td>
                                    <td style="font-size: 9px; text-align: left;">{{ $isFirst ? Str::limit($record->remarks, 50) : '' }}</td>
                                </tr>
                                @php $printRowCount++; @endphp
                                @if($printRowCount % $rowsPerPage === 0)
                                    <tr class="page-break-row"><td colspan="11"></td></tr>
                                @endif
                            @endforeach

                            <!-- Owner Total Row -->
                            @if($ownerRecordCount > 1 || count($subItems) > 0)
                            <tr>
                                <td colspan="6" style="text-align: right; padding-right: 15px; font-size: 9px; color: #555;">
                                    Sub Total {{ $ownerName }}@if($subProject) ({{ $subProjectName }})@endif
                                </td>
                                <td style="text-align: right; padding-right: 8px; border-top: 1px solid #999; font-size: 9px;">
                                    {{ number_format($ownerSubTotal, 2) }}
                                </td>
                                <td colspan="4"></td>
                            </tr>
                            @php $printRowCount++; @endphp
                            @if($printRowCount % $rowsPerPage === 0)
                                <tr class="page-break-row"><td colspan="11"></td></tr>
                            @endif
                            @endif
                        @endforeach
                        
                        @php $workerTotal += $ownerSubTotal; @endphp
                    @endforeach

                    <!-- Worker Sub-total row -->
                    @if(count($recordsByWorker) > 1)
                    <tr style="border-top: 1px solid #000; background-color: #f1f5f9;">
                        <td colspan="6" style="text-align: right; padding-right: 15px; height: 30px; text-transform: uppercase; font-size: 10px;">
                            Sub Total {{ $workerName }} ({{ count($recordsByOwner) }})
                        </td>
                        <td style="text-align: right; padding-right: 8px; font-size: 9px; border-bottom: 1px solid #000;">
                            {{ number_format($workerTotal, 2) }}
                        </td>
                        <td colspan="4"></td>
                    </tr>
                    @php $printRowCount++; @endphp
                    @if($printRowCount % $rowsPerPage === 0)
                        <tr class="page-break-row"><td colspan="11"></td></tr>
                    @endif
                    @endif

                    @php $subProjectTotal += $workerTotal; @endphp
                @endforeach

                <!-- Sub-Project Sub-total row (only when a sub-project is set) -->
                @if($subProject)
                <tr style="border-top: 1px solid #000;">
                    <td colspan="6" style="text-align: right; padding-right: 15px; font-size: 9px; color: #555; font-weight: normal;">
                        Sub Total {{ $subProjectName }}
                    </td>
                    <td style="text-align: right; padding-right: 8px; font-size: 9px; font-weight: normal;">
                        {{ number_format($subProjectTotal, 2) }}
                    </td>
                    <td colspan="4"></td>
                </tr>
                @php $printRowCount++; @endphp
                @if($printRowCount % $rowsPerPage === 0)
                    <tr class="page-break-row"><td colspan="11"></td></tr>
                @endif
                @endif

                @php $totalValuation += $subProjectTotal; @endphp
            @endforeach
            
            @php
                // Grand-total maths, computed once all rows have been summed.
                $percent = $customPercentage ?? ($project->apply_percentage ?? 0);
                $appliedAmount = ($totalValuation * $percent) / 100;
                $finalTotal = $totalValuation + $appliedAmount;
            @endphp

            <!-- Sub Total stays in the main tbody so it prints on the same sheet
                 as the itemized rows (bottom of sheet 1), rather than moving to
                 the next sheet with the closing block. -->
            <tr class="total-row" style="border-top: 1px solid #000;">
                <td colspan="6" style="text-align: right; padding-right: 15px; font-size: 9px; color: #555; font-weight: normal;">Sub Total</td>
                <td style="text-align: right; padding-right: 8px; font-size: 9px; font-weight: normal;">{{ number_format($totalValuation, 2) }}</td>
                <td colspan="4"></td>
            </tr>
        </tbody>

        <!-- Closing block: percent + Grand Total + signatures live inside the
             MAIN table (as a second tbody) rather than a separate table, so the
             column headers (thead, which repeats on every printed sheet) sit
             above them too. page-break-inside: avoid keeps the block together,
             so when it can't fit it moves to the next sheet as a unit. -->
        <tbody class="closing-block">
            @if($percent > 0)
            <tr class="total-row" style="border-top: 1px solid #000;">
                <td colspan="6" style="text-align: right; padding-right: 15px; font-size: 9px; color: #555; font-weight: normal;">{{ $percent }}%</td>
                <td style="text-align: right; padding-right: 8px; font-size: 9px; font-weight: normal;">{{ number_format($appliedAmount, 2) }}</td>
                <td colspan="4"></td>
            </tr>
            @endif

            <tr class="total-row grand-total-row" style="font-weight: 900; border: 2.16px solid #000;">
                <td colspan="6" style="text-align: right; padding-right: 15px; height: 22.5px; font-size: 12px; font-weight: 900;">Grand Total</td>
                <td style="text-align: right; padding-right: 8px; font-size: 14.48px; font-weight: 900; white-space: nowrap;">₦{{ number_format($finalTotal, 2) }}</td>
                <td colspan="4"></td>
            </tr>

            <!-- Signature lines (spacer row leaves room to sign above each line) -->
            <tr class="sig-spacer"><td colspan="11"></td></tr>
            <tr>
                <td colspan="3" class="sig-cell">Head of Valuation</td>
                <td colspan="1" class="sig-gap"></td>
                <td colspan="3" class="sig-cell">Director Deeds</td>
                <td colspan="1" class="sig-gap"></td>
                <td colspan="3" class="sig-cell">Permanent Secretary</td>
            </tr>

            <!-- Footer logos + generation info -->
            <tr>
                <td colspan="4" class="footer-cell" style="text-align: left;">
                    <img class="footer-logo" src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Footer Left">
                </td>
                <td colspan="3" class="footer-cell">
                    <div class="generation-info">
                        Generated by {{ Auth::user()->first_name ?? 'Klaes' }} {{ Auth::user()->last_name ?? 'Admin' }}
                        at {{ now()->format('g:i A') }} & {{ now()->format('d/m/Y') }}
                    </div>
                </td>
                <td colspan="4" class="footer-cell" style="text-align: right;">
                    <img class="footer-logo" src="http://app.klaes.ng/assets/logo/las.jpg" alt="Footer Right">
                </td>
            </tr>
        </tbody>
    </table>
</div>

</body>
</html>

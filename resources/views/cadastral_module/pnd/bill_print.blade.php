{{--
    The official sheet "Right of Occupancy - Cadastral Fees and Area"
    (docs/templates/cadastral/Cadastral-Fees-and-Area-RightofOccupancy.html),
    copied with its CSS, page size and layout, the blanks filled.

    The grid's three empty columns are read as Quantity | Unit rate | Amount:
    the label already names the rate, so the three columns are what is
    multiplied and what it comes to. The Total row fills Amount only.

    Every line prints from the bill's own snapshot (rate, quantity, amount), so
    a reprint matches the issued copy after the rates change. Only the two
    schedules at the foot are read live from Configurable Entries -> Cadastral,
    with the row and band this bill was charged at highlighted.

    Standalone page: no layout, so it prints alone.
--}}
@php
    $n = function ($v) {
        if ($v === null) return '';
        $v = (float) $v;
        return number_format($v, floor($v) == $v ? 0 : 2);
    };
    $m = fn ($v) => $v === null ? '' : number_format((float) $v, 2);
    $rate = fn (string $key, string $paper) => $lines[$key]['rate'] !== null ? 'N' . $n($lines[$key]['rate']) : $paper;
    $areaHa = $bill->area_ha !== null ? rtrim(rtrim(number_format((float) $bill->area_ha, 4), '0'), '.') : null;
    $usedRow = $bill->schedule_row_ha !== null ? round((float) $bill->schedule_row_ha, 2) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Right of Occupancy - Cadastral Fees and Area — {{ $bill->bill_ref }}</title>
    <style>
        /* Global Page Setup for Single-Page Printing */
        @page {
            size: A4;
            margin: 10mm 15mm;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .document-container {
            background-color: #fff;
            max-width: 750px;
            margin: 0 auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            page-break-inside: avoid; /* Prevents splitting into 2 pages */
        }

        /* Header Layout */
        .header-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 12px;
            line-height: 1.3;
        }

        .header-left {
            width: 50%;
        }

        .header-right {
            width: 40%;
            text-align: left;
        }

        .line-field {
            border-bottom: 1px solid #000;
            display: inline-block;
        }

        /* Title Style */
        .document-title {
            text-align: center;
            color: green;
            font-weight: bold;
            font-size: 16px;
            margin: 12px 0;
            line-height: 1.2;
        }

        /* Grid Table Breakdown */
        .fee-breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .fee-breakdown-table td {
            border: 1px solid green;
            padding: 4px 6px;
            height: 18px;
            font-size: 12px;
        }

        .fee-breakdown-table td.label-col {
            color: green;
            font-weight: bold;
            width: 60%;
        }

        .fee-breakdown-table td.empty-col {
            width: 13.33%;
        }

        /* Info Text and Schedules */
        .info-text {
            font-size: 12px;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .schedule-title {
            font-weight: bold;
            font-size: 12px;
            margin-top: 5px;
        }

        .schedule-list {
            font-size: 11px;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .flex-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .signature-space {
            text-align: right;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Compact Schedule of Area Fee Table */
        .area-fee-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            text-align: left;
        }

        .area-fee-table th, .area-fee-table td {
            border: 1px solid green;
            padding: 2px 5px;
            line-height: 1.1;
        }

        .area-fee-table th {
            color: green;
            font-weight: bold;
            font-size: 10px;
            background-color: #fff;
        }

        /* Print Override */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .document-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
        }

        /* KLAES additions: filled values, the row charged, the screen toolbar. */
        .fee-breakdown-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .fee-breakdown-table td.qty { text-align: center; font-size: 11px; }
        .area-fee-table tr.row-used td { background-color: #e3f2e3; font-weight: bold; }
        .band-used { font-weight: bold; text-decoration: underline; }
        .void-mark { text-align: center; color: #b00; font-weight: bold; font-size: 13px; letter-spacing: .2em; margin-bottom: 6px; }
        .toolbar { max-width: 750px; margin: 10px auto; font-size: 13px; display: flex; gap: 12px; align-items: center; }
        .toolbar .warn { color: #8a5a00; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>

<div class="toolbar">
    @canDo('Cad - Records', 'print')
        <button onclick="window.print()">Print</button>
    @endcanDo
    <a href="{{ route('cadastral-module.plan-description.fees', ['record' => $record->id]) }}">Back to the Fee Calculator</a>
    <span>{{ $bill->bill_ref }} · {{ $bill->status }}</span>
    @if ($bill->unconfirmed_rules)
        <span class="warn">Issued under unconfirmed rule(s): {{ $bill->unconfirmed_rules }}</span>
    @endif
    @unless ($bill->is_fee_sheet)
        <span class="warn">This bill predates the fee sheet; its lines are not on record.</span>
    @endunless
</div>

<div class="document-container">

    @if ($bill->status === 'Cancelled')
        <div class="void-mark">CANCELLED{{ $bill->cancel_reason ? ' — ' . $bill->cancel_reason : '' }}</div>
    @endif

    <!-- Top Reference / Header Section -->
    <div class="header-section">
        <div class="header-left">
            <strong>The Director Lands,<br>
            Kano State Bureau for Land Management,<br>
            Kano.</strong>
        </div>
        <div class="header-right">
            Re CAD/ <span class="line-field" style="width: 130px;">{{ $bill->re_cad_ref }}</span><br>
            Cadastral Department<br>
            P.M.B 3083, Kano<br>
            Date <span class="line-field" style="width: 130px;">{{ optional($bill->bill_date ?? $bill->issued_at)->format('d/m/Y') }}</span>
        </div>
    </div>

    <!-- Document Title -->
    <div class="document-title">
        Right of Occupancy No <span class="line-field" style="min-width: 150px;">{{ $bill->rofo_no ?: $bill->file_number }}</span><br>
        Cadastral Fees and Area
    </div>

    <!-- Investigation and Fee breakdown Grid: Quantity | Unit rate | Amount -->
    @php
        $labels = [
            'investigation' => 'Investigation and Search @ ' . $rate('investigation', 'N4,000'),
            'beacon'        => 'Beacons @ (' . $rate('beacon', 'N4,000') . ' each)',
            'area'          => 'Area fee @ (schedule below)',
            'delay'         => 'Delay @ ' . $rate('delay', 'N 350') . ' (Per day)',
            'transport'     => 'Transport @ Schedule below',
            'field_work'    => 'Additional field work @ ' . $rate('field_work', 'N350:00') . ' (per day)',
            'office_work'   => 'Office Work @ ' . $rate('office_work', 'N10,000') . ' (per day)',
            'plan_print'    => 'Plan Prints @ ' . $rate('plan_print', 'N400:00') . ' (per file)',
        ];
    @endphp
    <table class="fee-breakdown-table">
        @foreach ($labels as $key => $label)
            <tr>
                <td class="label-col">{{ $label }}</td>
                <td class="empty-col qty">{{ $lines[$key]['qty_label'] }}</td>
                <td class="empty-col num">{{ $m($lines[$key]['rate']) }}</td>
                <td class="empty-col num">{{ $m($lines[$key]['amount']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="label-col">Total</td>
            <td></td><td></td><td class="num"><strong>{{ $m($bill->grand_total) }}</strong></td>
        </tr>
    </table>

    <!-- Context Text Fields -->
    <div class="info-text">
        The Area of this plot is <span class="line-field" style="min-width: 120px; text-align: center;">{{ $areaHa }}</span> Hectares<br>
        Please inform me you require the plans and Description to be issued.
    </div>

    <!-- Transport & Signature Row -->
    <div class="flex-row">
        <div>
            <div class="schedule-title">Transport Schedule</div>
            <div class="schedule-list">
                @foreach ($bands as $band)
                    <span class="{{ $bill->transport_qty && $band['label'] === $bill->transport_band_label ? 'band-used' : '' }}">{{ $band['label'] }} - N{{ number_format($band['fee'], 2) }}</span>@if (! $loop->last)<br>@endif
                @endforeach
            </div>
            <div class="schedule-title">Schedule of area Fee: &nbsp;&nbsp;&nbsp;&nbsp;(Kano S.L.N. No 3 of 1983)</div>
        </div>
        <div class="signature-space">
            For Director Cadastral
        </div>
    </div>

    <!-- Schedule Matrix Table -->
    <table class="area-fee-table">
        <thead>
            <tr>
                <th style="width: 18%;">HEACTARES</th>
                <th style="width: 22%;">CURRENT FEES N</th>
                <th style="width: 22%;">PROPOSED FEES N</th>
                <th style="width: 20%;">ADDITIONAL N</th>
                <th style="width: 18%;">PROPOSED N</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schedule as $row)
                <tr class="{{ $usedRow !== null && abs(round($row['hectares'], 2) - $usedRow) < 0.001 ? 'row-used' : '' }}">
                    <td>{{ number_format($row['hectares'], 2) }}</td>
                    <td>{{ $row['current_fee'] === null ? '' : number_format($row['current_fee'], 2) }}</td>
                    <td>{{ $row['proposed_fee'] === null ? '' : number_format($row['proposed_fee'], 2) }}</td>
                    <td>{{ $row['additional_note'] }}</td>
                    <td>{{ $row['proposed_additional_note'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</div>

</body>
</html>

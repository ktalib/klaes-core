{{--
    Index card, print-on-demand.

    A standalone document rather than a page in the app shell: it is printed and
    filed, so it carries no sidebar, no navigation and no colour that costs toner.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Index Card {{ $card->card_ref }} — {{ $card->file_number }}</title>
    <style>
        @page { size: A5 landscape; margin: 12mm; }

        body {
            font-family: "Times New Roman", Georgia, serif;
            font-size: 12px;
            color: #000;
            margin: 0;
        }

        .sheet { max-width: 210mm; margin: 0 auto; }

        .head {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .head h1 { font-size: 15px; margin: 0; letter-spacing: .06em; text-transform: uppercase; }
        .head h2 { font-size: 12px; margin: 2px 0 0; font-weight: normal; }

        table { width: 100%; border-collapse: collapse; }
        .fields td { padding: 4px 6px; vertical-align: top; }
        .fields .label {
            width: 26%;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #333;
        }
        .fields .value { border-bottom: 1px dotted #666; font-weight: bold; }

        .movements { margin-top: 10px; }
        .movements caption {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding-bottom: 3px;
        }
        .movements th, .movements td {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 10px;
            text-align: left;
        }
        .movements th { background: #eee; }

        .foot {
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #444;
        }

        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">

    <div class="no-print">
        @canDo('Cad - Records', 'print')
            <button onclick="window.print()">Print</button>
        @endcanDo
        <a href="{{ route('cadastral-module.index-cards.show', $card) }}">Back</a>
    </div>

    <div class="head">
        <h1>Kano State Ministry of Land and Physical Planning</h1>
        <h2>Cadastral Department — Index Card</h2>
    </div>

    <table class="fields">
        <tr>
            <td class="label">File Number</td>
            <td class="value">{{ $card->file_number }}</td>
            <td class="label">Card Ref</td>
            <td class="value">{{ $card->card_ref }}</td>
        </tr>
        <tr>
            <td class="label">File Name</td>
            <td class="value" colspan="3">{{ $card->file_title ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Plot No.</td>
            <td class="value">{{ $card->plot_no ?: '—' }}</td>
            <td class="label">Block No.</td>
            <td class="value">{{ $card->block_no ?: '—' }}</td>
        </tr>
        <tr>
            {{-- District, LGA, State. The plot number has its own row above. --}}
            <td class="label">Plot Location</td>
            <td class="value">{{ $card->property_location ?: '—' }}</td>
            <td class="label">Layout</td>
            <td class="value">{{ $card->layout_name ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Survey Job No.</td>
            <td class="value">{{ $card->survey_job_number ?: '—' }}</td>
            <td class="label">File Status</td>
            <td class="value">{{ $card->file_status_label }}</td>
        </tr>
        @if ($card->chart)
            <tr>
                <td class="label">Chart Ref</td>
                <td class="value">{{ $card->chart->chart_ref }} (v{{ $card->chart->version }})</td>
                <td class="label">Approved Plan</td>
                <td class="value">{{ $card->chart->approved_plan_no ?: '—' }}</td>
            </tr>
        @endif
    </table>

    <table class="movements">
        <caption>Card movement (stage inside Cadastral)</caption>
        <thead>
            <tr><th style="width:30%">Stage</th><th style="width:24%">By</th><th style="width:20%">When</th><th>Note</th></tr>
        </thead>
        <tbody>
            @forelse ($stages as $stage)
                <tr>
                    <td>{{ $stage['label'] }}</td>
                    <td>{{ $stage['by'] }}</td>
                    <td>{{ optional($stage['at'])->format('d M Y H:i') }}</td>
                    <td>{{ $stage['note'] }}</td>
                </tr>
            @empty
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="movements">
        <caption>File movement record (file tracker)</caption>
        <thead>
            <tr><th style="width:30%">Office</th><th style="width:22%">In</th><th style="width:22%">Out</th><th>Note</th></tr>
        </thead>
        <tbody>
            @forelse ($movements as $move)
                <tr>
                    <td>{{ $move['office_name'] ?? $move['to_office_name'] ?? $move['to'] ?? '' }}</td>
                    <td>{{ trim(($move['log_in_date'] ?? '') . ' ' . ($move['log_in_time'] ?? '')) ?: ($move['timestamp'] ?? '') }}</td>
                    <td>{{ trim(($move['log_out_date'] ?? '') . ' ' . ($move['log_out_time'] ?? '')) }}</td>
                    <td>{{ $move['notes'] ?? $move['status_label'] ?? $move['note'] ?? '' }}</td>
                </tr>
            @empty
                {{-- Ruled blank lines, so a card with no tracked history can still
                     be written on by hand the way the paper ones are. --}}
                @for ($i = 0; $i < 6; $i++)
                    <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                @endfor
            @endforelse
        </tbody>
    </table>

    <div class="foot">
        <span>Commissioned {{ optional($card->commissioned_at)->format('d M Y') ?: '—' }}</span>
        <span>Printed {{ now()->format('d M Y H:i') }} · copy {{ $card->print_count }}</span>
    </div>
</div>
</body>
</html>

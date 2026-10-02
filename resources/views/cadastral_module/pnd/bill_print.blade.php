{{-- Consolidated cadastral bill — the printed demand note. Standalone. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Cadastral Bill {{ $bill->bill_ref }}</title>
    <style>
        @page { size: A4; margin: 18mm; }

        body { font-family: "Times New Roman", Georgia, serif; font-size: 12.5px; color: #000; margin: 0; line-height: 1.5; }
        .sheet { max-width: 180mm; margin: 0 auto; }

        .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { font-size: 16px; margin: 0; letter-spacing: .06em; text-transform: uppercase; }
        .head h2 { font-size: 12.5px; margin: 3px 0 0; font-weight: normal; }
        .head h3 { font-size: 13.5px; margin: 10px 0 0; text-transform: uppercase; letter-spacing: .1em; }

        .refs { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 11.5px; }

        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.fields td { padding: 4px 6px; vertical-align: top; }
        table.fields .label { width: 26%; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
        table.fields .value { border-bottom: 1px dotted #666; font-weight: bold; }

        table.bill { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.bill th, table.bill td { border: 1px solid #000; padding: 6px 8px; font-size: 11.5px; }
        table.bill th { background: #eee; text-align: left; }
        table.bill .amount { text-align: right; font-variant-numeric: tabular-nums; width: 24%; }
        table.bill tr.total td { font-weight: bold; font-size: 13px; background: #f6f6f6; }

        .note { margin-top: 12px; font-size: 10.5px; color: #333; }

        .sig { margin-top: 36px; display: flex; justify-content: space-between; }
        .sig .block { width: 45%; }
        .sig .rule { border-top: 1px solid #000; padding-top: 4px; font-size: 10.5px; }

        .foot { margin-top: 22px; border-top: 1px solid #999; padding-top: 6px; font-size: 9.5px; color: #444; }

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
        <a href="{{ route('cadastral-module.plan-description.edit', $record) }}">Back</a>
    </div>

    <div class="head">
        <h1>Kano State Ministry of Land and Physical Planning</h1>
        <h2>Cadastral Department — Plan and Description Unit</h2>
        <h3>Cadastral Fee Note</h3>
    </div>

    <div class="refs">
        <span><strong>Bill No.:</strong> {{ $bill->bill_ref }}</span>
        <span><strong>File No.:</strong> {{ $bill->file_number }}</span>
        <span><strong>Issued:</strong> {{ optional($bill->issued_at)->format('d F Y') ?: '—' }}</span>
    </div>

    <table class="fields">
        <tr>
            <td class="label">File Title</td>
            <td class="value" colspan="3">{{ $record->file_title ?: '—' }}</td>
        </tr>
        <tr>
            {{-- District, LGA, State. The plot number has its own row. --}}
            <td class="label">Location</td>
            <td class="value">{{ $record->property_location ?: '—' }}</td>
            <td class="label">Plot No.</td>
            <td class="value">{{ $record->chart?->plot_no ?: $record->prop_plot ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Land Use</td>
            <td class="value">{{ $record->land_use ?: '—' }}</td>
            <td class="label">Zone</td>
            <td class="value">{{ \App\Models\Cadastral\CadastralPlanDescription::ZONES[$record->location_zone] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Area</td>
            <td class="value" colspan="3">
                {{ $areas['sqm'] !== null ? number_format($areas['sqm'], 2) : '—' }} sqm
                @if ($areas['hectares'] !== null)
                    · {{ number_format($areas['hectares'], 4) }} ha
                    · {{ number_format($areas['acres'], 4) }} acres
                    · {{ number_format($areas['plots'], 2) }} plots
                @endif
            </td>
        </tr>
    </table>

    <table class="bill">
        <thead>
            <tr><th>Item</th><th>Basis</th><th class="amount">Amount (&#8358;)</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['label'] }}</td>
                    <td>{{ $line['detail'] }}</td>
                    <td class="amount">{{ number_format($line['amount'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Grand Total</td>
                <td class="amount">{{ number_format($bill->grand_total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="note">
        The rates above are those in force on the date of issue and are recorded with this bill, so
        a reprint always matches the copy issued. Government and private pillars are charged at the
        same rate.
    </div>

    @if ($bill->receipt_no)
        <table class="fields" style="margin-top:14px;">
            <tr>
                <td class="label">Receipt No.</td>
                <td class="value">{{ $bill->receipt_no }}</td>
                <td class="label">Receipt Date</td>
                <td class="value">{{ optional($bill->receipt_date)->format('d M Y') ?: '—' }}</td>
            </tr>
        </table>
    @endif

    <div class="sig">
        <div class="block">
            <div class="rule" style="margin-top:40px;">
                Prepared — Plan and Description Unit
            </div>
        </div>
        <div class="block">
            <div class="rule" style="margin-top:40px;">
                Approved — Assistant Director, Plans and Descriptions
            </div>
        </div>
    </div>

    <div class="foot">
        {{ $bill->bill_ref }} · {{ $record->pd_ref }} · status {{ $bill->status }} ·
        printed {{ now()->format('d M Y H:i') }}
    </div>
</div>
</body>
</html>

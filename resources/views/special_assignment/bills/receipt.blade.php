{{--
    Payment receipt for one SPAS bill payment.

    Itemised from the payment's own lines, not recomputed from the tariff: a
    receipt reprinted next year must show what was actually collected that day,
    even after somebody edits a bill item's amount.

    Payments recorded before item-by-item entry existed have no lines. Those
    print as a single unallocated figure rather than a fabricated split.
--}}
@php
    $bill      = $payment->bill;
    $app       = $bill?->application;
    $billTotal = (float) ($bill->amount ?? 0);
    $thisPaid  = (float) $payment->amount_paid;
    $balance   = max(0, $billTotal - $priorPaid - $thisPaid);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt – {{ $payment->receipt_number ?: $bill?->reference_id }}</title>
    <style>
        @page { size: A4; margin: 0; }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f0f0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            box-sizing: border-box;
            padding: 20mm;
            box-shadow: 0 0 15px rgba(0,0,0,.1);
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
        }

        .logo-box img { max-width: 80px; max-height: 80px; object-fit: contain; }

        .gov-title-container { text-align: center; }
        .gov-title-container h1 { font-size: 18px; margin: 0; }
        .gov-title-container h2 { font-size: 14px; margin: 2px 0 0; font-weight: normal; }
        .gov-title-container h3 {
            font-size: 15px;
            margin: 8px 0 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            margin: 18px 0 6px;
            font-size: 12px;
        }
        .meta div { line-height: 1.7; }
        .meta strong { display: inline-block; min-width: 105px; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 12.5px;
        }
        table.items th, table.items td {
            border: 1px solid #cfcfcf;
            padding: 8px 10px;
            text-align: left;
        }
        table.items th { background: #f4f4f0; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        table.items td.num, table.items th.num { text-align: right; white-space: nowrap; }
        table.items tfoot td { font-weight: bold; background: #fbfbf5; }

        .summary {
            margin-top: 14px;
            margin-left: auto;
            width: 62mm;
            font-size: 12.5px;
        }
        .summary div {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .summary .total {
            border-top: 1px solid #999;
            border-bottom: 3px double #111;
            font-weight: bold;
            font-size: 14px;
        }

        .note { margin-top: 10px; font-size: 11px; color: #666; }

        .sign-row {
            display: flex;
            justify-content: space-between;
            margin-top: 28mm;
            font-size: 12px;
        }
        .sign-box { width: 62mm; text-align: center; }
        .sign-line { border-top: 1px solid #333; padding-top: 4px; }

        .toolbar {
            text-align: center;
            padding: 12px;
        }
        .toolbar button {
            padding: 8px 18px;
            font-size: 13px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 0 4px;
            background: rgb(186,191,12);
            color: #fff;
        }
        .toolbar .btn-close { background: #6b7280; }

        .doc-footer-logo {
            margin-top: 24px; padding-top: 12px;
            border-top: 1px solid #ddd; text-align: center;
        }
        .doc-footer-logo img { max-height: 44px; width: auto; object-fit: contain; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .a4-page { margin: 0; box-shadow: none; width: auto; min-height: auto; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()">Print Receipt</button>
    <button class="btn-close" onclick="window.close()">Close</button>
</div>

<div class="a4-page">

    <div class="header-section">
        <div class="logo-box">
            <img src="{{ url('assets/logo/ministry2.png') }}" alt="Ministry Logo">
        </div>
        <div class="gov-title-container">
            <h1>Kano State Government</h1>
            <h2>Ministry of Land &amp; Physical Planning</h2>
            <h3>Payment Receipt</h3>
        </div>
        <div class="logo-box">
            <img src="{{ url('assets/logo/ministry1.jpg') }}" alt="Coat of Arms">
        </div>
    </div>

    <div class="meta">
        <div>
            <strong>Receipt No:</strong> {{ $payment->receipt_number ?: '—' }}<br>
            <strong>Bill Ref:</strong> {{ $bill->reference_id ?? '—' }}<br>
            <strong>File No:</strong> {{ $app->file_number ?? '—' }}
        </div>
        <div>
            <strong>Date:</strong> {{ $payment->payment_date?->format('d/m/Y') ?? '—' }}<br>
            <strong>Payment Method:</strong> {{ ucwords(str_replace('_', ' ', $payment->payment_method ?? '—')) }}<br>
            <strong>Received By:</strong> {{ $payment->recorded_by ?? '—' }}
        </div>
    </div>

    <div style="font-size:12.5px;margin-top:6px;">
        <strong style="display:inline-block;min-width:105px;">Received From:</strong>
        {{ $app->owner_name ?? '—' }}
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:6%">#</th>
                <th>Bill Item</th>
                <th class="num" style="width:22%">Billed (₦)</th>
                <th class="num" style="width:22%">Paid Now (₦)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payment->lines as $i => $line)
                @php $billed = $bill?->lines->firstWhere('id', $line->spa_bill_line_id); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->name }}</td>
                    <td class="num">{{ $billed ? number_format($billed->amount, 2) : '—' }}</td>
                    <td class="num">{{ number_format($line->amount_paid, 2) }}</td>
                </tr>
            @empty
                {{-- Recorded before item-by-item entry: one figure, no split. --}}
                <tr>
                    <td>1</td>
                    <td>{{ $bill->bill_type ?? 'Payment' }} — not itemised</td>
                    <td class="num">{{ number_format($billTotal, 2) }}</td>
                    <td class="num">{{ number_format($thisPaid, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="num">Total Paid on This Receipt</td>
                <td class="num">{{ number_format($thisPaid, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="summary">
        <div><span>Bill Total</span><span>₦{{ number_format($billTotal, 2) }}</span></div>
        <div><span>Previously Paid</span><span>₦{{ number_format($priorPaid, 2) }}</span></div>
        <div class="total"><span>Paid Now</span><span>₦{{ number_format($thisPaid, 2) }}</span></div>
        <div><span>Balance Outstanding</span><span>₦{{ number_format($balance, 2) }}</span></div>
    </div>

    @if ($balance > 0)
        <p class="note">
            This receipt settles part of the bill. ₦{{ number_format($balance, 2) }} remains outstanding
            @if ($bill?->due_date) (due {{ $bill->due_date->format('d/m/Y') }})@endif.
        </p>
    @endif

    <div class="sign-row">
        <div class="sign-box"><div class="sign-line">Payer's Signature</div></div>
        <div class="sign-box"><div class="sign-line">Cashier / Authorised Officer</div></div>
    </div>

    {{-- Ministry mark at the foot of the printed receipt, matching the memo and
         certificate. Served from the live host rather than asset(), because
         these documents are printed and emailed from environments whose
         APP_URL is not app.klaes.ng. --}}
    <div class="doc-footer-logo">
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="">
    </div>

</div>

</body>
</html>

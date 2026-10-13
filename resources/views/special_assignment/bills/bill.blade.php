{{--
    The SPAS BILL for one bill (2026-10-13): what the owner is to pay, item by
    item. Same layout as the payment receipt (bills/receipt) — which is the
    PAYMENT document, printed from Special Assignment – Payments — but titled
    BILL, with no receipt number and no Paid column.
--}}
@php
    $app       = $bill->application;
    $billLines = $bill->lines ?? collect();
    // Every fee on the tariff, in tariff order; "Not yet decided" for one this
    // bill does not carry — as on the receipt.
    $tariff = \App\Models\SpaBillItem::query()->orderBy('sort_order')->orderBy('name')->get();
    $rows = $tariff->map(function ($item) use ($billLines) {
        $line = $billLines->firstWhere('spa_bill_item_id', $item->id);
        return ['name' => $line ? $line->display_name : $item->name, 'billed' => $line ? (float) $line->amount : null];
    });
    foreach ($billLines->reject(fn ($l) => $l->spa_bill_item_id && $tariff->contains('id', $l->spa_bill_item_id)) as $line) {
        $rows->push(['name' => $line->display_name, 'billed' => (float) $line->amount]);
    }
    // Penalty Fee and Survey Fee are left off the printout for now, as on the
    // receipt; the printed total is the sum of the rows shown.
    $hiddenFee = fn ($name) => in_array(mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $name)), 'UTF-8'), ['penalty fee', 'penalty fees', 'survey fee', 'survey fees'], true);
    $rows = $rows->reject(fn ($r) => $hiddenFee($r['name']))->values();
    $billTotal = max(0, (float) $bill->amount - (float) $billLines->filter(fn ($l) => $hiddenFee($l->display_name))->sum('amount'));
    $fileRef   = ($app->file_number ?? null) ?: ($app->spa_id ?? null);
    $qrRenderer = new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(160, 4),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
    );
    $qrSvg = (new \BaconQrCode\Writer($qrRenderer))->writeString(route('special-assignment.bills.print', $bill->id));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill – {{ $bill->reference_id }}</title>
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
            display: flex;
            flex-direction: column;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            box-sizing: border-box;
            padding: 20mm;
            box-shadow: 0 0 15px rgba(0,0,0,.1);
        }
        .a4-page > * { flex-shrink: 0; }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
        }

        .logo-box img { max-width: 80px; max-height: 80px; object-fit: contain; }
        .header-marks { display: flex; align-items: center; gap: 8px; }
        .bill-qr { width: 60px; height: 60px; flex-shrink: 0; }

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
        .gov-title-container h2.dept-line { font-size: 12.5px; color: #333; }
        .receipt-sign { margin-top: 18mm; width: 60%; font-size: 12.5px; display: flex; flex-direction: column; gap: 14px; }
        .rs-field { display: flex; align-items: flex-end; gap: 8px; }
        .rs-field span { flex: 0 0 48px; font-weight: bold; }
        .rs-field i { flex: 1; border-bottom: 1px solid #000; height: 14px; }
        .rs-for { margin-top: 6px; font-weight: bold; }

        .sign-row {
            display: flex;
            justify-content: space-between;
            margin-top: 28mm;
            margin-bottom: 24px;
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
            margin-top: auto; padding-top: 12px;
            border-top: 1px solid #ddd; text-align: right;
        }
        .doc-footer-logo img { max-height: 44px; width: auto; object-fit: contain; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .a4-page { margin: 0; box-shadow: none; width: auto; min-height: 297mm; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()">Print Bill</button>
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
            <h2 class="dept-line">Department of Special Assignment</h2>
            <h3>Bill</h3>
        </div>
        <div class="header-marks">
            <img class="bill-qr" src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" alt="QR code to open this bill">
            <div class="logo-box">
                <img src="{{ url('assets/logo/ministry1.jpg') }}" alt="Coat of Arms">
            </div>
        </div>
    </div>

    <div class="meta">
        <div>
            <strong>Bill Ref.:</strong> {{ $bill->reference_id ?? '—' }}<br>
            <strong>File No.:</strong> {{ $fileRef ?: '—' }}<br>
            @if (($app->spa_id ?? null) && $app->spa_id !== $fileRef)<strong>SPAS ID:</strong> {{ $app->spa_id }}<br>@endif
            <strong>Billed To:</strong> {{ $app->owner_name ?? '—' }}
        </div>
        <div>
            <strong>Date:</strong> {{ now()->format('d/m/Y') }}<br>
            <strong>Due Date:</strong> {{ $bill->due_date?->format('d/m/Y') ?? '—' }}<br>
            <strong>Status:</strong> {{ ucfirst($bill->status ?? 'unpaid') }}
        </div>
    </div>


    <table class="items">
        <thead>
            <tr>
                <th style="width:6%">N</th>
                <th>Bill Item</th>
                <th class="num" style="width:26%">Billed (₦)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    @if ($row['billed'] === null)
                        <td class="num" style="color:#666;font-style:italic">Not yet decided</td>
                    @else
                        <td class="num">{{ number_format($row['billed'], 2) }}</td>
                    @endif
                </tr>
            @endforeach
            @if ($billLines->isEmpty())
                {{-- A bill raised before itemised tariffs: one figure. --}}
                <tr>
                    <td>1</td>
                    <td>{{ $bill->bill_type ?? 'Bill' }} — not itemised</td>
                    <td class="num">{{ number_format($billTotal, 2) }}</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td></td>
                <td>Total</td>
                <td class="num">₦{{ number_format($billTotal, 2) }}</td>
            </tr>
        </tfoot>
    </table>



    {{-- One sign-off, for the Hon. Commissioner (updated receipt). --}}
    <div class="receipt-sign">
        <div class="rs-field"><span>Name:</span><i></i></div>
        <div class="rs-field"><span>Rank:</span><i></i></div>
        <div class="rs-field"><span>Date:</span><i></i></div>
        <div class="rs-for">For: Hon. Commissioner</div>
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

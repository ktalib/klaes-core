@php
    $logo = function (string $file) {
        $path = public_path('assets/logo/' . $file);
        return is_file($path) ? 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path)) : null;
    };
    $leftLogo = $logo('logo1.jpg');
    $rightLogo = $logo('logo3.jpeg');
    $amount = (float) ($bill->Betterment_Charges ?? 0);
    $area = (float) ($bill->property_value ?? 0);
    $billDate = $bill->created_at ? \Carbon\Carbon::parse($bill->created_at)->format('d F Y') : 'N/A';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Betterment Bill {{ $bill->ref_id ?: $bill->ID }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #111827; margin: 24px; }
        .head { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 16px; }
        .head td { vertical-align: middle; }
        .head img { height: 64px; }
        .title { text-align: center; }
        .title h1 { font-size: 14px; margin: 0 0 4px; }
        .title h2 { font-size: 12px; margin: 0; font-weight: normal; }
        .meta { width: 100%; margin-bottom: 14px; }
        .ref { font-weight: bold; }
        .details p { margin: 3px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin: 14px 0; }
        table.lines th, table.lines td { border: 1px solid #9ca3af; padding: 6px 8px; text-align: left; }
        table.lines th { background: #f3f4f6; }
        .right { text-align: right; }
        .total td { font-weight: bold; }
        .toolbar { margin-bottom: 16px; }
        .toolbar a, .toolbar button { display: inline-block; padding: 7px 12px; margin-right: 6px; border: 0; border-radius: 4px; background: #2563eb; color: #fff; font-size: 12px; text-decoration: none; cursor: pointer; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    @if(!$isPdf)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Print Bill</button>
            <a href="{{ route('physical-planning.bills.print', ['id' => $bill->ID, 'download' => 1]) }}">Download PDF</a>
        </div>
    @endif

    <table class="head">
        <tr>
            <td style="width: 80px;">@if($leftLogo)<img src="{{ $leftLogo }}" alt="Kano State Logo">@endif</td>
            <td class="title">
                <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h2>{{ strtoupper($typeLabel) }} BETTERMENT CHARGES BILL</h2>
            </td>
            <td style="width: 80px; text-align: right;">@if($rightLogo)<img src="{{ $rightLogo }}" alt="Ministry Logo">@endif</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td><strong>Date:</strong> {{ $billDate }}</td>
            <td style="text-align: right;"><strong>Bill Reference:</strong> <span class="ref">{{ $bill->ref_id ?: 'N/A' }}</span></td>
        </tr>
    </table>

    <div class="details">
        <p><strong>File No:</strong> {{ $bill->Sectional_Title_File_No ?: 'N/A' }}</p>
        <p><strong>Name of Applicant:</strong> {{ $applicantName ?: 'N/A' }}</p>
        <p><strong>Land Use:</strong> {{ $landUse ?: 'N/A' }}</p>
        <p><strong>Plot No.:</strong> {{ $plotNumber ?: 'N/A' }}</p>
        <p><strong>Location:</strong> {{ $location ?: 'N/A' }}</p>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Value</th>
                <th class="right">Amount (₦)</th>
            </tr>
        </thead>
        <tbody>
            @if($area > 0)
                <tr>
                    <td>Total Area</td>
                    <td class="right">{{ number_format($area, 2) }} m²</td>
                    <td class="right">—</td>
                </tr>
            @endif
            <tr class="total">
                <td>Total Betterment Charges</td>
                <td class="right">—</td>
                <td class="right">₦{{ number_format($amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <p>You are hereby directed to settle this bill promptly in order to accelerate the processing of your application.</p>
    <p><strong>Note:</strong> Documentary Payments can be made at the Checkout-Point and KANGIS Cashier's Office.</p>
</body>
</html>

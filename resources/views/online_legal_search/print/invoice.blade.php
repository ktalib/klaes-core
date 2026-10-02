{{--
    Payment invoice for an Online Legal Search request, emailed alongside the
    approved report.

    Modelled on the PHS Portal invoice (resources/views/phs/transaction-invoice-template.blade.php)
    so both portals issue a recognisably identical document. The differences are
    inherent to the product: an ONLS payment is a single flat-fee search rather
    than a token package, so the line items and the "Billed To" block differ.
--}}
@php
    use App\Support\QrPng;

    $logo = function (string $relative) {
        $path = public_path($relative);
        return is_file($path) ? $path : null;
    };

    $ministryLeft  = $logo('assets/logo/ministry2.jpeg') ?: $logo('assets/logo/ministry2.png');
    $ministryRight = $logo('assets/logo/ministry1.jpg');
    $brandLogo     = $logo('assets/logo/online_ls_print.jpeg') ?: $logo('assets/logo/online_ls.jpeg');
    // KLAES mark. Prefer a locally deployed copy; otherwise pull it from the app
    // host, exactly as the PHS invoice does — the file is not present in every
    // checkout, and DomPDF has isRemoteEnabled turned on.
    $klaesLogo     = $logo('storage/upload/logo/Klase.png')
        ?: $logo('storage/upload/logo/logo.png')
        ?: 'http://app.klaes.ng/storage/upload/logo/Klase.png';

    // Paystack amounts are stored in kobo.
    $amount = ((int) ($payment->amount ?? 0)) / 100;
    $paid   = $payment && $payment->isPaid();

    $qr = QrPng::dataUri($qr_content ?? $invoice_number, 4);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 0; }
        .wrap { padding: 20px 36px; }
        .head { width: 100%; }
        .head td { vertical-align: middle; }
        .head .logo-cell { width: 100px; }
        .head .logo-cell img { height: 74px; width: auto; }
        .head .logo-cell.r { text-align: right; }
        .brand { text-align: center; padding: 0 8px; }
        .brand h1 { color: #166534; font-size: 16px; margin: 0; }
        .brand p { color: #6b7280; font-size: 11px; margin: 2px 0 0; }
        .brand h2 { font-size: 13px; margin: 4px 0 0; color: #111827; }
        .head-rule { border-bottom: 3px solid #166534; margin-top: 10px; }
        .title-row { width: 100%; margin-top: 10px; }
        .title-row td { vertical-align: top; }
        .title-row .qr-cell { width: 84px; }
        .qr-cell img { width: 72px; height: 72px; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 22px; letter-spacing: 2px; color: #111827; margin: 0; }
        .doc-title p { margin: 3px 0 0; color: #6b7280; font-size: 11px; }
        .doc-title .num { font-weight: 700; color: #374151; }
        .doc-title .ref span { font-weight: 600; color: #374151; }
        .meta { width: 100%; margin-top: 12px; }
        .meta td { vertical-align: top; width: 50%; }
        .meta h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #166534; margin: 0 0 4px; }
        .meta p { margin: 2px 0; }
        .meta .muted { color: #6b7280; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.items th { background: #166534; color: #fff; text-align: left; padding: 7px 12px; font-size: 11px; text-transform: uppercase; }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 6px 12px; border-bottom: 1px solid #e5e7eb; }
        .totals { width: 100%; margin-top: 4px; }
        .totals td { padding: 4px 12px; }
        .totals .label { text-align: right; color: #6b7280; }
        .totals .val { text-align: right; width: 160px; font-weight: bold; }
        .totals .grand { font-size: 15px; color: #166534; border-top: 2px solid #166534; }
        .pay { margin-top: 14px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; background: #f9fafb; }
        .pay h3 { margin: 0 0 6px; font-size: 12px; color: #166534; }
        .pay p { margin: 2px 0; }
        .status { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #f3f4f6; color: #4b5563; }
        .foot { width: 100%; margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .foot td { vertical-align: middle; }
        .foot-text { color: #9ca3af; font-size: 10px; text-align: center; }
    </style>
</head>
<body>

<div class="wrap">
    <table class="head">
        <tr>
            <td class="logo-cell">@if($ministryLeft)<img src="{{ $ministryLeft }}" alt="">@endif</td>
            <td class="brand">
                <h1>Kano State Ministry of Land &amp; Physical Planning</h1>
                <p>Department of Lands</p>
                <h2>Online Legal Search (ONLS) Payment Invoice</h2>
            </td>
            <td class="logo-cell r">@if($ministryRight)<img src="{{ $ministryRight }}" alt="">@endif</td>
        </tr>
    </table>
    <div class="head-rule"></div>

    <table class="title-row">
        <tr>
            <td class="qr-cell">@if($qr)<img src="{{ $qr }}" alt="QR">@endif</td>
            <td class="doc-title">
                <h2>INVOICE</h2>
                <p class="num">{{ $invoice_number }}</p>
                @if($payment && $payment->reference)
                    <p class="ref">Ref: <span>{{ $payment->tracking_id ?: $payment->reference }}</span></p>
                @endif
                <p>Date: {{ $invoice_date->format('F j, Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <h3>Billed To</h3>
                <p><strong>{{ $searchRequest->requester_name ?: $searchRequest->requester_email }}</strong></p>
                @if($searchRequest->requester_name)<p class="muted">{{ $searchRequest->requester_email }}</p>@endif
                @if($searchRequest->requester_phone)<p class="muted">{{ $searchRequest->requester_phone }}</p>@endif
                <p class="muted">Request: {{ $searchRequest->request_no }}</p>
            </td>
            <td>
                <h3>Payment Status</h3>
                @if($paid)
                    <p><span class="status status-paid">PAID</span></p>
                @else
                    <p><span class="status status-pending">PENDING</span></p>
                @endif
                @if($payment && $payment->paid_at)
                    <p class="muted">Paid: {{ $payment->paid_at->format('F j, Y') }}</p>
                @endif
                @if($payment && $payment->tracking_id)
                    <p class="muted">Tracking id: {{ $payment->tracking_id }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="r">Qty</th>
                <th class="r">Amount (NGN)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Online Legal Search</strong><br>
                    <span style="color:#6b7280">
                        Search report for File No: {{ $searchRequest->file_number ?: '—' }}
                        @if($searchRequest->purpose)<br>Purpose: {{ $searchRequest->purpose }}@endif
                    </span>
                </td>
                <td class="r">1</td>
                <td class="r">{{ number_format($amount, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Search Report (PDF)</strong></td>
                <td class="r">1</td>
                <td class="r">Included</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="val">NGN {{ number_format($amount, 2) }}</td>
        </tr>
        @if($paid)
            <tr><td class="label">Amount paid</td><td class="val">NGN {{ number_format($amount, 2) }}</td></tr>
        @endif
        <tr>
            <td class="label grand">Total</td>
            <td class="val grand">NGN {{ number_format($amount, 2) }}</td>
        </tr>
    </table>

    <div class="pay">
        <h3>Payment Confirmation</h3>
        <p>Payment Method: <strong>Paystack (Online)</strong></p>
        <p>Payment Reference: <strong>{{ $payment->tracking_id ?? ($payment->reference ?? $invoice_number) }}</strong></p>
        @if($paid)
            <p style="color:#166534">&#10003; Payment processed via Paystack</p>
        @endif
    </div>

    <table class="foot">
        <tr>
            <td style="width:110px; text-align:left;">
                @if($brandLogo)<img src="{{ $brandLogo }}" alt="Online Legal Search" style="height:40px; width:auto;">@endif
            </td>
            <td class="foot-text">
                <p style="margin:0;">This invoice was generated electronically by the KLAES Online Legal Search portal. For enquiries contact the ministry's finance office.</p>
            </td>
            <td style="width:100px; text-align:right;">
                @if($klaesLogo)<img src="{{ $klaesLogo }}" alt="KLAES" style="height:44px; width:auto;">@endif
            </td>
        </tr>
    </table>
</div>
</body>
</html>

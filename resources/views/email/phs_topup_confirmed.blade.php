<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Token Top-up Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .content { padding: 24px; }
        .success-box { background: #dcfce7; border-left: 4px solid #10b981; padding: 14px 16px; border-radius: 6px; color: #166534; }
        .balance { font-size: 28px; font-weight: 800; color: #166534; }
        .list { margin: 12px 0 0; padding-left: 0; list-style: none; }
        .list li { padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
        .footer { margin-top: 28px; font-size: 13px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <h1>Token Top-up Confirmed</h1>
            <div class="success-box">
                <strong>✅ Your wallet has been topped up.</strong>
            </div>

            <p style="margin-top:18px;">New wallet balance:</p>
            <p class="balance">{{ number_format($newBalance) }} tokens</p>

            <ul class="list">
                <li><strong>Organization:</strong> {{ optional($institution)->name }}</li>
                <li><strong>Bundle:</strong> {{ $txn->topup_bundle_count ? $txn->topup_bundle_count . ' × ' : '' }}{{ $txn->package_name }}</li>
                <li><strong>Tokens added:</strong> {{ number_format(abs((int) $txn->tokens)) }}</li>
                <li><strong>Amount:</strong> ₦{{ number_format((float) $txn->amount, 2) }}</li>
                <li><strong>Reference:</strong> {{ $txn->reference_no }}</li>
                <li><strong>Date:</strong> {{ optional($txn->created_at)->format('F j, Y g:i A') }}</li>
            </ul>

            <p class="footer">If you did not authorize this top-up, contact your administrator or the ministry's finance office immediately.<br>
            Kano State Ministry of Land &amp; Physical Planning — PHS Portal.</p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Token Balance Low</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .content { padding: 24px; }
        .warning-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 6px; color: #92400e; }
        .balance { font-size: 28px; font-weight: 800; color: #b45309; }
        .footer { margin-top: 28px; font-size: 13px; color: #6b7280; }
        .btn { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 12px 22px; border-radius: 8px; font-weight: 600; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <h1>Your token balance is running low</h1>
            <div class="warning-box">
                <strong>⚠️ {{ $institution->name }}</strong> has fewer than {{ number_format($threshold) }} tokens remaining.
            </div>

            <p style="margin-top:18px;">Current balance:</p>
            <p class="balance">{{ number_format($balance) }} tokens</p>

            <p>To avoid interruptions to your property history searches, top up your wallet from the organization dashboard.</p>

            <a class="btn" href="{{ rtrim(config('app.url'), '/') }}/phs/organization?tab=subscription">Top Up Tokens</a>

            <p class="footer">You're receiving this because you administer this organization's PHS Portal account.<br>
            Kano State Ministry of Land &amp; Physical Planning — PHS Portal.</p>
        </div>
    </div>
</body>
</html>

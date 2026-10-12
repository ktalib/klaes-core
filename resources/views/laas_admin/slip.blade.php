<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal sign-in slip — {{ $applicant->username }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; background: #f3f4f6; }
        .sheet { max-width: 640px; margin: 24px auto; background: #fff; border: 1px solid #d1d5db; padding: 32px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; font-size: 14px; vertical-align: top; }
        td:first-child { width: 38%; color: #4b5563; }
        .mono { font-family: Consolas, 'Courier New', monospace; font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .notice { background: #fef3c7; border: 1px solid #fcd34d; padding: 10px 12px; font-size: 13px; margin-bottom: 16px; }
        .bar { max-width: 640px; margin: 16px auto 0; text-align: right; }
        .bar button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
        @media print { body { background: #fff; } .bar, .notice { display: none; } .sheet { border: 0; margin: 0; } }
    </style>
</head>
<body>
    <div class="bar"><button type="button" onclick="window.print()">Print slip</button></div>
    <div class="sheet">
        @if(!empty($notice))
            <div class="notice">{{ $notice }}</div>
        @endif

        <h1>KLAES Lands Portal: your sign-in details</h1>
        <p class="muted">Keep this slip private. You will choose your own password the first time you sign in.</p>

        <table>
            <tr><td>Name</td><td>{{ $applicant->name }}</td></tr>
            <tr><td>Portal address</td><td>{{ $url }}</td></tr>
            <tr><td>Username</td><td class="mono">{{ $applicant->username }}</td></tr>
            <tr><td>Temporary password</td><td class="mono">{{ $temp_password }}</td></tr>
            <tr><td>File number(s)</td><td>{{ $files ? implode(', ', $files) : '—' }}</td></tr>
        </table>

        @if(\App\Models\Laas\LaasApplicant::isSystemPhone($applicant->phone))
            <p class="muted">After signing in, please add your own phone number on your profile page so we can send you updates.</p>
        @endif
        <p class="muted">Issued {{ now()->format('j M Y, g:i a') }} by {{ trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')) ?: 'the Lands office' }}.</p>
    </div>
</body>
</html>

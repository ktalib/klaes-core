<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e1b2e">
    <title>No signal — Survey Mobile</title>
    <style>
        body { margin: 0; font-family: 'Outfit', system-ui, -apple-system, sans-serif; background: #f6f5f9; color: #1e1b2e;
               min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; }
        .card { background: #fff; border-radius: 18px; padding: 28px 22px; max-width: 420px; text-align: center; box-shadow: 0 10px 30px -16px rgba(30,27,46,.3); }
        h1 { font-size: 20px; margin: 10px 0 6px; }
        p { color: #52514e; font-size: 14.5px; line-height: 1.5; margin: 6px 0; }
        .icon { font-size: 42px; }
        a { display: inline-block; margin-top: 14px; padding: 11px 18px; border-radius: 12px; background: #29213D; color: #fff; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon" aria-hidden="true">📶</div>
        <h1>No signal</h1>
        <p>This screen has not been opened on this phone while online, so it is not stored yet.</p>
        <p>The dashboard, the case register and Field Observations work offline once you have opened them with a signal. Anything you save is kept on the phone and synced later.</p>
        <a href="{{ route('survey-module.mobile.index') }}">Go to the dashboard</a>
    </div>
</body>
</html>

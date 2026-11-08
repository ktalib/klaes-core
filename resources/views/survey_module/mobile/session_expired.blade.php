<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e1b2e">
    <title>Session expired — {{ $app['name'] }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px; background: #f6f4f9; color: #1e1b2e; font: 16px/1.5 Outfit, system-ui, sans-serif; }
        main { width: min(100%, 440px); padding: 28px; border: 1px solid #e5e7eb; border-radius: 20px; background: #fff; box-shadow: 0 12px 32px -18px #1e1b2e; text-align: center; }
        .icon { width: 52px; height: 52px; margin: 0 auto 14px; display: grid; place-items: center; border-radius: 16px; background: #fff7ed; color: #c2410c; font-size: 24px; }
        h1 { margin: 0 0 8px; font-size: 21px; }
        p { margin: 0 0 20px; color: #6b7280; }
        a { display: inline-block; padding: 12px 20px; border-radius: 12px; background: #be185d; color: #fff; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
<main>
    <div class="icon" aria-hidden="true">!</div>
    <h1>Session expired</h1>
    <p>Your {{ $app['name'] }} session expired, so this request was not submitted. Sign in again to continue.</p>
    <a href="{{ route('survey-module.mobile.login', ['app' => $app['key']]) }}">Return to {{ $app['name'] }} sign in</a>
</main>
</body>
</html>

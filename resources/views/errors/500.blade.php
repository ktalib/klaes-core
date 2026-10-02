<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #0b63ce;
            --primary-hover: #084f9d;
            --border: #dbe3ef;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top right, #e9f1ff 0%, var(--bg) 45%, #eef3f8 100%);
            color: var(--text);
        }

        .card {
            width: 100%;
            max-width: 560px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 20px 45px rgba(31, 41, 55, 0.08);
            padding: 28px;
        }

        .code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #fff0f0;
            color: #b42318;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 14px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 30px;
            line-height: 1.2;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 16px;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 1px solid transparent;
            border-radius: 10px;
            height: 42px;
            padding: 0 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            border-color: var(--border);
            color: var(--text);
            background: #fff;
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="code">Error 500</span>
        <h1>Something went wrong on our side</h1>
        <p>
            We could not complete your request right now. Please refresh the page or try again in a few minutes.
            If this keeps happening, contact support.
        </p>

        <div class="actions">
            <button class="btn btn-primary" onclick="window.location.reload()">Refresh Page</button>
            <a class="btn btn-secondary" href="{{ url()->previous() }}">Go Back</a>
            <a class="btn btn-secondary" href="{{ url('/') }}">Home</a>
        </div>
    </main>
</body>
</html>

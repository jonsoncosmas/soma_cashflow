<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - Soma Cashflow</title>
    <style>
        body {
            font-family: -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f4f6f8; color: #0f172a; margin: 0;
            display: flex; align-items: center; justify-content: center; min-height: 100vh; text-align: center; padding: 24px;
        }
        .box { max-width: 360px; }
        .icon { font-size: 3rem; margin-bottom: 12px; }
        h1 { font-size: 1.2rem; margin: 0 0 8px; }
        p { color: #475569; font-size: 0.92rem; line-height: 1.5; }
        button {
            margin-top: 16px; background: linear-gradient(135deg, #0d5c53, #14957f); color: #fff; border: none;
            padding: 11px 22px; border-radius: 10px; font-size: 0.96rem; font-weight: 600; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">📴</div>
        <h1>You're offline</h1>
        <p>This page hasn't been loaded before while you had a connection, so there's nothing saved to show. Pages you've already visited will still work offline. Any transaction you save now will sync automatically once you're back online.</p>
        <button onclick="window.location.reload()">Try again</button>
    </div>
</body>
</html>

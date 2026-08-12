<?php
/** @var string $pageTitle */
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Soma Cashflow') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-900: #052e2b;
            --brand-700: #0d5c53;
            --brand-600: #0f7a6c;
            --brand-500: #14957f;
            --brand-100: #e3f5f1;
            --ink-900: #0f172a;
            --ink-600: #475569;
            --ink-400: #94a3b8;
            --bg: #f4f6f8;
            --surface: #ffffff;
            --border: #e6e9ee;
            --success-bg: #ecfdf5;
            --success-fg: #067647;
            --error-bg: #fef2f2;
            --error-fg: #b42318;
            --radius-lg: 16px;
            --radius-md: 10px;
            --shadow-card: 0 1px 2px rgba(16,24,40,0.04), 0 4px 16px rgba(16,24,40,0.06);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, Segoe UI, Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink-900);
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }
        header {
            background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 55%, var(--brand-500) 100%);
            color: #fff;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(5,46,43,0.18);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.08rem;
            letter-spacing: -0.01em;
        }
        .brand svg { flex-shrink: 0; }
        header nav { display: flex; align-items: center; gap: 6px; }
        header nav a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 8px 14px;
            border-radius: 8px;
            transition: background 0.15s ease;
        }
        header nav a:hover { background: rgba(255,255,255,0.14); }
        header nav a.cta {
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.28);
        }
        .hi-user {
            color: rgba(255,255,255,0.75);
            font-size: 0.86rem;
            margin-right: 4px;
        }
        main { max-width: 680px; margin: 40px auto; padding: 0 20px 60px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-card);
            margin-bottom: 22px;
        }
        .card h2 {
            margin: 0 0 4px;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .card > p.muted:first-of-type { margin-top: 0; }
        label { display: block; margin: 14px 0 6px; font-weight: 600; font-size: 0.85rem; color: var(--ink-600); }
        input[type=text], input[type=email], input[type=password] {
            width: 100%;
            padding: 11px 13px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 0.96rem;
            font-family: inherit;
            background: #fbfcfd;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        input[type=text]:focus, input[type=email]:focus, input[type=password]:focus {
            outline: none;
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px var(--brand-100);
            background: #fff;
        }
        button {
            margin-top: 20px;
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            border: none;
            padding: 11px 22px;
            border-radius: var(--radius-md);
            font-size: 0.96rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(5,46,43,0.15);
            transition: transform 0.1s ease, box-shadow 0.15s ease;
        }
        button:hover { box-shadow: 0 4px 14px rgba(15,122,108,0.32); transform: translateY(-1px); }
        button:active { transform: translateY(0); }
        .flash {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            font-size: 0.88rem;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        .flash.success { background: var(--success-bg); color: var(--success-fg); border-left-color: var(--success-fg); }
        .flash.error { background: var(--error-bg); color: var(--error-fg); border-left-color: var(--error-fg); }
        .muted { color: var(--ink-600); font-size: 0.87rem; }
        table { width: 100%; border-collapse: collapse; }
        table th {
            text-align: left;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--ink-400);
            font-weight: 600;
            padding: 0 10px 10px;
            border-bottom: 1.5px solid var(--border);
        }
        table td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            font-size: 0.92rem;
        }
        table tr:last-child td { border-bottom: none; }
        a.link { color: var(--brand-600); font-weight: 600; text-decoration: none; }
        a.link:hover { text-decoration: underline; }

        .auth-wrap { max-width: 400px; margin: 60px auto 0; }
        .auth-wrap .brand-mark {
            display: flex; align-items: center; gap: 10px; justify-content: center;
            margin-bottom: 22px; color: var(--brand-700); font-weight: 800; font-size: 1.15rem;
        }
        .auth-wrap .card { padding: 32px 30px; }
        .auth-wrap .card h2 { text-align: center; font-size: 1.3rem; }
        .auth-wrap .card p.muted { text-align: center; }
        .auth-wrap button { width: 100%; }
    </style>
</head>
<body>
<header>
    <a class="brand" href="/soma_cashflow/public/">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="6" fill="rgba(255,255,255,0.16)"/>
            <path d="M6 15L10 10L13 13L18 7" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M14 7H18V11" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Soma Cashflow
    </a>
    <nav>
        <?php if ($user): ?>
            <span class="hi-user">Hi, <?= h($user['name']) ?></span>
            <a href="/soma_cashflow/public/logout.php">Logout</a>
        <?php else: ?>
            <a href="/soma_cashflow/public/login.php">Login</a>
            <a class="cta" href="/soma_cashflow/public/register.php">Get started</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <?php foreach (flash_all() as $f): ?>
        <div class="flash <?= h($f['type']) ?>"><?= h($f['message']) ?></div>
    <?php endforeach; ?>

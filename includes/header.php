<?php
/** @var string $pageTitle */
/** @var string|null $pageSubtitle */
$user = current_user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

function nav_active(string $path, string $needle): string
{
    return str_contains($path, $needle) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Soma Cashflow') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-900: #052e2b;
            --brand-800: #073c37;
            --brand-700: #0d5c53;
            --brand-600: #0f7a6c;
            --brand-500: #14957f;
            --brand-400: #3dab97;
            --brand-100: #e3f5f1;
            --accent-500: #f59e0b;
            --accent-100: #fef3e0;
            --blue-500: #3b82f6;
            --blue-100: #dbeafe;
            --ink-900: #0f172a;
            --ink-700: #334155;
            --ink-600: #475569;
            --ink-400: #94a3b8;
            --bg: #f4f6f8;
            --surface: #ffffff;
            --border: #e6e9ee;
            --success-bg: #ecfdf5;
            --success-fg: #067647;
            --error-bg: #fef2f2;
            --error-fg: #b42318;
            --radius-xl: 20px;
            --radius-lg: 16px;
            --radius-md: 10px;
            --shadow-card: 0 1px 2px rgba(16,24,40,0.04), 0 4px 16px rgba(16,24,40,0.06);
            --shadow-lifted: 0 8px 30px rgba(5,46,43,0.12);
        }
        * { box-sizing: border-box; }
        html, body { max-width: 100%; overflow-x: hidden; }
        body {
            font-family: 'Inter', -apple-system, Segoe UI, Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink-900);
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- Marketing top bar (logged-out pages) ---------- */
        header.topbar {
            background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 55%, var(--brand-500) 100%);
            color: #fff;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(5,46,43,0.18);
        }
        .brand {
            display: flex; align-items: center; gap: 10px;
            color: #fff; text-decoration: none; font-weight: 800; font-size: 1.08rem; letter-spacing: -0.01em;
        }
        header.topbar nav { display: flex; align-items: center; gap: 6px; }
        header.topbar nav a {
            color: #fff; text-decoration: none; font-weight: 500; font-size: 0.9rem;
            padding: 8px 14px; border-radius: 8px; transition: background 0.15s ease;
        }
        header.topbar nav a:hover { background: rgba(255,255,255,0.14); }
        header.topbar nav a.cta { background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.28); }

        /* ---------- App shell (logged-in pages) ---------- */
        .app-shell { display: flex; min-height: 100vh; }
        .sidebar {
            width: 252px;
            background: linear-gradient(190deg, var(--brand-900) 0%, var(--brand-800) 60%, var(--brand-700) 100%);
            color: #fff;
            position: fixed; top: 0; left: 0; bottom: 0;
            padding: 22px 16px;
            display: flex; flex-direction: column;
            z-index: 10;
        }
        .sidebar .brand { padding: 6px 10px 22px; }
        .sidebar nav { display: flex; flex-direction: column; gap: 3px; flex: 1; }
        .sidebar nav a {
            display: flex; align-items: center; gap: 10px;
            color: rgba(255,255,255,0.82); text-decoration: none; font-weight: 500; font-size: 0.92rem;
            padding: 10px 12px; border-radius: 10px; transition: background 0.15s ease, color 0.15s ease;
        }
        .sidebar nav a:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .sidebar nav a.active { background: rgba(255,255,255,0.14); color: #fff; font-weight: 600; }
        .sidebar nav a .navicon { width: 18px; text-align: center; opacity: 0.9; }
        .sidebar .user-card {
            display: flex; align-items: center; gap: 10px;
            padding: 12px; border-radius: 12px; background: rgba(255,255,255,0.06);
            margin-top: 10px;
        }
        .sidebar .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-400), var(--accent-500));
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem; color: #fff; flex-shrink: 0;
        }
        .sidebar .user-card .u-name { font-size: 0.85rem; font-weight: 600; color: #fff; line-height: 1.2; }
        .sidebar .user-card .u-logout { font-size: 0.76rem; color: rgba(255,255,255,0.6); text-decoration: none; }
        .sidebar .user-card .u-logout:hover { color: #fff; }

        .app-main { margin-left: 252px; flex: 1; padding: 38px 44px 60px; max-width: 980px; }

        /* ---------- Mobile top bar (shown only when sidebar collapses) ---------- */
        .mobile-topbar {
            display: none;
            position: sticky; top: 0; z-index: 20;
            background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 100%);
            color: #fff; padding: 12px 16px; align-items: center; justify-content: space-between;
        }
        .mobile-topbar .brand { font-size: 1rem; }
        .hamburger-btn {
            background: rgba(255,255,255,0.14); border: none; color: #fff; width: 38px; height: 38px;
            border-radius: 9px; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
        }
        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(5,20,18,0.5); z-index: 15;
        }
        body.sidebar-open .sidebar-overlay { display: block; }
        body.sidebar-open .sidebar { transform: translateX(0); }

        /* ---------- Shared components ---------- */
        main.public-main { max-width: 680px; margin: 40px auto; padding: 0 20px 60px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-card);
            margin-bottom: 22px;
        }
        .card h2 { margin: 0 0 4px; font-size: 1.15rem; font-weight: 700; letter-spacing: -0.01em; }
        .card > p.muted:first-of-type { margin-top: 0; }
        .eyebrow {
            display: inline-block; background: var(--brand-100); color: var(--brand-700);
            font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
            padding: 4px 10px; border-radius: 20px; margin-bottom: 10px;
        }
        label { display: block; margin: 14px 0 6px; font-weight: 600; font-size: 0.85rem; color: var(--ink-600); }
        input[type=text], input[type=email], input[type=password], select {
            width: 100%; padding: 11px 13px; border: 1.5px solid var(--border); border-radius: var(--radius-md);
            font-size: 0.96rem; font-family: inherit; background: #fbfcfd;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        input:focus, select:focus {
            outline: none; border-color: var(--brand-500); box-shadow: 0 0 0 3px var(--brand-100); background: #fff;
        }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; }
        .form-grid .full { grid-column: 1 / -1; }
        button, .btn {
            margin-top: 20px; background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff; border: none; padding: 11px 22px; border-radius: var(--radius-md);
            font-size: 0.96rem; font-weight: 600; font-family: inherit; cursor: pointer;
            box-shadow: 0 1px 2px rgba(5,46,43,0.15); transition: transform 0.1s ease, box-shadow 0.15s ease;
            display: inline-block; text-decoration: none;
        }
        button:hover, .btn:hover { box-shadow: 0 4px 14px rgba(15,122,108,0.32); transform: translateY(-1px); }
        button:active, .btn:active { transform: translateY(0); }
        .flash {
            padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 16px;
            font-size: 0.88rem; font-weight: 500; border-left: 3px solid transparent;
        }
        .flash.success { background: var(--success-bg); color: var(--success-fg); border-left-color: var(--success-fg); }
        .flash.error { background: var(--error-bg); color: var(--error-fg); border-left-color: var(--error-fg); }
        .muted { color: var(--ink-600); font-size: 0.87rem; }
        table { width: 100%; border-collapse: collapse; }
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        @media (max-width: 640px) {
            .table-scroll table { min-width: 560px; }
        }
        table th {
            text-align: left; font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--ink-400); font-weight: 600; padding: 0 10px 10px; border-bottom: 1.5px solid var(--border);
        }
        table td { padding: 12px 10px; border-bottom: 1px solid var(--border); font-size: 0.92rem; }
        table tr:last-child td { border-bottom: none; }
        a.link { color: var(--brand-600); font-weight: 600; text-decoration: none; }
        a.link:hover { text-decoration: underline; }

        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
            padding: 18px; box-shadow: var(--shadow-card); min-width: 0;
        }
        .stat-card .stat-icon {
            width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center;
            font-size: 1rem; margin-bottom: 10px;
        }
        .stat-card .stat-label { font-size: 0.76rem; color: var(--ink-400); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
        .stat-card .stat-value { font-size: 1.25rem; font-weight: 800; margin-top: 2px; letter-spacing: -0.01em; overflow-wrap: anywhere; }

        .pill {
            display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .pill.income { background: var(--success-bg); color: var(--success-fg); }
        .pill.expense { background: var(--error-bg); color: var(--error-fg); }
        .pill.loan_received { background: var(--blue-100); color: #1d4ed8; }
        .pill.loan_given { background: var(--accent-100); color: #b45309; }

        .biz-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .biz-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
            padding: 20px; box-shadow: var(--shadow-card); text-decoration: none; color: inherit;
            transition: transform 0.12s ease, box-shadow 0.15s ease; display: block; min-width: 0;
        }
        .biz-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lifted); }
        .biz-card .biz-icon {
            width: 40px; height: 40px; border-radius: 10px; background: var(--brand-100);
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 12px;
        }
        .biz-card .biz-name { font-weight: 700; font-size: 1.02rem; color: var(--ink-900); overflow-wrap: anywhere; }
        .biz-card .biz-desc { color: var(--ink-600); font-size: 0.85rem; margin-top: 2px; min-height: 1.2em; overflow-wrap: anywhere; }
        .biz-card .biz-balance { margin-top: 14px; font-weight: 800; font-size: 1.15rem; overflow-wrap: anywhere; }

        .auth-wrap { max-width: 400px; margin: 60px auto 0; }
        .auth-wrap .brand-mark {
            display: flex; align-items: center; gap: 10px; justify-content: center;
            margin-bottom: 22px; color: var(--brand-700); font-weight: 800; font-size: 1.15rem;
        }
        .auth-wrap .card { padding: 32px 30px; }
        .auth-wrap .card h2 { text-align: center; font-size: 1.3rem; }
        .auth-wrap .card p.muted { text-align: center; }
        .auth-wrap button { width: 100%; }

        .hero {
            background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 55%, var(--brand-500) 100%);
            color: #fff; border-radius: var(--radius-xl); padding: 44px 36px; margin-bottom: 24px;
            position: relative; overflow: hidden;
        }
        .hero::after {
            content: ""; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px;
            background: rgba(255,255,255,0.08); border-radius: 50%;
        }
        .hero h1 { font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 10px; position: relative; }
        .hero p { color: rgba(255,255,255,0.85); font-size: 0.98rem; max-width: 460px; margin: 0 0 20px; position: relative; }
        .hero .btn { background: #fff; color: var(--brand-700); position: relative; }
        .hero .btn:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.2); }

        .feature-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 24px; }
        .feature-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px; }
        .feature-card .f-icon { font-size: 1.3rem; margin-bottom: 8px; }
        .feature-card h3 { font-size: 0.94rem; margin: 0 0 4px; }
        .feature-card p { font-size: 0.82rem; color: var(--ink-600); margin: 0; }

        @media (max-width: 860px) {
            .sidebar {
                display: flex;
                transform: translateX(-100%);
                transition: transform 0.22s ease;
                z-index: 25;
                box-shadow: 4px 0 24px rgba(0,0,0,0.25);
            }
            .mobile-topbar { display: flex; }
            .app-main { margin-left: 0; padding: 22px 18px 50px; }
            .stat-grid, .biz-grid, .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .form-grid { grid-template-columns: minmax(0, 1fr); }
            .hero { padding: 30px 22px; }
            .sidebar-close-btn { display: block !important; }
        }
        @media (max-width: 520px) {
            .stat-grid, .biz-grid, .feature-grid { grid-template-columns: minmax(0, 1fr); }
            .card { padding: 20px 16px; }
            .hero { padding: 24px 18px; }
            .hero h1 { font-size: 1.4rem; }
            .auth-wrap { margin-top: 30px; }
            .auth-wrap .card { padding: 24px 20px; }
        }
    </style>
</head>
<body>
<?php if ($user): ?>
<div class="mobile-topbar">
    <a class="brand" href="/soma_cashflow/public/dashboard.php">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="6" fill="rgba(255,255,255,0.16)"/>
            <path d="M6 15L10 10L13 13L18 7" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M14 7H18V11" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Soma Cashflow
    </a>
    <button class="hamburger-btn" onclick="document.body.classList.add('sidebar-open')" aria-label="Open menu">&#9776;</button>
</div>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<div class="app-shell">
    <aside class="sidebar" aria-label="Main navigation">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <a class="brand" href="/soma_cashflow/public/dashboard.php">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="24" height="24" rx="6" fill="rgba(255,255,255,0.16)"/>
                    <path d="M6 15L10 10L13 13L18 7" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M14 7H18V11" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Soma Cashflow
            </a>
            <button onclick="document.body.classList.remove('sidebar-open')" aria-label="Close menu"
                    style="display:none; background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer;"
                    class="sidebar-close-btn">&times;</button>
        </div>
        <nav>
            <a class="<?= nav_active((string) $currentPath, 'dashboard.php') ?>" href="/soma_cashflow/public/dashboard.php">
                <span class="navicon">&#9635;</span> Dashboard
            </a>
            <a class="<?= nav_active((string) $currentPath, 'business.php') ?>" href="/soma_cashflow/public/dashboard.php">
                <span class="navicon">&#127970;</span> Businesses
            </a>
        </nav>
        <div class="user-card">
            <div class="avatar"><?= h(strtoupper(substr($user['name'], 0, 1))) ?></div>
            <div style="flex:1; min-width:0;">
                <div class="u-name"><?= h($user['name']) ?></div>
                <a class="u-logout" href="/soma_cashflow/public/logout.php">Log out</a>
            </div>
        </div>
    </aside>
    <main class="app-main">
<?php else: ?>
<header class="topbar">
    <a class="brand" href="/soma_cashflow/public/">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="6" fill="rgba(255,255,255,0.16)"/>
            <path d="M6 15L10 10L13 13L18 7" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M14 7H18V11" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Soma Cashflow
    </a>
    <nav>
        <a href="/soma_cashflow/public/login.php">Login</a>
        <a class="cta" href="/soma_cashflow/public/register.php">Get started</a>
    </nav>
</header>
<main class="public-main">
<?php endif; ?>
    <?php foreach (flash_all() as $f): ?>
        <div class="flash <?= h($f['type']) ?>"><?= h($f['message']) ?></div>
    <?php endforeach; ?>

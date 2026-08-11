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
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f5f6f8; color: #1a1a1a; margin: 0; }
        header { background: #14532d; color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; font-weight: 600; }
        header nav a { margin-left: 16px; font-weight: 400; opacity: 0.9; }
        main { max-width: 640px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        label { display: block; margin: 12px 0 4px; font-weight: 600; font-size: 0.9rem; }
        input[type=text], input[type=email], input[type=password] {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem;
        }
        button {
            margin-top: 16px; background: #14532d; color: #fff; border: none; padding: 10px 18px;
            border-radius: 6px; font-size: 1rem; cursor: pointer;
        }
        button:hover { background: #0f3d21; }
        .flash { padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 0.9rem; }
        .flash.success { background: #dcfce7; color: #166534; }
        .flash.error { background: #fee2e2; color: #991b1b; }
        .muted { color: #666; font-size: 0.85rem; }
        table { width: 100%; border-collapse: collapse; }
        table td, table th { padding: 8px; border-bottom: 1px solid #eee; text-align: left; }
        a.link { color: #14532d; }
    </style>
</head>
<body>
<header>
    <a href="/soma_cashflow/public/">Soma Cashflow</a>
    <nav>
        <?php if ($user): ?>
            <span class="muted" style="color:#cde; margin-right:10px;">Hi, <?= h($user['name']) ?></span>
            <a href="/soma_cashflow/public/logout.php">Logout</a>
        <?php else: ?>
            <a href="/soma_cashflow/public/login.php">Login</a>
            <a href="/soma_cashflow/public/register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <?php foreach (flash_all() as $f): ?>
        <div class="flash <?= h($f['type']) ?>"><?= h($f['message']) ?></div>
    <?php endforeach; ?>

<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../config/database.php';

$tables = ['users', 'organizations', 'businesses'];
$status = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM {$table}");
        $count = $stmt->fetch()['c'];
        $status[$table] = ['ok' => true, 'count' => $count];
    } catch (PDOException $e) {
        $status[$table] = ['ok' => false, 'error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Soma Cashflow - Setup Check</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; color: #222; }
        h1 { font-size: 1.4rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        td, th { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .ok { color: #1a7f37; font-weight: bold; }
        .fail { color: #cf222e; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Soma Cashflow &mdash; Phase 0 Setup Check</h1>
    <p>Database connection: <span class="ok">OK</span></p>
    <table>
        <tr><th>Table</th><th>Status</th><th>Rows</th></tr>
        <?php foreach ($status as $table => $info): ?>
        <tr>
            <td><?= htmlspecialchars($table) ?></td>
            <td><?= $info['ok'] ? '<span class="ok">OK</span>' : '<span class="fail">MISSING</span>' ?></td>
            <td><?= $info['ok'] ? (int)$info['count'] : htmlspecialchars($info['error']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p>If all three tables show OK, Phase 0 is complete.</p>
</body>
</html>

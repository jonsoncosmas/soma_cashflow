<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
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

$allOk = !in_array(false, array_column($status, 'ok'), true);
$pageTitle = 'Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <h2>Track every shilling, across every business</h2>
    <p class="muted">Personal income, business ledgers, savings growth, and multi-user access &mdash; all in one place.</p>
    <?php if (!current_user()): ?>
        <a href="/soma_cashflow/public/register.php"><button type="button">Get started</button></a>
    <?php else: ?>
        <a href="/soma_cashflow/public/dashboard.php"><button type="button">Go to dashboard</button></a>
    <?php endif; ?>
</div>

<div class="card">
    <h2>System status</h2>
    <p class="muted">Database connection: <strong style="color:var(--success-fg)">Connected</strong></p>
    <table>
        <tr><th>Table</th><th>Status</th><th>Rows</th></tr>
        <?php foreach ($status as $table => $info): ?>
        <tr>
            <td><?= h($table) ?></td>
            <td>
                <?php if ($info['ok']): ?>
                    <span style="color:var(--success-fg); font-weight:600;">OK</span>
                <?php else: ?>
                    <span style="color:var(--error-fg); font-weight:600;">MISSING</span>
                <?php endif; ?>
            </td>
            <td><?= $info['ok'] ? (int) $info['count'] : h($info['error']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

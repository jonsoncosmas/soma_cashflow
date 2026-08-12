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
<div class="hero">
    <span class="eyebrow" style="background:rgba(255,255,255,0.16); color:#fff;">Finance tracking, built for real businesses</span>
    <h1>Track every shilling, across every business you run</h1>
    <p>Personal income, business ledgers, inter-business funding, savings growth, and multi-user access &mdash; all in one place, offline-ready.</p>
    <?php if (!current_user()): ?>
        <a class="btn" href="/soma_cashflow/public/register.php">Get started free</a>
    <?php else: ?>
        <a class="btn" href="/soma_cashflow/public/dashboard.php">Go to dashboard</a>
    <?php endif; ?>
</div>

<div class="feature-grid">
    <div class="feature-card">
        <div class="f-icon">🏢</div>
        <h3>Multiple businesses</h3>
        <p>Track each business separately, then see them combined.</p>
    </div>
    <div class="feature-card">
        <div class="f-icon">🔁</div>
        <h3>Fund flows</h3>
        <p>Move money between businesses or from personal income.</p>
    </div>
    <div class="feature-card">
        <div class="f-icon">📊</div>
        <h3>Real statements</h3>
        <p>Income statements and cash flow, any date range.</p>
    </div>
</div>

<?php if (!$allOk): ?>
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
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

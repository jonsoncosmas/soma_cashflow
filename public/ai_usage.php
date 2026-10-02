<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/ai_categorizer.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';

$providers = ['openrouter', 'openai', 'anthropic'];

// Aggregate today's usage across all providers into one unified figure -
// which specific backend is doing the work is an internal implementation
// detail, never surfaced here.
$totalRequestsToday = 0;
$totalTokensToday = 0;
$anyConfigured = false;
$allConfiguredAreCapped = true;

foreach ($providers as $p) {
    $isConfigured = !empty($config['ai'][$p . '_api_key']) || ($p === 'openrouter' && !empty($config['ai']['openrouter_models']));
    if (!$isConfigured) {
        continue;
    }
    $anyConfigured = true;
    $usage = ai_get_daily_usage($pdo, $p);
    $totalRequestsToday += $usage['requests'];
    $totalTokensToday += $usage['tokens'];
    if (!ai_provider_capped($pdo, $p, $config)) {
        $allConfiguredAreCapped = false;
    }
}
$fullyUnavailable = $anyConfigured && $allConfiguredAreCapped;

// Last 14 days, aggregated across all providers per day (no provider column).
$stmt = $pdo->query(
    "SELECT DATE(created_at) AS day,
            COUNT(*) AS requests,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS successes,
            COALESCE(SUM(openrouter_input_tokens + openrouter_output_tokens), 0)
                + COALESCE(SUM(openai_input_tokens + openai_output_tokens), 0)
                + COALESCE(SUM(anthropic_input_tokens + anthropic_output_tokens), 0) AS tokens
     FROM ai_suggestions_log
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day DESC"
);
$history = $stmt->fetchAll();

$pageTitle = 'ORION AI Usage - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <span class="eyebrow">ORION</span>
    <h2 style="margin-bottom:2px;">ORION AI usage</h2>
    <p class="muted" style="margin-top:2px;">Category suggestions made by ORION, the AI assistant built into your transaction forms.</p>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: <?= $fullyUnavailable ? 'var(--error-bg)' : 'var(--brand-100)' ?>;">
            <?= $fullyUnavailable ? '⛔' : '🤖' ?>
        </div>
        <div class="stat-label">Requests today</div>
        <?php if (!$anyConfigured): ?>
            <div class="stat-value muted" style="font-size:0.9rem;">ORION not configured</div>
        <?php else: ?>
            <div class="stat-value" style="font-size:1.3rem;"><?= (int) $totalRequestsToday ?></div>
            <?php if ($fullyUnavailable): ?>
                <p style="margin:6px 0 0; font-size:0.8rem; font-weight:700; color:var(--error-fg);">Temporarily unavailable &mdash; try again tomorrow</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-100);">📊</div>
        <div class="stat-label">Volume today</div>
        <div class="stat-value" style="font-size:1.3rem;"><?= number_format($totalTokensToday) ?></div>
        <p class="muted" style="margin:4px 0 0; font-size:0.8rem;">usage units</p>
    </div>
</div>

<div class="card">
    <h2>Last 14 days</h2>
    <?php if (!$history): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">🤖</div>
            <p class="muted" style="margin:0;">No ORION activity logged yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Date</th><th style="text-align:right;">Requests</th><th style="text-align:right;">Succeeded</th><th style="text-align:right;">Volume</th></tr>
            <?php foreach ($history as $row): ?>
            <tr>
                <td><?= h($row['day']) ?></td>
                <td style="text-align:right;"><?= (int) $row['requests'] ?></td>
                <td style="text-align:right;"><?= (int) $row['successes'] ?></td>
                <td style="text-align:right;"><?= number_format((float) $row['tokens']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

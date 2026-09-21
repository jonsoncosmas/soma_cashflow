<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/ai_categorizer.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';

$providers = ['openrouter', 'openai', 'anthropic'];
$providerLabels = ['openrouter' => 'OpenRouter', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic'];
$dashboardLinks = [
    'openrouter' => 'https://openrouter.ai/activity',
    'openai'     => 'https://platform.openai.com/usage',
    'anthropic'  => 'https://console.anthropic.com/settings/usage',
];

$today = [];
foreach ($providers as $p) {
    $usage = ai_get_daily_usage($pdo, $p);
    $caps = $config['ai']['daily_caps'][$p] ?? null;
    $requestCap = $caps['requests'] ?? null;
    $tokenCap = $caps['tokens'] ?? null;
    $today[$p] = [
        'requests' => $usage['requests'],
        'tokens' => $usage['tokens'],
        'request_cap' => $requestCap,
        'token_cap' => $tokenCap,
        'capped' => ai_provider_capped($pdo, $p, $config),
        'configured' => !empty($config['ai'][$p . '_api_key']) || ($p === 'openrouter' && !empty($config['ai']['openrouter_models'])),
    ];
}

// Last 14 days, per provider, per day
$stmt = $pdo->query(
    "SELECT DATE(created_at) AS day, provider,
            COUNT(*) AS requests,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS successes,
            COALESCE(SUM(openrouter_input_tokens + openrouter_output_tokens), 0)
                + COALESCE(SUM(openai_input_tokens + openai_output_tokens), 0)
                + COALESCE(SUM(anthropic_input_tokens + anthropic_output_tokens), 0) AS tokens
     FROM ai_suggestions_log
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at), provider
     ORDER BY day DESC, provider"
);
$history = $stmt->fetchAll();

$pageTitle = 'AI Usage - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <span class="eyebrow">AI Usage</span>
    <h2 style="margin-bottom:2px;">Usage &amp; reconciliation</h2>
    <p class="muted" style="margin-top:2px;">What's logged here is very reliable, but not gospel to the last token &mdash; a request can occasionally fail to log if the connection drops after a provider already processed it. Periodically spot-check these numbers against each provider's own dashboard, especially before trusting the caps below as a hard guarantee.</p>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom:24px;">
    <?php foreach ($providers as $p): $t = $today[$p]; ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: <?= $t['capped'] ? 'var(--error-bg)' : 'var(--brand-100)' ?>;">
            <?= $t['capped'] ? '⛔' : '🤖' ?>
        </div>
        <div class="stat-label"><?= h($providerLabels[$p]) ?></div>
        <?php if (!$t['configured']): ?>
            <div class="stat-value muted" style="font-size:0.9rem;">Not configured</div>
        <?php else: ?>
            <div class="stat-value" style="font-size:1.1rem;">
                <?= (int) $t['requests'] ?><?= $t['request_cap'] !== null ? ' / ' . (int) $t['request_cap'] : '' ?> requests today
            </div>
            <?php if ($t['token_cap'] !== null): ?>
                <p class="muted" style="margin:4px 0 0; font-size:0.8rem;"><?= number_format($t['tokens']) ?> / <?= number_format($t['token_cap']) ?> tokens</p>
            <?php else: ?>
                <p class="muted" style="margin:4px 0 0; font-size:0.8rem;"><?= number_format($t['tokens']) ?> tokens today</p>
            <?php endif; ?>
            <?php if ($t['capped']): ?>
                <p style="margin:6px 0 0; font-size:0.8rem; font-weight:700; color:var(--error-fg);">Daily cap reached</p>
            <?php elseif ($t['request_cap'] === null && $t['token_cap'] === null): ?>
                <p class="muted" style="margin:6px 0 0; font-size:0.78rem;">No cap set</p>
            <?php endif; ?>
        <?php endif; ?>
        <a class="link" style="display:block; margin-top:10px; font-size:0.8rem;" href="<?= h($dashboardLinks[$p]) ?>" target="_blank" rel="noopener">Check <?= h($providerLabels[$p]) ?>'s own dashboard &rarr;</a>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Last 14 days</h2>
    <?php if (!$history): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">🤖</div>
            <p class="muted" style="margin:0;">No AI suggestions logged yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Date</th><th>Provider</th><th style="text-align:right;">Requests</th><th style="text-align:right;">Succeeded</th><th style="text-align:right;">Tokens</th></tr>
            <?php foreach ($history as $row): ?>
            <tr>
                <td><?= h($row['day']) ?></td>
                <td><?= h($providerLabels[$row['provider']] ?? ($row['provider'] ?: 'none/failed')) ?></td>
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

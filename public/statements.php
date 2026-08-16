<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/statement_helpers.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$stmt = $pdo->prepare('SELECT id, name FROM organizations WHERE owner_user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$org = $stmt->fetch();

$businesses = [];
if ($org) {
    $stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE organization_id = ? ORDER BY name');
    $stmt->execute([$org['id']]);
    $businesses = $stmt->fetchAll();
}

// ---- Resolve scope ----
$scopeParam = (string) ($_GET['scope'] ?? 'all');
$scopeType = 'all';
$scopeBusinessId = null;
$scopeLabel = 'All (combined)';

if ($scopeParam === 'personal') {
    $scopeType = 'personal';
    $scopeLabel = 'Personal';
} elseif (str_starts_with($scopeParam, 'business:')) {
    $bid = (int) substr($scopeParam, 9);
    foreach ($businesses as $b) {
        if ((int) $b['id'] === $bid) {
            $scopeType = 'business';
            $scopeBusinessId = $bid;
            $scopeLabel = $b['name'];
            break;
        }
    }
}

// ---- Resolve date range ----
$preset = (string) ($_GET['preset'] ?? 'month');
$range = resolve_date_range($preset, $_GET['start'] ?? null, $_GET['end'] ?? null);

$presetLabels = [
    'week' => 'This week', 'month' => 'This month', 'quarter' => 'This quarter',
    'half' => 'This half-year', 'year' => 'This year', 'custom' => 'Custom range',
];

$pageTitle = 'Statements - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <span class="eyebrow">Statements</span>
    <h2 style="margin-bottom:2px;">Financial statements</h2>
    <p class="muted" style="margin-top:2px;">Income statement and cash flow, for any date range. Share this page's URL to give an accountant a specific report.</p>

    <form method="get" action="/soma_cashflow/public/statements.php" style="margin-top:16px;">
        <div class="form-grid">
            <div>
                <label for="scope">Scope</label>
                <select id="scope" name="scope">
                    <option value="all" <?= $scopeParam === 'all' ? 'selected' : '' ?>>All (combined)</option>
                    <option value="personal" <?= $scopeParam === 'personal' ? 'selected' : '' ?>>Personal</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="business:<?= (int) $b['id'] ?>" <?= $scopeParam === 'business:' . $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="preset">Period</label>
                <select id="preset" name="preset">
                    <?php foreach ($presetLabels as $val => $label): ?>
                        <option value="<?= h($val) ?>" <?= $range['preset'] === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="start">Start date</label>
                <input type="text" id="start" name="start" value="<?= h($range['start']) ?>" onfocus="this.type='date'" onblur="if(!this.value)this.type='text'">
            </div>
            <div>
                <label for="end">End date</label>
                <input type="text" id="end" name="end" value="<?= h($range['end']) ?>" onfocus="this.type='date'" onblur="if(!this.value)this.type='text'">
            </div>
        </div>
        <button type="submit">Generate statement</button>
    </form>
</div>

<div class="card" style="background: var(--brand-100); border-color: var(--brand-500);">
    <p class="muted" style="margin:0; color:var(--brand-700); font-weight:600;">
        <?= h($scopeLabel) ?> &middot; <?= h($range['label']) ?>
    </p>
</div>

<?php if ($scopeType === 'all'): ?>
    <?php
    $ci = combined_income_statement($pdo, $org['id'], $user['id'], $range['start'], $range['end']);
    $openingNW = combined_net_worth_as_of($pdo, $org['id'], $user['id'], day_before($range['start']));
    $closingNW = combined_net_worth_as_of($pdo, $org['id'], $user['id'], $range['end']);
    $transferVolume = combined_internal_transfer_volume($pdo, $org['id'], $range['start'], $range['end']);
    ?>
    <div class="card">
        <h2>Income statement by entity</h2>
        <div class="table-scroll">
        <table>
            <tr><th>Entity</th><th style="text-align:right;">Income</th><th style="text-align:right;">Expenses</th><th style="text-align:right;">Net</th></tr>
            <?php foreach ($ci['rows'] as $r): ?>
            <tr>
                <td><?= h($r['entity']) ?></td>
                <td style="text-align:right; color:var(--success-fg);">+<?= number_format($r['income'], 2) ?></td>
                <td style="text-align:right; color:var(--error-fg);">-<?= number_format($r['expense'], 2) ?></td>
                <td style="text-align:right; font-weight:700; color: <?= $r['net'] >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;"><?= number_format($r['net'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="font-weight:800; background:#fafbfc;">
                <td>Total</td>
                <td style="text-align:right; color:var(--success-fg);">+<?= number_format($ci['total_income'], 2) ?></td>
                <td style="text-align:right; color:var(--error-fg);">-<?= number_format($ci['total_expense'], 2) ?></td>
                <td style="text-align:right; color: <?= $ci['total_net'] >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;"><?= number_format($ci['total_net'], 2) ?></td>
            </tr>
        </table>
        </div>
    </div>

    <div class="card">
        <h2>Net worth</h2>
        <table>
            <tr><td>Opening net worth (as of <?= h(day_before($range['start'])) ?>)</td><td style="text-align:right;"><?= number_format($openingNW, 2) ?></td></tr>
            <tr><td>Change during period</td><td style="text-align:right; font-weight:700; color: <?= ($closingNW - $openingNW) >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;"><?= number_format($closingNW - $openingNW, 2) ?></td></tr>
            <tr style="font-weight:800;"><td>Closing net worth (as of <?= h($range['end']) ?>)</td><td style="text-align:right;"><?= number_format($closingNW, 2) ?></td></tr>
        </table>
        <p class="muted" style="margin-top:14px; margin-bottom:0;">
            Internal transfers moved within your organization this period: <?= number_format($transferVolume, 2) ?> total volume.
            These are eliminated from the combined income statement above since they're not real income or expense &mdash; just money moving between your own entities.
        </p>
    </div>

<?php else: ?>
    <?php
    $income = $scopeType === 'personal'
        ? personal_income_statement($pdo, $user['id'], $range['start'], $range['end'])
        : business_income_statement($pdo, $scopeBusinessId, $range['start'], $range['end']);
    $cf = $scopeType === 'personal'
        ? personal_cash_flow($pdo, $user['id'], $range['start'], $range['end'])
        : business_cash_flow($pdo, $scopeBusinessId, $range['start'], $range['end']);
    ?>
    <div class="card">
        <h2>Income statement</h2>
        <?php if (!$income['income'] && !$income['expense']): ?>
            <p class="muted">No income or expenses recorded in this period.</p>
        <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Category</th><th style="text-align:right;">Amount</th></tr>
            <?php if ($income['income']): ?>
                <tr style="background:#fafbfc;"><td colspan="2" style="font-weight:700;">Income</td></tr>
                <?php foreach ($income['income'] as $cat => $amt): ?>
                <tr><td style="padding-left:24px;"><?= h($cat) ?></td><td style="text-align:right; color:var(--success-fg);">+<?= number_format($amt, 2) ?></td></tr>
                <?php endforeach; ?>
                <tr><td style="font-weight:700;">Total income</td><td style="text-align:right; font-weight:700; color:var(--success-fg);">+<?= number_format($income['income_total'], 2) ?></td></tr>
            <?php endif; ?>
            <?php if ($income['expense']): ?>
                <tr style="background:#fafbfc;"><td colspan="2" style="font-weight:700;">Expenses</td></tr>
                <?php foreach ($income['expense'] as $cat => $amt): ?>
                <tr><td style="padding-left:24px;"><?= h($cat) ?></td><td style="text-align:right; color:var(--error-fg);">-<?= number_format($amt, 2) ?></td></tr>
                <?php endforeach; ?>
                <tr><td style="font-weight:700;">Total expenses</td><td style="text-align:right; font-weight:700; color:var(--error-fg);">-<?= number_format($income['expense_total'], 2) ?></td></tr>
            <?php endif; ?>
            <tr style="font-weight:800; border-top:2px solid var(--border);">
                <td>Net income</td>
                <td style="text-align:right; color: <?= $income['net'] >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;"><?= number_format($income['net'], 2) ?></td>
            </tr>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Cash flow statement</h2>
        <div class="table-scroll">
        <table>
            <tr style="background:#fafbfc;"><td colspan="2" style="font-weight:700;">Operating activities</td></tr>
            <tr><td style="padding-left:24px;">Cash from income</td><td style="text-align:right; color:var(--success-fg);">+<?= number_format($cf['operatingIn'], 2) ?></td></tr>
            <tr><td style="padding-left:24px;">Cash used in expenses</td><td style="text-align:right; color:var(--error-fg);">-<?= number_format($cf['operatingOut'], 2) ?></td></tr>
            <tr><td style="font-weight:700;">Net operating cash flow</td><td style="text-align:right; font-weight:700;"><?= number_format($cf['operatingNet'], 2) ?></td></tr>

            <tr style="background:#fafbfc;"><td colspan="2" style="font-weight:700;">Financing activities</td></tr>
            <?php if ($scopeType === 'business'): ?>
            <tr><td style="padding-left:24px;">Loans received</td><td style="text-align:right; color:var(--success-fg);">+<?= number_format($cf['loanReceived'], 2) ?></td></tr>
            <tr><td style="padding-left:24px;">Loans given</td><td style="text-align:right; color:var(--error-fg);">-<?= number_format($cf['loanGiven'], 2) ?></td></tr>
            <?php endif; ?>
            <tr><td style="padding-left:24px;">Transfers in</td><td style="text-align:right; color:var(--success-fg);">+<?= number_format($cf['transferIn'], 2) ?></td></tr>
            <tr><td style="padding-left:24px;">Transfers out</td><td style="text-align:right; color:var(--error-fg);">-<?= number_format($cf['transferOut'], 2) ?></td></tr>
            <tr><td style="font-weight:700;">Net financing cash flow</td><td style="text-align:right; font-weight:700;"><?= number_format($cf['financingNet'], 2) ?></td></tr>

            <tr style="font-weight:800; border-top:2px solid var(--border);">
                <td>Net change in cash</td>
                <td style="text-align:right; color: <?= $cf['netChange'] >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;"><?= number_format($cf['netChange'], 2) ?></td>
            </tr>
            <tr><td>Opening balance (<?= h(day_before($range['start'])) ?>)</td><td style="text-align:right;"><?= number_format($cf['opening'], 2) ?></td></tr>
            <tr style="font-weight:800;"><td>Closing balance (<?= h($range['end']) ?>)</td><td style="text-align:right;"><?= number_format($cf['closing'], 2) ?></td></tr>
        </table>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

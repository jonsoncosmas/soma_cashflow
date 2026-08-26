<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/statement_helpers.php';
require __DIR__ . '/../includes/access_control.php';
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

$sharedBusinesses = get_shared_businesses($pdo, $user['id']);

// All selectable entity keys, in display order: personal first (owner only),
// then owned businesses, then businesses shared with this user.
$allEntityKeys = [];
if ($org) {
    $allEntityKeys[] = 'personal';
}
foreach ($businesses as $b) {
    $allEntityKeys[] = 'business:' . $b['id'];
}
foreach ($sharedBusinesses as $sb) {
    $allEntityKeys[] = 'business:' . $sb['id'];
}

$submitted = isset($_GET['submitted']);
$selectedEntityKeys = $submitted ? (array) ($_GET['entities'] ?? []) : $allEntityKeys;
$selectedSections   = $submitted ? (array) ($_GET['sections'] ?? []) : ['income', 'cashflow'];

$errors = [];
if ($submitted && !$selectedEntityKeys) {
    $errors[] = 'Select at least one entity (Personal or a business).';
}
if ($submitted && !$selectedSections) {
    $errors[] = 'Select at least one statement type.';
}

// Resolve selected entity keys into entity objects, preserving display order.
$allBusinessesById = [];
foreach ($businesses as $b) { $allBusinessesById[(int) $b['id']] = $b['name']; }
foreach ($sharedBusinesses as $sb) { $allBusinessesById[(int) $sb['id']] = $sb['name']; }

$entities = [];
foreach ($allEntityKeys as $key) {
    if (!in_array($key, $selectedEntityKeys, true)) {
        continue;
    }
    if ($key === 'personal') {
        $entities[] = ['type' => 'personal', 'key' => 'personal'];
    } else {
        $bid = (int) substr($key, 9);
        if (isset($allBusinessesById[$bid])) {
            $entities[] = ['type' => 'business', 'id' => $bid, 'name' => $allBusinessesById[$bid], 'key' => $key];
        }
    }
}

$showIncome = in_array('income', $selectedSections, true);
$showCashflow = in_array('cashflow', $selectedSections, true);

// ---- Resolve date range ----
$preset = (string) ($_GET['preset'] ?? 'month');
$range = resolve_date_range($preset, $_GET['start'] ?? null, $_GET['end'] ?? null);

$presetLabels = [
    'week' => 'This week', 'month' => 'This month', 'quarter' => 'This quarter',
    'half' => 'This half-year', 'year' => 'This year', 'custom' => 'Custom range',
];

$scopeLabel = count($entities) === 1 ? entity_label($entities[0]) : (count($entities) . ' entities combined');
$isAllEntities = count($entities) === count($allEntityKeys);

$pageTitle = 'Statements - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <span class="eyebrow">Statements</span>
    <h2 style="margin-bottom:2px;">Financial statements</h2>
    <p class="muted" style="margin-top:2px;">Choose which entities and which statement types to include &mdash; generate them separately or combined, whichever you need.</p>

    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>

    <form method="get" action="/soma_cashflow/public/statements.php" style="margin-top:16px;">
        <input type="hidden" name="submitted" value="1">

        <label>Entities</label>
        <div style="display:flex; flex-wrap:wrap; gap:8px 18px; padding:10px 0;">
            <?php if ($org): ?>
            <label style="display:flex; align-items:center; gap:6px; font-weight:500; color:var(--ink-900); margin:0;">
                <input type="checkbox" name="entities[]" value="personal" <?= in_array('personal', $selectedEntityKeys, true) ? 'checked' : '' ?> style="width:auto;"> 👤 Personal
            </label>
            <?php endif; ?>
            <?php foreach ($businesses as $b): ?>
            <label style="display:flex; align-items:center; gap:6px; font-weight:500; color:var(--ink-900); margin:0;">
                <input type="checkbox" name="entities[]" value="business:<?= (int) $b['id'] ?>" <?= in_array('business:' . $b['id'], $selectedEntityKeys, true) ? 'checked' : '' ?> style="width:auto;"> 🏢 <?= h($b['name']) ?>
            </label>
            <?php endforeach; ?>
            <?php foreach ($sharedBusinesses as $sb): ?>
            <label style="display:flex; align-items:center; gap:6px; font-weight:500; color:var(--ink-900); margin:0;">
                <input type="checkbox" name="entities[]" value="business:<?= (int) $sb['id'] ?>" <?= in_array('business:' . $sb['id'], $selectedEntityKeys, true) ? 'checked' : '' ?> style="width:auto;"> 🤝 <?= h($sb['name']) ?> <span class="muted" style="font-weight:400;">(<?= h($sb['org_name']) ?>)</span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php if (!$org && !$sharedBusinesses): ?>
            <p class="muted">No businesses available yet.</p>
        <?php endif; ?>

        <label>Statement types</label>
        <div style="display:flex; flex-wrap:wrap; gap:8px 18px; padding:10px 0;">
            <label style="display:flex; align-items:center; gap:6px; font-weight:500; color:var(--ink-900); margin:0;">
                <input type="checkbox" name="sections[]" value="income" <?= $showIncome ? 'checked' : '' ?> style="width:auto;"> Income Statement
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-weight:500; color:var(--ink-900); margin:0;">
                <input type="checkbox" name="sections[]" value="cashflow" <?= $showCashflow ? 'checked' : '' ?> style="width:auto;"> Cash Flow Statement
            </label>
        </div>

        <div class="form-grid">
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
                <input type="date" id="start" name="start" value="<?= h($range['start']) ?>">
            </div>
            <div>
                <label for="end">End date</label>
                <input type="date" id="end" name="end" value="<?= h($range['end']) ?>">
            </div>
        </div>
        <button type="submit">Generate statement</button>
    </form>
</div>

<?php if ($entities && ($showIncome || $showCashflow)): ?>

<div class="card" style="background: var(--brand-100); border-color: var(--brand-500); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <p class="muted" style="margin:0; color:var(--brand-700); font-weight:600;">
        <?= h($scopeLabel) ?> &middot; <?= h($range['label']) ?>
    </p>
    <a class="btn" style="margin-top:0;"
       href="/soma_cashflow/public/statement_pdf.php?<?= http_build_query(['entities' => array_column($entities, 'key'), 'sections' => $selectedSections, 'preset' => $range['preset'], 'start' => $range['start'], 'end' => $range['end']]) ?>">
        &#128190; Download PDF
    </a>
</div>

<?php if (count($entities) > 1): ?>
    <?php
    $ci = combined_income_statement($pdo, $entities, $user['id'], $range['start'], $range['end']);
    $openingNW = combined_net_worth_as_of($pdo, $entities, $user['id'], day_before($range['start']));
    $closingNW = combined_net_worth_as_of($pdo, $entities, $user['id'], $range['end']);
    $transferVolume = combined_transfer_volume($pdo, $entities, $user['id'], $range['start'], $range['end']);
    ?>
    <?php if ($showIncome): ?>
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
    <?php endif; ?>

    <?php if ($showCashflow): ?>
    <div class="card">
        <h2>Combined cash position</h2>
        <table>
            <tr><td>Opening (as of <?= h(day_before($range['start'])) ?>)</td><td style="text-align:right;"><?= number_format($openingNW, 2) ?></td></tr>
            <tr><td>Change during period</td><td style="text-align:right; font-weight:700; color: <?= ($closingNW - $openingNW) >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;"><?= number_format($closingNW - $openingNW, 2) ?></td></tr>
            <tr style="font-weight:800;"><td>Closing (as of <?= h($range['end']) ?>)</td><td style="text-align:right;"><?= number_format($closingNW, 2) ?></td></tr>
        </table>
        <p class="muted" style="margin-top:14px; margin-bottom:0;">
            <?php if ($isAllEntities): ?>
                Internal transfers within your organization this period: <?= number_format($transferVolume, 2) ?> total volume. These are eliminated from the income statement above since they're not real income or expense &mdash; just money moving between your own entities.
            <?php else: ?>
                Transfers touching this group of entities this period: <?= number_format($transferVolume, 2) ?> total volume (includes any transfers to/from entities outside this selection).
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

<?php else: ?>
    <?php
    $entity = $entities[0];
    $income = entity_income_statement($pdo, $entity, $user['id'], $range['start'], $range['end']);
    $cf = $entity['type'] === 'personal'
        ? personal_cash_flow($pdo, $user['id'], $range['start'], $range['end'])
        : business_cash_flow($pdo, (int) $entity['id'], $range['start'], $range['end']);
    ?>
    <?php if ($showIncome): ?>
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
    <?php endif; ?>

    <?php if ($showCashflow): ?>
    <div class="card">
        <h2>Cash flow statement</h2>
        <div class="table-scroll">
        <table>
            <tr style="background:#fafbfc;"><td colspan="2" style="font-weight:700;">Operating activities</td></tr>
            <tr><td style="padding-left:24px;">Cash from income</td><td style="text-align:right; color:var(--success-fg);">+<?= number_format($cf['operatingIn'], 2) ?></td></tr>
            <tr><td style="padding-left:24px;">Cash used in expenses</td><td style="text-align:right; color:var(--error-fg);">-<?= number_format($cf['operatingOut'], 2) ?></td></tr>
            <tr><td style="font-weight:700;">Net operating cash flow</td><td style="text-align:right; font-weight:700;"><?= number_format($cf['operatingNet'], 2) ?></td></tr>

            <tr style="background:#fafbfc;"><td colspan="2" style="font-weight:700;">Financing activities</td></tr>
            <?php if ($entity['type'] === 'business'): ?>
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
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

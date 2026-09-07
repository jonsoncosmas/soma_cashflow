<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/access_control.php';
require __DIR__ . '/../includes/investment_helpers.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$accountId = (int) ($_GET['id'] ?? 0);
$access = get_investment_access($pdo, $accountId, $user['id']);

if (!$access) {
    flash_set('error', 'Investment account not found or you do not have access to it.');
    header('Location: /soma_cashflow/public/investments.php');
    exit;
}
$account = $access['account'];
$canEdit = role_can_edit($access['role']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        flash_set('error', 'You have view-only access to this account.');
        header('Location: /soma_cashflow/public/investment.php?id=' . $accountId);
        exit;
    }
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    }

    $entryType = (string) ($_POST['entry_type'] ?? '');
    $amountRaw = trim((string) ($_POST['amount'] ?? ''));
    $entryDate = (string) ($_POST['entry_date'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));

    if (!in_array($entryType, ['deposit', 'valuation'], true)) {
        $errors[] = 'Select a valid entry type.';
    }
    if (!is_numeric($amountRaw) || (float) $amountRaw < 0) {
        $errors[] = 'Amount must be zero or a positive number.';
    }
    if ($entryDate === '' || !DateTime::createFromFormat('Y-m-d', $entryDate)) {
        $errors[] = 'Please provide a valid date.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO investment_entries (account_id, entry_type, amount, entry_date, note) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$accountId, $entryType, (float) $amountRaw, $entryDate, $note ?: null]);
        flash_set('success', $entryType === 'deposit' ? 'Deposit recorded.' : 'Valuation recorded.');
        header('Location: /soma_cashflow/public/investment.php?id=' . $accountId);
        exit;
    }
}

$entries = get_investment_entries($pdo, $accountId);
$stats = compute_investment_growth($entries);

$pageTitle = h($account['name']) . ' - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<p class="muted" style="margin-bottom:14px;"><a class="link" href="/soma_cashflow/public/investments.php">&larr; Investments</a></p>

<div class="hero">
    <span class="eyebrow" style="background:rgba(255,255,255,0.16); color:#fff;">Investment</span>
    <h1><?= h($account['name']) ?></h1>
    <?php if ($account['owner_type'] === 'business'): ?>
        <p style="font-size:0.85rem; color:rgba(255,255,255,0.85); margin:-4px 0 6px;">
            <?= h($access['business_name'] ?? '') ?> &middot; your access: <strong><?= h(ucfirst($access['role'])) ?></strong>
        </p>
    <?php endif; ?>
    <?php if ($account['institution']): ?>
        <p><?= h($account['institution']) ?></p>
    <?php endif; ?>
    <div style="position:relative;">
        <div style="font-size:0.8rem; color:rgba(255,255,255,0.88); font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Current value</div>
        <div style="font-size:2.4rem; font-weight:800; margin-top:2px;"><?= number_format($stats['current_value'], 2) ?></div>
    </div>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-100);">💰</div>
        <div class="stat-label">Total deposited</div>
        <div class="stat-value"><?= number_format($stats['total_deposits'], 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:<?= $stats['total_growth'] >= 0 ? 'var(--success-bg)' : 'var(--error-bg)' ?>;">📈</div>
        <div class="stat-label">Growth</div>
        <?php if ($stats['has_valuation']): ?>
        <div class="stat-value" style="color: <?= $stats['total_growth'] >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;">
            <?= $stats['total_growth'] >= 0 ? '+' : '' ?><?= number_format($stats['total_growth'], 2) ?>
        </div>
        <?php else: ?>
        <div class="stat-value muted" style="font-size:0.95rem;">No check yet</div>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-100);">%</div>
        <div class="stat-label">Growth %</div>
        <div class="stat-value" style="color: <?= $stats['total_growth'] >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;">
            <?= number_format($stats['growth_percent'], 1) ?>%
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--accent-100);">🔢</div>
        <div class="stat-label">Deposits made</div>
        <div class="stat-value"><?= (int) $stats['deposit_count'] ?></div>
    </div>
</div>

<?php if ($canEdit): ?>
<div class="card">
    <h2>Add an entry</h2>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/investment.php?id=<?= (int) $accountId ?>">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="form-grid">
            <div>
                <label for="entry_type">Type</label>
                <select id="entry_type" name="entry_type" required>
                    <option value="deposit">💰 Deposit (money added)</option>
                    <option value="valuation">📈 Valuation check (what it's worth today)</option>
                </select>
            </div>
            <div>
                <label for="amount">Amount (TZS)</label>
                <input type="text" id="amount" name="amount" placeholder="e.g. 100000" required>
            </div>
            <div>
                <label for="entry_date">Date</label>
                <input type="date" id="entry_date" name="entry_date" value="<?= h(date('Y-m-d')) ?>" required>
            </div>
            <div class="full">
                <label for="note">Note (optional)</label>
                <input type="text" id="note" name="note" placeholder="e.g. Checked on UTT AMIS app">
            </div>
        </div>
        <button type="submit">+ Add entry</button>
    </form>
</div>
<?php else: ?>
<div class="card" style="background:var(--bg); border-style:dashed; text-align:center;">
    <p class="muted" style="margin:0;">👁️ You have view-only access to this account.</p>
</div>
<?php endif; ?>

<div class="card">
    <h2>History</h2>
    <?php if (!$entries): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">📈</div>
            <p class="muted" style="margin:0;">No entries yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Date</th><th>Type</th><th>Note</th><th style="text-align:right;">Amount</th></tr>
            <?php foreach (array_reverse($entries) as $e): ?>
            <tr>
                <td><?= h($e['date']) ?></td>
                <td><span class="pill <?= $e['type'] === 'deposit' ? 'income' : 'loan_received' ?>"><?= $e['type'] === 'deposit' ? '💰 Deposit' : '📈 Valuation' ?></span></td>
                <td><?= h($e['note'] ?? '') ?></td>
                <td style="text-align:right; font-weight:700;"><?= number_format((float) $e['amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

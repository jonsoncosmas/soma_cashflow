<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/ledger_helpers.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$errors = [];

$typeLabels = ['income' => 'Income', 'expense' => 'Expense'];
$typeIcons  = ['income' => '💵', 'expense' => '🧾'];
$categorySuggestions = [
    'Salary', 'Freelance', 'Gift', 'Investment Income', 'Other Income',
    'Rent', 'Food', 'Transport', 'Personal Expense', 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    }

    $type            = (string) ($_POST['type'] ?? '');
    $category        = trim((string) ($_POST['category'] ?? ''));
    $amountRaw       = trim((string) ($_POST['amount'] ?? ''));
    $description     = trim((string) ($_POST['description'] ?? ''));
    $transactionDate = (string) ($_POST['transaction_date'] ?? '');

    if (!array_key_exists($type, $typeLabels)) {
        $errors[] = 'Please select a valid type.';
    }
    if ($category === '') {
        $errors[] = 'Category is required.';
    }
    if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
        $errors[] = 'Amount must be a positive number.';
    }
    if ($transactionDate === '' || !DateTime::createFromFormat('Y-m-d', $transactionDate)) {
        $errors[] = 'Please provide a valid date.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO personal_transactions (user_id, type, category, amount, description, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $type, $category, (float) $amountRaw, $description ?: null, $transactionDate]);
        flash_set('success', 'Personal transaction recorded.');
        header('Location: /soma_cashflow/public/personal.php');
        exit;
    }
}

$balance = get_personal_balance($pdo, $user['id']);

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income,
            COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS expense
     FROM personal_transactions WHERE user_id = ?"
);
$stmt->execute([$user['id']]);
$totals = $stmt->fetch();

// Business name lookup for transfer labels
$stmt = $pdo->prepare(
    'SELECT b.id, b.name FROM businesses b
     INNER JOIN organizations o ON o.id = b.organization_id
     WHERE o.owner_user_id = ?'
);
$stmt->execute([$user['id']]);
$businessNames = array_column($stmt->fetchAll(), 'name', 'id');

// Combine personal transactions + transfers touching the personal ledger into one history feed
$history = [];

$stmt = $pdo->prepare(
    'SELECT type, category, amount, description, transaction_date AS date
     FROM personal_transactions WHERE user_id = ? ORDER BY transaction_date DESC, id DESC LIMIT 100'
);
$stmt->execute([$user['id']]);
foreach ($stmt->fetchAll() as $row) {
    $history[] = [
        'date'        => $row['date'],
        'kind'        => $row['type'],
        'label'       => $typeLabels[$row['type']],
        'category'    => $row['category'],
        'description' => $row['description'],
        'amount'      => (float) $row['amount'],
        'inflow'      => $row['type'] === 'income',
    ];
}

$stmt = $pdo->prepare(
    'SELECT from_type, from_business_id, to_type, to_business_id, amount, description, transfer_date AS date
     FROM fund_transfers WHERE user_id = ? AND (from_type = "personal" OR to_type = "personal")
     ORDER BY transfer_date DESC, id DESC LIMIT 100'
);
$stmt->execute([$user['id']]);
foreach ($stmt->fetchAll() as $row) {
    $isInflow = $row['to_type'] === 'personal';
    $otherLabel = $isInflow
        ? transfer_endpoint_label($row['from_type'], $row['from_business_id'], $businessNames)
        : transfer_endpoint_label($row['to_type'], $row['to_business_id'], $businessNames);
    $history[] = [
        'date'        => $row['date'],
        'kind'        => $isInflow ? 'transfer_in' : 'transfer_out',
        'label'       => $isInflow ? 'Transfer in' : 'Transfer out',
        'category'    => $isInflow ? ('From ' . $otherLabel) : ('To ' . $otherLabel),
        'description' => $row['description'],
        'amount'      => (float) $row['amount'],
        'inflow'      => $isInflow,
    ];
}

usort($history, fn($a, $b) => strcmp($b['date'], $a['date']));
$history = array_slice($history, 0, 100);

$pageTitle = 'Personal Ledger - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="hero">
    <span class="eyebrow" style="background:rgba(255,255,255,0.16); color:#fff;">Personal</span>
    <h1>Your personal ledger</h1>
    <p>Salary, freelance income, gifts, and personal spending &mdash; separate from your businesses.</p>
    <div style="position:relative;">
        <div style="font-size:0.8rem; color:rgba(255,255,255,0.88); font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Current balance</div>
        <div style="font-size:2.4rem; font-weight:800; margin-top:2px;"><?= number_format($balance, 2) ?></div>
    </div>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(2, minmax(0,1fr)); margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--success-bg);">💵</div>
        <div class="stat-label">Income</div>
        <div class="stat-value" style="color:var(--success-fg);">+<?= number_format((float) $totals['income'], 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--error-bg);">🧾</div>
        <div class="stat-label">Expenses</div>
        <div class="stat-value" style="color:var(--error-fg);">-<?= number_format((float) $totals['expense'], 2) ?></div>
    </div>
</div>

<div class="card">
    <h2>Add a personal transaction</h2>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/personal.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="form-grid">
            <div>
                <label for="type">Type</label>
                <select id="type" name="type" required>
                    <option value="">Select type&hellip;</option>
                    <?php foreach ($typeLabels as $val => $label): ?>
                        <option value="<?= h($val) ?>" <?= ($_POST['type'] ?? '') === $val ? 'selected' : '' ?>><?= h($typeIcons[$val]) ?> <?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="category">Category</label>
                <input type="text" id="category" name="category" list="category-suggestions" value="<?= h($_POST['category'] ?? '') ?>" placeholder="e.g. Salary, Rent" required>
                <datalist id="category-suggestions">
                    <?php foreach ($categorySuggestions as $c): ?>
                        <option value="<?= h($c) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label for="amount">Amount (TZS)</label>
                <input type="text" id="amount" name="amount" value="<?= h($_POST['amount'] ?? '') ?>" placeholder="e.g. 500000" required>
            </div>
            <div>
                <label for="transaction_date">Date</label>
                <input type="text" id="transaction_date" name="transaction_date" value="<?= h($_POST['transaction_date'] ?? date('Y-m-d')) ?>" required
                       onfocus="this.type='date'" onblur="if(!this.value)this.type='text'">
            </div>
            <div class="full">
                <label for="description">Description (optional)</label>
                <input type="text" id="description" name="description" value="<?= h($_POST['description'] ?? '') ?>" placeholder="e.g. August salary">
            </div>
        </div>
        <button type="submit">+ Add transaction</button>
    </form>
</div>

<div class="card">
    <h2>History</h2>
    <?php if (!$history): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">💵</div>
            <p class="muted" style="margin:0;">No personal transactions yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th style="text-align:right;">Amount</th></tr>
            <?php foreach ($history as $h): ?>
            <tr>
                <td><?= h($h['date']) ?></td>
                <td><span class="pill <?= $h['inflow'] ? 'income' : 'expense' ?>"><?= h($h['label']) ?></span></td>
                <td><?= h($h['category']) ?></td>
                <td><?= h($h['description'] ?? '') ?></td>
                <td style="text-align:right; color: <?= $h['inflow'] ? 'var(--success-fg)' : 'var(--error-fg)' ?>; font-weight:700;">
                    <?= $h['inflow'] ? '+' : '-' ?><?= number_format($h['amount'], 2) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

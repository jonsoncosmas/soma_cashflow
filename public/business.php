<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/business_access.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$businessId = (int) ($_GET['id'] ?? 0);
$business = get_owned_business($pdo, $businessId, $user['id']);

if (!$business) {
    flash_set('error', 'Business not found or you do not have access to it.');
    header('Location: /soma_cashflow/public/dashboard.php');
    exit;
}

$errors = [];

$typeLabels = [
    'income'        => 'Income',
    'expense'       => 'Expense',
    'loan_received' => 'Loan received',
    'loan_given'    => 'Loan given',
];
$typeIcons = [
    'income'        => '💰',
    'expense'       => '🧾',
    'loan_received' => '🏦',
    'loan_given'    => '🤝',
];

$categorySuggestions = [
    'Sales', 'Materials / Purchase', 'Labor', 'Machinery', 'Livestock',
    'Utilities', 'Rent', 'Transport', 'Loan', 'Capital Injection', 'Other',
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
        $errors[] = 'Please select a valid transaction type.';
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
            'INSERT INTO transactions (business_id, user_id, type, category, amount, description, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $business['id'],
            $user['id'],
            $type,
            $category,
            (float) $amountRaw,
            $description ?: null,
            $transactionDate,
        ]);
        flash_set('success', 'Transaction recorded.');
        header('Location: /soma_cashflow/public/business.php?id=' . $business['id']);
        exit;
    }
}

// Balance summary
$stmt = $pdo->prepare(
    "SELECT type, COALESCE(SUM(amount), 0) AS total
     FROM transactions WHERE business_id = ? GROUP BY type"
);
$stmt->execute([$business['id']]);
$totals = ['income' => 0.0, 'expense' => 0.0, 'loan_received' => 0.0, 'loan_given' => 0.0];
foreach ($stmt->fetchAll() as $row) {
    $totals[$row['type']] = (float) $row['total'];
}
$balance = $totals['income'] + $totals['loan_received'] - $totals['expense'] - $totals['loan_given'];

// Transaction history (most recent first)
$stmt = $pdo->prepare(
    'SELECT type, category, amount, description, transaction_date
     FROM transactions WHERE business_id = ?
     ORDER BY transaction_date DESC, id DESC
     LIMIT 100'
);
$stmt->execute([$business['id']]);
$transactions = $stmt->fetchAll();

$pageTitle = h($business['name']) . ' - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<p class="muted" style="margin-bottom:14px;"><a class="link" href="/soma_cashflow/public/dashboard.php">&larr; Dashboard</a></p>

<div class="hero">
    <span class="eyebrow" style="background:rgba(255,255,255,0.16); color:#fff;">Business</span>
    <h1><?= h($business['name']) ?></h1>
    <?php if ($business['description']): ?>
        <p><?= h($business['description']) ?></p>
    <?php endif; ?>
    <div style="position:relative;">
        <div style="font-size:0.8rem; color:rgba(255,255,255,0.88); font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Current balance</div>
        <div style="font-size:2.4rem; font-weight:800; margin-top:2px;"><?= number_format($balance, 2) ?></div>
    </div>
</div>

<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--success-bg);">💰</div>
        <div class="stat-label">Income</div>
        <div class="stat-value" style="color:var(--success-fg);">+<?= number_format($totals['income'], 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--error-bg);">🧾</div>
        <div class="stat-label">Expenses</div>
        <div class="stat-value" style="color:var(--error-fg);">-<?= number_format($totals['expense'], 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-100);">🏦</div>
        <div class="stat-label">Loans received</div>
        <div class="stat-value" style="color:#1d4ed8;">+<?= number_format($totals['loan_received'], 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--accent-100);">🤝</div>
        <div class="stat-label">Loans given</div>
        <div class="stat-value" style="color:#b45309;">-<?= number_format($totals['loan_given'], 2) ?></div>
    </div>
</div>

<div class="card">
    <h2>Add a transaction</h2>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/business.php?id=<?= (int) $business['id'] ?>">
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
                <input type="text" id="category" name="category" list="category-suggestions" value="<?= h($_POST['category'] ?? '') ?>" placeholder="e.g. Timber, Machinery, Sales" required>
                <datalist id="category-suggestions">
                    <?php foreach ($categorySuggestions as $c): ?>
                        <option value="<?= h($c) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label for="amount">Amount (TZS)</label>
                <input type="text" id="amount" name="amount" value="<?= h($_POST['amount'] ?? '') ?>" placeholder="e.g. 10000" required>
            </div>
            <div>
                <label for="transaction_date">Date</label>
                <input type="text" id="transaction_date" name="transaction_date" value="<?= h($_POST['transaction_date'] ?? date('Y-m-d')) ?>" placeholder="YYYY-MM-DD" required
                       onfocus="this.type='date'" onblur="if(!this.value)this.type='text'">
            </div>
            <div class="full">
                <label for="description">Description (optional)</label>
                <input type="text" id="description" name="description" value="<?= h($_POST['description'] ?? '') ?>" placeholder="e.g. Bought timber for coop construction">
            </div>
        </div>
        <button type="submit">+ Add transaction</button>
    </form>
</div>

<div class="card">
    <h2>Transaction history</h2>
    <?php if (!$transactions): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">💵</div>
            <p class="muted" style="margin:0;">No transactions yet &mdash; add your first one above.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th style="text-align:right;">Amount</th></tr>
            <?php foreach ($transactions as $t):
                $isInflow = in_array($t['type'], ['income', 'loan_received'], true);
            ?>
            <tr>
                <td><?= h($t['transaction_date']) ?></td>
                <td><span class="pill <?= h($t['type']) ?>"><?= h($typeIcons[$t['type']] ?? '') ?> <?= h($typeLabels[$t['type']] ?? $t['type']) ?></span></td>
                <td><?= h($t['category']) ?></td>
                <td><?= h($t['description'] ?? '') ?></td>
                <td style="text-align:right; color: <?= $isInflow ? 'var(--success-fg)' : 'var(--error-fg)' ?>; font-weight:700;">
                    <?= $isInflow ? '+' : '-' ?><?= number_format((float) $t['amount'], 2) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

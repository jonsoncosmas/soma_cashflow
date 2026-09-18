<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/access_control.php';
require __DIR__ . '/../includes/ledger_helpers.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$businessId = (int) ($_GET['id'] ?? 0);
$access = get_business_access($pdo, $businessId, $user['id']);

if (!$access) {
    flash_set('error', 'Business not found or you do not have access to it.');
    header('Location: /soma_cashflow/public/dashboard.php');
    exit;
}
$business = $access['business'];
$canEdit = role_can_edit($access['role']);

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
    if (!$canEdit) {
        flash_set('error', 'You have view-only access to this business.');
        header('Location: /soma_cashflow/public/business.php?id=' . $business['id']);
        exit;
    }
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

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS t FROM fund_transfers WHERE to_type = ? AND to_business_id = ?');
$stmt->execute(['business', $business['id']]);
$transferIn = (float) $stmt->fetch()['t'];

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS t FROM fund_transfers WHERE from_type = ? AND from_business_id = ?');
$stmt->execute(['business', $business['id']]);
$transferOut = (float) $stmt->fetch()['t'];

$balance = $totals['income'] + $totals['loan_received'] - $totals['expense'] - $totals['loan_given'] + $transferIn - $transferOut;

// Transaction history (own transactions + transfers involving this business), most recent first
$stmt = $pdo->prepare(
    'SELECT type, category, amount, description, transaction_date AS date
     FROM transactions WHERE business_id = ?
     ORDER BY transaction_date DESC, id DESC
     LIMIT 100'
);
$stmt->execute([$business['id']]);
$history = [];
foreach ($stmt->fetchAll() as $row) {
    $isInflow = in_array($row['type'], ['income', 'loan_received'], true);
    $history[] = [
        'date'     => $row['date'],
        'pillType' => $row['type'],
        'label'    => $typeLabels[$row['type']] ?? $row['type'],
        'icon'     => $typeIcons[$row['type']] ?? '',
        'category' => $row['category'],
        'desc'     => $row['description'],
        'amount'   => (float) $row['amount'],
        'inflow'   => $isInflow,
    ];
}

// Business name lookup for transfer labels - scoped to this business's own
// organization, not the viewer's ownership (a shared member may not own it).
$stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE organization_id = ?');
$stmt->execute([$business['organization_id']]);
$businessNames = array_column($stmt->fetchAll(), 'name', 'id');

$stmt = $pdo->prepare(
    'SELECT from_type, from_business_id, to_type, to_business_id, amount, description, transfer_date AS date
     FROM fund_transfers
     WHERE (from_type = "business" AND from_business_id = ?) OR (to_type = "business" AND to_business_id = ?)
     ORDER BY transfer_date DESC, id DESC LIMIT 100'
);
$stmt->execute([$business['id'], $business['id']]);
foreach ($stmt->fetchAll() as $row) {
    $isInflow = $row['to_type'] === 'business' && (int) $row['to_business_id'] === $business['id'];
    $otherLabel = $isInflow
        ? transfer_endpoint_label($row['from_type'], $row['from_business_id'], $businessNames)
        : transfer_endpoint_label($row['to_type'], $row['to_business_id'], $businessNames);
    $history[] = [
        'date'     => $row['date'],
        'pillType' => $isInflow ? 'loan_received' : 'loan_given', // reuse blue/orange pill styling
        'label'    => $isInflow ? 'Transfer in' : 'Transfer out',
        'icon'     => $isInflow ? '🔁' : '🔁',
        'category' => $isInflow ? ('From ' . $otherLabel) : ('To ' . $otherLabel),
        'desc'     => $row['description'],
        'amount'   => (float) $row['amount'],
        'inflow'   => $isInflow,
    ];
}

usort($history, fn($a, $b) => strcmp($b['date'], $a['date']));
$history = array_slice($history, 0, 100);

$pageTitle = h($business['name']) . ' - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<p class="muted" style="margin-bottom:14px;">
    <a class="link" href="/soma_cashflow/public/dashboard.php">&larr; Dashboard</a>
    &nbsp;&middot;&nbsp;
    <a class="link" href="/soma_cashflow/public/gallery.php?business_id=<?= (int) $business['id'] ?>">📷 Photo gallery</a>
</p>

<div class="hero">
    <span class="eyebrow" style="background:rgba(255,255,255,0.16); color:#fff;">Business</span>
    <h1><?= h($business['name']) ?></h1>
    <?php if ($access['role'] !== 'owner'): ?>
        <p style="font-size:0.85rem; color:rgba(255,255,255,0.85); margin:-4px 0 6px;">
            Shared from <?= h($access['org_name']) ?> &middot; your access: <strong><?= h(ucfirst($access['role'])) ?></strong>
        </p>
    <?php endif; ?>
    <?php if ($business['description']): ?>
        <p><?= h($business['description']) ?></p>
    <?php endif; ?>
    <div style="position:relative;">
        <div style="font-size:0.8rem; color:rgba(255,255,255,0.88); font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Current balance</div>
        <div style="font-size:2.4rem; font-weight:800; margin-top:2px;"><?= number_format($balance, 2) ?></div>
    </div>
</div>

<div class="stat-grid" style="margin-bottom:24px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
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
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-100);">🔁</div>
        <div class="stat-label">Net transfers</div>
        <div class="stat-value" style="color: <?= ($transferIn - $transferOut) >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;">
            <?= ($transferIn - $transferOut) >= 0 ? '+' : '' ?><?= number_format($transferIn - $transferOut, 2) ?>
        </div>
    </div>
</div>

<?php if ($canEdit): ?>
<div class="card">
    <h2>Add a transaction</h2>
    <span id="offline-sync-status" class="muted" style="display:block; font-size:0.82rem; margin-bottom:8px;"></span>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/business.php?id=<?= (int) $business['id'] ?>" id="transaction-form">
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
                <label for="description">Description (optional, but helps AI suggest a category)</label>
                <input type="text" id="description" name="description" value="<?= h($_POST['description'] ?? '') ?>" placeholder="e.g. Bought timber for coop construction">
                <button type="button" id="ai-suggest-btn" onclick="somaAiSuggest()" style="margin-top:8px; background:var(--brand-100); color:var(--brand-700); box-shadow:none; padding:8px 14px; font-size:0.85rem;">✨ Suggest with AI</button>
                <span id="ai-suggest-status" class="muted" style="margin-left:8px; font-size:0.82rem;"></span>
            </div>
        </div>
        <button type="submit">+ Add transaction</button>
    </form>
</div>
<script>
async function somaAiSuggest() {
    const desc = document.getElementById('description').value.trim();
    const status = document.getElementById('ai-suggest-status');
    if (!desc) { status.textContent = 'Enter a description first.'; return; }
    status.textContent = 'Thinking…';
    try {
        const res = await fetch('/soma_cashflow/public/ai_suggest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                description: desc,
                amount: document.getElementById('amount').value,
                context: 'business',
                business_id: <?= (int) $business['id'] ?>
            })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('type').value = data.type;
            document.getElementById('category').value = data.category;
            if (data.description) {
                document.getElementById('description').value = data.description;
            }
            var providerLabel = data.provider === 'openrouter' ? 'openrouter (' + data.model + ')' : data.provider;
            status.textContent = 'Suggested by ' + providerLabel + ' (' + Math.round(data.confidence * 100) + '% confidence) — review before saving.';
        } else {
            status.textContent = 'Could not get a suggestion — pick manually.';
        }
    } catch (e) {
        status.textContent = 'Could not reach AI service — pick manually.';
    }
}
</script>
<script src="/soma_cashflow/public/js/offline_sync.js"></script>
<script>
    SomaOfflineSync.wireOfflineForm(
        document.getElementById('transaction-form'),
        'business',
        { business_id: <?= (int) $business['id'] ?> },
        document.getElementById('offline-sync-status')
    );
</script>
<?php else: ?>
<div class="card" style="background:var(--bg); border-style:dashed; text-align:center;">
    <p class="muted" style="margin:0;">👁️ You have view-only access to this business.</p>
</div>
<?php endif; ?>

<div class="card">
    <h2>Transaction history</h2>
    <?php if (!$history): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">💵</div>
            <p class="muted" style="margin:0;">No transactions yet &mdash; add your first one above.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th style="text-align:right;">Amount</th></tr>
            <?php foreach ($history as $h): ?>
            <tr>
                <td><?= h($h['date']) ?></td>
                <td><span class="pill <?= h($h['pillType']) ?>"><?= h($h['icon']) ?> <?= h($h['label']) ?></span></td>
                <td><?= h($h['category']) ?></td>
                <td><?= h($h['desc'] ?? '') ?></td>
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

<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/ledger_helpers.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$stmt = $pdo->prepare('SELECT id, name FROM organizations WHERE owner_user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$org = $stmt->fetch();

$stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE organization_id = ? ORDER BY name');
$stmt->execute([$org['id'] ?? 0]);
$businesses = $stmt->fetchAll();
$businessNames = array_column($businesses, 'name', 'id');

$errors = [];

// Build the list of valid endpoints: "personal" or "business:{id}"
function parse_endpoint(string $value): array
{
    if ($value === 'personal') {
        return ['personal', null];
    }
    if (str_starts_with($value, 'business:')) {
        return ['business', (int) substr($value, 9)];
    }
    return ['', null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    }
    if (!$org) {
        $errors[] = 'No workspace found.';
    }

    $fromRaw   = (string) ($_POST['from'] ?? '');
    $toRaw     = (string) ($_POST['to'] ?? '');
    $amountRaw = trim((string) ($_POST['amount'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $transferDate = (string) ($_POST['transfer_date'] ?? '');

    [$fromType, $fromBusinessId] = parse_endpoint($fromRaw);
    [$toType, $toBusinessId]     = parse_endpoint($toRaw);

    if ($fromType === '' || $toType === '') {
        $errors[] = 'Please select both a source and a destination.';
    } elseif ($fromRaw === $toRaw) {
        $errors[] = 'Source and destination must be different.';
    }
    if ($fromType === 'business' && !isset($businessNames[$fromBusinessId])) {
        $errors[] = 'Invalid source business.';
    }
    if ($toType === 'business' && !isset($businessNames[$toBusinessId])) {
        $errors[] = 'Invalid destination business.';
    }
    if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
        $errors[] = 'Amount must be a positive number.';
    }
    if ($transferDate === '' || !DateTime::createFromFormat('Y-m-d', $transferDate)) {
        $errors[] = 'Please provide a valid date.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO fund_transfers (organization_id, user_id, from_type, from_business_id, to_type, to_business_id, amount, description, transfer_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $org['id'],
            $user['id'],
            $fromType,
            $fromBusinessId,
            $toType,
            $toBusinessId,
            (float) $amountRaw,
            $description ?: null,
            $transferDate,
        ]);
        flash_set('success', 'Transfer recorded.');
        header('Location: /soma_cashflow/public/transfer.php');
        exit;
    }
}

// Recent transfers for reference
$transfers = [];
if ($org) {
    $stmt = $pdo->prepare(
        'SELECT from_type, from_business_id, to_type, to_business_id, amount, description, transfer_date
         FROM fund_transfers WHERE organization_id = ? ORDER BY transfer_date DESC, id DESC LIMIT 50'
    );
    $stmt->execute([$org['id']]);
    $transfers = $stmt->fetchAll();
}

$pageTitle = 'Transfer Funds - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <span class="eyebrow">Transfer</span>
    <h2 style="margin-bottom:2px;">Move money between ledgers</h2>
    <p class="muted" style="margin-top:2px;">Fund a business from your personal income, or move money between businesses.</p>
</div>

<div class="card">
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <?php if (!$businesses): ?>
        <p class="muted">You need at least one business before you can transfer funds. <a class="link" href="/soma_cashflow/public/dashboard.php">Create one first &rarr;</a></p>
    <?php else: ?>
    <form method="post" action="/soma_cashflow/public/transfer.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="form-grid">
            <div>
                <label for="from">From</label>
                <select id="from" name="from" required>
                    <option value="">Select source&hellip;</option>
                    <option value="personal" <?= ($_POST['from'] ?? '') === 'personal' ? 'selected' : '' ?>>👤 Personal</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="business:<?= (int) $b['id'] ?>" <?= ($_POST['from'] ?? '') === 'business:' . $b['id'] ? 'selected' : '' ?>>🏢 <?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="to">To</label>
                <select id="to" name="to" required>
                    <option value="">Select destination&hellip;</option>
                    <option value="personal" <?= ($_POST['to'] ?? '') === 'personal' ? 'selected' : '' ?>>👤 Personal</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="business:<?= (int) $b['id'] ?>" <?= ($_POST['to'] ?? '') === 'business:' . $b['id'] ? 'selected' : '' ?>>🏢 <?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="amount">Amount (TZS)</label>
                <input type="text" id="amount" name="amount" value="<?= h($_POST['amount'] ?? '') ?>" placeholder="e.g. 100000" required>
            </div>
            <div>
                <label for="transfer_date">Date</label>
                <input type="text" id="transfer_date" name="transfer_date" value="<?= h($_POST['transfer_date'] ?? date('Y-m-d')) ?>" required
                       onfocus="this.type='date'" onblur="if(!this.value)this.type='text'">
            </div>
            <div class="full">
                <label for="description">Description (optional)</label>
                <input type="text" id="description" name="description" value="<?= h($_POST['description'] ?? '') ?>" placeholder="e.g. Startup capital for poultry farm">
            </div>
        </div>
        <button type="submit">+ Record transfer</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Recent transfers</h2>
    <?php if (!$transfers): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">🔁</div>
            <p class="muted" style="margin:0;">No transfers yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Date</th><th>From</th><th>To</th><th>Description</th><th style="text-align:right;">Amount</th></tr>
            <?php foreach ($transfers as $t): ?>
            <tr>
                <td><?= h($t['transfer_date']) ?></td>
                <td><?= h(transfer_endpoint_label($t['from_type'], $t['from_business_id'], $businessNames)) ?></td>
                <td><?= h(transfer_endpoint_label($t['to_type'], $t['to_business_id'], $businessNames)) ?></td>
                <td><?= h($t['description'] ?? '') ?></td>
                <td style="text-align:right; font-weight:700;"><?= number_format((float) $t['amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

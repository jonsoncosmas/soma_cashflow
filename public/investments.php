<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/access_control.php';
require __DIR__ . '/../includes/investment_helpers.php';
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
// Only owner/admin of a shared business can create investment accounts there.
$editableSharedBusinesses = array_values(array_filter($sharedBusinesses, fn($b) => role_can_edit($b['role'])));

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    }

    $ownerType = (string) ($_POST['owner_type'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $institution = trim((string) ($_POST['institution'] ?? ''));
    $businessId = null;

    if ($name === '') {
        $errors[] = 'Account name is required.';
    }
    if ($ownerType === 'personal') {
        $businessId = null;
    } elseif (str_starts_with($ownerType, 'business:')) {
        $businessId = (int) substr($ownerType, 9);
        $canCreate = false;
        foreach ($businesses as $b) { if ((int) $b['id'] === $businessId) { $canCreate = true; break; } }
        foreach ($editableSharedBusinesses as $b) { if ((int) $b['id'] === $businessId) { $canCreate = true; break; } }
        if (!$canCreate) {
            $errors[] = 'Invalid business selected.';
        }
        $ownerType = 'business';
    } else {
        $errors[] = 'Select where this account belongs.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO investment_accounts (owner_type, business_id, user_id, name, institution) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ownerType, $businessId, $user['id'], $name, $institution ?: null]);
        flash_set('success', 'Investment account "' . $name . '" created.');
        header('Location: /soma_cashflow/public/investments.php');
        exit;
    }
}

// Gather all accessible accounts: personal ones the user created, plus
// business ones for businesses they own or have any access to.
$accessibleBusinessIds = array_column($businesses, 'id');
foreach ($sharedBusinesses as $sb) { $accessibleBusinessIds[] = $sb['id']; }

$accounts = [];
$stmt = $pdo->prepare("SELECT * FROM investment_accounts WHERE owner_type = 'personal' AND user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$accounts = array_merge($accounts, $stmt->fetchAll());

if ($accessibleBusinessIds) {
    $placeholders = implode(',', array_fill(0, count($accessibleBusinessIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM investment_accounts WHERE owner_type = 'business' AND business_id IN ($placeholders) ORDER BY created_at DESC");
    $stmt->execute($accessibleBusinessIds);
    $accounts = array_merge($accounts, $stmt->fetchAll());
}

$businessNameById = array_column($businesses, 'name', 'id');
foreach ($sharedBusinesses as $sb) { $businessNameById[$sb['id']] = $sb['name']; }

foreach ($accounts as &$acc) {
    $entries = get_investment_entries($pdo, (int) $acc['id']);
    $acc['stats'] = compute_investment_growth($entries);
}
unset($acc);

$pageTitle = 'Investments - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <span class="eyebrow">Investments</span>
    <h2 style="margin-bottom:2px;">Savings & investments</h2>
    <p class="muted" style="margin-top:2px;">Track deposits separately from growth &mdash; e.g. a UTT unit trust, fixed deposit, or any account whose value changes on its own.</p>
</div>

<div class="card">
    <h2>Add an account</h2>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/investments.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="form-grid">
            <div class="full">
                <label for="name">Account name</label>
                <input type="text" id="name" name="name" value="<?= h($_POST['name'] ?? '') ?>" placeholder="e.g. UTT Umoja Fund" required>
            </div>
            <div>
                <label for="institution">Institution (optional)</label>
                <input type="text" id="institution" name="institution" value="<?= h($_POST['institution'] ?? '') ?>" placeholder="e.g. UTT AMIS">
            </div>
            <div>
                <label for="owner_type">Belongs to</label>
                <select id="owner_type" name="owner_type" required>
                    <option value="personal">👤 Personal</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="business:<?= (int) $b['id'] ?>">🏢 <?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                    <?php foreach ($editableSharedBusinesses as $b): ?>
                        <option value="business:<?= (int) $b['id'] ?>">🤝 <?= h($b['name']) ?> (<?= h($b['org_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit">+ Create account</button>
    </form>
</div>

<h2 style="font-size:1.05rem; margin: 6px 2px 12px;">Your accounts</h2>
<?php if (!$accounts): ?>
    <div class="card" style="text-align:center; padding:40px 10px;">
        <div style="font-size:2.2rem; margin-bottom:8px;">📈</div>
        <p class="muted" style="margin:0;">No investment accounts yet &mdash; create your first one above.</p>
    </div>
<?php else: ?>
    <div class="biz-grid">
        <?php foreach ($accounts as $acc): $s = $acc['stats']; ?>
        <a class="biz-card" href="/soma_cashflow/public/investment.php?id=<?= (int) $acc['id'] ?>">
            <div class="biz-icon">📈</div>
            <div class="biz-name"><?= h($acc['name']) ?></div>
            <div class="biz-desc">
                <?= $acc['owner_type'] === 'personal' ? 'Personal' : h($businessNameById[$acc['business_id']] ?? 'Business') ?>
                <?= $acc['institution'] ? ' &middot; ' . h($acc['institution']) : '' ?>
            </div>
            <div class="biz-balance"><?= number_format($s['current_value'], 2) ?></div>
            <?php if ($s['has_valuation']): ?>
            <div class="muted" style="font-size:0.8rem; margin-top:4px; color: <?= $s['total_growth'] >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;">
                <?= $s['total_growth'] >= 0 ? '+' : '' ?><?= number_format($s['total_growth'], 2) ?> (<?= number_format($s['growth_percent'], 1) ?>%)
            </div>
            <?php else: ?>
            <div class="muted" style="font-size:0.8rem; margin-top:4px;">No valuation yet</div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

// A user currently has exactly one organization (created at registration).
$stmt = $pdo->prepare('SELECT id, name FROM organizations WHERE owner_user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$org = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $org) {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    }

    $bizName = trim((string) ($_POST['business_name'] ?? ''));
    $bizDesc = trim((string) ($_POST['business_description'] ?? ''));

    if ($bizName === '') {
        $errors[] = 'Business name is required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM businesses WHERE organization_id = ? AND name = ?');
        $stmt->execute([$org['id'], $bizName]);
        if ($stmt->fetch()) {
            $errors[] = 'You already have a business named "' . $bizName . '". Choose a different name.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO businesses (organization_id, name, description) VALUES (?, ?, ?)'
        );
        try {
            $stmt->execute([$org['id'], $bizName, $bizDesc ?: null]);
            flash_set('success', 'Business "' . $bizName . '" created.');
            header('Location: /soma_cashflow/public/dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'You already have a business named "' . $bizName . '". Choose a different name.';
        }
    }
}

$businesses = [];
$totalBalance = 0.0;
if ($org) {
    $stmt = $pdo->prepare(
        "SELECT b.id, b.name, b.description, b.created_at,
                COALESCE(SUM(CASE WHEN t.type IN ('income','loan_received') THEN t.amount
                                  WHEN t.type IN ('expense','loan_given') THEN -t.amount
                                  ELSE 0 END), 0) AS balance
         FROM businesses b
         LEFT JOIN transactions t ON t.business_id = b.id
         WHERE b.organization_id = ?
         GROUP BY b.id, b.name, b.description, b.created_at
         ORDER BY b.created_at DESC"
    );
    $stmt->execute([$org['id']]);
    $businesses = $stmt->fetchAll();
    foreach ($businesses as $b) {
        $totalBalance += (float) $b['balance'];
    }
}

$pageTitle = 'Dashboard - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="hero">
    <h1>Welcome back, <?= h(explode(' ', $user['name'])[0]) ?> 👋</h1>
    <p>Here's the combined position across all your businesses in <?= h($org['name'] ?? 'your workspace') ?>.</p>
    <div style="display:flex; gap:36px; flex-wrap:wrap; position:relative;">
        <div>
            <div style="font-size:0.8rem; color:rgba(255,255,255,0.88); font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Combined balance</div>
            <div style="font-size:2rem; font-weight:800; margin-top:2px;"><?= number_format($totalBalance, 2) ?></div>
        </div>
        <div>
            <div style="font-size:0.8rem; color:rgba(255,255,255,0.88); font-weight:600; text-transform:uppercase; letter-spacing:0.03em;">Businesses</div>
            <div style="font-size:2rem; font-weight:800; margin-top:2px;"><?= count($businesses) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h2>Add a business</h2>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/dashboard.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="form-grid">
            <div>
                <label for="business_name">Business name</label>
                <input type="text" id="business_name" name="business_name" value="<?= h($_POST['business_name'] ?? '') ?>" required>
            </div>
            <div>
                <label for="business_description">Description (optional)</label>
                <input type="text" id="business_description" name="business_description" value="<?= h($_POST['business_description'] ?? '') ?>">
            </div>
        </div>
        <button type="submit">+ Create business</button>
    </form>
</div>

<h2 style="font-size:1.05rem; margin: 6px 2px 12px;">Your businesses</h2>
<?php if (!$businesses): ?>
    <div class="card" style="text-align:center; padding:40px 10px;">
        <div style="font-size:2.2rem; margin-bottom:8px;">🏢</div>
        <p class="muted" style="margin:0;">No businesses yet &mdash; create your first one above.</p>
    </div>
<?php else: ?>
    <div class="biz-grid">
        <?php foreach ($businesses as $b): $bal = (float) $b['balance']; ?>
        <a class="biz-card" href="/soma_cashflow/public/business.php?id=<?= (int) $b['id'] ?>">
            <div class="biz-icon">🏢</div>
            <div class="biz-name"><?= h($b['name']) ?></div>
            <div class="biz-desc"><?= h($b['description'] ?? '') ?></div>
            <div class="biz-balance" style="color: <?= $bal >= 0 ? 'var(--success-fg)' : 'var(--error-fg)' ?>;">
                <?= number_format($bal, 2) ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

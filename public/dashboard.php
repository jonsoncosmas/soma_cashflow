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
            // Catches the race condition where two requests slip past the check above
            // at the same instant, and is also the safety net if the DB unique
            // constraint (sql/002_business_name_unique.sql) is the only thing that catches it.
            $errors[] = 'You already have a business named "' . $bizName . '". Choose a different name.';
        }
    }
}

$businesses = [];
if ($org) {
    $stmt = $pdo->prepare('SELECT id, name, description, created_at FROM businesses WHERE organization_id = ? ORDER BY created_at DESC');
    $stmt->execute([$org['id']]);
    $businesses = $stmt->fetchAll();
}

$pageTitle = 'Dashboard - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <span style="display:inline-block; background:var(--brand-100); color:var(--brand-700); font-size:0.72rem; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; padding:4px 10px; border-radius:20px; margin-bottom:10px;">Workspace</span>
    <h2 style="margin-bottom:2px;"><?= h($org['name'] ?? 'No organization found') ?></h2>
    <p class="muted" style="margin-top:2px;"><?= count($businesses) ?> business<?= count($businesses) === 1 ? '' : 'es' ?> tracked</p>
</div>

<div class="card">
    <h2>Add a business</h2>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/dashboard.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label for="business_name">Business name</label>
        <input type="text" id="business_name" name="business_name" value="<?= h($_POST['business_name'] ?? '') ?>" required>

        <label for="business_description">Description (optional)</label>
        <input type="text" id="business_description" name="business_description" value="<?= h($_POST['business_description'] ?? '') ?>">

        <button type="submit">Create business</button>
    </form>
</div>

<div class="card">
    <h2>Your businesses</h2>
    <?php if (!$businesses): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">🏢</div>
            <p class="muted" style="margin:0;">No businesses yet &mdash; create your first one above.</p>
        </div>
    <?php else: ?>
        <table>
            <tr><th>Name</th><th>Description</th><th>Created</th></tr>
            <?php foreach ($businesses as $b): ?>
            <tr>
                <td><?= h($b['name']) ?></td>
                <td><?= h($b['description'] ?? '') ?></td>
                <td><?= h($b['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

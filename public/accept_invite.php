<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];

$stmt = $pdo->prepare(
    "SELECT m.id, m.organization_id, m.invited_email, m.role, m.business_id, m.status,
            o.name AS org_name, b.name AS business_name
     FROM organization_members m
     INNER JOIN organizations o ON o.id = m.organization_id
     LEFT JOIN businesses b ON b.id = m.business_id
     WHERE m.invite_token = ?
     LIMIT 1"
);
$stmt->execute([$token]);
$invite = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invite && $user) {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    } elseif ($invite['status'] !== 'pending') {
        $errors[] = 'This invite has already been used.';
    } else {
        $stmt = $pdo->prepare(
            "UPDATE organization_members SET user_id = ?, status = 'active', accepted_at = NOW(), invite_token = NULL WHERE id = ?"
        );
        $stmt->execute([$user['id'], $invite['id']]);
        flash_set('success', 'You now have access to ' . $invite['org_name'] . '.');
        header('Location: /soma_cashflow/public/dashboard.php');
        exit;
    }
}

$pageTitle = 'Accept Invite - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
    <div class="brand-mark">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="6" fill="var(--brand-100)"/>
            <path d="M6 15L10 10L13 13L18 7" stroke="var(--brand-700)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M14 7H18V11" stroke="var(--brand-700)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Soma Cashflow
    </div>
    <div class="card">
        <?php foreach ($errors as $e): ?>
            <div class="flash error"><?= h($e) ?></div>
        <?php endforeach; ?>

        <?php if (!$invite): ?>
            <h2>Invite not found</h2>
            <p class="muted">This invite link is invalid. Ask the person who invited you to send a new one.</p>
        <?php elseif ($invite['status'] !== 'pending'): ?>
            <h2>Already used</h2>
            <p class="muted">This invite has already been accepted.</p>
        <?php else: ?>
            <h2>You've been invited</h2>
            <p class="muted">
                <strong><?= h($invite['org_name']) ?></strong> invited <strong><?= h($invite['invited_email']) ?></strong>
                as <strong><?= h(ucfirst($invite['role'])) ?></strong>
                for <strong><?= $invite['business_id'] ? h($invite['business_name']) : 'the whole workspace' ?></strong>.
            </p>

            <?php if ($user): ?>
                <?php if (strtolower($user['email']) !== strtolower($invite['invited_email'])): ?>
                    <div class="flash error">This invite was sent to <?= h($invite['invited_email']) ?>, but you're logged in as <?= h($user['email']) ?>. You can still accept if this is intentional.</div>
                <?php endif; ?>
                <form method="post" action="/soma_cashflow/public/accept_invite.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <button type="submit">Accept invite</button>
                </form>
            <?php else: ?>
                <p class="muted">Log in or create an account with <strong><?= h($invite['invited_email']) ?></strong> to accept.</p>
                <a class="btn" style="width:100%; display:block; text-align:center; box-sizing:border-box;" href="/soma_cashflow/public/register.php?invite=<?= h($token) ?>">Create account</a>
                <a class="link" style="display:block; text-align:center; margin-top:14px;" href="/soma_cashflow/public/login.php?invite=<?= h($token) ?>">Already have an account? Log in</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

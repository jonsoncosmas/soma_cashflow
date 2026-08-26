<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
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

$errors = [];
$newInviteLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $org) {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'invite') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role  = (string) ($_POST['role'] ?? '');
        $scope = (string) ($_POST['scope'] ?? 'org'); // 'org' or 'business:{id}'

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!in_array($role, ['admin', 'viewer'], true)) {
            $errors[] = 'Select a valid role.';
        }

        $businessId = null;
        if ($scope !== 'org') {
            $businessId = (int) substr($scope, 9);
            $valid = false;
            foreach ($businesses as $b) {
                if ((int) $b['id'] === $businessId) { $valid = true; break; }
            }
            if (!$valid) {
                $errors[] = 'Invalid business selected.';
            }
        }

        // Can't invite the owner's own email
        if (!$errors && $email === strtolower($user['email'])) {
            $errors[] = "You can't invite yourself.";
        }

        // Check for an existing invite/membership with the same email+scope
        if (!$errors) {
            $stmt = $pdo->prepare(
                'SELECT id FROM organization_members WHERE organization_id = ? AND invited_email = ? AND ' .
                ($businessId ? 'business_id = ?' : 'business_id IS NULL')
            );
            $params = [$org['id'], $email];
            if ($businessId) { $params[] = $businessId; }
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors[] = 'This person already has (or is pending) access at that scope.';
            }
        }

        if (!$errors) {
            // Does a user with this email already exist? If so, attach immediately.
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                $stmt = $pdo->prepare(
                    "INSERT INTO organization_members (organization_id, user_id, invited_email, role, business_id, invited_by, status, accepted_at)
                     VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())"
                );
                $stmt->execute([$org['id'], $existingUser['id'], $email, $role, $businessId, $user['id']]);
                flash_set('success', $email . ' now has ' . $role . ' access.');
            } else {
                $token = bin2hex(random_bytes(24));
                $stmt = $pdo->prepare(
                    "INSERT INTO organization_members (organization_id, user_id, invited_email, role, business_id, invited_by, invite_token, status)
                     VALUES (?, NULL, ?, ?, ?, ?, ?, 'pending')"
                );
                $stmt->execute([$org['id'], $email, $role, $businessId, $user['id'], $token]);
                $newInviteLink = '/soma_cashflow/public/accept_invite.php?token=' . $token;
                flash_set('success', 'Invite created for ' . $email . '. Share the link below with them.');
            }
        }
    } elseif ($action === 'remove') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM organization_members WHERE id = ? AND organization_id = ?');
        $stmt->execute([$memberId, $org['id']]);
        flash_set('success', 'Access removed.');
    }
}

$members = [];
if ($org) {
    $stmt = $pdo->prepare(
        "SELECT m.id, m.invited_email, m.role, m.business_id, m.status, m.invite_token, b.name AS business_name
         FROM organization_members m
         LEFT JOIN businesses b ON b.id = m.business_id
         WHERE m.organization_id = ?
         ORDER BY m.status DESC, m.created_at DESC"
    );
    $stmt->execute([$org['id']]);
    $members = $stmt->fetchAll();
}

$pageTitle = 'Team - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <span class="eyebrow">Team</span>
    <h2 style="margin-bottom:2px;">Manage access</h2>
    <p class="muted" style="margin-top:2px;">Invite an accountant, analyst, or teammate to view or edit your businesses.</p>
</div>

<div class="card">
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>

    <?php if ($newInviteLink): ?>
        <div class="flash success" style="word-break:break-all;">
            Invite link (no email is sent automatically &mdash; copy and share this):<br>
            <a class="link" href="<?= h($newInviteLink) ?>"><?= h($newInviteLink) ?></a>
        </div>
    <?php endif; ?>

    <?php if (!$businesses): ?>
        <p class="muted">Create a business first before inviting people to it.</p>
    <?php else: ?>
    <form method="post" action="/soma_cashflow/public/team.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="invite">
        <div class="form-grid">
            <div class="full">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="accountant@example.com" required>
            </div>
            <div>
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="viewer">Viewer (read-only)</option>
                    <option value="admin">Admin (can add transactions)</option>
                </select>
            </div>
            <div>
                <label for="scope">Access to</label>
                <select id="scope" name="scope" required>
                    <option value="org">Whole workspace (all businesses)</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="business:<?= (int) $b['id'] ?>">Just "<?= h($b['name']) ?>"</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit">Send invite</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>People with access</h2>
    <?php if (!$members): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">🤝</div>
            <p class="muted" style="margin:0;">No one else has access yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <tr><th>Email</th><th>Role</th><th>Scope</th><th>Status</th><th></th></tr>
            <?php foreach ($members as $m): ?>
            <tr>
                <td><?= h($m['invited_email']) ?></td>
                <td><span class="pill <?= $m['role'] === 'admin' ? 'loan_received' : 'income' ?>"><?= h(ucfirst($m['role'])) ?></span></td>
                <td><?= $m['business_id'] ? h($m['business_name']) : 'Whole workspace' ?></td>
                <td><?= $m['status'] === 'active' ? '<span style="color:var(--success-fg); font-weight:600;">Active</span>' : '<span style="color:var(--ink-400); font-weight:600;">Pending</span>' ?></td>
                <td>
                    <form method="post" action="/soma_cashflow/public/team.php" onsubmit="return confirm('Remove this person\'s access?');">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
                        <button type="submit" style="margin:0; background:var(--error-bg); color:var(--error-fg); box-shadow:none; padding:6px 12px; font-size:0.82rem;">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

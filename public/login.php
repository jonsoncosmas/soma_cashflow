<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
$pdo = require __DIR__ . '/../config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    }

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            login_user($user);
            flash_set('success', 'Welcome back, ' . $user['name'] . '.');
            header('Location: /soma_cashflow/public/dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Login - Soma Cashflow';
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
        <h2>Welcome back</h2>
        <p class="muted">Log in to your workspace.</p>
        <?php foreach ($errors as $e): ?>
            <div class="flash error"><?= h($e) ?></div>
        <?php endforeach; ?>
        <form method="post" action="/soma_cashflow/public/login.php">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Log in</button>
        </form>
        <p class="muted" style="margin-top:16px;">No account yet? <a class="link" href="/soma_cashflow/public/register.php">Register</a></p>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

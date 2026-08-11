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

    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
            );
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();

            $orgName = $name . "'s Workspace";
            $stmt = $pdo->prepare(
                'INSERT INTO organizations (name, owner_user_id) VALUES (?, ?)'
            );
            $stmt->execute([$orgName, $userId]);

            $pdo->commit();

            login_user(['id' => $userId, 'name' => $name, 'email' => $email]);
            flash_set('success', 'Welcome, ' . $name . '! Your workspace has been created.');
            header('Location: /soma_cashflow/public/dashboard.php');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong creating your account. Please try again.';
        }
    }
}

$pageTitle = 'Register - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <h2>Create your account</h2>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/register.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="6">

        <button type="submit">Create account</button>
    </form>
    <p class="muted">Already have an account? <a class="link" href="/soma_cashflow/public/login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

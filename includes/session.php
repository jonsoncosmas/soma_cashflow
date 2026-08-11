<?php
declare(strict_types=1);

/**
 * Soma Cashflow - Session / auth helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns the logged-in user's array (id, name, email) or null.
 */
function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id'    => (int) $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
    ];
}

/**
 * Redirects to login if no user is logged in. Call at the top of any
 * protected page.
 */
function require_login(): void
{
    if (!current_user()) {
        header('Location: /soma_cashflow/public/login.php');
        exit;
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

/**
 * Simple CSRF token helpers
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool
{
    return $token !== null && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

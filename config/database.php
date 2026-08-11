<?php
/**
 * Soma Cashflow - Database connection
 *
 * Returns a shared PDO instance. Include this file wherever a DB
 * connection is needed:
 *
 *   $pdo = require __DIR__ . '/config/database.php';
 */

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    die(
        "Missing config/config.php.\n" .
        "Copy config/config.sample.php to config/config.php and fill in your DB credentials."
    );
}

$config = require $configPath;
$db = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    $db['port'],
    $db['name'],
    $db['charset']
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    if (!empty($config['debug'])) {
        die('Database connection failed: ' . $e->getMessage());
    }
    die('Database connection failed.');
}

return $pdo;

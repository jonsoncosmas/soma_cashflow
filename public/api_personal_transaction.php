<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$uuid = (string) ($input['uuid'] ?? '');
$type = (string) ($input['type'] ?? '');
$category = trim((string) ($input['category'] ?? ''));
$amount = $input['amount'] ?? null;
$description = trim((string) ($input['description'] ?? ''));
$transactionDate = (string) ($input['transaction_date'] ?? '');

if (!preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing uuid.']);
    exit;
}
if (!in_array($type, ['income', 'expense'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid type.']);
    exit;
}
if ($category === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Category is required.']);
    exit;
}
if (!is_numeric($amount) || (float) $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Amount must be a positive number.']);
    exit;
}
if ($transactionDate === '' || !DateTime::createFromFormat('Y-m-d', $transactionDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid date.']);
    exit;
}

$pdo = require __DIR__ . '/../config/database.php';

$stmt = $pdo->prepare(
    'INSERT INTO personal_transactions (client_uuid, user_id, type, category, amount, description, transaction_date)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE id = id'
);
$stmt->execute([$uuid, $user['id'], $type, $category, (float) $amount, $description ?: null, $transactionDate]);

echo json_encode(['success' => true, 'uuid' => $uuid]);

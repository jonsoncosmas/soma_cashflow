<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/access_control.php';

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
$businessId = (int) ($input['business_id'] ?? 0);
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
if (!in_array($type, ['income', 'expense', 'loan_received', 'loan_given'], true)) {
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

$access = get_business_access($pdo, $businessId, $user['id']);
if (!$access || !role_can_edit($access['role'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No edit access to this business.']);
    exit;
}

// Idempotent upsert: if this uuid was already synced (e.g. a retried
// request after the first response was lost), this is a safe no-op -
// "ON DUPLICATE KEY UPDATE id=id" touches nothing and inserts nothing new.
$stmt = $pdo->prepare(
    'INSERT INTO transactions (client_uuid, business_id, user_id, type, category, amount, description, transaction_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE id = id'
);
$stmt->execute([$uuid, $businessId, $user['id'], $type, $category, (float) $amount, $description ?: null, $transactionDate]);

echo json_encode(['success' => true, 'uuid' => $uuid]);

<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/access_control.php';
require __DIR__ . '/../includes/ai_categorizer.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$description = trim((string) ($input['description'] ?? ''));
$amount = isset($input['amount']) && $input['amount'] !== '' ? (float) $input['amount'] : null;
$context = (string) ($input['context'] ?? '');
$businessId = isset($input['business_id']) ? (int) $input['business_id'] : null;

if ($description === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Description is required.']);
    exit;
}
if (!in_array($context, ['business', 'personal'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid context.']);
    exit;
}

$pdo = require __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';

if ($context === 'business') {
    if (!$businessId) {
        http_response_code(400);
        echo json_encode(['error' => 'business_id is required.']);
        exit;
    }
    $access = get_business_access($pdo, $businessId, $user['id']);
    if (!$access || !role_can_edit($access['role'])) {
        http_response_code(403);
        echo json_encode(['error' => 'No edit access to this business.']);
        exit;
    }
    $allowedTypes = ['income', 'expense', 'loan_received', 'loan_given'];
    $categorySuggestions = ['Sales', 'Materials / Purchase', 'Labor', 'Machinery', 'Livestock', 'Utilities', 'Rent', 'Transport', 'Loan', 'Capital Injection', 'Other'];
} else {
    $businessId = null;
    $allowedTypes = ['income', 'expense'];
    $categorySuggestions = ['Salary', 'Freelance', 'Gift', 'Investment Income', 'Other Income', 'Rent', 'Food', 'Transport', 'Personal Expense', 'Other'];
}

$result = ai_categorize($pdo, $config, $user['id'], $businessId, $context, $description, $amount, $allowedTypes, $categorySuggestions);

echo json_encode($result);

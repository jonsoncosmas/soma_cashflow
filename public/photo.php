<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/access_control.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$mediaId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM business_media WHERE id = ?');
$stmt->execute([$mediaId]);
$media = $stmt->fetch();

if (!$media) {
    http_response_code(404);
    exit;
}

$access = get_business_access($pdo, (int) $media['business_id'], $user['id']);
if (!$access) {
    http_response_code(403);
    exit;
}

$path = __DIR__ . '/../storage/business_photos/' . basename($media['file_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $media['mime_type']);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);

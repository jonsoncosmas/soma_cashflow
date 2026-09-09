<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/access_control.php';
require __DIR__ . '/../includes/media_helpers.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$businessId = (int) ($_GET['business_id'] ?? 0);
$access = get_business_access($pdo, $businessId, $user['id']);

if (!$access) {
    flash_set('error', 'Business not found or you do not have access to it.');
    header('Location: /soma_cashflow/public/dashboard.php');
    exit;
}
$business = $access['business'];
$canEdit = role_can_edit($access['role']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        flash_set('error', 'You have view-only access to this business.');
        header('Location: /soma_cashflow/public/gallery.php?business_id=' . $businessId);
        exit;
    }
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    }

    $action = (string) ($_POST['action'] ?? 'upload');

    if ($action === 'delete') {
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM business_media WHERE id = ? AND business_id = ?');
        $stmt->execute([$mediaId, $businessId]);
        $media = $stmt->fetch();
        if ($media) {
            $pdo->prepare('DELETE FROM business_media WHERE id = ?')->execute([$mediaId]);
            media_delete_file($media['file_name']);
            flash_set('success', 'Photo removed.');
        }
        header('Location: /soma_cashflow/public/gallery.php?business_id=' . $businessId);
        exit;
    }

    // action === 'upload'
    $caption = trim((string) ($_POST['caption'] ?? ''));
    $takenDate = (string) ($_POST['taken_date'] ?? date('Y-m-d'));

    if ($takenDate === '' || !DateTime::createFromFormat('Y-m-d', $takenDate)) {
        $errors[] = 'Please provide a valid date.';
    }
    if (empty($_FILES['photo']['name'])) {
        $errors[] = 'Please choose a photo to upload.';
    }

    if (!$errors) {
        $result = media_handle_upload($_FILES['photo']);
        if (isset($result['error'])) {
            $errors[] = $result['error'];
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO business_media (business_id, uploaded_by, caption, taken_date, file_name, original_name, mime_type, file_size)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $businessId, $user['id'], $caption ?: null, $takenDate,
                $result['file_name'], $_FILES['photo']['name'] ?? null, $result['mime_type'], $result['file_size'],
            ]);
            flash_set('success', 'Photo uploaded.');
            header('Location: /soma_cashflow/public/gallery.php?business_id=' . $businessId);
            exit;
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT id, caption, taken_date, uploaded_by FROM business_media
     WHERE business_id = ? ORDER BY taken_date DESC, id DESC'
);
$stmt->execute([$businessId]);
$photos = $stmt->fetchAll();

$pageTitle = 'Gallery - ' . h($business['name']) . ' - Soma Cashflow';
require __DIR__ . '/../includes/header.php';
?>
<p class="muted" style="margin-bottom:14px;"><a class="link" href="/soma_cashflow/public/business.php?id=<?= (int) $businessId ?>">&larr; <?= h($business['name']) ?></a></p>

<div class="card">
    <span class="eyebrow">Gallery</span>
    <h2 style="margin-bottom:2px;"><?= h($business['name']) ?> photos</h2>
    <p class="muted" style="margin-top:2px;">Track visual progress over time &mdash; e.g. "bought chickens" week 1, coop built week 3.</p>
</div>

<?php if ($canEdit): ?>
<div class="card">
    <h2>Upload a photo</h2>
    <?php foreach ($errors as $e): ?>
        <div class="flash error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="/soma_cashflow/public/gallery.php?business_id=<?= (int) $businessId ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">
        <div class="form-grid">
            <div class="full">
                <label for="photo">Photo (JPEG, PNG, GIF, or WebP, max 5MB)</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" required>
            </div>
            <div>
                <label for="taken_date">Date</label>
                <input type="date" id="taken_date" name="taken_date" value="<?= h(date('Y-m-d')) ?>" required>
            </div>
            <div>
                <label for="caption">Caption (optional)</label>
                <input type="text" id="caption" name="caption" placeholder="e.g. Bought chickens - week 1">
            </div>
        </div>
        <button type="submit">+ Upload photo</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Timeline</h2>
    <?php if (!$photos): ?>
        <div style="text-align:center; padding:24px 10px;">
            <div style="font-size:2rem; margin-bottom:6px;">📷</div>
            <p class="muted" style="margin:0;">No photos yet.</p>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:14px;">
            <?php foreach ($photos as $p): ?>
            <div>
                <a href="/soma_cashflow/public/photo.php?id=<?= (int) $p['id'] ?>" target="_blank">
                    <img src="/soma_cashflow/public/photo.php?id=<?= (int) $p['id'] ?>" alt="<?= h($p['caption'] ?? '') ?>"
                         style="width:100%; aspect-ratio:1; object-fit:cover; border-radius:var(--radius-md); border:1px solid var(--border); display:block;">
                </a>
                <p style="font-size:0.82rem; font-weight:600; margin:6px 0 0;"><?= h($p['taken_date']) ?></p>
                <?php if ($p['caption']): ?>
                    <p class="muted" style="font-size:0.8rem; margin:2px 0 0;"><?= h($p['caption']) ?></p>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                <form method="post" action="/soma_cashflow/public/gallery.php?business_id=<?= (int) $businessId ?>" onsubmit="return confirm('Delete this photo?');" style="margin-top:4px;">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="media_id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" style="margin:0; background:var(--error-bg); color:var(--error-fg); box-shadow:none; padding:4px 10px; font-size:0.76rem;">Delete</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

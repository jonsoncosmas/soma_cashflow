<?php
declare(strict_types=1);

/**
 * Soma Cashflow - Business photo gallery helpers (Phase 8)
 */

const MEDIA_MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const MEDIA_ALLOWED_MIME_TO_EXT = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

function media_storage_dir(): string
{
    return __DIR__ . '/../storage/business_photos';
}

/**
 * Validates and saves an uploaded file from $_FILES. Returns
 * ['file_name'=>string,'mime_type'=>string,'file_size'=>int] on success,
 * or ['error'=>string] on failure. Validates the ACTUAL image content via
 * getimagesize(), not just the client-supplied filename/extension, since
 * those can be spoofed.
 */
function media_handle_upload(array $file): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File is too large.',
            UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted, please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was selected.',
        ];
        return ['error' => $messages[$file['error'] ?? -1] ?? 'Upload failed, please try again.'];
    }

    if ($file['size'] > MEDIA_MAX_FILE_SIZE) {
        return ['error' => 'File must be under 5MB.'];
    }

    // Validate it's genuinely an image by reading its actual content, not the filename.
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['error' => 'File is not a valid image.'];
    }

    $mimeType = $imageInfo['mime'];
    if (!isset(MEDIA_ALLOWED_MIME_TO_EXT[$mimeType])) {
        return ['error' => 'Only JPEG, PNG, GIF, or WebP images are allowed.'];
    }

    $dir = media_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['error' => 'Server storage is not writable.'];
    }

    $ext = MEDIA_ALLOWED_MIME_TO_EXT[$mimeType];
    $fileName = bin2hex(random_bytes(20)) . '.' . $ext;
    $destination = $dir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['error' => 'Could not save the uploaded file.'];
    }

    return [
        'file_name' => $fileName,
        'mime_type' => $mimeType,
        'file_size' => (int) $file['size'],
    ];
}

function media_delete_file(string $fileName): void
{
    $path = media_storage_dir() . '/' . basename($fileName);
    if (is_file($path)) {
        @unlink($path);
    }
}

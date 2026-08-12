<?php
declare(strict_types=1);

/**
 * Returns the business row (joined with organization ownership check) if
 * the given user owns it, or null if it doesn't exist / isn't theirs.
 *
 * This is intentionally simple for now (one owner per org). Phase 5 will
 * replace the ownership check with proper per-business RBAC roles.
 */
function get_owned_business(PDO $pdo, int $businessId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT b.id, b.name, b.description, b.organization_id
         FROM businesses b
         INNER JOIN organizations o ON o.id = b.organization_id
         WHERE b.id = ? AND o.owner_user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$businessId, $userId]);
    $business = $stmt->fetch();

    return $business ?: null;
}

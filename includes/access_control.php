<?php
declare(strict_types=1);

/**
 * Returns ['role' => 'owner'|'admin'|'viewer', 'business' => row, 'org_name' => string]
 * if the user can access this business, or null if they cannot.
 */
function get_business_access(PDO $pdo, int $businessId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT b.id, b.name, b.description, b.organization_id, o.name AS org_name, o.owner_user_id
         FROM businesses b
         INNER JOIN organizations o ON o.id = b.organization_id
         WHERE b.id = ?'
    );
    $stmt->execute([$businessId]);
    $business = $stmt->fetch();
    if (!$business) {
        return null;
    }

    if ((int) $business['owner_user_id'] === $userId) {
        return ['role' => 'owner', 'business' => $business, 'org_name' => $business['org_name']];
    }

    // Business-specific membership takes precedence over org-wide.
    $stmt = $pdo->prepare(
        "SELECT role FROM organization_members
         WHERE organization_id = ? AND user_id = ? AND status = 'active' AND business_id = ?
         LIMIT 1"
    );
    $stmt->execute([$business['organization_id'], $userId, $businessId]);
    $row = $stmt->fetch();
    if ($row) {
        return ['role' => $row['role'], 'business' => $business, 'org_name' => $business['org_name']];
    }

    $stmt = $pdo->prepare(
        "SELECT role FROM organization_members
         WHERE organization_id = ? AND user_id = ? AND status = 'active' AND business_id IS NULL
         LIMIT 1"
    );
    $stmt->execute([$business['organization_id'], $userId]);
    $row = $stmt->fetch();
    if ($row) {
        return ['role' => $row['role'], 'business' => $business, 'org_name' => $business['org_name']];
    }

    return null;
}

/** True if the given access-control role is allowed to add/edit transactions. */
function role_can_edit(string $role): bool
{
    return $role === 'owner' || $role === 'admin';
}

/**
 * Businesses shared with this user via organization_members (not owned by
 * them), each tagged with role and the owning org's name.
 */
function get_shared_businesses(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT b.id, b.name, b.description, o.name AS org_name, m.role
         FROM organization_members m
         INNER JOIN organizations o ON o.id = m.organization_id
         INNER JOIN businesses b ON b.id = m.business_id
         WHERE m.user_id = ? AND m.status = 'active' AND m.business_id IS NOT NULL"
    );
    $stmt->execute([$userId]);
    $direct = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT b.id, b.name, b.description, o.name AS org_name, m.role
         FROM organization_members m
         INNER JOIN organizations o ON o.id = m.organization_id
         INNER JOIN businesses b ON b.organization_id = m.organization_id
         WHERE m.user_id = ? AND m.status = 'active' AND m.business_id IS NULL"
    );
    $stmt->execute([$userId]);
    $orgWide = $stmt->fetchAll();

    // De-dupe by business id (a user could theoretically have both a direct
    // and an org-wide grant; direct takes precedence but either way show once).
    $byId = [];
    foreach (array_merge($orgWide, $direct) as $row) {
        $byId[$row['id']] = $row;
    }
    return array_values($byId);
}

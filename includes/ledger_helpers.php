<?php
declare(strict_types=1);

/**
 * Returns the business's own transaction total (income/expense/loan) PLUS
 * net transfers in/out, i.e. the true current balance shown to the user.
 */
function get_business_balance(PDO $pdo, int $businessId): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type IN ('income','loan_received') THEN amount
                                   WHEN type IN ('expense','loan_given') THEN -amount
                                   ELSE 0 END), 0) AS total
         FROM transactions WHERE business_id = ?"
    );
    $stmt->execute([$businessId]);
    $ownTotal = (float) $stmt->fetch()['total'];

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS t FROM fund_transfers WHERE to_type = ? AND to_business_id = ?');
    $stmt->execute(['business', $businessId]);
    $transferIn = (float) $stmt->fetch()['t'];

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS t FROM fund_transfers WHERE from_type = ? AND from_business_id = ?');
    $stmt->execute(['business', $businessId]);
    $transferOut = (float) $stmt->fetch()['t'];

    return $ownTotal + $transferIn - $transferOut;
}

/**
 * Returns the logged-in user's personal balance: personal income - personal
 * expense, plus net transfers in/out of the personal ledger.
 */
function get_personal_balance(PDO $pdo, int $userId): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) AS total
         FROM personal_transactions WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $ownTotal = (float) $stmt->fetch()['total'];

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS t FROM fund_transfers WHERE to_type = ? AND user_id = ?');
    $stmt->execute(['personal', $userId]);
    $transferIn = (float) $stmt->fetch()['t'];

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS t FROM fund_transfers WHERE from_type = ? AND user_id = ?');
    $stmt->execute(['personal', $userId]);
    $transferOut = (float) $stmt->fetch()['t'];

    return $ownTotal + $transferIn - $transferOut;
}

/**
 * Human label for a transfer endpoint, e.g. "Personal" or "Poultry Farm".
 * $businessNames should be a [id => name] map, pre-fetched for the org.
 */
function transfer_endpoint_label(string $type, ?int $businessId, array $businessNames): string
{
    if ($type === 'personal') {
        return 'Personal';
    }
    return $businessNames[$businessId] ?? 'Unknown business';
}

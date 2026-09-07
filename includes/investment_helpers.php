<?php
declare(strict_types=1);

/**
 * Soma Cashflow - Investment growth calculation (Phase 7)
 *
 * $entries must be an array of ['type'=>'deposit'|'valuation','amount'=>float,'date'=>'Y-m-d'].
 * Order doesn't affect the result (see below), but callers typically pass
 * them sorted chronologically for history display purposes anyway.
 *
 * Growth = latest valuation - total deposits ever made (the standard
 * "total return" calculation any brokerage/investment app uses: your net
 * gain since day one is simply what it's worth now minus everything you've
 * put in, regardless of how many times you checked the balance in between).
 * This deliberately does NOT try to attribute growth to individual periods
 * between checks - only the running total matters.
 */
function compute_investment_growth(array $entries): array
{
    $totalDeposits = 0.0;
    $depositCount = 0;
    $latestValuation = null;
    $latestValuationDate = null;
    $firstValuationDate = null;

    foreach ($entries as $e) {
        $amount = (float) $e['amount'];
        if ($e['type'] === 'deposit') {
            $totalDeposits += $amount;
            $depositCount++;
        } else { // valuation
            if ($firstValuationDate === null || $e['date'] < $firstValuationDate) {
                $firstValuationDate = $e['date'];
            }
            if ($latestValuationDate === null || $e['date'] >= $latestValuationDate) {
                $latestValuation = $amount;
                $latestValuationDate = $e['date'];
            }
        }
    }

    $currentValue = $latestValuation ?? $totalDeposits;
    $totalGrowth = $latestValuation !== null ? ($latestValuation - $totalDeposits) : 0.0;
    $growthPercent = $totalDeposits > 0 ? ($totalGrowth / $totalDeposits) * 100 : 0.0;

    return [
        'total_deposits' => $totalDeposits,
        'deposit_count' => $depositCount,
        'total_growth' => $totalGrowth,
        'growth_percent' => $growthPercent,
        'current_value' => $currentValue,
        'has_valuation' => $latestValuation !== null,
        'first_valuation_date' => $firstValuationDate,
        'last_valuation_date' => $latestValuationDate,
    ];
}

/**
 * Returns ['role'=>'owner'|'admin'|'viewer','account'=>row] if the user can
 * access this investment account, or null if they cannot. Personal accounts
 * are owner-only (matching the personal ledger); business accounts follow
 * the business's own RBAC (owner/admin can edit, viewer is read-only).
 */
function get_investment_access(PDO $pdo, int $accountId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM investment_accounts WHERE id = ?');
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();
    if (!$account) {
        return null;
    }

    if ($account['owner_type'] === 'personal') {
        if ((int) $account['user_id'] === $userId) {
            return ['role' => 'owner', 'account' => $account];
        }
        return null;
    }

    $access = get_business_access($pdo, (int) $account['business_id'], $userId);
    if (!$access) {
        return null;
    }
    return ['role' => $access['role'], 'account' => $account, 'business_name' => $access['business']['name']];
}

/** Fetch all entries for an account, sorted chronologically. */
function get_investment_entries(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare(
        'SELECT entry_type AS type, amount, entry_date AS date, note
         FROM investment_entries WHERE account_id = ? ORDER BY entry_date ASC, id ASC'
    );
    $stmt->execute([$accountId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['amount'] = (float) $row['amount'];
    }
    unset($row);
    return $rows;
}

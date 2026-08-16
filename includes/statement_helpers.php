<?php
declare(strict_types=1);

/**
 * Soma Cashflow - Statement engine (Phase 4)
 */

/**
 * Resolves a preset (or custom start/end) into a concrete date range.
 */
function resolve_date_range(string $preset, ?string $customStart, ?string $customEnd): array
{
    $today = new DateTime('now');

    switch ($preset) {
        case 'week':
            $start = (clone $today)->modify('monday this week');
            $end   = (clone $today)->modify('sunday this week');
            break;

        case 'quarter':
            $month = (int) $today->format('n');
            $qStartMonth = (int) (floor(($month - 1) / 3) * 3 + 1);
            $start = new DateTime($today->format('Y') . '-' . str_pad((string) $qStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
            $end   = (clone $start)->modify('+2 months')->modify('last day of this month');
            break;

        case 'half':
            $half  = ((int) $today->format('n') <= 6) ? '01' : '07';
            $start = new DateTime($today->format('Y') . '-' . $half . '-01');
            $end   = (clone $start)->modify('+5 months')->modify('last day of this month');
            break;

        case 'year':
            $start = new DateTime($today->format('Y') . '-01-01');
            $end   = new DateTime($today->format('Y') . '-12-31');
            break;

        case 'custom':
            $start = ($customStart && DateTime::createFromFormat('Y-m-d', $customStart))
                ? new DateTime($customStart) : new DateTime('first day of this month');
            $end = ($customEnd && DateTime::createFromFormat('Y-m-d', $customEnd))
                ? new DateTime($customEnd) : new DateTime('last day of this month');
            break;

        case 'month':
        default:
            $preset = 'month';
            $start  = new DateTime('first day of this month');
            $end    = new DateTime('last day of this month');
            break;
    }

    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    return [
        'preset' => $preset,
        'start'  => $start->format('Y-m-d'),
        'end'    => $end->format('Y-m-d'),
        'label'  => $start->format('j M Y') . ' - ' . $end->format('j M Y'),
    ];
}

/** Income statement (categorized) for a single business over a date range. */
function business_income_statement(PDO $pdo, int $businessId, string $start, string $end): array
{
    $income = [];
    $expense = [];
    $stmt = $pdo->prepare(
        "SELECT type, category, SUM(amount) AS total FROM transactions
         WHERE business_id = ? AND type IN ('income','expense') AND transaction_date BETWEEN ? AND ?
         GROUP BY type, category ORDER BY total DESC"
    );
    $stmt->execute([$businessId, $start, $end]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['type'] === 'income') {
            $income[$row['category']] = (float) $row['total'];
        } else {
            $expense[$row['category']] = (float) $row['total'];
        }
    }
    $incomeTotal = array_sum($income);
    $expenseTotal = array_sum($expense);
    return [
        'income' => $income, 'income_total' => $incomeTotal,
        'expense' => $expense, 'expense_total' => $expenseTotal,
        'net' => $incomeTotal - $expenseTotal,
    ];
}

/** Income statement (categorized) for the personal ledger over a date range. */
function personal_income_statement(PDO $pdo, int $userId, string $start, string $end): array
{
    $income = [];
    $expense = [];
    $stmt = $pdo->prepare(
        "SELECT type, category, SUM(amount) AS total FROM personal_transactions
         WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
         GROUP BY type, category ORDER BY total DESC"
    );
    $stmt->execute([$userId, $start, $end]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['type'] === 'income') {
            $income[$row['category']] = (float) $row['total'];
        } else {
            $expense[$row['category']] = (float) $row['total'];
        }
    }
    $incomeTotal = array_sum($income);
    $expenseTotal = array_sum($expense);
    return [
        'income' => $income, 'income_total' => $incomeTotal,
        'expense' => $expense, 'expense_total' => $expenseTotal,
        'net' => $incomeTotal - $expenseTotal,
    ];
}

function get_business_balance_as_of(PDO $pdo, int $businessId, string $asOfDate): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type IN ('income','loan_received') THEN amount
                                   WHEN type IN ('expense','loan_given') THEN -amount ELSE 0 END), 0) AS t
         FROM transactions WHERE business_id = ? AND transaction_date <= ?"
    );
    $stmt->execute([$businessId, $asOfDate]);
    $own = (float) $stmt->fetch()['t'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE to_type='business' AND to_business_id=? AND transfer_date <= ?");
    $stmt->execute([$businessId, $asOfDate]);
    $in = (float) $stmt->fetch()['t'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE from_type='business' AND from_business_id=? AND transfer_date <= ?");
    $stmt->execute([$businessId, $asOfDate]);
    $out = (float) $stmt->fetch()['t'];

    return $own + $in - $out;
}

function get_personal_balance_as_of(PDO $pdo, int $userId, string $asOfDate): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) AS t
         FROM personal_transactions WHERE user_id = ? AND transaction_date <= ?"
    );
    $stmt->execute([$userId, $asOfDate]);
    $own = (float) $stmt->fetch()['t'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE to_type='personal' AND user_id=? AND transfer_date <= ?");
    $stmt->execute([$userId, $asOfDate]);
    $in = (float) $stmt->fetch()['t'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE from_type='personal' AND user_id=? AND transfer_date <= ?");
    $stmt->execute([$userId, $asOfDate]);
    $out = (float) $stmt->fetch()['t'];

    return $own + $in - $out;
}

function day_before(string $date): string
{
    return (new DateTime($date))->modify('-1 day')->format('Y-m-d');
}

/** Cash flow statement for a single business: operating vs financing activities. */
function business_cash_flow(PDO $pdo, int $businessId, string $start, string $end): array
{
    $stmt = $pdo->prepare(
        "SELECT type, COALESCE(SUM(amount),0) AS total FROM transactions
         WHERE business_id = ? AND transaction_date BETWEEN ? AND ? GROUP BY type"
    );
    $stmt->execute([$businessId, $start, $end]);
    $totals = ['income' => 0.0, 'expense' => 0.0, 'loan_received' => 0.0, 'loan_given' => 0.0];
    foreach ($stmt->fetchAll() as $r) {
        $totals[$r['type']] = (float) $r['total'];
    }

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE to_type='business' AND to_business_id=? AND transfer_date BETWEEN ? AND ?");
    $stmt->execute([$businessId, $start, $end]);
    $transferIn = (float) $stmt->fetch()['t'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE from_type='business' AND from_business_id=? AND transfer_date BETWEEN ? AND ?");
    $stmt->execute([$businessId, $start, $end]);
    $transferOut = (float) $stmt->fetch()['t'];

    $operatingIn  = $totals['income'];
    $operatingOut = $totals['expense'];
    $operatingNet = $operatingIn - $operatingOut;

    $financingIn  = $totals['loan_received'] + $transferIn;
    $financingOut = $totals['loan_given'] + $transferOut;
    $financingNet = $financingIn - $financingOut;

    $netChange = $operatingNet + $financingNet;
    $opening = get_business_balance_as_of($pdo, $businessId, day_before($start));
    $closing = get_business_balance_as_of($pdo, $businessId, $end);

    return [
        'operatingIn' => $operatingIn, 'operatingOut' => $operatingOut, 'operatingNet' => $operatingNet,
        'loanReceived' => $totals['loan_received'], 'loanGiven' => $totals['loan_given'],
        'transferIn' => $transferIn, 'transferOut' => $transferOut,
        'financingIn' => $financingIn, 'financingOut' => $financingOut, 'financingNet' => $financingNet,
        'netChange' => $netChange, 'opening' => $opening, 'closing' => $closing,
    ];
}

/** Cash flow statement for the personal ledger: operating vs financing (transfers only, no loans). */
function personal_cash_flow(PDO $pdo, int $userId, string $start, string $end): array
{
    $stmt = $pdo->prepare(
        "SELECT type, COALESCE(SUM(amount),0) AS total FROM personal_transactions
         WHERE user_id = ? AND transaction_date BETWEEN ? AND ? GROUP BY type"
    );
    $stmt->execute([$userId, $start, $end]);
    $totals = ['income' => 0.0, 'expense' => 0.0];
    foreach ($stmt->fetchAll() as $r) {
        $totals[$r['type']] = (float) $r['total'];
    }

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE to_type='personal' AND user_id=? AND transfer_date BETWEEN ? AND ?");
    $stmt->execute([$userId, $start, $end]);
    $transferIn = (float) $stmt->fetch()['t'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE from_type='personal' AND user_id=? AND transfer_date BETWEEN ? AND ?");
    $stmt->execute([$userId, $start, $end]);
    $transferOut = (float) $stmt->fetch()['t'];

    $operatingIn  = $totals['income'];
    $operatingOut = $totals['expense'];
    $operatingNet = $operatingIn - $operatingOut;

    $financingIn  = $transferIn;
    $financingOut = $transferOut;
    $financingNet = $financingIn - $financingOut;

    $netChange = $operatingNet + $financingNet;
    $opening = get_personal_balance_as_of($pdo, $userId, day_before($start));
    $closing = get_personal_balance_as_of($pdo, $userId, $end);

    return [
        'operatingIn' => $operatingIn, 'operatingOut' => $operatingOut, 'operatingNet' => $operatingNet,
        'loanReceived' => 0.0, 'loanGiven' => 0.0,
        'transferIn' => $transferIn, 'transferOut' => $transferOut,
        'financingIn' => $financingIn, 'financingOut' => $financingOut, 'financingNet' => $financingNet,
        'netChange' => $netChange, 'opening' => $opening, 'closing' => $closing,
    ];
}

/** Combined income statement across personal + all businesses in the org (intercompany-style breakdown by entity). */
function combined_income_statement(PDO $pdo, int $orgId, int $userId, string $start, string $end): array
{
    $rows = [];
    $p = personal_income_statement($pdo, $userId, $start, $end);
    $rows[] = ['entity' => 'Personal', 'income' => $p['income_total'], 'expense' => $p['expense_total'], 'net' => $p['net']];

    $stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE organization_id = ? ORDER BY name');
    $stmt->execute([$orgId]);
    foreach ($stmt->fetchAll() as $b) {
        $bi = business_income_statement($pdo, (int) $b['id'], $start, $end);
        $rows[] = ['entity' => $b['name'], 'income' => $bi['income_total'], 'expense' => $bi['expense_total'], 'net' => $bi['net']];
    }

    $totalIncome = array_sum(array_column($rows, 'income'));
    $totalExpense = array_sum(array_column($rows, 'expense'));

    return ['rows' => $rows, 'total_income' => $totalIncome, 'total_expense' => $totalExpense, 'total_net' => $totalIncome - $totalExpense];
}

/** Combined net worth (personal + all businesses) as of a given date. */
function combined_net_worth_as_of(PDO $pdo, int $orgId, int $userId, string $asOfDate): float
{
    $total = get_personal_balance_as_of($pdo, $userId, $asOfDate);
    $stmt = $pdo->prepare('SELECT id FROM businesses WHERE organization_id = ?');
    $stmt->execute([$orgId]);
    foreach ($stmt->fetchAll() as $b) {
        $total += get_business_balance_as_of($pdo, (int) $b['id'], $asOfDate);
    }
    return $total;
}

/** Total volume of internal transfers within the org for the period (informational/audit figure). */
function combined_internal_transfer_volume(PDO $pdo, int $orgId, string $start, string $end): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS t FROM fund_transfers WHERE organization_id = ? AND transfer_date BETWEEN ? AND ?');
    $stmt->execute([$orgId, $start, $end]);
    return (float) $stmt->fetch()['t'];
}

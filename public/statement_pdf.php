<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/statement_helpers.php';
require __DIR__ . '/../includes/simple_pdf.php';
require_login();
$pdo = require __DIR__ . '/../config/database.php';
$user = current_user();

$stmt = $pdo->prepare('SELECT id, name FROM organizations WHERE owner_user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$org = $stmt->fetch();

$businesses = [];
if ($org) {
    $stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE organization_id = ? ORDER BY name');
    $stmt->execute([$org['id']]);
    $businesses = $stmt->fetchAll();
}

// ---- Resolve scope (identical logic to statements.php) ----
$scopeParam = (string) ($_GET['scope'] ?? 'all');
$scopeType = 'all';
$scopeBusinessId = null;
$scopeLabel = 'All (combined)';

if ($scopeParam === 'personal') {
    $scopeType = 'personal';
    $scopeLabel = 'Personal';
} elseif (str_starts_with($scopeParam, 'business:')) {
    $bid = (int) substr($scopeParam, 9);
    foreach ($businesses as $b) {
        if ((int) $b['id'] === $bid) {
            $scopeType = 'business';
            $scopeBusinessId = $bid;
            $scopeLabel = $b['name'];
            break;
        }
    }
}

$preset = (string) ($_GET['preset'] ?? 'month');
$range = resolve_date_range($preset, $_GET['start'] ?? null, $_GET['end'] ?? null);

function money(float $n): string
{
    return number_format($n, 2);
}

$pdf = new SimplePdf();
$pdf->addTitle('Soma Cashflow - Financial Statement');
$pdf->addSubtitle($scopeLabel . '   |   Period: ' . $range['label']);
$pdf->addSubtitle('Generated ' . date('j M Y, H:i') . ' by ' . $user['name']);
$pdf->addSpacer(14);

if ($scopeType === 'all') {
    $ci = combined_income_statement($pdo, $org['id'], $user['id'], $range['start'], $range['end']);
    $openingNW = combined_net_worth_as_of($pdo, $org['id'], $user['id'], day_before($range['start']));
    $closingNW = combined_net_worth_as_of($pdo, $org['id'], $user['id'], $range['end']);
    $transferVolume = combined_internal_transfer_volume($pdo, $org['id'], $range['start'], $range['end']);

    $pdf->addSectionHeader('Income Statement by Entity');
    $rows = [];
    foreach ($ci['rows'] as $r) {
        $rows[] = [$r['entity'], '+' . money($r['income']), '-' . money($r['expense']), money($r['net'])];
    }
    $totalRow = ['Total', '+' . money($ci['total_income']), '-' . money($ci['total_expense']), money($ci['total_net'])];
    $pdf->addTable(['Entity', 'Income', 'Expenses', 'Net'], $rows, $totalRow);

    $pdf->addSpacer(20);
    $pdf->addSectionHeader('Net Worth');
    $pdf->addRow('Opening net worth (as of ' . day_before($range['start']) . ')', money($openingNW));
    $pdf->addRow('Change during period', money($closingNW - $openingNW), true);
    $pdf->addRow('Closing net worth (as of ' . $range['end'] . ')', money($closingNW), true, 0, true);
    $pdf->addSpacer(10);
    $pdf->addRow('Internal transfers this period (eliminated, informational)', money($transferVolume));
} else {
    $income = $scopeType === 'personal'
        ? personal_income_statement($pdo, $user['id'], $range['start'], $range['end'])
        : business_income_statement($pdo, $scopeBusinessId, $range['start'], $range['end']);
    $cf = $scopeType === 'personal'
        ? personal_cash_flow($pdo, $user['id'], $range['start'], $range['end'])
        : business_cash_flow($pdo, $scopeBusinessId, $range['start'], $range['end']);

    $pdf->addSectionHeader('Income Statement');
    if (!$income['income'] && !$income['expense']) {
        $pdf->addRow('No income or expenses recorded in this period.', '');
    } else {
        if ($income['income']) {
            $pdf->addRow('Income', '', true);
            foreach ($income['income'] as $cat => $amt) {
                $pdf->addRow($cat, '+' . money($amt), false, 14);
            }
            $pdf->addRow('Total income', '+' . money($income['income_total']), true);
        }
        if ($income['expense']) {
            $pdf->addSpacer(4);
            $pdf->addRow('Expenses', '', true);
            foreach ($income['expense'] as $cat => $amt) {
                $pdf->addRow($cat, '-' . money($amt), false, 14);
            }
            $pdf->addRow('Total expenses', '-' . money($income['expense_total']), true);
        }
        $pdf->addRow('Net income', money($income['net']), true, 0, true);
    }

    $pdf->addSpacer(20);
    $pdf->addSectionHeader('Cash Flow Statement');
    $pdf->addRow('Operating activities', '', true);
    $pdf->addRow('Cash from income', '+' . money($cf['operatingIn']), false, 14);
    $pdf->addRow('Cash used in expenses', '-' . money($cf['operatingOut']), false, 14);
    $pdf->addRow('Net operating cash flow', money($cf['operatingNet']), true);

    $pdf->addSpacer(4);
    $pdf->addRow('Financing activities', '', true);
    if ($scopeType === 'business') {
        $pdf->addRow('Loans received', '+' . money($cf['loanReceived']), false, 14);
        $pdf->addRow('Loans given', '-' . money($cf['loanGiven']), false, 14);
    }
    $pdf->addRow('Transfers in', '+' . money($cf['transferIn']), false, 14);
    $pdf->addRow('Transfers out', '-' . money($cf['transferOut']), false, 14);
    $pdf->addRow('Net financing cash flow', money($cf['financingNet']), true);

    $pdf->addRow('Net change in cash', money($cf['netChange']), true, 0, true);
    $pdf->addRow('Opening balance (' . day_before($range['start']) . ')', money($cf['opening']));
    $pdf->addRow('Closing balance (' . $range['end'] . ')', money($cf['closing']), true);
}

$filename = 'statement-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($scopeLabel)) . '-' . $range['start'] . '-to-' . $range['end'] . '.pdf';
$pdf->output($filename);

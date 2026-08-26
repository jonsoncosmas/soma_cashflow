<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/statement_helpers.php';
require __DIR__ . '/../includes/simple_pdf.php';
require __DIR__ . '/../includes/access_control.php';
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
$sharedBusinesses = get_shared_businesses($pdo, $user['id']);

$allEntityKeys = [];
if ($org) {
    $allEntityKeys[] = 'personal';
}
foreach ($businesses as $b) {
    $allEntityKeys[] = 'business:' . $b['id'];
}
foreach ($sharedBusinesses as $sb) {
    $allEntityKeys[] = 'business:' . $sb['id'];
}

$selectedEntityKeys = (array) ($_GET['entities'] ?? $allEntityKeys);
$selectedSections   = (array) ($_GET['sections'] ?? ['income', 'cashflow']);

$allBusinessesById = [];
foreach ($businesses as $b) { $allBusinessesById[(int) $b['id']] = $b['name']; }
foreach ($sharedBusinesses as $sb) { $allBusinessesById[(int) $sb['id']] = $sb['name']; }

$entities = [];
foreach ($allEntityKeys as $key) {
    if (!in_array($key, $selectedEntityKeys, true)) {
        continue;
    }
    if ($key === 'personal') {
        $entities[] = ['type' => 'personal', 'key' => 'personal'];
    } else {
        $bid = (int) substr($key, 9);
        if (isset($allBusinessesById[$bid])) {
            $entities[] = ['type' => 'business', 'id' => $bid, 'name' => $allBusinessesById[$bid], 'key' => $key];
        }
    }
}

if (!$entities) {
    http_response_code(400);
    die('No entities selected.');
}

$showIncome = in_array('income', $selectedSections, true);
$showCashflow = in_array('cashflow', $selectedSections, true);
if (!$showIncome && !$showCashflow) {
    http_response_code(400);
    die('No statement types selected.');
}

$preset = (string) ($_GET['preset'] ?? 'month');
$range = resolve_date_range($preset, $_GET['start'] ?? null, $_GET['end'] ?? null);
$isAllEntities = count($entities) === count($allEntityKeys);
$scopeLabel = count($entities) === 1 ? entity_label($entities[0]) : (count($entities) . ' entities combined (' . implode(', ', array_map('entity_label', $entities)) . ')');

function money(float $n): string
{
    return number_format($n, 2);
}

$pdf = new SimplePdf();
$pdf->addTitle('Soma Cashflow - Financial Statement');
$pdf->addSubtitle($scopeLabel);
$pdf->addSubtitle('Period: ' . $range['label']);
$pdf->addSubtitle('Generated ' . date('j M Y, H:i') . ' by ' . $user['name']);
$pdf->addSpacer(14);

if (count($entities) > 1) {
    if ($showIncome) {
        $ci = combined_income_statement($pdo, $entities, $user['id'], $range['start'], $range['end']);
        $pdf->addSectionHeader('Income Statement by Entity');
        $rows = [];
        foreach ($ci['rows'] as $r) {
            $rows[] = [$r['entity'], '+' . money($r['income']), '-' . money($r['expense']), money($r['net'])];
        }
        $totalRow = ['Total', '+' . money($ci['total_income']), '-' . money($ci['total_expense']), money($ci['total_net'])];
        $pdf->addTable(['Entity', 'Income', 'Expenses', 'Net'], $rows, $totalRow);
    }

    if ($showCashflow) {
        $openingNW = combined_net_worth_as_of($pdo, $entities, $user['id'], day_before($range['start']));
        $closingNW = combined_net_worth_as_of($pdo, $entities, $user['id'], $range['end']);
        $transferVolume = combined_transfer_volume($pdo, $entities, $user['id'], $range['start'], $range['end']);

        $pdf->addSpacer(20);
        $pdf->addSectionHeader('Combined Cash Position');
        $pdf->addRow('Opening (as of ' . day_before($range['start']) . ')', money($openingNW));
        $pdf->addRow('Change during period', money($closingNW - $openingNW), true);
        $pdf->addRow('Closing (as of ' . $range['end'] . ')', money($closingNW), true, 0, true);
        $pdf->addSpacer(10);
        $label = $isAllEntities
            ? 'Internal transfers this period (eliminated, informational)'
            : 'Transfers touching this group this period (informational)';
        $pdf->addRow($label, money($transferVolume));
    }
} else {
    $entity = $entities[0];
    $income = entity_income_statement($pdo, $entity, $user['id'], $range['start'], $range['end']);
    $cf = $entity['type'] === 'personal'
        ? personal_cash_flow($pdo, $user['id'], $range['start'], $range['end'])
        : business_cash_flow($pdo, (int) $entity['id'], $range['start'], $range['end']);

    if ($showIncome) {
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
    }

    if ($showCashflow) {
        if ($showIncome) {
            $pdf->addSpacer(20);
        }
        $pdf->addSectionHeader('Cash Flow Statement');
        $pdf->addRow('Operating activities', '', true);
        $pdf->addRow('Cash from income', '+' . money($cf['operatingIn']), false, 14);
        $pdf->addRow('Cash used in expenses', '-' . money($cf['operatingOut']), false, 14);
        $pdf->addRow('Net operating cash flow', money($cf['operatingNet']), true);

        $pdf->addSpacer(4);
        $pdf->addRow('Financing activities', '', true);
        if ($entity['type'] === 'business') {
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
}

$sectionsPart = implode('-', $selectedSections);
$filename = 'statement-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower(count($entities) === 1 ? entity_label($entities[0]) : 'combined'))
    . '-' . $sectionsPart . '-' . $range['start'] . '-to-' . $range['end'] . '.pdf';
$pdf->output($filename);

<?php
// DM / ASM Performance report (company-wide) — user request 2026-11-23:
// one sheet per DM (Assigned Districts, Monthly Target/Achieved, Weekly
// Target/Achieved for the current week, this month's New Shop Onboarding
// count, and Lumi9 Diaper sale value split into "TP direct" vs "converted
// from a DM Get Order"), plus a second sheet rolling the same numbers up
// per ASM. Reuses marketing/include/KpiDataPrep.php's own org-wide data
// prep (same Target/Achieved/New-Shop-Count/week-boundary logic the KPI
// page already uses) by rooting it at the single Sales Manager (SM) node,
// whose subtree is the entire marketing org — instead of a second,
// independently-maintained copy of that logic.
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

require_once("checksession.php");
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;
require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

// KpiDataPrep.php reads $_GET['from_date']/['to_date'] itself (default:
// 1st of this month through today — exactly "01-11-2026 to today" for a
// November run) and expects $markeingSTFID / $db_conn to already be set.
$smRow = $db_conn->query("SELECT id FROM marketing_staff WHERE team_level_id=1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1")->fetch_assoc();
if (!$smRow) { die("No Sales Manager (SM) found to root the org tree at."); }
$markeingSTFID = (int)$smRow['id'];
require_once __DIR__ . '/../marketing/include/KpiDataPrep.php';

// ── Diaper sale value, split TP-direct vs Get-Order-converted, per ms_id ──
// (a) TP-direct: a TP's own shop invoice (not bridged from a Get Order),
//     same district/TP scoping as getTpInvoiceAmountForDistricts() but
//     category='diaper' and excluding Get-Order-originated invoices.
function getTpDiaperInvoiceAmountDirect($db_conn, array $tpIds, array $districtIds, string $from, string $to): float {
    if (empty($tpIds) || empty($districtIds)) { return 0.0; }
    ensureTpInvoiceAmountIndexes($db_conn);
    $tpList   = implode(',', array_map('intval', $tpIds));
    $distList = implode(',', array_map('intval', $districtIds));
    $stmt = $db_conn->prepare("
        SELECT COALESCE(SUM(uii.total),0) AS amt
        FROM user_invoice ui
        STRAIGHT_JOIN shop s ON s.temp_id = ui.to_user_id
        STRAIGHT_JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
        STRAIGHT_JOIN products p ON p.id = uii.pr_id
        WHERE ui.from_user_type='territory_partner' AND ui.to_user_type='shop'
          AND ui.from_user_id IN ($tpList)
          AND ui.`date` BETWEEN ? AND ?
          AND COALESCE(ui.invoice_district_id, s.district_id) IN ($distList)
          AND p.category = 'diaper'
          AND ui.source_ms_order_id IS NULL
    ");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $amt = (float)($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
    $stmt->close();
    return $amt;
}

// (b) Get-Order-converted: same invoice ids getGetOrderInvoiceIdsByMsId()
//     already resolves for "Achieved"/Return%, just summed for diaper lines.
function getDiaperAmountForInvoiceIds($db_conn, array $invIds): float {
    if (empty($invIds)) { return 0.0; }
    $cast = _teamTargetCollationCast($db_conn);
    $idList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), $invIds)) . "'";
    $res = $db_conn->query("
        SELECT COALESCE(SUM(uii.total),0) AS amt
        FROM user_invoice_items uii
        JOIN products p ON p.id = uii.pr_id
        WHERE uii.inv_id$cast IN ($idList) AND p.category = 'diaper'
    ");
    return (float)($res->fetch_assoc()['amt'] ?? 0);
}

$getOrderInvIdsByMsId = getGetOrderInvoiceIdsByMsId($db_conn, $subtreeIds, $fromDate, $toDate);

$diaperTpByMsId = [];
$diaperGoByMsId = [];
$districtsByMsId = [];
foreach ($subtreeIds as $id) {
    $id = (int)$id;
    $diaperGoByMsId[$id] = getDiaperAmountForInvoiceIds($db_conn, $getOrderInvIdsByMsId[$id] ?? []);

    $scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $id);
    $districtsByMsId[$id] = array_column(getMsAssignedDistricts($db_conn, $id), 'name');
    if (empty($scopeNodes)) { $diaperTpByMsId[$id] = 0.0; continue; }
    $scopeIds = array_column($scopeNodes, 'id');
    $tpIds = getMsNodeTpIds($db_conn, $scopeIds, true);
    $districtIds = districtIdsFromScopeNodeIds($db_conn, $scopeIds);
    $diaperTpByMsId[$id] = getTpDiaperInvoiceAmountDirect($db_conn, $tpIds, $districtIds, $fromDate, $toDate);
}

// Current (most recent) calendar week of the selected range, same per-day
// proration convention kpi.php's own Weekly view uses (perDayTarget = the
// FIXED full-month target spread evenly over the SELECTED range's days, not
// true calendar-month days — matches renderWeeklyKpiRow()'s own call,
// which passes $wkDaysInRange where a "days in month" param is named).
$currentWeek = end($kpiWeeks);

function dmWeeklyFigures(int $id, array $byManager, array $rawStats, array $dailyByMsId, int $daysInRange, array $week): array {
    [$sum, $ids] = kpiSubtreeSumAndIds($id, $byManager, $rawStats);
    $combinedDaily = kpiCombinedDaily($ids, $dailyByMsId);
    $perDayTarget = $daysInRange > 0 ? ($sum['target'] / $daysInRange) : 0.0;
    $weekTarget = $perDayTarget * $week['days'];
    $weekAchieved = kpiSumInRange($combinedDaily, $week['start'], $week['end']);
    return [$sum, $ids, $weekTarget, $weekAchieved];
}

$spreadsheet = new Spreadsheet();
$HEADER_FILL = '2563EB';
$NUMFMT = '#,##0.00';

$headers = [
    'Name', 'Assigned Districts', 'Monthly Target', 'Monthly Achieved',
    'Weekly Target', 'Weekly Achieved', 'New Shop Onboard Count (This Month)',
    'Lumi9 Diaper Sale Value (TP Direct)', 'Lumi9 Diaper Sale Value (Get Order Converted)',
];

function writeSheet($sheet, string $title, string $nameLabel, array $headers, array $rows, string $NUMFMT, string $HEADER_FILL) {
    $sheet->setTitle($title);
    $sheet->setCellValue('A1', $nameLabel);
    foreach ($headers as $i => $label) {
        if ($i === 0) { continue; }
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue("{$col}1", $label);
    }
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $HEADER_FILL]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    $r = 2;
    foreach ($rows as $row) {
        foreach ($row as $i => $val) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$r}", $val);
            if ($i >= 2 && $i !== 6 && is_numeric($val)) {
                $sheet->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode($NUMFMT);
            }
        }
        $r++;
    }
    $lastRow = $r - 1;
    if ($lastRow >= 1) {
        $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");
    }
    $sheet->freezePane('A2');
    $sheet->getColumnDimension('A')->setWidth(24);
    $sheet->getColumnDimension('B')->setWidth(30);
    foreach (range('C', $lastCol) as $col) {
        $sheet->getColumnDimension($col)->setWidth(20);
    }
}

// ── Sheet 1: DM-wise ─────────────────────────────────────────────────────
$dmRows = [];
foreach ($staffRows as $row) {
    if ((int)$row['team_level_id'] !== 3) { continue; }
    $id = (int)$row['id'];
    [$sum, , $weekTarget, $weekAchieved] = dmWeeklyFigures($id, $byManager, $rawStats, $dailyByMsId, $daysInRange, $currentWeek);
    $dmRows[] = [
        $row['ms_name'],
        implode(', ', $districtsByMsId[$id] ?? []),
        round($sum['target'], 2),
        round($sum['achieved'], 2),
        round($weekTarget, 2),
        round($weekAchieved, 2),
        (int)($newShopCountByMsId[$id] ?? 0),
        round($diaperTpByMsId[$id] ?? 0, 2),
        round($diaperGoByMsId[$id] ?? 0, 2),
    ];
}
$sheet1 = $spreadsheet->getActiveSheet();
writeSheet($sheet1, 'DM Wise', 'DM Name', $headers, $dmRows, $NUMFMT, $HEADER_FILL);

// ── Sheet 2: ASM-wise (rollup of each ASM's own DM subtree) ─────────────
$asmRows = [];
foreach ($staffRows as $row) {
    if ((int)$row['team_level_id'] !== 2) { continue; }
    $id = (int)$row['id'];
    [$sum, $ids, $weekTarget, $weekAchieved] = dmWeeklyFigures($id, $byManager, $rawStats, $dailyByMsId, $daysInRange, $currentWeek);

    $newShopSum = 0; $diaperTpSum = 0.0; $diaperGoSum = 0.0; $districtNames = [];
    foreach ($ids as $memberId) {
        $newShopSum += (int)($newShopCountByMsId[$memberId] ?? 0);
        $diaperTpSum += (float)($diaperTpByMsId[$memberId] ?? 0);
        $diaperGoSum += (float)($diaperGoByMsId[$memberId] ?? 0);
        $districtNames = array_merge($districtNames, $districtsByMsId[$memberId] ?? []);
    }
    $districtNames = array_values(array_unique($districtNames));

    $asmRows[] = [
        $row['ms_name'],
        implode(', ', $districtNames),
        round($sum['target'], 2),
        round($sum['achieved'], 2),
        round($weekTarget, 2),
        round($weekAchieved, 2),
        $newShopSum,
        round($diaperTpSum, 2),
        round($diaperGoSum, 2),
    ];
}
$sheet2 = $spreadsheet->createSheet();
writeSheet($sheet2, 'ASM Wise', 'ASM Name', $headers, $asmRows, $NUMFMT, $HEADER_FILL);

$spreadsheet->setActiveSheetIndex(0);

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="dm-asm-performance-' . $fromDate . '-to-' . $toDate . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;

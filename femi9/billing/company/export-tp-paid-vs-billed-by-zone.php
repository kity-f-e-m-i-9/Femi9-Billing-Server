<?php
// Excel export: TP Advance-Paid vs TP-Invoice-Billed vs Balance, one sheet
// per Zone (partner_zones) — user-requested ad-hoc report, 2026-11-27.
// Paid Amount = SUM(tp_advance_payments.amount) in the date range (deleted_at
// IS NULL). Billed Amount = SUM(tp_invoices.total_amount) in the same range.
// Balance Amount = Billed - Paid. Date range defaults to 01-Jun-2026 to
// 30-Nov-2026 ("June to Nov"), overridable via ?from=YYYY-MM-DD&to=YYYY-MM-DD.
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("config.php");
require_once __DIR__ . '/include/PartnerZones.php';

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

$from = trim($_GET['from'] ?? '') ?: '2026-06-01';
$to   = trim($_GET['to']   ?? '') ?: '2026-11-30';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = '2026-06-01'; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = '2026-11-30'; }

ensurePartnerZonesTables($db_conn);
$zones = getAllZonesWithCounts($db_conn);

function fetchTpRowForExport(mysqli $db_conn, int $tpId, string $from, string $to): array {
    $tp = $db_conn->query("SELECT name, mobile, COALESCE(NULLIF(assigned_district,''), branch_district) AS district FROM territory_partners WHERE id = $tpId")->fetch_assoc();

    $paidRow = $db_conn->query("
        SELECT COALESCE(SUM(amount), 0) AS amt
        FROM tp_advance_payments
        WHERE territory_partner_id = $tpId AND deleted_at IS NULL
          AND payment_date BETWEEN '$from' AND '$to'
    ")->fetch_assoc();
    $paid = (float)$paidRow['amt'];

    $billedRow = $db_conn->query("
        SELECT COALESCE(SUM(total_amount), 0) AS amt
        FROM tp_invoices
        WHERE territory_partner_id = $tpId
          AND invoice_date BETWEEN '$from' AND '$to'
    ")->fetch_assoc();
    $billed = (float)$billedRow['amt'];

    // Balance = this TP's own remaining/unused advance wallet balance, same
    // figure manage-tp-advance-payments.php's "Total Balance" card shows for
    // the identical TP + date-range filter — NOT "Billed - Paid" (a
    // different, unrelated number). 2026-11-27, user-reported mismatch
    // against the live TP Advance Payments page.
    $balanceRow = $db_conn->query("
        SELECT COALESCE(SUM(balance_amount), 0) AS amt
        FROM tp_advance_payments
        WHERE territory_partner_id = $tpId AND deleted_at IS NULL
          AND payment_date BETWEEN '$from' AND '$to'
    ")->fetch_assoc();
    $balance = (float)$balanceRow['amt'];

    return [
        'name'     => $tp['name'] ?? ('TP #' . $tpId),
        'mobile'   => $tp['mobile'] ?? '',
        'district' => $tp['district'] ?? '',
        'paid'     => $paid,
        'billed'   => $billed,
        'balance'  => $balance,
    ];
}

function writeZoneSheetForExport(Spreadsheet $spreadsheet, string $zoneName, array $rows, int $sheetIdx, string $paidHeaderLabel): void {
    $sheet = $spreadsheet->createSheet($sheetIdx);
    $safeName = substr(preg_replace('/[\[\]\*\/\\\\\?\:]/', '', $zoneName), 0, 31) ?: ('Zone' . $sheetIdx);
    $sheet->setTitle($safeName);

    $sheet->mergeCells('A1:F1');
    $sheet->setCellValue('A1', $zoneName);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $headers = ['TP Name', 'Number', 'District', $paidHeaderLabel, 'Billed Amount', 'Balance Amount'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '2', $h);
        $col++;
    }
    $sheet->getStyle('A2:F2')->getFont()->setBold(true);
    $sheet->getStyle('A2:F2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E2F3');
    $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

    $r = 3;
    $lastDataRow = $r - 1; // tracks the last real TP row, excluding the Total row below, for the AutoFilter range
    $totalPaid = 0.0; $totalBilled = 0.0; $totalBalance = 0.0;
    foreach ($rows as $row) {
        $sheet->setCellValue("A$r", $row['name']);
        $sheet->setCellValue("B$r", $row['mobile']);
        $sheet->setCellValue("C$r", $row['district']);
        $sheet->setCellValue("D$r", $row['paid']);
        $sheet->setCellValue("E$r", $row['billed']);
        $sheet->setCellValue("F$r", $row['balance']);
        $totalPaid    += $row['paid'];
        $totalBilled  += $row['billed'];
        $totalBalance += $row['balance'];
        $lastDataRow = $r;
        $r++;
    }
    $sheet->setCellValue("A$r", 'Total');
    $sheet->getStyle("A$r")->getFont()->setBold(true);
    $sheet->setCellValue("D$r", $totalPaid);
    $sheet->setCellValue("E$r", $totalBilled);
    $sheet->setCellValue("F$r", $totalBalance);
    $sheet->getStyle("D$r:F$r")->getFont()->setBold(true);
    $sheet->getStyle("D$r:F$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');

    foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c) { $sheet->getColumnDimension($c)->setWidth(22); }
    $sheet->getStyle("D3:F$r")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("A2:F$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->freezePane('A3');

    // Filter dropdown arrows on the header row (A2:F2), scoped over just the
    // real TP rows — not the Total row below, so "Total" never appears as a
    // filterable value. 2026-11-27, user-requested.
    $sheet->setAutoFilter('A2:F' . $lastDataRow);
}

$paidHeaderLabel = (date('M', strtotime($from)) . ' to ' . date('M', strtotime($to)) . ' Paid Amount');

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$seenTpIds = [];
$sheetIdx = 0;
foreach ($zones as $z) {
    $zoneId = (int)$z['id'];
    $tpIds = getZoneTpIds($db_conn, $zoneId, false);
    if (empty($tpIds)) continue;
    $rows = [];
    foreach ($tpIds as $tpId) {
        $seenTpIds[(int)$tpId] = true;
        $rows[] = fetchTpRowForExport($db_conn, (int)$tpId, $from, $to);
    }
    usort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));
    writeZoneSheetForExport($spreadsheet, $z['name'], $rows, $sheetIdx, $paidHeaderLabel);
    $sheetIdx++;
}

// Active TPs not covered by any zone — own sheet, so nobody is silently dropped.
$allActive = $db_conn->query("SELECT id FROM territory_partners WHERE is_active = 1 AND deleted_at IS NULL")->fetch_all(MYSQLI_ASSOC);
$unmapped = [];
foreach ($allActive as $r) {
    $id = (int)$r['id'];
    if (!isset($seenTpIds[$id])) { $unmapped[] = fetchTpRowForExport($db_conn, $id, $from, $to); }
}
if (!empty($unmapped)) {
    usort($unmapped, fn($a, $b) => strcmp($a['name'], $b['name']));
    writeZoneSheetForExport($spreadsheet, 'No Zone Assigned', $unmapped, $sheetIdx, $paidHeaderLabel);
    $sheetIdx++;
}

if ($sheetIdx === 0) {
    // Nothing to show — still produce a valid, openable workbook rather than
    // a broken/empty file.
    $spreadsheet->createSheet(0)->setCellValue('A1', 'No zones or territory partners found.');
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'TP_Paid_vs_Billed_by_Zone_' . date('Y-m-d') . '.xlsx';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;

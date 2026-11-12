<?php
/**
 * For one Territory Partner, how many times each shop got invoiced in each
 * of 3 given calendar months — one sheet per month (every shop, with an
 * Invoice Count + Category column showing the exact count: "0 times",
 * "1 time", "2 times", "4 times", etc.), plus a combined "3-Month Summary"
 * sheet so a gap or an unusually heavy month for one shop is visible at a
 * glance across all three.
 *
 * Shop list = every shop she has actually invoiced across the 3 months,
 * UNION her own onboarded ("Manage Shop") list — not onboard_userID alone,
 * since a shop onboarded by someone else (another TP, or a DM's field
 * order not yet bridged to her) can still have been invoiced by her
 * directly, and that must still count here.
 *
 * "Invoiced" = every user_invoice row this TP raised directly to that shop
 * (from_user_type='territory_partner', to_user_type='shop'), live only
 * (not deleted/voided) — same convention used throughout the rest of the
 * company reports.
 */
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

// TP identified by tp_id (e.g. "TP-0082") or internal id — tp_id preferred
// since that's what's visible on-screen everywhere else.
$tpCodeParam = trim($_GET['tp_id'] ?? '');
$tpInternalIdParam = (int)($_GET['id'] ?? 0);

if ($tpCodeParam !== '') {
    $stmt = $db_conn->prepare("SELECT id, tp_id, name FROM territory_partners WHERE tp_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param('s', $tpCodeParam);
} elseif ($tpInternalIdParam > 0) {
    $stmt = $db_conn->prepare("SELECT id, tp_id, name FROM territory_partners WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param('i', $tpInternalIdParam);
} else {
    die("Pass ?tp_id=TP-0082 (or ?id=82) in the URL.");
}
$stmt->execute();
$tp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tp) { die("Territory Partner not found."); }
$tpId = (int)$tp['id'];

// Fixed 3 calendar months per request — September, October, November 2026.
$months = [
    ['label' => 'September 2026', 'from' => '2026-09-01', 'to' => '2026-09-30'],
    ['label' => 'October 2026',   'from' => '2026-10-01', 'to' => '2026-10-31'],
    ['label' => 'November 2026',  'from' => '2026-11-01', 'to' => '2026-11-30'],
];

// Every shop this TP has actually invoiced across the 3-month window —
// NOT just her own onboarded ("Manage Shop") list. A shop onboarded by a
// different TP (or by a DM's field order not yet bridged to her) can still
// have been invoiced by her directly, and that invoice must count here too;
// restricting to onboard_userID would silently drop it. Her own onboarded
// shops that never got an invoice in this window still show up with a
// trailing LEFT JOIN below so 0-invoice months aren't missed either.
$stmt = $db_conn->prepare("
    SELECT DISTINCT ui.to_user_id AS temp_id
    FROM user_invoice ui
    WHERE ui.from_user_type = 'territory_partner' AND ui.from_user_id = ?
      AND ui.to_user_type = 'shop'
      AND ui.date BETWEEN ? AND ?
      AND ui.deleted_at IS NULL AND ui.voided_at IS NULL
");
$invoicedShopIds = [];
foreach ($months as $m) {
    $stmt->bind_param('iss', $tpId, $m['from'], $m['to']);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) { $invoicedShopIds[$row['temp_id']] = true; }
}
$stmt->close();

// Union with her own onboarded ("Manage Shop") list — so a shop she owns
// but hasn't invoiced yet this window still shows up as all-zero rather
// than being silently left out entirely.
$stmtOwn = $db_conn->prepare("
    SELECT temp_id FROM shop WHERE onboard_userID = ? AND onboard_userTYPE = 'territory_partner' AND deleted_at IS NULL
");
$stmtOwn->bind_param('i', $tpId);
$stmtOwn->execute();
$r = $stmtOwn->get_result();
while ($row = $r->fetch_assoc()) { $invoicedShopIds[$row['temp_id']] = true; }
$stmtOwn->close();

$shops = [];
if (!empty($invoicedShopIds)) {
    $idList = implode(',', array_map('intval', array_keys($invoicedShopIds)));
    $resShops = $db_conn->query("
        SELECT temp_id, name, mobile_number AS mobile
        FROM shop
        WHERE temp_id IN ($idList)
        ORDER BY name ASC
    ");
    $shops = $resShops ? $resShops->fetch_all(MYSQLI_ASSOC) : [];
}

// Per-shop, per-month invoice counts — one query per month (grouped by
// shop), rather than one query per shop per month.
$countsByMonthByShop = []; // [monthIdx][shop_temp_id] = count
foreach ($months as $mi => $m) {
    $countsByMonthByShop[$mi] = [];
    $stmtCnt = $db_conn->prepare("
        SELECT ui.to_user_id AS shop_temp_id, COUNT(*) AS cnt
        FROM user_invoice ui
        WHERE ui.from_user_type = 'territory_partner' AND ui.from_user_id = ?
          AND ui.to_user_type = 'shop'
          AND ui.date BETWEEN ? AND ?
          AND ui.deleted_at IS NULL AND ui.voided_at IS NULL
        GROUP BY ui.to_user_id
    ");
    $stmtCnt->bind_param('iss', $tpId, $m['from'], $m['to']);
    $stmtCnt->execute();
    $r = $stmtCnt->get_result();
    while ($row = $r->fetch_assoc()) {
        $countsByMonthByShop[$mi][$row['shop_temp_id']] = (int)$row['cnt'];
    }
    $stmtCnt->close();
}

// Exact count as the label — "4 times", "5 times", etc. instead of lumping
// everything above 2 into a vague "3+ times" bucket, per request.
function invoiceCategory(int $cnt): string {
    return $cnt . ' ' . ($cnt === 1 ? 'time' : 'times');
}
function invoiceCategoryColor(int $cnt): string {
    if ($cnt === 0) return 'FFFEE2E2'; // red tint — no invoice at all this month
    if ($cnt === 1) return 'FFFEF3C7'; // amber tint
    if ($cnt === 2) return 'FFDCFCE7'; // green tint
    return 'FFDBEAFE'; // blue tint — 3 or more
}

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$headerFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']];
$headerFont = ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']];
$titleFill  = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']];
$titleFont  = ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']];

$sheetIndex = 0;
foreach ($months as $mi => $m) {
    $sheet = $spreadsheet->createSheet($sheetIndex);
    $sheet->setTitle($m['label']);
    $sheetIndex++;

    $sheet->mergeCells('A1:D1');
    $sheet->setCellValue('A1', $tp['name'] . ' (' . $tp['tp_id'] . ') | ' . $m['label'] . ' | Invoice Frequency by Shop');
    $sheet->getStyle('A1')->applyFromArray(['font' => $titleFont, 'fill' => $titleFill, 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $sheet->getRowDimension(1)->setRowHeight(26);

    $headers = ['Shop Name', 'Mobile', 'Invoice Count', 'Category'];
    foreach ($headers as $i => $h) { $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '3', $h); }
    $sheet->getStyle('A3:D3')->applyFromArray(['font' => $headerFont, 'fill' => $headerFill, 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

    // Build + sort rows: by invoice count ascending (0 first, needs
    // attention), then shop name within the same count.
    $rows = [];
    foreach ($shops as $shop) {
        $cnt = $countsByMonthByShop[$mi][$shop['temp_id']] ?? 0;
        $rows[] = ['name' => $shop['name'], 'mobile' => $shop['mobile'], 'cnt' => $cnt, 'cat' => invoiceCategory($cnt)];
    }
    usort($rows, fn($a, $b) => $a['cnt'] <=> $b['cnt'] ?: strcmp($a['name'], $b['name']));

    $row = 4;
    foreach ($rows as $r) {
        $sheet->setCellValue("A{$row}", $r['name']);
        $sheet->setCellValue("B{$row}", $r['mobile']);
        $sheet->setCellValue("C{$row}", $r['cnt']);
        $sheet->setCellValue("D{$row}", $r['cat']);
        $sheet->getStyle("D{$row}")->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => invoiceCategoryColor($r['cnt'])]]]);
        $sheet->getStyle("A{$row}:D{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDDDDDD');
        $row++;
    }

    $sheet->getColumnDimension('A')->setWidth(28);
    $sheet->getColumnDimension('B')->setWidth(16);
    $sheet->getColumnDimension('C')->setWidth(14);
    $sheet->getColumnDimension('D')->setWidth(14);
    $sheet->freezePane('A4');
    $sheet->setAutoFilter('A3:D3');
}

// ── 3-Month Summary sheet — one row per shop, one column per month, so a
// gap or a sudden spike for any shop is visible side by side. ──────────────
$summarySheet = $spreadsheet->createSheet($sheetIndex);
$summarySheet->setTitle('3-Month Summary');

$lastCol = chr(ord('A') + 3 + count($months) - 1); // A=Shop, B=Mobile, then one col per month
$summarySheet->mergeCells("A1:{$lastCol}1");
$summarySheet->setCellValue('A1', $tp['name'] . ' (' . $tp['tp_id'] . ') | 3-Month Invoice Frequency Summary');
$summarySheet->getStyle('A1')->applyFromArray(['font' => $titleFont, 'fill' => $titleFill, 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
$summarySheet->getRowDimension(1)->setRowHeight(26);

$summaryHeaders = array_merge(['Shop Name', 'Mobile'], array_column($months, 'label'));
foreach ($summaryHeaders as $i => $h) { $summarySheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '3', $h); }
$summarySheet->getStyle("A3:{$lastCol}3")->applyFromArray(['font' => $headerFont, 'fill' => $headerFill, 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true]]);

$row = 4;
foreach ($shops as $shop) {
    $summarySheet->setCellValue("A{$row}", $shop['name']);
    $summarySheet->setCellValue("B{$row}", $shop['mobile']);
    $hadGap = false;
    foreach ($months as $mi => $m) {
        $cnt = $countsByMonthByShop[$mi][$shop['temp_id']] ?? 0;
        $col = chr(ord('C') + $mi);
        $summarySheet->setCellValue("{$col}{$row}", $cnt);
        if ($cnt === 0) {
            $hadGap = true;
            $summarySheet->getStyle("{$col}{$row}")->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEE2E2']]]);
        } elseif ($cnt >= 2) {
            $summarySheet->getStyle("{$col}{$row}")->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDCFCE7']]]);
        }
    }
    if ($hadGap) {
        $summarySheet->getStyle("A{$row}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFB91C1C'));
    }
    $summarySheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDDDDDD');
    $row++;
}

$summarySheet->getColumnDimension('A')->setWidth(28);
$summarySheet->getColumnDimension('B')->setWidth(16);
foreach ($months as $mi => $m) { $summarySheet->getColumnDimension(chr(ord('C') + $mi))->setWidth(16); }
$summarySheet->freezePane('A4');
$summarySheet->setAutoFilter("A3:{$lastCol}3");

$spreadsheet->setActiveSheetIndex(0);

$filename = preg_replace('/[^a-zA-Z0-9]+/', '', $tp['name']) . '_Shop_Invoice_Frequency_Sep_to_Nov_2026.xlsx';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;

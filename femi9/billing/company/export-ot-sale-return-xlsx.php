<?php
// Excel export for Manage OT Sales Return (ot-sale-manage-return.php), one
// sheet per Category — same format/columns as export-ot-sale-view-xlsx.php,
// but for returns: S.No/Return Date/Rate/Name/District/State/Order No/
// [product return-qty columns]/Invoice No/Remarks, with a green TOTAL row.
// Category, customer, district and state all come from the original sale
// (ot_sales), joined via tempid, since ot_sales_return itself only carries
// the return's own qty/price/date.
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

require_once __DIR__ . '/../shared/db-helpers.php';
require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

function validateDate_otrx(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$_districtColExp = $db_conn->query("SHOW COLUMNS FROM ot_sales LIKE 'district'");
if ($_districtColExp && $_districtColExp->num_rows === 0) {
    $db_conn->query("ALTER TABLE ot_sales ADD COLUMN district VARCHAR(100) NULL DEFAULT NULL AFTER customer_address");
}

$se_fromDate = validateDate_otrx($_GET['frdate'] ?? null, date('Y-m-01'));
$se_toDate   = validateDate_otrx($_GET['todate'] ?? null, date('Y-m-d'));
$se_cat      = trim((string)($_GET['se_cat'] ?? ''));

$_otrExtraFilter = '';
if ($se_cat !== '') {
    $_otrExtraFilter .= " AND os.cat = '" . db_esc($se_cat) . "'";
}

$select_return_list = "SELECT DISTINCT osr.tempid
    FROM ot_sales_return osr
    JOIN ot_sales os ON os.tempid = osr.tempid
    WHERE osr.return_date BETWEEN '" . db_esc($se_fromDate) . "' AND '" . db_esc($se_toDate) . "' $_otrExtraFilter";
$fetch_return_list = mysqli_query($db_conn, $select_return_list);
$tempidList = [];
while ($row = mysqli_fetch_assoc($fetch_return_list)) { $tempidList[] = $row['tempid']; }

if (empty($tempidList)) {
    die("No OT Sales Return records found for this filter.");
}

$tempidsEsc = implode(',', array_map(function ($t) { return "'" . db_esc($t) . "'"; }, $tempidList));

$salesByTempid = [];     // tempid => first ot_sales row (header fields: cat, customer, district, state, order_number)
$returnRows = [];        // tempid => [prid => ['qty'=>, 'price'=>, 'return_date'=>]]
$returnDateByTempid = []; // tempid => earliest return_date in range
$invoiceMap = [];        // tempid => ot_sales_invoice row
$stateMap = [];
$productNames = [];

$allSalesRes = mysqli_query($db_conn, "SELECT * FROM ot_sales WHERE tempid IN ($tempidsEsc) ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($allSalesRes)) {
    $tid = $row['tempid'];
    if (!isset($salesByTempid[$tid])) { $salesByTempid[$tid] = $row; }
}

$allReturnsRes = mysqli_query($db_conn, "SELECT * FROM ot_sales_return WHERE tempid IN ($tempidsEsc) AND return_date BETWEEN '" . db_esc($se_fromDate) . "' AND '" . db_esc($se_toDate) . "'");
while ($row = mysqli_fetch_assoc($allReturnsRes)) {
    $tid = $row['tempid'];
    $returnRows[$tid][(int)$row['prid']] = ['qty' => (int)$row['qty'], 'price' => (float)$row['price']];
    if (!isset($returnDateByTempid[$tid]) || $row['return_date'] < $returnDateByTempid[$tid]) {
        $returnDateByTempid[$tid] = $row['return_date'];
    }
}

$invRes = mysqli_query($db_conn, "SELECT * FROM ot_sales_invoice WHERE tempid IN ($tempidsEsc)");
while ($iv = mysqli_fetch_assoc($invRes)) {
    if (!isset($invoiceMap[$iv['tempid']])) { $invoiceMap[$iv['tempid']] = $iv; }
}

$stateIds = array_unique(array_filter(array_column($salesByTempid, 'state_id')));
if (!empty($stateIds)) {
    $sIdsEsc = implode(',', array_map('intval', $stateIds));
    $sres = mysqli_query($db_conn, "SELECT id, st_name FROM `state` WHERE id IN ($sIdsEsc)");
    while ($s = mysqli_fetch_assoc($sres)) { $stateMap[(int)$s['id']] = $s['st_name']; }
}

$prRes = mysqli_query($db_conn, "SELECT id, productName FROM products");
while ($p = mysqli_fetch_assoc($prRes)) { $productNames[(int)$p['id']] = $p['productName']; }

// Group by Category (from the original sale), dynamic product columns per category.
$byCategory = [];
$categoryProducts = [];
foreach ($tempidList as $tid) {
    $s = $salesByTempid[$tid] ?? null;
    if (!$s || empty($returnRows[$tid])) continue;
    $cat = $s['cat'] ?: 'Uncategorized';
    $byCategory[$cat][] = $tid;
    foreach ($returnRows[$tid] as $prid => $r) {
        if ($r['qty'] > 0) { $categoryProducts[$cat][$prid] = true; }
    }
}
ksort($byCategory);

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$HEADER_FILL = 'FFFF00';
$TOTAL_FILL  = '92D050';
$colLetter = function (int $idx): string { return Coordinate::stringFromColumnIndex($idx + 1); };

$sheetIndex = 0;
foreach ($byCategory as $cat => $tids) {
    $prids = array_keys($categoryProducts[$cat] ?? []);
    sort($prids);

    $sheetTitle = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $cat);
    $sheetTitle = mb_substr($sheetTitle !== '' ? $sheetTitle : 'Sheet', 0, 31);
    $sheet = $spreadsheet->createSheet($sheetIndex++);
    $sheet->setTitle($sheetTitle);

    $fixedHeaders = ['S.NO', 'RETURN DATE', 'RATE', 'NAME', 'DISTRICT', 'STATE', 'ORDER NO'];
    $tailHeaders  = ['INVOICE NO', 'REMARKS'];
    $headers = array_merge($fixedHeaders, array_map(function ($prid) use ($productNames) {
        return $productNames[$prid] ?? ('Product #' . $prid);
    }, $prids), $tailHeaders);

    $lastColIdx = count($headers) - 1;
    foreach ($headers as $i => $h) {
        $sheet->setCellValue($colLetter($i) . '1', $h);
    }
    $headerRange = 'A1:' . $colLetter($lastColIdx) . '1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($HEADER_FILL);
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

    $row = 2;
    $productTotals = array_fill_keys($prids, 0);
    $sNo = 0;
    foreach ($tids as $tid) {
        $s = $salesByTempid[$tid] ?? null;
        if (!$s) continue;
        $inv = $invoiceMap[$tid] ?? [];
        $rowReturns = $returnRows[$tid] ?? [];
        $sNo++;

        $firstPrice = 0.0;
        foreach ($rowReturns as $r) { if ($r['price'] > 0) { $firstPrice = $r['price']; break; } }

        $sheet->setCellValue('A' . $row, $sNo);
        $sheet->setCellValue('B' . $row, date('d-m-Y', strtotime($returnDateByTempid[$tid] ?? $s['date'])));
        $sheet->setCellValue('C' . $row, $firstPrice);
        $sheet->setCellValue('D' . $row, $s['customer_name']);
        $sheet->setCellValue('E' . $row, $s['district'] ?? '');
        $stateId = (int)($s['state_id'] ?? 0);
        $sheet->setCellValue('F' . $row, $stateId > 0 ? ($stateMap[$stateId] ?? '') : '');
        $sheet->setCellValue('G' . $row, $s['order_number']);

        $col = 7;
        foreach ($prids as $prid) {
            $qty = $rowReturns[$prid]['qty'] ?? 0;
            if ($qty > 0) {
                $sheet->setCellValue($colLetter($col) . $row, $qty);
                $productTotals[$prid] += $qty;
            }
            $col++;
        }

        $sheet->setCellValue($colLetter($col) . $row, $inv['inv_number'] ?? '');
        $col++;
        $sheet->setCellValue($colLetter($col) . $row, '');

        $row++;
    }

    $sheet->setCellValue('A' . $row, '');
    $sheet->setCellValue('B' . $row, '');
    $sheet->setCellValue('C' . $row, '');
    $sheet->setCellValue('D' . $row, '');
    $sheet->setCellValue('E' . $row, '');
    $sheet->setCellValue('F' . $row, 'TOTAL');
    $sheet->setCellValue('G' . $row, '');
    $col = 7;
    foreach ($prids as $prid) {
        $sheet->setCellValue($colLetter($col) . $row, $productTotals[$prid]);
        $col++;
    }
    $sheet->setCellValue($colLetter($col) . $row, '');
    $col++;
    $sheet->setCellValue($colLetter($col) . $row, '');

    $totalRange = 'A' . $row . ':' . $colLetter($lastColIdx) . $row;
    $sheet->getStyle($totalRange)->getFont()->setBold(true);
    $sheet->getStyle($totalRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($TOTAL_FILL);
    $sheet->getStyle($totalRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $dataRange = 'A1:' . $colLetter($lastColIdx) . $row;
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $sheet->getColumnDimension('D')->setWidth(22);
    $sheet->getColumnDimension('E')->setWidth(16);
    $sheet->getColumnDimension('G')->setWidth(22);
    $sheet->freezePane('A2');
}

if ($spreadsheet->getSheetCount() === 0) {
    die("No OT Sales Return records found for this filter.");
}
$spreadsheet->setActiveSheetIndex(0);

$filename = 'ot_sales_return_' . $se_fromDate . '_to_' . $se_toDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;

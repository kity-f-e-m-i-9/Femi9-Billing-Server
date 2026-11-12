<?php
// Excel export for Manage OT Sales (ot-sale-view.php), respecting the exact
// same on-screen date/category/status filter. One sheet per Category —
// matching the user's own hand-maintained tracker format: S.No/Date/Billing
// Rate/Name/District/State/Order No/[product qty columns]/Invoice No/Remarks,
// with a green TOTAL row.
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
require_once("include/GodownAccess.php");
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

function validateDate_otvx(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$_districtColExp = $db_conn->query("SHOW COLUMNS FROM ot_sales LIKE 'district'");
if ($_districtColExp && $_districtColExp->num_rows === 0) {
    $db_conn->query("ALTER TABLE ot_sales ADD COLUMN district VARCHAR(100) NULL DEFAULT NULL AFTER customer_address");
}

$se_fromDate = validateDate_otvx($_GET['frdate'] ?? null, date('Y-m-d'));
$se_toDate   = validateDate_otvx($_GET['todate'] ?? null, date('Y-m-d'));
$se_cat      = trim((string)($_GET['se_cat'] ?? ''));
$se_status   = trim((string)($_GET['se_status'] ?? ''));

$_otExtraFilter = '';
if ($se_cat !== '') {
    $_otExtraFilter .= " AND ot_sales_invoice.cat = '" . db_esc($se_cat) . "'";
}
if ($se_status !== '') {
    $_otExtraFilter .= " AND COALESCE(ot_sales_invoice.status, 'confirmed') = '" . db_esc($se_status) . "'";
}

$select_product_list = "SELECT DISTINCT ot_sales.tempid FROM ot_sales
    JOIN ot_sales_invoice ON ot_sales_invoice.tempid = ot_sales.tempid
    WHERE ot_sales.date BETWEEN '" . db_esc($se_fromDate) . "' AND '" . db_esc($se_toDate) . "'
    AND ot_sales.godownid IN (" . godown_ids_subquery($db_conn) . ") $_otExtraFilter";
$fetch_product_list = mysqli_query($db_conn, $select_product_list);
$tempidList = [];
while ($row = mysqli_fetch_assoc($fetch_product_list)) { $tempidList[] = $row['tempid']; }

if (empty($tempidList)) {
    die("No OT Sales records found for this filter.");
}

$tempidsEsc = implode(',', array_map(function ($t) { return "'" . db_esc($t) . "'"; }, $tempidList));

$salesByTempid = [];     // tempid => first ot_sales row (header fields)
$qtyByTempidProd = [];   // tempid => [prid => qty]
$discountByTempid = [];  // tempid => sum of discount across all product lines
$invoiceMap = [];        // tempid => ot_sales_invoice row
$stateMap = [];
$productNames = [];

$allSalesRes = mysqli_query($db_conn, "SELECT * FROM ot_sales WHERE tempid IN ($tempidsEsc) ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($allSalesRes)) {
    $tid = $row['tempid'];
    if (!isset($salesByTempid[$tid])) { $salesByTempid[$tid] = $row; }
    $qtyByTempidProd[$tid][(int)$row['prid']] = (int)$row['qty'];
    // discount is a per-line column (ot-sale-action.php writes/edits it per
    // product row), not per-order — sum every line's discount for this
    // order, not just the first row kept in $salesByTempid.
    $discountByTempid[$tid] = ($discountByTempid[$tid] ?? 0) + (float)($row['discount'] ?? 0);
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

// Group by Category, with dynamic product columns per category (only
// products that actually have a nonzero qty somewhere in that category).
$byCategory = [];
$categoryProducts = [];
foreach ($tempidList as $tid) {
    $s = $salesByTempid[$tid] ?? null;
    if (!$s) continue;
    $cat = $s['cat'] ?: 'Uncategorized';
    $byCategory[$cat][] = $tid;
    foreach (($qtyByTempidProd[$tid] ?? []) as $prid => $qty) {
        if ($qty > 0) { $categoryProducts[$cat][$prid] = true; }
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

    // Courier Charge / Total Amount / Discount are only meaningful for the
    // WEBSITE category's own per-order economics — every other category
    // (Amazon, Flipkart, WhatsApp Sales, ...) keeps the plain Invoice No +
    // Remarks tail it already had.
    $catUpper = mb_strtoupper(trim($cat));
    $isWebsiteCat = ($catUpper === 'WEBSITE');
    // Billing/Shipping Address only make sense for orders the company itself
    // ships out — Website, ID Concept, WhatsApp Sales — not marketplace
    // categories (Amazon/Flipkart/Myntra etc. ship from their own fulfilment,
    // and ot_sales.shipping_address/customer_address are never filled for those).
    $hasAddressCols = in_array($catUpper, ['WEBSITE', 'ID CONCEPT', 'WHATSAPP SALES'], true);

    $fixedHeaders = ['S.NO', 'DATE', 'BILLING RATE', 'NAME', 'MOBILE', 'DISTRICT', 'STATE', 'ORDER NO', 'INVOICE NO'];
    $tailHeaders  = [];
    if ($isWebsiteCat) { $tailHeaders = array_merge($tailHeaders, ['COURIER CHARGE', 'TOTAL AMOUNT', 'DISCOUNT']); }
    if ($hasAddressCols) { $tailHeaders = array_merge($tailHeaders, ['BILLING ADDRESS', 'SHIPPING ADDRESS']); }
    $tailHeaders[] = 'REMARKS';
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
    $courierTotal = 0.0;
    $amountTotal  = 0.0;
    $discountTotal = 0.0;
    $sNo = 0;
    foreach ($tids as $tid) {
        $s = $salesByTempid[$tid] ?? null;
        if (!$s) continue;
        $inv = $invoiceMap[$tid] ?? [];
        $sNo++;

        $sheet->setCellValue('A' . $row, $sNo);
        $sheet->setCellValue('B' . $row, date('d-m-Y', strtotime($s['date'])));
        // BILLING RATE = net per-unit rate after discount, not the raw
        // entered rate — ot_sales.discount is a flat amount taken off that
        // line's subtotal (rate * qty), same math as ot-sale-action.php's
        // own $sub_total = $sub_total_rate - $discount_value.
        $sQty = (float)($s['qty'] ?? 0);
        $netRate = $sQty > 0
            ? ((float)$s['price'] - ((float)($s['discount'] ?? 0) / $sQty))
            : (float)$s['price'];
        $sheet->setCellValue('C' . $row, $netRate);
        $sheet->setCellValue('D' . $row, $s['customer_name']);
        $sheet->setCellValue('E' . $row, $s['customer_mobile'] ?? '');
        $sheet->setCellValue('F' . $row, $s['district'] ?? '');
        $stateId = (int)($s['state_id'] ?? 0);
        $sheet->setCellValue('G' . $row, $stateId > 0 ? ($stateMap[$stateId] ?? '') : '');
        $sheet->setCellValue('H' . $row, $s['order_number']);
        $sheet->setCellValue('I' . $row, $inv['inv_number'] ?? '');

        $col = 9;
        foreach ($prids as $prid) {
            $qty = $qtyByTempidProd[$tid][$prid] ?? 0;
            if ($qty > 0) {
                $sheet->setCellValue($colLetter($col) . $row, $qty);
                $productTotals[$prid] += $qty;
            }
            $col++;
        }

        if ($isWebsiteCat) {
            $courierCharge = (float)($inv['courier_charges'] ?? 0);
            $totalAmount   = (float)($inv['total'] ?? 0);
            $discount      = (float)($discountByTempid[$tid] ?? 0);
            $courierTotal  += $courierCharge;
            $amountTotal   += $totalAmount;
            $discountTotal += $discount;

            $sheet->setCellValue($colLetter($col) . $row, $courierCharge);
            $col++;
            $sheet->setCellValue($colLetter($col) . $row, $totalAmount);
            $col++;
            $sheet->setCellValue($colLetter($col) . $row, $discount);
            $col++;
        }
        if ($hasAddressCols) {
            $sheet->setCellValue($colLetter($col) . $row, $s['customer_address'] ?? '');
            $col++;
            $sheet->setCellValue($colLetter($col) . $row, $s['shipping_address'] ?? '');
            $col++;
        }
        $sheet->setCellValue($colLetter($col) . $row, '');

        $row++;
    }

    $sheet->setCellValue('A' . $row, '');
    $sheet->setCellValue('B' . $row, '');
    $sheet->setCellValue('C' . $row, '');
    $sheet->setCellValue('D' . $row, '');
    $sheet->setCellValue('E' . $row, '');
    $sheet->setCellValue('F' . $row, '');
    $sheet->setCellValue('G' . $row, 'TOTAL');
    $sheet->setCellValue('H' . $row, '');
    $sheet->setCellValue('I' . $row, '');
    $col = 9;
    foreach ($prids as $prid) {
        $sheet->setCellValue($colLetter($col) . $row, $productTotals[$prid]);
        $col++;
    }
    if ($isWebsiteCat) {
        $sheet->setCellValue($colLetter($col) . $row, $courierTotal);
        $col++;
        $sheet->setCellValue($colLetter($col) . $row, $amountTotal);
        $col++;
        $sheet->setCellValue($colLetter($col) . $row, $discountTotal);
        $col++;
    }
    if ($hasAddressCols) {
        $sheet->setCellValue($colLetter($col) . $row, '');
        $col++;
        $sheet->setCellValue($colLetter($col) . $row, '');
        $col++;
    }
    $sheet->setCellValue($colLetter($col) . $row, '');

    $totalRange = 'A' . $row . ':' . $colLetter($lastColIdx) . $row;
    $sheet->getStyle($totalRange)->getFont()->setBold(true);
    $sheet->getStyle($totalRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($TOTAL_FILL);
    $sheet->getStyle($totalRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $dataRange = 'A1:' . $colLetter($lastColIdx) . $row;
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $sheet->getColumnDimension('D')->setWidth(22);
    $sheet->getColumnDimension('E')->setWidth(16);
    $sheet->getColumnDimension('F')->setWidth(16);
    $sheet->getColumnDimension('H')->setWidth(22);
    $sheet->getColumnDimension('I')->setWidth(18);
    if ($hasAddressCols) {
        $addrColIdx = array_search('BILLING ADDRESS', $headers, true);
        $billCol = $colLetter($addrColIdx);
        $shipCol = $colLetter($addrColIdx + 1);
        $sheet->getColumnDimension($billCol)->setWidth(35);
        $sheet->getColumnDimension($shipCol)->setWidth(35);
        // Address text already carries its own line breaks (street / city,
        // state, pincode) — without wrap text Excel crams it all onto one
        // line past the column edge instead of showing those breaks.
        $addrRange = $billCol . '2:' . $shipCol . $row;
        $sheet->getStyle($addrRange)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

    // Force these columns to plain Text format, not just on the rows filled
    // in here but down the WHOLE column (not only $dataRange) — Mobile/Order
    // No/Invoice No are numeric-looking strings a user may later type/paste
    // into the sheet by hand; without this Excel's own "General" auto-format
    // mangles them (strips a leading 0, flips a long number into scientific
    // notation, or — if a value starts with '=' — tries to run it as a
    // formula). Setting the column itself (not just the filled range) means
    // it stays safe for any row added below the current data too.
    foreach (['E', 'H', 'I'] as $textCol) {
        $sheet->getStyle($textCol . '1:' . $textCol . '1048576')->getNumberFormat()->setFormatCode('@');
    }
    $sheet->freezePane('A2');
}

if ($spreadsheet->getSheetCount() === 0) {
    die("No OT Sales records found for this filter.");
}
$spreadsheet->setActiveSheetIndex(0);

$filename = 'ot_sales_' . $se_fromDate . '_to_' . $se_toDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;

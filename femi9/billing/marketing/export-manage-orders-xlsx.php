<?php
// Proper .xlsx export for manage_order_product.php ("Manage Orders -> Product
// Orders") — same date-range + view_ms_id/view_ms_ids scoping rules as that
// page (a manager can only ever export their own real downline, never an
// arbitrary id), same row set. Used by both the page's "Export Excel" link
// and its "Share via WhatsApp" button (which fetches this same file as a
// blob and hands it to navigator.share).
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

// ── Same scoping as manage_order_product.php ────────────────────────────────
$ownId = (int)$markeingSTFID;
$allowedSubtree = getMsSubtreeIds($db_conn, $ownId);
$allowedSubtreeSet = array_flip($allowedSubtree);

$viewMsIds = [];
if (!empty($_REQUEST['view_ms_ids'])) {
    $requestedIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $_REQUEST['view_ms_ids'])))));
    foreach ($requestedIds as $rid) {
        if (isset($allowedSubtreeSet[$rid])) { $viewMsIds[] = $rid; }
    }
} elseif (isset($_REQUEST['view_ms_id']) && $_REQUEST['view_ms_id'] !== '') {
    $requestedMsId = (int)$_REQUEST['view_ms_id'];
    if (isset($allowedSubtreeSet[$requestedMsId])) { $viewMsIds = [$requestedMsId]; }
}
if (empty($viewMsIds)) { $viewMsIds = [$ownId]; }
$isTeamMode = count($viewMsIds) > 1;
$msIdListSql = implode(',', array_map('intval', $viewMsIds));

function validateDate_mox(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}
$from_date = validateDate_mox($_REQUEST['frdate'] ?? null, date('Y-m-d'));
$to_date   = validateDate_mox($_REQUEST['todate'] ?? null, date('Y-m-d'));
if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

// ── Same batched data-fetch pattern as manage_order_product.php ────────────
$allProducts = [];
$productPriceMap = [];
$resAllProd = $db_conn->query("SELECT id, productName, outlet_price FROM products ORDER BY id ASC");
while ($apr = mysqli_fetch_assoc($resAllProd)) {
    $allProducts[] = $apr;
    $productPriceMap[(int)$apr['id']] = (float)$apr['outlet_price'];
}

// assigned_cp_id is a self-migrating column (added by order_action_get.php
// on its first use) -- guard here too so this export never 500s on an
// environment where no vacant-area order has ever been saved yet.
$chkCpCol = $db_conn->query("SHOW COLUMNS FROM ms_orders LIKE 'assigned_cp_id'");
if ($chkCpCol && $chkCpCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE ms_orders ADD COLUMN assigned_cp_id INT UNSIGNED NULL DEFAULT NULL AFTER tp_id");
}

$orderIdsInRange = [];
$orderMeta = [];
$qtyMap = [];
$shopIds = [];
$msIdsInRange = [];
$orderValueMap = [];
$resOrders = $db_conn->query(
    "SELECT order_id, shop_id, ms_id, order_date, marketing_tool, latitude, longitude, pr_id, qty, discount_percentage, assigned_cp_id
     FROM ms_orders
     WHERE ms_id IN ($msIdListSql) AND new_order='yes' AND order_date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' AND '" . $db_conn->real_escape_string($to_date) . "'
     ORDER BY order_date DESC, order_id DESC"
);
while ($orow = mysqli_fetch_assoc($resOrders)) {
    $oid = $orow['order_id'];
    if (!isset($orderMeta[$oid])) {
        $orderMeta[$oid] = $orow;
        $orderIdsInRange[] = $oid;
        if (!empty($orow['shop_id'])) { $shopIds[$orow['shop_id']] = true; }
        if (!empty($orow['ms_id']))   { $msIdsInRange[$orow['ms_id']] = true; }
    }
    $qtyMap[$oid][$orow['pr_id']] = (int)$orow['qty'];
    $lineQty = (int)$orow['qty'];
    $linePrice = $productPriceMap[(int)$orow['pr_id']] ?? 0;
    $lineDiscount = (float)($orow['discount_percentage'] ?? 0);
    $orderValueMap[$oid] = ($orderValueMap[$oid] ?? 0) + ($lineQty * $linePrice * (1 - $lineDiscount / 100));
}

$shopMeta = [];
if (!empty($shopIds)) {
    $shopIdList = implode(',', array_map('intval', array_keys($shopIds)));
    $resShops = $db_conn->query("SELECT * FROM ms_shop WHERE id IN ($shopIdList)");
    while ($srow = mysqli_fetch_assoc($resShops)) { $shopMeta[$srow['id']] = $srow; }
}

$msMeta = [];
if ($isTeamMode && !empty($msIdsInRange)) {
    $msIdList2 = implode(',', array_map('intval', array_keys($msIdsInRange)));
    $resMs = $db_conn->query("SELECT id, ms_name FROM marketing_staff WHERE id IN ($msIdList2)");
    while ($mrow = mysqli_fetch_assoc($resMs)) { $msMeta[$mrow['id']] = $mrow; }
}

// Which orders already went to a TP (tp_orders bridge) — needed to tell a
// real TP order apart from a Vacant Area order attributed to a CP/Company.
$tpOrderIds = [];
if (!empty($orderIdsInRange)) {
    $oidList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), $orderIdsInRange)) . "'";
    $resTp = $db_conn->query("SELECT DISTINCT order_id FROM tp_orders WHERE order_id IN ($oidList)");
    while ($trow = mysqli_fetch_assoc($resTp)) { $tpOrderIds[$trow['order_id']] = true; }
}

// Which CP (if any) a Vacant Area order was attributed to — see
// add_order.php's "Assign To CP" block (2026-11-12, user request).
$cpNameMap = [];
$cpIdsSeen = [];
foreach ($orderIdsInRange as $oid) {
    $cpid = $orderMeta[$oid]['assigned_cp_id'] ?? null;
    if (!empty($cpid)) { $cpIdsSeen[(int)$cpid] = true; }
}
if (!empty($cpIdsSeen)) {
    $cpIdList = implode(',', array_map('intval', array_keys($cpIdsSeen)));
    $resCpNames = $db_conn->query("SELECT id, name FROM channel_partners WHERE id IN ($cpIdList)");
    while ($cnr = mysqli_fetch_assoc($resCpNames)) { $cpNameMap[$cnr['id']] = $cnr['name']; }
}

// ── Build the workbook ───────────────────────────────────────────────────
require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Product Orders');

$colLetter = function (int $idx): string { return Coordinate::stringFromColumnIndex($idx); };

$headers = ['#'];
if ($isTeamMode) { $headers[] = 'Marketing Staff'; }
$headers = array_merge($headers, ['Shop Name', 'Contact Number', 'Address', 'Date', 'Marketing Tool', 'Order Value']);
foreach ($allProducts as $apr) { $headers[] = $apr['productName']; }
$headers[] = 'Location';
$headers[] = 'Assigned CP';

foreach ($headers as $i => $h) { $sheet->setCellValue($colLetter($i + 1) . '1', $h); }
$lastColIdx = count($headers);
$lastColLetter = $colLetter($lastColIdx);
$sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$row = 2;
$i = 0;
foreach ($orderIdsInRange as $orderid) {
    $meta = $orderMeta[$orderid];
    $shop = $shopMeta[$meta['shop_id']] ?? [];
    $col = 1;

    $sheet->setCellValue($colLetter($col++) . $row, ++$i);
    if ($isTeamMode) {
        $sheet->setCellValue($colLetter($col++) . $row, $msMeta[$meta['ms_id']]['ms_name'] ?? '');
    }
    $sheet->setCellValue($colLetter($col++) . $row, $shop['name'] ?? '');
    $sheet->setCellValue($colLetter($col++) . $row, $shop['mobile_number'] ?? '');
    $sheet->setCellValue($colLetter($col++) . $row, ucwords((string)($shop['address'] ?? '')));
    $sheet->setCellValue($colLetter($col++) . $row, date('d-m-Y', strtotime($meta['order_date'])));
    $sheet->setCellValue($colLetter($col++) . $row, $meta['marketing_tool'] ?? '');
    $sheet->setCellValue($colLetter($col++) . $row, (float)($orderValueMap[$orderid] ?? 0));

    foreach ($allProducts as $apr) {
        $qty = $qtyMap[$orderid][$apr['id']] ?? 0;
        $sheet->setCellValue($colLetter($col++) . $row, $qty > 0 ? (int)$qty : '');
    }

    $lat = $meta['latitude']; $lng = $meta['longitude'];
    $sheet->setCellValue($colLetter($col++) . $row, ($lat && $lng) ? "https://www.google.com/maps?q={$lat},{$lng}" : '');

    if (isset($tpOrderIds[$orderid])) {
        $sheet->setCellValue($colLetter($col++) . $row, '—');
    } else {
        $cpid = $meta['assigned_cp_id'] ?? null;
        $sheet->setCellValue($colLetter($col++) . $row, !empty($cpid) ? ($cpNameMap[$cpid] ?? ('CP #' . $cpid)) : 'Company');
    }

    $row++;
}
$lastRow = max($row - 1, 1);

// Fixed widths instead of setAutoSize() -- autosize forces PhpSpreadsheet to
// measure every cell's text in every column (O(rows x cols)), which with
// ~50+ product columns and a full month of orders added ~0.8s just to this
// one step (measured 2026-11-12: 1.8s with autosize vs 1.0s with fixed
// widths). Product columns are just small qty numbers, so a narrow fixed
// width suits them better than a measured one anyway.
$fixedWidths = ['#' => 6, 'Marketing Staff' => 18, 'Shop Name' => 24, 'Contact Number' => 14,
    'Address' => 28, 'Date' => 12, 'Marketing Tool' => 16, 'Order Value' => 13,
    'Location' => 30, 'Assigned CP' => 18];
foreach ($headers as $i => $h) {
    $sheet->getColumnDimension($colLetter($i + 1))->setWidth($fixedWidths[$h] ?? 10);
}
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:' . $lastColLetter . $lastRow);

$filename = 'Product_Orders_' . $from_date . '_to_' . $to_date . '.xlsx';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;

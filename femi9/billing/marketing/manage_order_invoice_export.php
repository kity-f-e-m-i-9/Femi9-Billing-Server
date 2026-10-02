<?php
// Invoice-level breakdown for a DM's (or a manager's team's) Get Orders in
// a date range -- same scope/period as manage_order_product.php's KPI
// cards, but per-invoice instead of aggregated, and with the two things
// "Total Invoice Value" doesn't show: Returned Amount and Deleted Invoice
// Amount, so Net Invoice Value = Invoice Amount - Returned Amount (0 once
// the invoice itself is deleted/voided). See company/asm-dm-order-
// conversion-export.php for the same invnumber=inv_id convention on
// user_return_stock (confirmed against live data).
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

@include("checksession.php");
@include("config.php");
require_once("include/TeamSubtree.php");

if (!isset($_SESSION) || !isset($db_conn)) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Session or database error. Please try again.";
    exit;
}

try {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Excel library not found. Please install PhpSpreadsheet.";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function xlsx_set(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colIndex, int $row, $value): void {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $row, $value);
}

// ---- Same scoping/validation as manage_order_product.php ----------------
$ownId = (int) $markeingSTFID;
$allowedSubtree = getMsSubtreeIds($db_conn, $ownId);
$allowedSubtreeSet = array_flip($allowedSubtree);

$viewMsIds = [];
if (!empty($_REQUEST['view_ms_ids'])) {
    $requestedIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $_REQUEST['view_ms_ids'])))));
    foreach ($requestedIds as $rid) {
        if (isset($allowedSubtreeSet[$rid])) { $viewMsIds[] = $rid; }
    }
} elseif (isset($_REQUEST['view_ms_id']) && $_REQUEST['view_ms_id'] !== '') {
    $requestedMsId = (int) $_REQUEST['view_ms_id'];
    if (isset($allowedSubtreeSet[$requestedMsId])) { $viewMsIds = [$requestedMsId]; }
}
if (empty($viewMsIds)) { $viewMsIds = [$ownId]; }
$msIdListSql = implode(',', array_map('intval', $viewMsIds));

$from_date = $_REQUEST['frd'] ?? date('Y-m-d');
$to_date   = $_REQUEST['tod'] ?? date('Y-m-d');

$viewLabel = 'My Own Orders';
if (count($viewMsIds) > 1) {
    $viewLabel = 'Team (' . count($viewMsIds) . ' people)';
} elseif ($viewMsIds !== [$ownId]) {
    $stmtName = $db_conn->prepare("SELECT ms_name FROM marketing_staff WHERE id=?");
    $stmtName->bind_param('i', $viewMsIds[0]);
    $stmtName->execute();
    $viewLabel = $stmtName->get_result()->fetch_assoc()['ms_name'] ?? 'Selected DM';
    $stmtName->close();
}

// ---- Get Orders in range, with their shop + product-estimate value ------
$productPriceMap = [];
$resAllProd = $db_conn->query("SELECT id, outlet_price FROM products");
while ($apr = mysqli_fetch_assoc($resAllProd)) { $productPriceMap[(int) $apr['id']] = (float) $apr['outlet_price']; }

$orderIdsInRange = [];
$orderMeta = [];
$shopIds = [];
$orderValueMap = [];
$resOrders = $db_conn->query(
    "SELECT order_id, shop_id, order_date, marketing_tool, pr_id, qty, discount_percentage
     FROM ms_orders
     WHERE ms_id IN ($msIdListSql) AND new_order='yes' AND order_date BETWEEN '$from_date' AND '$to_date'
     ORDER BY order_date DESC, order_id DESC"
);
while ($orow = mysqli_fetch_assoc($resOrders)) {
    $oid = $orow['order_id'];
    if (!isset($orderMeta[$oid])) {
        $orderMeta[$oid] = $orow;
        $orderIdsInRange[] = $oid;
        if (!empty($orow['shop_id'])) { $shopIds[$orow['shop_id']] = true; }
    }
    $lineQty = (int) $orow['qty'];
    $linePrice = $productPriceMap[(int) $orow['pr_id']] ?? 0;
    $lineDiscount = (float) ($orow['discount_percentage'] ?? 0);
    $orderValueMap[$oid] = ($orderValueMap[$oid] ?? 0) + ($lineQty * $linePrice * (1 - $lineDiscount / 100));
}

$shopMeta = [];
if (!empty($shopIds)) {
    $shopIdList = implode(',', array_map('intval', array_keys($shopIds)));
    $resShops = $db_conn->query("SELECT id, name, mobile_number, taluk_name FROM ms_shop WHERE id IN ($shopIdList)");
    while ($srow = mysqli_fetch_assoc($resShops)) { $shopMeta[$srow['id']] = $srow; }
}

// Which orders the TP has invoiced, and that invoice's id.
$tpOrderMeta = [];
if (!empty($orderIdsInRange)) {
    $oidList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $orderIdsInRange)) . "'";
    $resTp = $db_conn->query("SELECT order_id, invoiced_inv_id FROM tp_orders WHERE order_id IN ($oidList)");
    while ($trow = mysqli_fetch_assoc($resTp)) {
        if (!isset($tpOrderMeta[$trow['order_id']])) { $tpOrderMeta[$trow['order_id']] = $trow; }
    }
}

$invIdToOrderIds = [];
foreach ($orderIdsInRange as $oid) {
    $invId = $tpOrderMeta[$oid]['invoiced_inv_id'] ?? null;
    if (!empty($invId)) { $invIdToOrderIds[$invId][] = $oid; }
}

// ---- Invoice amounts, live/deleted/voided split --------------------------
$invInfo = [];
if (!empty($invIdToOrderIds)) {
    $idList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), array_keys($invIdToOrderIds))) . "'";
    $resInv = $db_conn->query("SELECT inv_id, inv_number, total, status, deleted_at, voided_at FROM user_invoice WHERE inv_id IN ($idList)");
    while ($ir = mysqli_fetch_assoc($resInv)) { $invInfo[$ir['inv_id']] = $ir; }
}

// ---- Returns against the live invoices -- user_return_stock.invnumber is
// confirmed (against live data) to hold user_invoice.inv_id, not
// inv_number, despite the column name. Dedupe by returnid before summing,
// since one return repeats its total on every line item.
$liveInvIds = [];
foreach ($invInfo as $iid => $ir) {
    $isDeletedOrVoided = !empty($ir['deleted_at']) || !empty($ir['voided_at']) || $ir['status'] === 'cancelled';
    if (!$isDeletedOrVoided) { $liveInvIds[] = $iid; }
}
$returnedByInv = [];
if (!empty($liveInvIds)) {
    $placeholders = implode(',', array_fill(0, count($liveInvIds), '?'));
    $types = str_repeat('s', count($liveInvIds));
    $stmt = $db_conn->prepare(
        "SELECT invnumber, returnid, MAX(total) AS total
         FROM user_return_stock
         WHERE invnumber IN ($placeholders) AND deleted_at IS NULL
         GROUP BY invnumber, returnid"
    );
    $stmt->bind_param($types, ...$liveInvIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $returnedByInv[$row['invnumber']] = ($returnedByInv[$row['invnumber']] ?? 0) + (float) $row['total'];
    }
    $stmt->close();
}

// ---- Build one detail row per Get Order -----------------------------------
$detailRows = [];
$sumGetOrderValue = 0.0;
$sumInvoiceAmount = 0.0;
$sumReturned = 0.0;
$sumDeleted = 0.0;
$sumNet = 0.0;

foreach ($orderIdsInRange as $oid) {
    $o = $orderMeta[$oid];
    $shop = $shopMeta[$o['shop_id']] ?? [];
    $invId = $tpOrderMeta[$oid]['invoiced_inv_id'] ?? null;
    $ir = (!empty($invId) && isset($invInfo[$invId])) ? $invInfo[$invId] : null;

    $invoiceAmount = $ir ? (float) $ir['total'] : 0.0;
    $isDeletedOrVoided = $ir && (!empty($ir['deleted_at']) || !empty($ir['voided_at']) || $ir['status'] === 'cancelled');
    $deletedAmount = $isDeletedOrVoided ? $invoiceAmount : 0.0;
    $returnedAmount = (!$isDeletedOrVoided && $invId) ? ($returnedByInv[$invId] ?? 0.0) : 0.0;
    $netAmount = $isDeletedOrVoided ? 0.0 : ($invoiceAmount - $returnedAmount);

    $status = 'Not Invoiced Yet';
    if ($ir) {
        if ($isDeletedOrVoided) { $status = !empty($ir['deleted_at']) ? 'Deleted' : 'Voided'; }
        else { $status = 'Live'; }
    }

    $detailRows[] = [
        'date' => $o['order_date'],
        'shop_name' => $shop['name'] ?? '-',
        'shop_mobile' => $shop['mobile_number'] ?? '-',
        'taluk' => $shop['taluk_name'] ?? '-',
        'tool' => $o['marketing_tool'] ?? '-',
        'get_order_value' => $orderValueMap[$oid] ?? 0.0,
        'inv_number' => $ir['inv_number'] ?? '-',
        'invoice_amount' => $invoiceAmount,
        'status' => $status,
        'returned_amount' => $returnedAmount,
        'deleted_amount' => $deletedAmount,
        'net_amount' => $netAmount,
    ];

    $sumGetOrderValue += $orderValueMap[$oid] ?? 0.0;
    $sumInvoiceAmount += $invoiceAmount;
    $sumReturned += $returnedAmount;
    $sumDeleted += $deletedAmount;
    $sumNet += $netAmount;
}

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Invoice Value Breakdown');

    $heading = "Get Order -> Invoice Breakdown — {$viewLabel} | " . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date));
    $sheet->setCellValue('A1', $heading);
    $sheet->mergeCells('A1:L1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

    // ---- Summary block --------------------------------------------------
    $summaryLabels = ['Get Order Value (Est.)', 'Invoice Amount', 'Returned Amount', 'Deleted Invoice Amount', 'Net Invoice Value'];
    $summaryValues = [$sumGetOrderValue, $sumInvoiceAmount, $sumReturned, $sumDeleted, $sumNet];
    $summaryColors = ['BDD7EE', 'D9D9D9', 'FFEB9C', 'F4CCCC', 'C6EFCE'];
    $sCol = 1;
    foreach ($summaryLabels as $i => $label) {
        xlsx_set($sheet, $sCol, 3, $label);
        xlsx_set($sheet, $sCol, 4, $summaryValues[$i]);
        $colLetter = Coordinate::stringFromColumnIndex($sCol);
        $sheet->getStyle($colLetter . '3')->getFont()->setBold(true);
        $sheet->getStyle($colLetter . '4')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle($colLetter . '4')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle($colLetter . '3:' . $colLetter . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($summaryColors[$i]);
        $sheet->getStyle($colLetter . '3:' . $colLetter . '4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sCol += 2;
    }

    // ---- Detail table -----------------------------------------------------
    $headerRow = 7;
    $columns = [
        'Order Date', 'Shop Name', 'Shop Contact', 'Taluk', 'Marketing Tool',
        'Get Order Value (Est.)', 'Invoice Number', 'Invoice Amount', 'Status',
        'Returned Amount', 'Deleted Invoice Amount', 'Net Invoice Value',
    ];
    $col = 1;
    foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
    $lastCol = $col - 1;

    $headerRange = 'A' . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $row = $headerRow + 1;
    foreach ($detailRows as $r) {
        xlsx_set($sheet, 1, $row, date('d-m-Y', strtotime($r['date'])));
        xlsx_set($sheet, 2, $row, $r['shop_name']);
        xlsx_set($sheet, 3, $row, $r['shop_mobile']);
        xlsx_set($sheet, 4, $row, $r['taluk']);
        xlsx_set($sheet, 5, $row, $r['tool']);
        xlsx_set($sheet, 6, $row, $r['get_order_value']);
        xlsx_set($sheet, 7, $row, $r['inv_number']);
        xlsx_set($sheet, 8, $row, $r['invoice_amount']);
        xlsx_set($sheet, 9, $row, $r['status']);
        xlsx_set($sheet, 10, $row, $r['returned_amount']);
        xlsx_set($sheet, 11, $row, $r['deleted_amount']);
        xlsx_set($sheet, 12, $row, $r['net_amount']);
        $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('J' . $row . ':L' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEB9C'); // Returned - amber
        $sheet->getStyle('K' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F4CCCC'); // Deleted - dusty red
        $sheet->getStyle('L' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE'); // Net - green
        $sheet->getStyle('A' . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $row++;
    }

    if ($row > $headerRow + 1) {
        $dataRange = 'A' . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
    foreach (range(1, $lastCol) as $ci) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
    }
    $sheet->freezePane('A' . ($headerRow + 1));

    $today = date('Y-m-d');
    $filename = "Invoice_Value_Breakdown_{$today}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');
    if (ob_get_level() > 0) { ob_end_clean(); }
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Throwable $e) {
    if (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/plain; charset=utf-8');
    error_log('[manage_order_invoice_export] ' . $e->getMessage());
    echo "Export failed. Please try again.";
    exit;
}

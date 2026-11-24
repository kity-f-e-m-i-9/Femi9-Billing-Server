<?php
ob_start();
error_reporting(0);

include("checksession.php");
include("config.php");

date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Same Manage Field Orders list as manage-orders.php, exported with the
// location lat/long columns a plain on-screen table can't show well.
// 2026-11-24, user-requested.

$today    = date("Y-m-d");
$from_date = $_GET['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_GET['todate'] ?? $today;
$status_filter = $_GET['status_filter'] ?? 'pending';
if (!in_array($status_filter, ['pending', 'incomplete', 'completed'], true)) { $status_filter = 'pending'; }
$shop_search = trim($_GET['shop_search'] ?? '');
$mt_filter = $_GET['mt_filter'] ?? '';
if (!in_array($mt_filter, ['', 'available', 'not_available'], true)) { $mt_filter = ''; }

// Same marketing_tool parsing as manage-orders.php's own mt_filter — kept as
// a separate copy here since this export has no shared include to pull it
// from. 2026-11-30, user-requested ("export excel-layum mt_filter add pannu").
function parseMarketingTool(?string $raw): array {
    $raw = trim((string)$raw);
    if ($raw === '') { return ['status' => null, 'tools' => [], 'raw' => '']; }
    foreach (['Available', 'Not Available'] as $prefix) {
        if (stripos($raw, $prefix . ':') === 0) {
            $toolsPart = substr($raw, strlen($prefix) + 1);
            $tools = array_values(array_filter(array_map('trim', explode(',', $toolsPart)), fn($t) => $t !== ''));
            return ['status' => $prefix, 'tools' => $tools, 'raw' => $raw];
        }
    }
    return ['status' => null, 'tools' => [], 'raw' => $raw];
}

$stmt = mysqli_prepare($db_conn,
    "SELECT o.id, o.order_id, o.order_date, o.new_order, o.noorder_reason, o.marketing_tool,
            o.pr_id, o.qty, o.invoiced_inv_id, o.assigned_by_ms_id, o.voided_at, o.void_reason,
            s.name shop_name, s.latitude shop_lat, s.longitude shop_lng, s.mobile_number shop_mobile,
            p.productName, dm.ms_name, mo.dm_lat, mo.dm_lng, ui.inv_number
     FROM tp_orders o
     LEFT JOIN shop s ON s.id=o.shop_id
     LEFT JOIN products p ON p.id=o.pr_id
     LEFT JOIN marketing_staff dm ON dm.id=o.assigned_by_ms_id
     LEFT JOIN (SELECT order_id, MAX(latitude) dm_lat, MAX(longitude) dm_lng FROM ms_orders GROUP BY order_id) mo ON mo.order_id=o.order_id
     LEFT JOIN user_invoice ui ON ui.inv_id=o.invoiced_inv_id COLLATE utf8mb4_general_ci
     WHERE o.tp_id=? AND o.order_date BETWEEN ? AND ?
     ORDER BY o.order_date DESC, o.id DESC"
);
mysqli_stmt_bind_param($stmt, "iss", $Login_user_IDvl, $from_date, $to_date);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$visits = [];
foreach ($rows as $r) {
    $oid = $r['order_id'];
    if (!isset($visits[$oid])) {
        $visits[$oid] = [
            'order_date' => $r['order_date'], 'shop_name' => $r['shop_name'],
            'shop_lat' => $r['shop_lat'], 'shop_lng' => $r['shop_lng'],
            'shop_mobile' => $r['shop_mobile'],
            'new_order' => $r['new_order'], 'noorder_reason' => $r['noorder_reason'],
            'marketing_tool' => $r['marketing_tool'],
            'invoiced_inv_id' => $r['invoiced_inv_id'], 'assigned_by_ms_id' => $r['assigned_by_ms_id'],
            'voided_at' => $r['voided_at'], 'void_reason' => $r['void_reason'],
            'dm_lat' => $r['dm_lat'], 'dm_lng' => $r['dm_lng'], 'dm_name' => $r['ms_name'],
            'inv_number' => $r['inv_number'], 'lines' => [],
        ];
    }
    if ($r['new_order'] === 'yes') {
        $visits[$oid]['lines'][] = $r['productName'] . ' x ' . $r['qty'];
    }
}

$invIds = array_values(array_unique(array_filter(array_column($visits, 'invoiced_inv_id'))));
$completedInvIds = [];
if (!empty($invIds)) {
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $types = str_repeat('s', count($invIds));
    $stmtR = mysqli_prepare($db_conn, "SELECT DISTINCT inv_id FROM receipt WHERE inv_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmtR, $types, ...$invIds);
    mysqli_stmt_execute($stmtR);
    $resR = mysqli_stmt_get_result($stmtR);
    while ($rr = mysqli_fetch_assoc($resR)) { $completedInvIds[$rr['inv_id']] = true; }
    mysqli_stmt_close($stmtR);
}

foreach ($visits as $oid => &$v) {
    if ($v['new_order'] !== 'yes' || !empty($v['voided_at'])) {
        $v['invoice_status'] = null;
    } elseif (empty($v['invoiced_inv_id'])) {
        $v['invoice_status'] = 'pending';
    } elseif (isset($completedInvIds[$v['invoiced_inv_id']])) {
        $v['invoice_status'] = 'completed';
    } else {
        $v['invoice_status'] = 'incomplete';
    }
}
unset($v);

$visits = array_filter($visits, fn($v) => $v['invoice_status'] === $status_filter);
if ($shop_search !== '') {
    $visits = array_filter($visits, fn($v) => stripos($v['shop_name'] ?? '', $shop_search) !== false);
}

foreach ($visits as $oid => &$v) {
    $v['marketing_tool_parsed'] = parseMarketingTool($v['marketing_tool'] ?? '');
}
unset($v);

if ($mt_filter === 'available') {
    $visits = array_filter($visits, fn($v) => $v['marketing_tool_parsed']['status'] === 'Available');
} elseif ($mt_filter === 'not_available') {
    $visits = array_filter($visits, fn($v) => $v['marketing_tool_parsed']['status'] === 'Not Available');
}

ob_end_clean();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Field Orders');

$headers = [
    'A' => 'Date', 'B' => 'Shop', 'C' => 'Shop Mobile', 'D' => 'From DM',
    'E' => 'Products', 'F' => 'Status', 'G' => 'Invoice Number',
    'H' => 'Marketing Tool', 'I' => 'Marketing Tool Details',
    'J' => 'Shop Latitude', 'K' => 'Shop Longitude',
    'L' => 'DM Order Latitude', 'M' => 'DM Order Longitude',
];
$lastCol = 'M';

foreach ($headers as $col => $label) { $sheet->setCellValue("{$col}1", $label); }
$sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
]);

$rowNum = 2;
foreach ($visits as $v) {
    $statusLabel = !empty($v['voided_at']) ? 'Not Converted' : ucfirst((string)($v['invoice_status'] ?? '-'));
    $sheet->setCellValue("A{$rowNum}", date('d-m-Y', strtotime($v['order_date'])));
    $sheet->setCellValue("B{$rowNum}", $v['shop_name'] ?? '-');
    $sheet->setCellValue("C{$rowNum}", $v['shop_mobile'] ?? '-');
    $sheet->setCellValue("D{$rowNum}", $v['dm_name'] ?? '-');
    $sheet->setCellValue("E{$rowNum}", $v['new_order'] === 'yes' ? implode('; ', $v['lines']) : 'No Order: ' . ($v['noorder_reason'] ?? ''));
    $sheet->setCellValue("F{$rowNum}", $statusLabel);
    $sheet->setCellValue("G{$rowNum}", $v['inv_number'] ?? '-');
    $mtp = $v['marketing_tool_parsed'];
    $sheet->setCellValue("H{$rowNum}", $mtp['status'] ?? '-');
    $sheet->setCellValue("I{$rowNum}", !empty($mtp['tools']) ? implode(', ', $mtp['tools']) : ($mtp['raw'] ?: '-'));
    $sheet->setCellValue("J{$rowNum}", $v['shop_lat'] ?? '');
    $sheet->setCellValue("K{$rowNum}", $v['shop_lng'] ?? '');
    $sheet->setCellValue("L{$rowNum}", $v['dm_lat'] ?? '');
    $sheet->setCellValue("M{$rowNum}", $v['dm_lng'] ?? '');
    $rowNum++;
}

foreach (range('A', $lastCol) as $letter) {
    $sheet->getColumnDimension($letter)->setAutoSize(true);
}

$filename = "Field_Orders_{$from_date}_to_{$to_date}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

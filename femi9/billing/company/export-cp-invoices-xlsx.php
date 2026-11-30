<?php
// Excel export for "CP Invoices" (manage-cp-invoices.php) — same filters
// (Channel Partner / Date From / Date To / Type), same row set and sort
// order as the on-screen table, so what the company sees is exactly what
// they download.
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

require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

// ── Filters (mirrors manage-cp-invoices.php) ────────────────────────────────
$filter_cp_id     = (int)($_GET['cp_id'] ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to']   ?? '');
$filter_type      = $_GET['type_filter'] ?? '';
if (!in_array($filter_type, ['napkin', 'diaper'], true)) $filter_type = '';

$where = ['1=1'];
$params = []; $types = '';

if ($filter_cp_id > 0) {
    $where[]  = "cpi.channel_partner_id = ?";
    $params[] = $filter_cp_id;
    $types   .= 'i';
}
if ($filter_date_from !== '') {
    $where[]  = "cpi.invoice_date >= ?";
    $params[] = $filter_date_from;
    $types   .= 's';
}
if ($filter_date_to !== '') {
    $where[]  = "cpi.invoice_date <= ?";
    $params[] = $filter_date_to;
    $types   .= 's';
}
if ($filter_type !== '') {
    $where[]  = "cpi.product_type = ?";
    $params[] = $filter_type;
    $types   .= 's';
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT cpi.invoice_number, cpi.invoice_date, cpi.total_amount, cpi.product_type,
           cpi.created_at, cpi.channel_partner_id,
           cp.name AS cp_name, cp.cp_id AS cp_code,
           COALESCE(NULLIF(cp.delivery_district,''), cp.branch_district) AS cp_district,
           gd.gname AS godown_name
    FROM cp_invoices cpi
    JOIN channel_partners cp       ON cp.id = cpi.channel_partner_id
    LEFT JOIN company_godown gd    ON gd.id = cpi.source_godown_id
    $where_sql
    ORDER BY cpi.created_at DESC
";
if ($params) {
    $stmt_main = $db_conn->prepare($sql);
    $stmt_main->bind_param($types, ...$params);
    $stmt_main->execute();
    $invoices = $stmt_main->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_main->close();
} else {
    $result   = $db_conn->query($sql);
    $invoices = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// ── Product/qty breakdown per invoice — a separate query (not joined into the
// main one) so a multi-product invoice doesn't multiply its own row above.
// One column per product that actually appears in this filtered set (not
// every product in the catalog) — same pivot convention as the OT Sales
// export (export-ot-sale-view-xlsx.php): header = product name, cell = qty,
// blank if that invoice didn't carry that product, TOTAL row at the bottom.
$qtyByInvNumberProduct = []; // invoice_number => [product_id => qty]
$productNames = [];          // product_id => name
if ($invoices) {
    $invNumbers   = array_column($invoices, 'invoice_number');
    $placeholders = implode(',', array_fill(0, count($invNumbers), '?'));
    $stmt_items = $db_conn->prepare("
        SELECT cpi.invoice_number, cii.product_id, p.productName, cii.quantity
        FROM cp_invoice_items cii
        JOIN cp_invoices cpi ON cpi.id = cii.cp_invoice_id
        JOIN products p      ON p.id = cii.product_id
        WHERE cpi.invoice_number IN ($placeholders)
        ORDER BY cii.id ASC
    ");
    $stmt_items->bind_param(str_repeat('s', count($invNumbers)), ...$invNumbers);
    $stmt_items->execute();
    foreach ($stmt_items->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
        $pid = (int)$item['product_id'];
        $qtyByInvNumberProduct[$item['invoice_number']][$pid] = (int)$item['quantity'];
        $productNames[$pid] = $item['productName'];
    }
    $stmt_items->close();
}
$prids = array_keys($productNames);
sort($prids);

// Division — same node-walk as manage-channel-partner.php's District/Division
// columns (channel_partner_locations -> partner_location_nodes, walking up
// from the CP's assigned node by its own depth), not the free-text
// branch/delivery district already used above for the District column.
$cpDivisionNames = []; // cp_id => division names (comma-joined)
if ($invoices) {
    $cpIdList = implode(',', array_unique(array_map('intval', array_column($invoices, 'channel_partner_id'))));
    $resDiv = $db_conn->query("
        SELECT cpl.channel_partner_id AS cp_id,
               GROUP_CONCAT(DISTINCT
                   CASE leaf.depth
                       WHEN 4 THEN leaf.name
                       WHEN 5 THEN p1.name
                       WHEN 6 THEN p2.name
                       ELSE NULL
                   END
               ORDER BY leaf.name SEPARATOR ', ') AS division_names
        FROM channel_partner_locations cpl
        JOIN partner_location_nodes leaf ON leaf.id = cpl.location_id
        LEFT JOIN partner_location_nodes p1 ON p1.id = leaf.parent_id
        LEFT JOIN partner_location_nodes p2 ON p2.id = p1.parent_id
        WHERE cpl.channel_partner_id IN ($cpIdList)
        GROUP BY cpl.channel_partner_id
    ");
    while ($row = $resDiv->fetch_assoc()) {
        $cpDivisionNames[(int)$row['cp_id']] = $row['division_names'];
    }
}

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('CP Invoices');

$colLetter = function (int $idx): string { return Coordinate::stringFromColumnIndex($idx + 1); };

$fixedHeaders = ['Invoice #', 'Type', 'Channel Partner', 'CP Code', 'District', 'Division', 'Source Godown', 'Invoice Date', 'Amount'];
$productHeaders = array_map(function ($pid) use ($productNames) { return $productNames[$pid]; }, $prids);
$headers = array_merge($fixedHeaders, $productHeaders, ['Created At']);
$lastColIdx = count($headers) - 1;
foreach ($headers as $i => $h) { $sheet->setCellValue($colLetter($i) . '1', $h); }
$lastCol = $colLetter($lastColIdx);
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
]);
// Product-qty columns get their own yellow header, same convention as the OT
// Sales export (export-ot-sale-view-xlsx.php) — visually separates the
// per-product qty block from the fixed invoice-detail columns around it.
if ($prids) {
    $prodHeaderRange = $colLetter(count($fixedHeaders)) . '1:' . $colLetter(count($fixedHeaders) + count($prids) - 1) . '1';
    $sheet->getStyle($prodHeaderRange)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFF00']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    ]);
}

$row = 2;
$productTotals = array_fill_keys($prids, 0);
$amountTotal = 0.0;
foreach ($invoices as $inv) {
    $typeLabel = tpProductTypeLabel(tpResolveProductType($inv['product_type'] ?? null));
    $sheet->setCellValue("A{$row}", $inv['invoice_number']);
    $sheet->setCellValue("B{$row}", $typeLabel);
    $sheet->setCellValue("C{$row}", $inv['cp_name']);
    $sheet->setCellValue("D{$row}", $inv['cp_code']);
    $sheet->setCellValue("E{$row}", $inv['cp_district'] ?: '—');
    $sheet->setCellValue("F{$row}", $cpDivisionNames[(int)$inv['channel_partner_id']] ?? '—');
    $sheet->setCellValue("G{$row}", $inv['godown_name'] ?: '—');
    $sheet->setCellValue("H{$row}", date('d-m-Y', strtotime($inv['invoice_date'])));
    $sheet->setCellValue("I{$row}", (float)$inv['total_amount']);
    $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    $amountTotal += (float)$inv['total_amount'];

    $invQtys = $qtyByInvNumberProduct[$inv['invoice_number']] ?? [];
    $col = count($fixedHeaders);
    foreach ($prids as $pid) {
        $qty = $invQtys[$pid] ?? 0;
        if ($qty > 0) {
            $sheet->setCellValue($colLetter($col) . $row, $qty);
            $productTotals[$pid] += $qty;
        }
        $col++;
    }
    $sheet->setCellValue($colLetter($col) . $row, $inv['created_at'] ? date('d-m-Y h:i A', strtotime($inv['created_at'])) : '');
    $row++;
}
$lastRow = max($row - 1, 1);

// TOTAL row — per-product qty sums, plus the invoice-amount sum.
$sheet->setCellValue('H' . $row, 'TOTAL');
$sheet->setCellValue('I' . $row, $amountTotal);
$sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
$col = count($fixedHeaders);
foreach ($prids as $pid) {
    $sheet->setCellValue($colLetter($col) . $row, $productTotals[$pid]);
    $col++;
}
$totalRange = 'A' . $row . ':' . $lastCol . $row;
$sheet->getStyle($totalRange)->getFont()->setBold(true);
$sheet->getStyle($totalRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF92D050');
$lastRow = $row;

$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(14);
$sheet->getColumnDimension('C')->setWidth(26);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(18);
$sheet->getColumnDimension('H')->setWidth(14);
$sheet->getColumnDimension('I')->setWidth(14);
for ($i = count($fixedHeaders); $i < count($fixedHeaders) + count($prids); $i++) {
    $sheet->getColumnDimension($colLetter($i))->setWidth(14);
}
$sheet->getColumnDimension($lastCol)->setWidth(18);
$sheet->getStyle('A1:' . $lastCol . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:' . $lastCol . $lastRow);

$filename = 'CP_Invoices_' . date('Y-m-d') . '.xlsx';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;

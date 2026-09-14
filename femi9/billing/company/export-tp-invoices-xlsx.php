<?php
/**
 * Exports the TP Invoices list (manage-tp-invoices.php) for whatever
 * filters are currently applied — same state/location(s)/TP/date-range/type
 * filters, same rows, so what's on screen is exactly what lands in the
 * sheet. Mirrors the filter-building logic there rather than sharing a
 * function, matching this codebase's existing export-file pattern (see
 * reward_points_tp_export_xlsx.php).
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

require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/GodownAccess.php");
require_once("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

// ── Filters — identical to manage-tp-invoices.php ───────────────────────────
$filter_state_id     = (int)($_GET['state_id']    ?? 0);
$filter_location_ids = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['location_id'] ?? [])))));
$filter_tp_id        = (int)($_GET['tp_id']       ?? 0);
$filter_date_from    = trim($_GET['date_from'] ?? '');
$filter_date_to      = trim($_GET['date_to']   ?? '');
$filter_type         = $_GET['type_filter'] ?? '';
if (!in_array($filter_type, ['napkin', 'diaper'], true)) $filter_type = '';

$where  = ['(tpi.source_cp_id > 0 OR tpi.source_godown_id > 0)'];
$params = [];
$types  = '';

if (!empty($filter_location_ids)) {
    $placeholders = implode(',', array_fill(0, count($filter_location_ids), '?'));
    $where[]  = "tpi.source_location_id IN ($placeholders)";
    foreach ($filter_location_ids as $lid) { $params[] = $lid; $types .= 'i'; }
} elseif ($filter_state_id > 0) {
    $where[]  = "(pln.id IS NOT NULL AND (pln.id = ? OR pln.parent_id = ?))";
    $params[] = $filter_state_id;
    $params[] = $filter_state_id;
    $types   .= 'ii';
}

if ($filter_tp_id > 0) {
    $where[]  = "tpi.territory_partner_id = ?";
    $params[] = $filter_tp_id;
    $types   .= 'i';
}

if ($filter_date_from !== '') {
    $where[]  = "tpi.invoice_date >= ?";
    $params[] = $filter_date_from;
    $types   .= 's';
}
if ($filter_date_to !== '') {
    $where[]  = "tpi.invoice_date <= ?";
    $params[] = $filter_date_to;
    $types   .= 's';
}

if ($filter_type !== '') {
    $where[]  = "tpi.product_type = ?";
    $params[] = $filter_type;
    $types   .= 's';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT tpi.id, tpi.invoice_number, tpi.invoice_date, tpi.total_amount,
           tpi.rwpoints_enable, tpi.product_type,
           COALESCE(tpi.courier_charges, 0) AS courier_charges,
           tpi.created_by, tpi.created_at,
           tp.name AS tp_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile,
           COALESCE(cp_src.name, gd.gname, pln.name) AS source_location,
           COALESCE(cp_src.name, cp_old.name) AS cp_name,
           COALESCE(cp_src.cp_id, cp_old.cp_id) AS cp_code,
           COALESCE(rcpt.collected, 0) AS courier_collected
    FROM tp_invoices tpi
    JOIN territory_partners tp             ON tp.id  = tpi.territory_partner_id
    LEFT JOIN partner_location_nodes pln   ON pln.id = tpi.source_location_id
    LEFT JOIN channel_partner_locations cpl ON cpl.location_id = tpi.source_location_id
    LEFT JOIN channel_partners cp_old      ON cp_old.id = cpl.channel_partner_id
    LEFT JOIN channel_partners cp_src      ON cp_src.id = tpi.source_cp_id
    LEFT JOIN company_godown gd            ON gd.id = tpi.source_godown_id AND (" . godown_finance_filter_sql($db_conn, 'gd') . ")
    LEFT JOIN (
        SELECT tp_invoice_id, SUM(amount) AS collected
        FROM tp_invoice_receipts
        GROUP BY tp_invoice_id
    ) rcpt ON rcpt.tp_invoice_id = tpi.id
    $where_sql
    ORDER BY tpi.invoice_date ASC, tpi.created_at ASC
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

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('TP Invoices');

$headers = [
    'A' => 'S.No',
    'B' => 'Invoice #',
    'C' => 'Type',
    'D' => 'Territory Partner',
    'E' => 'TP Code',
    'F' => 'TP Mobile',
    'G' => 'Source Location',
    'H' => 'Channel Partner',
    'I' => 'CP Code',
    'J' => 'Date',
    'K' => 'Amount (excl. courier)',
    'L' => 'Courier Charges',
    'M' => 'Total Amount',
    'N' => 'Courier Collected',
    'O' => 'Payment Status',
    'P' => 'Created By',
    'Q' => 'Invoiced Time',
    'R' => 'Reward Points',
];
$lastCol = 'R';

// ── Title ──
$dateLabel = ($filter_date_from !== '' || $filter_date_to !== '')
    ? (($filter_date_from !== '' ? date('d M Y', strtotime($filter_date_from)) : 'Start') . ' - ' . ($filter_date_to !== '' ? date('d M Y', strtotime($filter_date_to)) : 'Today'))
    : 'All Dates';
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'TP Invoices | ' . $dateLabel);
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

// ── Headers ──
foreach ($headers as $col => $label) {
    $sheet->setCellValue("{$col}2", $label);
}
$sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

// ── Data ──
$rowNum = 3;
$numericCols = ['K', 'L', 'M', 'N'];

foreach ($invoices as $i => $inv) {
    $sn = $i + 1;
    $courier   = (float)$inv['courier_charges'];
    $subtotal  = round((float)$inv['total_amount'] - $courier, 2);
    $collected = (float)$inv['courier_collected'];

    if ($courier < 0.01) {
        $payStatus = 'N/A';
    } elseif ($collected <= 0) {
        $payStatus = 'Not Collected';
    } elseif ($collected < $courier - 0.01) {
        $payStatus = 'Partial';
    } else {
        $payStatus = 'Collected';
    }

    $sheet->setCellValue("A{$rowNum}", $sn);
    $sheet->setCellValue("B{$rowNum}", $inv['invoice_number']);
    $sheet->setCellValue("C{$rowNum}", tpProductTypeLabel(tpResolveProductType($inv['product_type'] ?? null)));
    $sheet->setCellValue("D{$rowNum}", $inv['tp_name']);
    $sheet->setCellValue("E{$rowNum}", $inv['tp_code']);
    $sheet->setCellValue("F{$rowNum}", $inv['tp_mobile']);
    $sheet->setCellValue("G{$rowNum}", $inv['source_location']);
    $sheet->setCellValue("H{$rowNum}", $inv['cp_name'] ?: '-');
    $sheet->setCellValue("I{$rowNum}", $inv['cp_code'] ?: '-');
    $sheet->setCellValue("J{$rowNum}", date('d-m-Y', strtotime($inv['invoice_date'])));
    $sheet->setCellValue("K{$rowNum}", $subtotal);
    $sheet->setCellValue("L{$rowNum}", $courier);
    $sheet->setCellValue("M{$rowNum}", (float)$inv['total_amount']);
    $sheet->setCellValue("N{$rowNum}", $collected);
    $sheet->setCellValue("O{$rowNum}", $payStatus);
    $sheet->setCellValue("P{$rowNum}", $inv['created_by']);
    $sheet->setCellValue("Q{$rowNum}", !empty($inv['created_at']) ? date('d-m-Y h:i A', strtotime($inv['created_at'])) : '-');
    $sheet->setCellValue("R{$rowNum}", (int)$inv['rwpoints_enable'] === 1 ? 'Enabled' : 'Disabled');

    $bgColor = ($sn % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);

    foreach ($numericCols as $nc) {
        $sheet->getStyle("{$nc}{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("{$nc}{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    $rowNum++;
}

// ── Totals row ──
$totalsRow = $rowNum;
$dataStart = 3;
$dataEnd   = $rowNum - 1;

$sheet->setCellValue("A{$totalsRow}", 'TOTAL');
$sheet->mergeCells("A{$totalsRow}:J{$totalsRow}");

if ($dataEnd >= $dataStart) {
    foreach (['K', 'L', 'M', 'N'] as $nc) {
        $sheet->setCellValue("{$nc}{$totalsRow}", "=SUM({$nc}{$dataStart}:{$nc}{$dataEnd})");
    }
}
foreach ($numericCols as $nc) {
    $sheet->getStyle("{$nc}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("{$nc}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
$sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

// ── Column widths ──
$sheet->getColumnDimension('A')->setWidth(7);
$sheet->getColumnDimension('B')->setWidth(16);
$sheet->getColumnDimension('C')->setWidth(10);
$sheet->getColumnDimension('D')->setWidth(22);
$sheet->getColumnDimension('E')->setWidth(10);
$sheet->getColumnDimension('F')->setWidth(14);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(20);
$sheet->getColumnDimension('I')->setWidth(10);
$sheet->getColumnDimension('J')->setWidth(13);
foreach (['K', 'L', 'M', 'N'] as $nc) $sheet->getColumnDimension($nc)->setWidth(16);
$sheet->getColumnDimension('O')->setWidth(15);
$sheet->getColumnDimension('P')->setWidth(14);
$sheet->getColumnDimension('Q')->setWidth(18);
$sheet->getColumnDimension('R')->setWidth(13);

// ── Freeze + filter ──
$sheet->freezePane('A3');
$sheet->setAutoFilter("A2:{$lastCol}2");

// ── Output ──
$fnDateSuffix = ($filter_date_from !== '' || $filter_date_to !== '')
    ? '_' . ($filter_date_from ?: 'start') . '_to_' . ($filter_date_to ?: 'today')
    : '_all';
$filename = "tp_invoices{$fnDateSuffix}.xlsx";

if (ob_get_length()) { ob_end_clean(); }

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;

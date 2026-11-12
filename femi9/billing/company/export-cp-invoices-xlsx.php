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
           cpi.created_by, cpi.created_at,
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

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('CP Invoices');

$headers = ['Invoice #', 'Type', 'Channel Partner', 'CP Code', 'District', 'Source Godown', 'Invoice Date', 'Amount', 'Created By', 'Created At'];
foreach ($headers as $i => $h) { $sheet->setCellValue(chr(ord('A') + $i) . '1', $h); }
$lastCol = chr(ord('A') + count($headers) - 1);
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$row = 2;
foreach ($invoices as $inv) {
    $typeLabel = tpProductTypeLabel(tpResolveProductType($inv['product_type'] ?? null));
    $sheet->setCellValue("A{$row}", $inv['invoice_number']);
    $sheet->setCellValue("B{$row}", $typeLabel);
    $sheet->setCellValue("C{$row}", $inv['cp_name']);
    $sheet->setCellValue("D{$row}", $inv['cp_code']);
    $sheet->setCellValue("E{$row}", $inv['cp_district'] ?: '—');
    $sheet->setCellValue("F{$row}", $inv['godown_name'] ?: '—');
    $sheet->setCellValue("G{$row}", date('d-m-Y', strtotime($inv['invoice_date'])));
    $sheet->setCellValue("H{$row}", (float)$inv['total_amount']);
    $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->setCellValue("I{$row}", $inv['created_by']);
    $sheet->setCellValue("J{$row}", $inv['created_at'] ? date('d-m-Y h:i A', strtotime($inv['created_at'])) : '');
    $row++;
}
$lastRow = max($row - 1, 1);

$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(14);
$sheet->getColumnDimension('C')->setWidth(26);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(14);
$sheet->getColumnDimension('H')->setWidth(14);
$sheet->getColumnDimension('I')->setWidth(18);
$sheet->getColumnDimension('J')->setWidth(18);
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

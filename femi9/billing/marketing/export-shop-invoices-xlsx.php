<?php
// Exports the dashboard's "Shop Invoices" list (date-filtered TP->shop
// invoices within the logged-in marketing staff member's assigned district)
// as an xlsx — same query shape as dashboard.php, minus client-side
// search/pagination, so every matching row lands in the sheet. Mirrors
// salesbdm/export-unassigned-firkas-xlsx.php's pattern.

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

require_once __DIR__ . "/checksession.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/include/AssignedLocations.php";
require_once __DIR__ . "/include/MsDistrictScope.php";

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

$msId          = (int)$Login_user_IDvl;
$districtNames = getMsAssignedDistrictNames($db_conn, $msId);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-d'); }

$allTpIds = getMsDistrictTpIds($db_conn, $districtNames, true);

$rows = [];
if (!empty($allTpIds)) {
    $idList = implode(',', $allTpIds);
    $stmt = $db_conn->prepare(
        "SELECT ui.inv_number, ui.date, ui.total,
                tp.name AS tp_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile,
                COALESCE((
                    SELECT SUM(pln.target_amount) FROM territory_partner_locations tpl
                    JOIN partner_location_nodes pln ON pln.id = tpl.location_id
                    WHERE tpl.territory_partner_id = tp.id
                ), 0) AS tp_target
         FROM user_invoice ui
         JOIN territory_partners tp ON tp.id = ui.from_user_id
         WHERE ui.from_user_type='territory_partner' AND ui.from_user_id IN ($idList)
           AND ui.`date` BETWEEN ? AND ?
         ORDER BY ui.date DESC, ui.inv_number DESC"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Shop Invoices');

$headers = [
    'A' => 'TP Name',
    'B' => 'TP ID',
    'C' => 'Phone',
    'D' => 'Target Amount',
    'E' => 'Invoice Number',
    'F' => 'Invoice Date',
    'G' => 'Invoice Amount',
];
$lastCol = 'G';

$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'Shop Invoices | ' . $from . ' to ' . $to);
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

foreach ($headers as $col => $label) {
    $sheet->setCellValue("{$col}2", $label);
}
$sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$rowNum = 3;
foreach ($rows as $r) {
    $sheet->setCellValue("A{$rowNum}", ucwords(strtolower($r['tp_name'])));
    $sheet->setCellValue("B{$rowNum}", $r['tp_code']);
    $sheet->setCellValue("C{$rowNum}", $r['tp_mobile']);
    $sheet->setCellValue("D{$rowNum}", (float)$r['tp_target']);
    $sheet->setCellValue("E{$rowNum}", $r['inv_number']);
    $sheet->setCellValue("F{$rowNum}", date('d-m-Y', strtotime($r['date'])));
    $sheet->setCellValue("G{$rowNum}", (float)$r['total']);

    $bgColor = (($rowNum - 2) % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);
    $sheet->getStyle("D{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("G{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("D{$rowNum}:D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("G{$rowNum}:G{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $rowNum++;
}

$totalsRow = $rowNum;
$dataStart = 3;
$dataEnd   = $rowNum - 1;

$sheet->setCellValue("A{$totalsRow}", 'TOTAL');
$sheet->mergeCells("A{$totalsRow}:F{$totalsRow}");
if ($dataEnd >= $dataStart) {
    $sheet->setCellValue("G{$totalsRow}", "=SUM(G{$dataStart}:G{$dataEnd})");
}
$sheet->getStyle("G{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("G{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$sheet->getColumnDimension('A')->setWidth(22);
$sheet->getColumnDimension('B')->setWidth(12);
$sheet->getColumnDimension('C')->setWidth(14);
$sheet->getColumnDimension('D')->setWidth(16);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(14);
$sheet->getColumnDimension('G')->setWidth(16);

$sheet->freezePane('A3');
$sheet->setAutoFilter("A2:{$lastCol}2");

$filename = 'shop_invoices_' . $from . '_to_' . $to . '.xlsx';

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

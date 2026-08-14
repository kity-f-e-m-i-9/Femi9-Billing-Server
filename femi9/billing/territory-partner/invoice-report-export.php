<?php
ob_start();
error_reporting(0);

include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/invoice-report-data.php';

date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function xlsx_set(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colIndex, int $row, $value): void {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $row, $value);
}

$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-01');
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-t');

$typeFilter = $_GET['type_filter'] ?? 'all';
if (!in_array($typeFilter, ['all', 'shop', 'customer'], true)) $typeFilter = 'all';

$statusFilter = $_GET['status_filter'] ?? 'all';
if (!in_array($statusFilter, ['all', 'not_paid', 'partially_paid', 'fully_paid'], true)) $statusFilter = 'all';

$searchTerm = trim($_GET['q'] ?? '');

$uid   = (int)$Login_user_IDvl;
$utype = $Login_user_TYPEvl;

$report = tp_invoice_report_fetch($db_conn, $uid, $utype, $from, $to, $typeFilter, $statusFilter, $searchTerm);
$rows   = $report['rows'];

ob_end_clean();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Invoice Report');

$sheet->setCellValue('A1', 'Invoice Report');
$sheet->mergeCells('A1:J1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);

$sheet->setCellValue('A2', 'Period: ' . date('d-M-Y', strtotime($from)) . ' to ' . date('d-M-Y', strtotime($to)));
$sheet->mergeCells('A2:J2');
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

$headerRow = 4;
$columns = ['S.No', 'Date', 'Invoice No.', 'Type', 'Party Name', 'Mobile', 'Billed', 'Received', 'Due', 'Status'];
$colIndex = 1;
foreach ($columns as $title) {
    xlsx_set($sheet, $colIndex, $headerRow, $title);
    $colIndex++;
}
$lastCol = $colIndex - 1;
$headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4338CA');
$sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$statusLabels = ['fully_paid' => 'Fully Paid', 'partially_paid' => 'Partially Paid', 'not_paid' => 'Not Paid'];

$row = $headerRow + 1;
$serial = 1;
$grand_total = 0; $grand_received = 0; $grand_due = 0;
foreach ($rows as $inv) {
    xlsx_set($sheet, 1, $row, $serial++);
    xlsx_set($sheet, 2, $row, date('d-m-Y', strtotime($inv['date'])));
    xlsx_set($sheet, 3, $row, $inv['inv_no']);
    xlsx_set($sheet, 4, $row, $inv['kind'] === 'shop' ? 'Shop' : 'Customer');
    xlsx_set($sheet, 5, $row, ucwords($inv['party']));
    xlsx_set($sheet, 6, $row, $inv['mobile']);
    xlsx_set($sheet, 7, $row, $inv['total']);
    xlsx_set($sheet, 8, $row, $inv['received']);
    xlsx_set($sheet, 9, $row, $inv['due']);
    xlsx_set($sheet, 10, $row, $statusLabels[$inv['status']] ?? $inv['status']);

    $grand_total    += $inv['total'];
    $grand_received += $inv['received'];
    $grand_due      += $inv['due'];
    $row++;
}

if ($row > $headerRow + 1) {
    $dataRange = Coordinate::stringFromColumnIndex(1) . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('G' . ($headerRow + 1) . ':I' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
}

xlsx_set($sheet, 5, $row, 'GRAND TOTAL');
xlsx_set($sheet, 7, $row, $grand_total);
xlsx_set($sheet, 8, $row, $grand_received);
xlsx_set($sheet, 9, $row, $grand_due);
$totalsRange = Coordinate::stringFromColumnIndex(1) . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row;
$sheet->getStyle($totalsRange)->getFont()->setBold(true);
$sheet->getStyle($totalsRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E6F7F5');
$sheet->getStyle('G' . $row . ':I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

foreach (range('A', 'J') as $letter) {
    $sheet->getColumnDimension($letter)->setAutoSize(true);
}

$filename = "Invoice_Report_{$from}_to_{$to}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

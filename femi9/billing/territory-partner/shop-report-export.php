<?php
ob_start();
error_reporting(0);

include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/shop-report-data.php';

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

$preset = $_GET['preset'] ?? 'month';
$today  = date('Y-m-d');
switch ($preset) {
    case 'today': $default_from = $today; $default_to = $today; break;
    case 'week':
        $default_from = date('Y-m-d', strtotime('monday this week'));
        $default_to   = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year': $default_from = date('Y-01-01'); $default_to = date('Y-12-31'); break;
    case 'all':  $default_from = '2000-01-01'; $default_to = $today; break;
    default:     $default_from = date('Y-m-01'); $default_to = date('Y-m-t');
}

$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : $default_from;
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : $default_to;

$statusFilter = $_GET['status_filter'] ?? 'all';
if (!in_array($statusFilter, ['all', 'not_paid', 'partially_paid', 'fully_paid'], true)) $statusFilter = 'all';

$searchTerm = trim($_GET['q'] ?? '');

$uid   = (int)$Login_user_IDvl;
$utype = $Login_user_TYPEvl;

$report = tp_shop_report_fetch($db_conn, $uid, $utype, $from, $to, $statusFilter, $searchTerm, true);
$shops  = $report['rows'];
$invoicesByShop = $report['invoices_by_shop'];

ob_end_clean();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Shop Report');

$sheet->setCellValue('A1', 'Shop Report — Sales & Payments');
$sheet->mergeCells('A1:L1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);

$sheet->setCellValue('A2', 'Period: ' . date('d-M-Y', strtotime($from)) . ' to ' . date('d-M-Y', strtotime($to)));
$sheet->mergeCells('A2:L2');
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

$headerRow = 4;
$columns = ['S.No', 'Shop Name', 'Shop ID', 'Mobile', 'Category', 'GST Number', 'Address', 'Invoices', 'Billed', 'Received', 'Outstanding Due', 'Status'];
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

$statusLabels = [
    'fully_paid'     => 'Fully Paid',
    'partially_paid' => 'Partially Paid',
    'not_paid'       => 'Not Paid',
    'no_invoices'    => 'No Invoices',
];

// Invoice sub-header, written once inside each shop's invoice block.
$invColumns = ['', '  ↳ Invoice No.', 'Date', '', '', '', '', '', 'Billed', 'Received', 'Due', 'Status'];

$row = $headerRow + 1;
$serial = 1;
$grand_billed = 0; $grand_received = 0; $grand_due = 0; $grand_invoices = 0;
$numberRanges = []; // [row1, row2] pairs needing #,##0.00 on I:K
foreach ($shops as $s) {
    $shopRow = $row;
    xlsx_set($sheet, 1, $row, $serial++);
    xlsx_set($sheet, 2, $row, ucwords($s['name']));
    xlsx_set($sheet, 3, $row, $s['useridtext']);
    xlsx_set($sheet, 4, $row, trim(($s['country_code'] ?? '') . ' ' . ($s['mobile_number'] ?? '')));
    xlsx_set($sheet, 5, $row, $s['catlable'] ?? '');
    xlsx_set($sheet, 6, $row, $s['gstin'] ?? '');
    xlsx_set($sheet, 7, $row, $s['address'] ?? '');
    xlsx_set($sheet, 8, $row, (int)$s['inv_count']);
    xlsx_set($sheet, 9, $row, $s['billed']);
    xlsx_set($sheet, 10, $row, $s['received']);
    xlsx_set($sheet, 11, $row, $s['due']);
    xlsx_set($sheet, 12, $row, $statusLabels[$s['status']] ?? $s['status']);
    $shopSummaryRange = Coordinate::stringFromColumnIndex(1) . $shopRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $shopRow;
    $sheet->getStyle($shopSummaryRange)->getFont()->setBold(true);
    $sheet->getStyle($shopSummaryRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF0FD');

    $grand_billed   += $s['billed'];
    $grand_received += $s['received'];
    $grand_due      += $s['due'];
    $grand_invoices += (int)$s['inv_count'];
    $numberRanges[] = [$row, $row];
    $row++;

    // Invoice-wise splitup for this shop -------------------------------
    $shopInvoices = $invoicesByShop[$s['temp_id']] ?? [];
    if (!empty($shopInvoices)) {
        $subHeaderRow = $row;
        $c = 1;
        foreach ($invColumns as $title) {
            if ($title !== '') xlsx_set($sheet, $c, $subHeaderRow, $title);
            $c++;
        }
        $subHeaderRange = 'B' . $subHeaderRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $subHeaderRow;
        $sheet->getStyle($subHeaderRange)->getFont()->setBold(true)->setItalic(true)->setSize(10.5);
        $sheet->getStyle($subHeaderRange)->getFont()->getColor()->setRGB('5C6072');
        $row++;

        foreach ($shopInvoices as $inv) {
            xlsx_set($sheet, 2, $row, $inv['inv_no']);
            xlsx_set($sheet, 3, $row, date('d-m-Y', strtotime($inv['date'])));
            xlsx_set($sheet, 9, $row, $inv['billed']);
            xlsx_set($sheet, 10, $row, $inv['received']);
            xlsx_set($sheet, 11, $row, $inv['due']);
            xlsx_set($sheet, 12, $row, $statusLabels[$inv['status']] ?? $inv['status']);
            $sheet->getStyle('B' . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row)->getFont()->setSize(10.5);
            $sheet->getStyle('B' . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row)->getFont()->getColor()->setRGB('5C6072');
            $numberRanges[] = [$row, $row];
            $row++;
        }
    }
}

if ($row > $headerRow + 1) {
    $dataRange = Coordinate::stringFromColumnIndex(1) . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    foreach ($numberRanges as [$r1, $r2]) {
        $sheet->getStyle("I{$r1}:K{$r2}")->getNumberFormat()->setFormatCode('#,##0.00');
    }
}

xlsx_set($sheet, 2, $row, 'GRAND TOTAL');
xlsx_set($sheet, 8, $row, $grand_invoices);
xlsx_set($sheet, 9, $row, $grand_billed);
xlsx_set($sheet, 10, $row, $grand_received);
xlsx_set($sheet, 11, $row, $grand_due);
$totalsRange = Coordinate::stringFromColumnIndex(1) . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row;
$sheet->getStyle($totalsRange)->getFont()->setBold(true);
$sheet->getStyle($totalsRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E6F7F5');
$sheet->getStyle('I' . $row . ':K' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

foreach (range('A', 'L') as $letter) {
    $sheet->getColumnDimension($letter)->setAutoSize(true);
}

$filename = "Shop_Report_{$from}_to_{$to}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

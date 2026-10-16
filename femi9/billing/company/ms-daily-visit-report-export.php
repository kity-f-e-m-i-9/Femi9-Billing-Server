<?php
// Excel export for ms-daily-visit-report.php — laid out to match the
// reference sheet: a merged orange "<Month Year>" title row, then one row
// per shop visit: S.No / Date / Shop Name / District / Taluk / Firka /
// TP Name / Visit Type / Femi9 (Napkin) / Lumi9 (Diaper) / Total Order Value.
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

@include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
@include("config.php");
require_once __DIR__ . '/../shared/MsDailyVisitReport.php';

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

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');
$msFilter = (int) ($_GET['ms_id'] ?? 0);
$districtFilter = (int) ($_GET['district_node_id'] ?? 0);

$rows = getMsDailyVisitRows($db_conn, $from, $to, ['ms_id' => $msFilter, 'district_node_id' => $districtFilter]);
if (empty($_GET['include_no_order'])) {
    $rows = array_values(array_filter($rows, fn($r) => $r['visit_type'] === 'order'));
}

ob_end_clean();

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('MS Daily Visit Report');

    $monthLabel = date('F Y', strtotime($from));
    $sheet->setCellValue('A1', $monthLabel);
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC000');

    $headerRow = 2;
    $columns = [
        'S. No', 'Date', 'Shop Name', 'District', 'Taluk', 'Firka', 'TP Name', 'Visit Type',
        'Femi9 (Napkin) Order Value', 'Lumi9 (Diaper) Order Value', 'Total Order Value',
    ];
    $col = 1;
    foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
    $lastCol = $col - 1;
    $headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

    $row = $headerRow + 1;
    $i = 1;
    foreach ($rows as $r) {
        xlsx_set($sheet, 1, $row, $i++);
        xlsx_set($sheet, 2, $row, date('d-m-Y', strtotime($r['date'])));
        xlsx_set($sheet, 3, $row, $r['shop_name']);
        xlsx_set($sheet, 4, $row, $r['district']);
        xlsx_set($sheet, 5, $row, $r['taluk']);
        xlsx_set($sheet, 6, $row, $r['firka']);
        xlsx_set($sheet, 7, $row, $r['tp_name']);
        xlsx_set($sheet, 8, $row, $r['is_new'] ? 'New' : 'Existing');
        xlsx_set($sheet, 9, $row, round($r['napkin_value'], 2));
        xlsx_set($sheet, 10, $row, round($r['diaper_value'], 2));
        xlsx_set($sheet, 11, $row, round($r['total_value'], 2));
        if ($r['tp_name'] === 'Vacant') {
            $sheet->getStyle('G' . $row)->getFont()->getColor()->setRGB('B91C1C');
            $sheet->getStyle('G' . $row)->getFont()->setBold(true);
        } elseif ($r['tp_name'] === 'Not Captured') {
            $sheet->getStyle('G' . $row)->getFont()->getColor()->setRGB('92400E');
            $sheet->getStyle('G' . $row)->getFont()->setBold(true);
        }
        $row++;
    }

    if ($row > $headerRow + 1) {
        $dataRange = Coordinate::stringFromColumnIndex(1) . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('I' . ($headerRow + 1) . ':K' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
    }

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet->freezePane('A' . ($headerRow + 1));

    $filename = "MS_Daily_Visit_Report_{$from}_to_{$to}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save('php://output');
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error generating Excel file: " . $e->getMessage();
    error_log("Excel generation error: " . $e->getMessage());
}

exit;

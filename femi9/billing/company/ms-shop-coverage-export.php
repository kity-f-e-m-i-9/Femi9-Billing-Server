<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

@include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
@include("config.php");
require_once("include/MsShopCoverage.php");

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

$fromDate = $_GET['from_date'] ?? '';
$toDate   = $_GET['to_date'] ?? '';
$fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : date('Y-m-01');
$toDate   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)   ? $toDate   : date('Y-m-t');

$data = getMsShopCoverageReport($db_conn, $fromDate, $toDate);
$byId      = $data['byId'];
$byManager = $data['byManager'];
$rawStats  = $data['rawStats'];
$districtsByMs = getMsDistrictMap($db_conn);
$memo = [];

function msExportDistrict(int $id, array $byManager, array $districtsByMs): string {
    $district = $districtsByMs[$id] ?? '';
    if ($district !== '' || empty($byManager[$id])) { return $district; }
    $teamDistricts = [];
    $queue = $byManager[$id];
    while ($child = array_shift($queue)) {
        $cId = (int)$child['id'];
        if (!empty($districtsByMs[$cId])) {
            foreach (explode(', ', $districtsByMs[$cId]) as $d) { $teamDistricts[$d] = true; }
        }
        foreach (($byManager[$cId] ?? []) as $grandchild) { $queue[] = $grandchild; }
    }
    return implode(', ', array_keys($teamDistricts));
}

$byRank = [];
foreach ($byId as $id => $row) {
    if ($row['level_rank'] !== null) { $byRank[(int)$row['level_rank']][] = $row; }
}
ksort($byRank);

$unassigned = [];
foreach ($byId as $id => $row) {
    if ($row['level_rank'] === null && (($rawStats[$id]['shops'] ?? 0) > 0)) { $unassigned[] = $row; }
}

ob_end_clean();

try {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);
    $sheetIndex = 0;

    $writeTierSheet = function (string $title, array $rows) use ($spreadsheet, &$sheetIndex, $byManager, $rawStats, $byId, $districtsByMs, &$memo, $fromDate, $toDate) {
        $sheet = $spreadsheet->createSheet($sheetIndex++);
        $sheet->setTitle(substr($title, 0, 31));

        $sheet->setCellValue('A1', $title . ' — New Shop Coverage (' . $fromDate . ' to ' . $toDate . ')');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headerRow = 3;
        $columns = ['Name', 'Reports To', 'District', 'New Shops Added', 'Shops With First Order', 'Coverage %', 'Get Order (New Shops)', 'Get Order Value (New Shops)', 'Invoiced Value'];
        $col = 1;
        foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
        $lastCol = $col - 1;
        $headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = $headerRow + 1;
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $sum = msCoverageSubtreeSum($id, $byManager, $rawStats, $memo);
            $pct = msCoveragePercent($sum);
            $mgrId = $r['manager_id'] ? (int)$r['manager_id'] : 0;
            $mgrName = $mgrId && isset($byId[$mgrId]) ? $byId[$mgrId]['ms_name'] : '-';
            $district = msExportDistrict($id, $byManager, $districtsByMs);

            xlsx_set($sheet, 1, $row, $r['ms_name']);
            xlsx_set($sheet, 2, $row, $mgrName);
            xlsx_set($sheet, 3, $row, $district);
            xlsx_set($sheet, 4, $row, $sum['shops']);
            xlsx_set($sheet, 5, $row, $sum['ordered']);
            xlsx_set($sheet, 6, $row, $pct . '%');
            xlsx_set($sheet, 7, $row, $sum['new_shop_orders']);
            xlsx_set($sheet, 8, $row, round($sum['new_shop_order_value'], 2));
            xlsx_set($sheet, 9, $row, round($sum['invoiced_value'], 2));
            $row++;
        }

        if ($row > $headerRow + 1) {
            $dataRange = Coordinate::stringFromColumnIndex(1) . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        foreach (range(1, $lastCol) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
    };

    foreach ($byRank as $rank => $rows) {
        $tierLabel = $rows[0]['level_name'] ?: ('Level ' . $rank);
        $writeTierSheet($tierLabel . ' Wise', $rows);
    }

    if (!empty($unassigned)) {
        $sheet = $spreadsheet->createSheet($sheetIndex++);
        $sheet->setTitle('Unassigned Staff');
        $sheet->setCellValue('A1', 'Unassigned Staff (no team level) — ' . $fromDate . ' to ' . $toDate);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $headerRow = 3;
        $columns = ['Name', 'New Shops Added', 'Shops With First Order', 'Coverage %', 'Get Order (New Shops)', 'Get Order Value (New Shops)', 'Invoiced Value'];
        $col = 1;
        foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
        $lastCol = $col - 1;
        $headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $row = $headerRow + 1;
        foreach ($unassigned as $r) {
            $id = (int)$r['id'];
            $sum = $rawStats[$id] ?? ['shops' => 0, 'ordered' => 0, 'invoiced_value' => 0.0, 'new_shop_orders' => 0, 'new_shop_order_value' => 0.0];
            $pct = msCoveragePercent($sum);
            xlsx_set($sheet, 1, $row, $r['ms_name']);
            xlsx_set($sheet, 2, $row, $sum['shops']);
            xlsx_set($sheet, 3, $row, $sum['ordered']);
            xlsx_set($sheet, 4, $row, $pct . '%');
            xlsx_set($sheet, 5, $row, $sum['new_shop_orders']);
            xlsx_set($sheet, 6, $row, round($sum['new_shop_order_value'], 2));
            xlsx_set($sheet, 7, $row, round($sum['invoiced_value'], 2));
            $row++;
        }
        foreach (range(1, $lastCol) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
    }

    $spreadsheet->setActiveSheetIndex(0);

    $filename = "New_Shop_Coverage_{$fromDate}_to_{$toDate}.xlsx";
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

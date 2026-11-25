<?php
// Excel export for marketing/kpi.php — one row per staff member, same
// subtree/date-range scoping and the exact same per-row numbers (via
// computeKpiRowValues(), shared with kpi.php through
// include/KpiDataPrep.php) so this can never silently disagree with what
// the page itself shows. 2026-11-21, user request.
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/KpiDataPrep.php';

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;
require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

if (!$selfRow || !$selfRow['team_level_id']) {
    die("No team level has been assigned to you yet.");
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('KPI Scorecard');

$headers = [
    'A' => 'Name', 'B' => 'Level',
    'C' => 'Napkin Sales Plan', 'D' => 'Napkin Sales Ach', 'E' => 'Napkin Sales Ach%', 'F' => 'Napkin Sales Score (/15)',
    'G' => 'District Overach Ach%', 'H' => 'District Overach vs Top', 'I' => 'District Overach Score (/10)',
    'J' => 'Weekly Sales Score (/5)',
    'K' => 'Early Month Plan', 'L' => 'Early Month Ach', 'M' => 'Early Month Score (/5)',
    'N' => 'Daily Market Plan', 'O' => 'Daily Market Ach', 'P' => 'Daily Market Score (/10)',
    'Q' => 'New Shop Min', 'R' => 'New Shop Ach', 'S' => 'New Shop Score (/5)',
    'T' => 'New Category Min', 'U' => 'New Category Ach', 'V' => 'New Category Score (/5)',
    'W' => 'Merchandising Shops', 'X' => 'Merchandising Compliant', 'Y' => 'Merchandising Score (/5)',
    'Z' => 'Return%', 'AA' => 'Return Penalty',
    'AB' => 'KPI Score (/60)',
];
$lastCol = 'AB';

$titleText = 'KPI Scorecard | ' . $fromDate . ' to ' . $toDate;
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', $titleText);
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
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
]);

// Same recursive walk as kpi.php's renderKpiRow() — just writes a
// spreadsheet row per person from computeKpiRowValues() instead of HTML.
$rowNum = 3;
function writeKpiExportRow(
    $sheet, int &$rowNum, array $row, array $byManager, array $rawStats, array $dailyByMsId, array $levelColorMap,
    int $depth, string $fromDate, int $daysInRange, array $newShopCountByMsId, array $marketWorkDatesByMsId,
    int $workingDaysPlan, array $kx, int $parentId = 0
): void {
    $v = computeKpiRowValues($row, $byManager, $rawStats, $dailyByMsId, $levelColorMap, $depth, $fromDate, $daysInRange, $newShopCountByMsId, $marketWorkDatesByMsId, $workingDaysPlan, $kx, $parentId);
    $indent = str_repeat('    ', $depth);
    $sheet->setCellValue("A{$rowNum}", $indent . $v['name']);
    $sheet->setCellValue("B{$rowNum}", $v['level_name']);
    $sheet->setCellValue("C{$rowNum}", $v['target']);
    $sheet->setCellValue("D{$rowNum}", $v['achieved']);
    $sheet->setCellValue("E{$rowNum}", number_format($v['sales_ach_pct'] * 100, 0) . '%');
    $sheet->setCellValue("F{$rowNum}", round($v['sales_ach_pct_capped'] * 15, 1));
    $sheet->setCellValue("G{$rowNum}", number_format($v['sales_ach_pct'] * 100, 0) . '%');
    $sheet->setCellValue("H{$rowNum}", $v['overach_pct'] > 0 ? (number_format($v['overach_pct'] * 100, 0) . '%') : '-');
    $sheet->setCellValue("I{$rowNum}", round($v['overach_pct'] * 10, 1));
    $sheet->setCellValue("J{$rowNum}", round($v['weekly_ach_pct'] * 5, 1));
    $sheet->setCellValue("K{$rowNum}", 5);
    $sheet->setCellValue("L{$rowNum}", $v['early_month_ach']);
    $sheet->setCellValue("M{$rowNum}", round($v['early_month_ach_pct'] * 5, 1));
    $sheet->setCellValue("N{$rowNum}", $v['working_days_plan']);
    $sheet->setCellValue("O{$rowNum}", $v['market_work_ach']);
    $sheet->setCellValue("P{$rowNum}", round($v['market_work_ach_pct'] * 10, 1));
    $sheet->setCellValue("Q{$rowNum}", $v['new_shop_eligibility']);
    $sheet->setCellValue("R{$rowNum}", $v['new_shop_ach']);
    $sheet->setCellValue("S{$rowNum}", round($v['new_shop_ach_pct'] * 5, 1));
    $sheet->setCellValue("T{$rowNum}", $v['new_category_eligibility']);
    $sheet->setCellValue("U{$rowNum}", $v['category_ach']);
    $sheet->setCellValue("V{$rowNum}", round($v['category_ach_pct'] * 5, 1));
    $sheet->setCellValue("W{$rowNum}", $v['merch_total']);
    $sheet->setCellValue("X{$rowNum}", $v['merch_compliant']);
    $sheet->setCellValue("Y{$rowNum}", round($v['merch_pct'] * 5, 1));
    $sheet->setCellValue("Z{$rowNum}", number_format(max($v['napkin_ret_pct'], $v['diaper_ret_pct']) * 100, 1) . '%');
    $sheet->setCellValue("AA{$rowNum}", $v['return_penalty']);
    $sheet->setCellValue("AB{$rowNum}", round($v['kpi_score_total'], 1));
    $rowNum++;

    foreach ($byManager[$v['id']] ?? [] as $child) {
        writeKpiExportRow($sheet, $rowNum, $child, $byManager, $rawStats, $dailyByMsId, $levelColorMap, $depth + 1, $fromDate, $daysInRange, $newShopCountByMsId, $marketWorkDatesByMsId, $workingDaysPlan, $kx, $v['id']);
    }
}
writeKpiExportRow($sheet, $rowNum, $selfRow, $byManager, $rawStats, $dailyByMsId, $levelColorMap, 0, $fromDate, $daysInRange, $newShopCountByMsId, $marketWorkDatesByMsId, $workingDaysPlan, $kx);

foreach (array_keys($headers) as $col) {
    $sheet->getColumnDimension($col)->setWidth(18);
}
$sheet->getColumnDimension('A')->setWidth(26);

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="kpi-scorecard-' . $fromDate . '-to-' . $toDate . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;

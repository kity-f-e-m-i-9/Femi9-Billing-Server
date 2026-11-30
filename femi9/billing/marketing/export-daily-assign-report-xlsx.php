<?php
// Excel export for marketing's daily-assign-report.php — same ms_id/from/to
// filters, same subtree scoping rule as get-daily-assign-report.php, so the
// download can never contain another team's assignments.
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

require_once __DIR__ . "/checksession.php";
require_once __DIR__ . '/include/TeamSubtree.php';
require_once __DIR__ . "/../company/include/DailyAssignReportData.php";

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

$loginMsId  = (int)$Login_user_IDvl;
$subtreeIds = array_values(array_diff(getMsSubtreeIds($db_conn, $loginMsId), [$loginMsId]));

$ms_id = (int)($_GET['ms_id'] ?? 0);
$from  = $_GET['from'] ?? '';
$to    = $_GET['to'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-d'); }

$staffName = '';
if ($ms_id && in_array($ms_id, $subtreeIds, true)) {
    $stmt = $db_conn->prepare("SELECT ms_name FROM marketing_staff WHERE id = ?");
    $stmt->bind_param('i', $ms_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $staffName = $row['ms_name'] ?? '';
    $rows = fetchDailyAssignRows($db_conn, $ms_id, $from, $to);
} elseif ($ms_id) {
    $rows = []; // ms_id outside caller's subtree — never leak another team's data
} else {
    $rows = fetchDailyAssignRowsForMsIds($db_conn, $subtreeIds, $from, $to);
}

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Assign Report');

$headers = ['A' => 'Date', 'B' => 'District', 'C' => 'Division', 'D' => 'Firka', 'E' => 'DM Name', 'F' => 'TP Name', 'G' => 'Assigned By'];
$lastCol = 'G';

$titleText = 'Daily Assign Report | ' . $from . ' to ' . $to . ($staffName !== '' ? ' | Staff: ' . $staffName : '');
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

$rowNum = 3;
foreach ($rows as $r) {
    $sheet->setCellValue("A{$rowNum}", $r['assign_date']);
    $sheet->setCellValue("B{$rowNum}", $r['district_name']);
    $sheet->setCellValue("C{$rowNum}", $r['division_name']);
    $sheet->setCellValue("D{$rowNum}", $r['firka_name']);
    $sheet->setCellValue("E{$rowNum}", $r['dm_name'] ?? 'Unassigned');
    $sheet->setCellValue("F{$rowNum}", $r['tp_name']);
    $sheet->setCellValue("G{$rowNum}", $r['assigned_by'] ?? '');
    $rowNum++;
}
if ($rowNum === 3) {
    $sheet->setCellValue('A3', 'No assignment records found for this filter.');
    $sheet->mergeCells("A3:{$lastCol}3");
}

foreach (['A' => 14, 'B' => 20, 'C' => 24, 'D' => 20, 'E' => 22, 'F' => 24, 'G' => 18] as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="daily-assign-report-' . $from . '-to-' . $to . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;

<?php
// One-off audit export: Territory Partners who also exist as a Stockist
// under (what looks like) the same real person — matched by NAME only,
// normalised to ignore case/spacing/punctuation (e.g. "V PREMA" vs
// "V.PREMA") — where the two accounts' mobile numbers DON'T match. Built
// 2026-11-21 after finding this exact situation for TP-0106 / FEMI9-S-370
// (different mobiles meant the Stockist's reward points never showed on her
// TP login, since that bridge matches by mobile). A name match alone is
// never treated as proof of "same person" elsewhere in this app (shop
// dedupe matching always requires mobile too) — this report is the
// opposite case on purpose: it exists BECAUSE the mobiles differ, so it
// can't require them to match first. Flags every such pair for manual
// review, not an automatic merge.
require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('st');

set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;
require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

function tpStockistNormName(string $name): string {
    return mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
}

$tps = $db_conn->query("SELECT tp_id, name, mobile FROM territory_partners WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);
$stockists = $db_conn->query("SELECT useridtext, name, mobile_number FROM stockiest WHERE account_status = 'active'")->fetch_all(MYSQLI_ASSOC);

$stockistsByNormName = [];
foreach ($stockists as $s) {
    $stockistsByNormName[tpStockistNormName($s['name'])][] = $s;
}

$mismatches = [];
foreach ($tps as $tp) {
    $norm = tpStockistNormName($tp['name']);
    if ($norm === '' || empty($stockistsByNormName[$norm])) { continue; }
    foreach ($stockistsByNormName[$norm] as $s) {
        if ($tp['mobile'] !== $s['mobile_number']) {
            $mismatches[] = [
                'tp_id' => $tp['tp_id'], 'tp_name' => $tp['name'], 'tp_mobile' => $tp['mobile'],
                'st_id' => $s['useridtext'], 'st_name' => $s['name'], 'st_mobile' => $s['mobile_number'],
            ];
        }
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('TP-Stockist Mobile Mismatch');

$headers = [
    'A' => 'TP ID', 'B' => 'TP Name', 'C' => 'TP Phone Number',
    'D' => 'Stockist ID', 'E' => 'Stockist Name', 'F' => 'Stockist Phone Number',
];
$lastCol = 'F';

$titleText = 'TP <-> Stockist, Same Name but Different Mobile Number (' . count($mismatches) . ' found)';
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
foreach ($mismatches as $m) {
    $sheet->setCellValue("A{$rowNum}", $m['tp_id']);
    $sheet->setCellValue("B{$rowNum}", $m['tp_name']);
    $sheet->setCellValue("C{$rowNum}", $m['tp_mobile']);
    $sheet->setCellValue("D{$rowNum}", $m['st_id']);
    $sheet->setCellValue("E{$rowNum}", $m['st_name']);
    $sheet->setCellValue("F{$rowNum}", $m['st_mobile']);
    $rowNum++;
}
if ($rowNum === 3) {
    $sheet->setCellValue('A3', 'No name-matched TP/Stockist pairs with different mobile numbers found.');
    $sheet->mergeCells("A3:{$lastCol}3");
}

foreach (['A' => 14, 'B' => 24, 'C' => 18, 'D' => 14, 'E' => 24, 'F' => 18] as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="tp-stockist-mobile-mismatch.xlsx"');
header('Cache-Control: max-age=0');
$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;

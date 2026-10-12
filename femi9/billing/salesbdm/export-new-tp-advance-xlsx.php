<?php
// Every TP onboarded in September 2026, outside Tamil Nadu (every other
// state) — with Firka(s), Target Amount, Napkin advance paid, and a
// colored Active/Inactive Status column. Status is NOT filtered on —
// Active and Inactive both show, colored (green/red), in case a future
// month has an inactive TP outside Tamil Nadu. branch_state has messy
// variants ("Tamil Nadu", "TAMIL NADU", "TamilNadu", trailing comma), so
// the exclusion normalizes by upper-casing and stripping spaces. Same
// source tables/columns as the dashboard's own New TPs modal (target/
// firka) and Advance Paid card (tp_advance_payments).
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_once __DIR__ . "/checksession.php";
require_once __DIR__ . "/config.php";

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

$from = '2026-09-01';
$to   = '2026-09-30';

$stmt = $db_conn->prepare(
    "SELECT tp.id, tp.tp_id, tp.name, tp.branch_state, tp.created_at, tp.is_active,
            COALESCE(SUM(pln.target_amount), 0) AS target,
            GROUP_CONCAT(DISTINCT pln.name ORDER BY pln.name SEPARATOR ', ') AS firka_names
     FROM territory_partners tp
     LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
     LEFT JOIN partner_location_nodes pln ON pln.id = tpl.location_id
     WHERE tp.deleted_at IS NULL
       AND tp.created_at BETWEEN ? AND ?
       AND UPPER(REPLACE(tp.branch_state, ' ', '')) NOT LIKE 'TAMILNADU%'
     GROUP BY tp.id
     ORDER BY tp.created_at ASC"
);
$fromDateTime = $from . ' 00:00:00';
$toDateTime = $to . ' 23:59:59';
$stmt->bind_param('ss', $fromDateTime, $toDateTime);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$tpIds = array_map(fn($r) => (int)$r['id'], $rows);
$advancePaid = [];
if (!empty($tpIds)) {
    $idList = implode(',', $tpIds);
    $stmt2 = $db_conn->prepare(
        "SELECT territory_partner_id, COALESCE(SUM(amount), 0) AS paid
         FROM tp_advance_payments
         WHERE territory_partner_id IN ($idList) AND product_type = 'napkin' AND deleted_at IS NULL
           AND payment_date BETWEEN ? AND ?
         GROUP BY territory_partner_id"
    );
    $stmt2->bind_param('ss', $from, $to);
    $stmt2->execute();
    foreach ($stmt2->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $advancePaid[(int)$r['territory_partner_id']] = (float)$r['paid'];
    }
    $stmt2->close();
}

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('New TPs');

$headers = [
    'A' => 'S.No',
    'B' => 'TP ID',
    'C' => 'TP Name',
    'D' => 'State',
    'E' => 'Firkas',
    'F' => 'Target Amount',
    'G' => 'Advance Amount Paid',
    'H' => 'Status',
];
$lastCol = 'H';

$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'New Territory Partners (Outside Tamil Nadu) | ' . date('d M', strtotime($from)) . ' - ' . date('d M Y', strtotime($to)));
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
foreach ($rows as $i => $r) {
    $paid = $advancePaid[(int)$r['id']] ?? 0.0;

    $sheet->setCellValue("A{$rowNum}", $i + 1);
    $sheet->setCellValue("B{$rowNum}", $r['tp_id']);
    $sheet->setCellValue("C{$rowNum}", $r['name']);
    $sheet->setCellValue("D{$rowNum}", $r['branch_state'] ?: '—');
    $sheet->setCellValue("E{$rowNum}", $r['firka_names'] ?: '—');
    $sheet->setCellValue("F{$rowNum}", (float)$r['target']);
    $sheet->setCellValue("G{$rowNum}", $paid);
    $isActive = (int)$r['is_active'] === 1;
    $sheet->setCellValue("H{$rowNum}", $isActive ? 'Active' : 'Inactive');

    $bgColor = (($rowNum - 2) % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);
    $sheet->getStyle("F{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("G{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("F{$rowNum}:G{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("H{$rowNum}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => $isActive ? 'FF166534' : 'FF991B1B']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $isActive ? 'FFDCFCE7' : 'FFFEE2E2']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $rowNum++;
}

$totalsRow = $rowNum;
$dataStart = 3;
$dataEnd   = $rowNum - 1;

$sheet->setCellValue("A{$totalsRow}", 'TOTAL');
$sheet->mergeCells("A{$totalsRow}:E{$totalsRow}");
if ($dataEnd >= $dataStart) {
    $sheet->setCellValue("F{$totalsRow}", "=SUM(F{$dataStart}:F{$dataEnd})");
    $sheet->setCellValue("G{$totalsRow}", "=SUM(G{$dataStart}:G{$dataEnd})");
}
$sheet->getStyle("F{$totalsRow}:G{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("F{$totalsRow}:G{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(14);
$sheet->getColumnDimension('C')->setWidth(22);
$sheet->getColumnDimension('D')->setWidth(16);
$sheet->getColumnDimension('E')->setWidth(22);
$sheet->getColumnDimension('F')->setWidth(16);
$sheet->getColumnDimension('G')->setWidth(18);
$sheet->getColumnDimension('H')->setWidth(12);

$sheet->freezePane('A3');
$sheet->setAutoFilter("A2:{$lastCol}2");

$filename = 'new_tps_advance_' . $from . '_to_' . $to . '.xlsx';

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

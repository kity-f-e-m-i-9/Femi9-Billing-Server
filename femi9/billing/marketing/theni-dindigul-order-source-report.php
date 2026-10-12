<?php
// Theni & Dindigul TP report: for each TP, splits shop-invoice amount
// into "Get Order" invoices (tp_orders rows a DM assigned, identified by
// tp_orders.assigned_by_ms_id) vs invoices the TP made on their own by
// visiting the shop directly (no DM-assigned tp_orders row behind them) —
// plus a combined total. Two sheets: 01-09-2026..30-09-2026 and
// 01-10-2026..09-10-2026. Column/link shapes mirror export-shop-invoices-xlsx.php
// and territory-partner/manage-orders.php (same inv_id<->tp_orders join,
// same COLLATE cast for the charset mismatch between the two tables).

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
require_once __DIR__ . "/include/MsDistrictScope.php";

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

$districtNames = ['Theni', 'Dindigul'];
$tpIds = getMsDistrictTpIds($db_conn, $districtNames, true);

$tpRoster = [];
if (!empty($tpIds)) {
    $idList = implode(',', $tpIds);
    $res = $db_conn->query(
        "SELECT id, tp_id AS tp_code, name,
                COALESCE(NULLIF(assigned_district,''), branch_district) AS district
         FROM territory_partners
         WHERE id IN ($idList)
         ORDER BY district ASC, name ASC"
    );
    $tpRoster = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

$locLabels = getTpTalukFirkaLabels($db_conn, $tpIds);

// Pulls, for one date range, every invoice row for these TPs plus whether
// tp_orders behind it (if any) was DM-assigned — same inv_id<->tp_orders
// join and COLLATE cast manage-orders.php uses, since user_invoice and
// tp_orders were created under different default charsets.
function fetchInvoiceRows(mysqli $db_conn, array $tpIds, string $from, string $to): array {
    if (empty($tpIds)) return [];
    $idList = implode(',', $tpIds);
    $stmt = $db_conn->prepare(
        "SELECT ui.from_user_id AS tp_id, ui.to_user_id AS shop_ref, ui.total,
                tpo.assigned_by_ms_id
         FROM user_invoice ui
         LEFT JOIN tp_orders tpo
                ON tpo.invoiced_inv_id = ui.inv_id COLLATE utf8mb4_general_ci
               AND tpo.tp_id = ui.from_user_id
         WHERE ui.from_user_type = 'territory_partner'
           AND ui.from_user_id IN ($idList)
           AND ui.`date` BETWEEN ? AND ?"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Buckets a date range's raw invoice rows per TP: shop count, DM-sourced
// ("Get Order") amount, TP's own direct-visit amount, and the combined total.
function summarizeByTp(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $tpId = (int)$r['tp_id'];
        if (!isset($out[$tpId])) {
            $out[$tpId] = ['shops' => [], 'dm_amount' => 0.0, 'direct_amount' => 0.0, 'total_amount' => 0.0];
        }
        $out[$tpId]['shops'][$r['shop_ref']] = true;
        $out[$tpId]['total_amount'] += (float)$r['total'];
        if (!empty($r['assigned_by_ms_id'])) {
            $out[$tpId]['dm_amount'] += (float)$r['total'];
        } else {
            $out[$tpId]['direct_amount'] += (float)$r['total'];
        }
    }
    foreach ($out as &$s) { $s['shop_count'] = count($s['shops']); unset($s['shops']); }
    unset($s);
    return $out;
}

$dateRanges = [
    ['label' => '01-09-2026 to 30-09-2026', 'from' => '2026-09-01', 'to' => '2026-09-30'],
    ['label' => '01-10-2026 to 09-10-2026', 'from' => '2026-10-01', 'to' => '2026-10-09'],
];

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$headers = [
    'A' => 'TP Name',
    'B' => 'TP ID',
    'C' => 'District',
    'D' => 'Firka',
    'E' => 'Target Amount',
    'F' => 'Shop Count',
    'G' => 'Get Order (DM) Invoice Amount',
    'H' => 'Direct Shop Invoice Amount',
    'I' => 'Total Invoice Amount',
];
$lastCol = 'I';

foreach ($dateRanges as $sheetIdx => $range) {
    $summary = summarizeByTp(fetchInvoiceRows($db_conn, $tpIds, $range['from'], $range['to']));

    $sheet = $spreadsheet->createSheet($sheetIdx);
    $sheet->setTitle($range['from'] . '_to_' . $range['to']);

    $sheet->mergeCells("A1:{$lastCol}1");
    $sheet->setCellValue('A1', 'Theni & Dindigul TP Report | ' . $range['label']);
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
    foreach ($tpRoster as $tp) {
        $tpId = (int)$tp['id'];
        $s = $summary[$tpId] ?? ['shop_count' => 0, 'dm_amount' => 0.0, 'direct_amount' => 0.0, 'total_amount' => 0.0];
        $loc = $locLabels[$tpId] ?? ['firkas' => [], 'target' => 0.0];

        $sheet->setCellValue("A{$rowNum}", ucwords(strtolower($tp['name'])));
        $sheet->setCellValue("B{$rowNum}", $tp['tp_code']);
        $sheet->setCellValue("C{$rowNum}", $tp['district']);
        $sheet->setCellValue("D{$rowNum}", implode(', ', $loc['firkas']));
        $sheet->setCellValue("E{$rowNum}", (float)$loc['target']);
        $sheet->setCellValue("F{$rowNum}", $s['shop_count']);
        $sheet->setCellValue("G{$rowNum}", $s['dm_amount']);
        $sheet->setCellValue("H{$rowNum}", $s['direct_amount']);
        $sheet->setCellValue("I{$rowNum}", $s['total_amount']);

        $bgColor = (($rowNum - 2) % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
        $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
        ]);
        foreach (['E', 'G', 'H', 'I'] as $col) {
            $sheet->getStyle("{$col}{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$col}{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle("F{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $rowNum++;
    }

    $totalsRow = $rowNum;
    $dataStart = 3;
    $dataEnd   = $rowNum - 1;

    $sheet->setCellValue("A{$totalsRow}", 'TOTAL');
    $sheet->mergeCells("A{$totalsRow}:F{$totalsRow}");
    if ($dataEnd >= $dataStart) {
        $sheet->setCellValue("G{$totalsRow}", "=SUM(G{$dataStart}:G{$dataEnd})");
        $sheet->setCellValue("H{$totalsRow}", "=SUM(H{$dataStart}:H{$dataEnd})");
        $sheet->setCellValue("I{$totalsRow}", "=SUM(I{$dataStart}:I{$dataEnd})");
    }
    foreach (['G', 'H', 'I'] as $col) {
        $sheet->getStyle("{$col}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("{$col}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
    ]);

    $sheet->getColumnDimension('A')->setWidth(22);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(14);
    $sheet->getColumnDimension('D')->setWidth(24);
    $sheet->getColumnDimension('E')->setWidth(16);
    $sheet->getColumnDimension('F')->setWidth(12);
    $sheet->getColumnDimension('G')->setWidth(22);
    $sheet->getColumnDimension('H')->setWidth(20);
    $sheet->getColumnDimension('I')->setWidth(18);

    $sheet->freezePane('A3');
    $sheet->setAutoFilter("A2:{$lastCol}2");
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'theni_dindigul_tp_report.xlsx';

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

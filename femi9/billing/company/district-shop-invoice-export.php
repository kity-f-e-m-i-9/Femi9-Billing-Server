<?php
// District-wise, month-wise Shop Invoice count + Napkin-only amount export.
// Scoped to invoices a Territory Partner issued TO a shop
// (ui.from_user_type='territory_partner' AND ui.to_user_type='shop') —
// excludes TP's direct-to-customer sales and TP sales to SS/Stockist/
// Distributor, and excludes company/stockist-issued shop invoices, per
// user request (2026-10-17, Tamil chat): "tp ku shop ku napkin la invoice
// potathu mattum district wise venum", then extended to three months
// (July/August/September 2026) side by side. Diaper-category items are
// excluded (COALESCE(p.category,'')!='diaper' — same "unmapped == napkin"
// convention as mis-report.php's own Napkin/Diaper split). Invoice count is
// the number of distinct invoices containing at least one napkin item;
// amount is the sum of just the napkin item lines (an invoice mixing
// napkin+diaper items contributes only its napkin portion to the amount).
// District is resolved via shop.district_id walked up the location tree to
// its depth-3 (district) ancestor — same resolution used by mis-report.php's
// district breakdown.
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

// ---- Months to report, each as [label, from, to] --------------------------
$months = [
    ['label' => 'Jul 2026', 'from' => '2026-07-01', 'to' => '2026-07-31'],
    ['label' => 'Aug 2026', 'from' => '2026-08-01', 'to' => '2026-08-31'],
    ['label' => 'Sep 2026', 'from' => '2026-09-01', 'to' => '2026-09-30'],
];
$overallFrom = $months[0]['from'];
$overallTo   = end($months)['to'];

$sql = "WITH RECURSIVE danc AS (
    SELECT id AS node_id, id AS anc_id, name AS anc_name FROM partner_location_nodes WHERE depth=3
    UNION ALL
    SELECT c.id, a.anc_id, a.anc_name FROM partner_location_nodes c JOIN danc a ON c.parent_id=a.node_id
)
SELECT COALESCE(danc.anc_name, 'Unmapped') AS district_name,
       COUNT(DISTINCT ui.inv_id) AS invoice_count,
       COALESCE(SUM(uii.total), 0) AS napkin_amount
FROM user_invoice ui
JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
JOIN products p ON p.id = uii.pr_id
JOIN shop s ON s.temp_id = ui.to_user_id
LEFT JOIN danc ON danc.node_id = s.district_id
WHERE ui.from_user_type = 'territory_partner' AND ui.to_user_type = 'shop'
  AND ui.sub_total > 0 AND ui.date BETWEEN ? AND ?
  AND COALESCE(p.category, '') != 'diaper'
GROUP BY COALESCE(danc.anc_id, -1), district_name
ORDER BY napkin_amount DESC";
$stmt = $db_conn->prepare($sql);

// ---- Run one query per month, pivot into district -> month -> {cnt, amt} --
$districtData  = [];   // district_name => [month_label => ['cnt'=>, 'amt'=>]]
$districtOrder = [];
foreach ($months as $m) {
    $stmt->bind_param('ss', $m['from'], $m['to']);
    $stmt->execute();
    $monthRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($monthRows as $r) {
        $dn = $r['district_name'];
        if (!isset($districtData[$dn])) {
            $districtData[$dn] = [];
            $districtOrder[] = $dn;
        }
        $districtData[$dn][$m['label']] = [
            'cnt' => (int)$r['invoice_count'],
            'amt' => (float)$r['napkin_amount'],
        ];
    }
}
$stmt->close();

// Order districts by their total napkin amount across all three months, descending
usort($districtOrder, function ($a, $b) use ($districtData, $months) {
    $sumA = 0.0; $sumB = 0.0;
    foreach ($months as $m) {
        $sumA += $districtData[$a][$m['label']]['amt'] ?? 0.0;
        $sumB += $districtData[$b][$m['label']]['amt'] ?? 0.0;
    }
    return $sumB <=> $sumA;
});

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('TP-Shop Napkin Invoices');

// Column layout: A S.No, B District, then per-month [Invoice Count, Napkin Amount],
// then Total Invoice Count, Total Napkin Amount.
$cols = ['A', 'B'];
$col = 'C';
foreach ($months as $m) {
    $cols[] = $col; $col++; // count col
    $cols[] = $col; $col++; // amount col
}
$totalCntCol = $col; $col++;
$totalAmtCol = $col;
$lastCol = $totalAmtCol;

$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'District-wise, Month-wise TP to Shop Napkin Invoices | Jul-Sep 2026');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

// Row 2: month group headers (merged over each month's 2 columns)
$sheet->setCellValue('A2', '');
$sheet->setCellValue('B2', '');
$mi = 0;
$c = 'C';
foreach ($months as $m) {
    $startCol = $c; $c++;
    $endCol   = $c; $c++;
    $sheet->mergeCells("{$startCol}2:{$endCol}2");
    $sheet->setCellValue("{$startCol}2", $m['label']);
    $mi++;
}
$sheet->mergeCells("{$totalCntCol}2:{$totalAmtCol}2");
$sheet->setCellValue("{$totalCntCol}2", 'Total (Jul-Sep)');

$sheet->mergeCells("A2:A3");
$sheet->mergeCells("B2:B3");
$sheet->setCellValue('A3', 'S.No');
$sheet->setCellValue('B3', 'District');
$c = 'C';
foreach ($months as $m) {
    $sheet->setCellValue("{$c}3", 'Invoices'); $c++;
    $sheet->setCellValue("{$c}3", 'Napkin Amount'); $c++;
}
$sheet->setCellValue("{$totalCntCol}3", 'Invoices');
$sheet->setCellValue("{$totalAmtCol}3", 'Napkin Amount');

$sheet->getStyle("A2:{$lastCol}3")->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$rowNum = 4;
foreach ($districtOrder as $i => $dn) {
    $sheet->setCellValue("A{$rowNum}", $i + 1);
    $sheet->setCellValue("B{$rowNum}", $dn);

    $c = 'C';
    $totalCnt = 0; $totalAmt = 0.0;
    foreach ($months as $m) {
        $cnt = $districtData[$dn][$m['label']]['cnt'] ?? 0;
        $amt = $districtData[$dn][$m['label']]['amt'] ?? 0.0;
        $sheet->setCellValue("{$c}{$rowNum}", $cnt); $c++;
        $sheet->setCellValue("{$c}{$rowNum}", $amt); $c++;
        $totalCnt += $cnt;
        $totalAmt += $amt;
    }
    $sheet->setCellValue("{$totalCntCol}{$rowNum}", $totalCnt);
    $sheet->setCellValue("{$totalAmtCol}{$rowNum}", $totalAmt);

    $bgColor = (($rowNum - 3) % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);
    $sheet->getStyle("C{$rowNum}:{$lastCol}{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    // Amount columns get a number format: D, F, H ... and the final total-amount column
    $ac = 'D';
    while ($ac <= $totalAmtCol) {
        $sheet->getStyle("{$ac}{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
        $ac++;
        if ($ac > $totalAmtCol) break;
    }
    $sheet->getStyle("{$totalAmtCol}{$rowNum}")->getFont()->setBold(true);
    $sheet->getStyle("{$totalCntCol}{$rowNum}")->getFont()->setBold(true);

    $rowNum++;
}

$totalsRow = $rowNum;
$dataStart = 4;
$dataEnd   = $rowNum - 1;
$sheet->setCellValue("A{$totalsRow}", 'TOTAL');
$sheet->mergeCells("A{$totalsRow}:B{$totalsRow}");
if ($dataEnd >= $dataStart) {
    $c = 'C';
    while (true) {
        $sheet->setCellValue("{$c}{$totalsRow}", "=SUM({$c}{$dataStart}:{$c}{$dataEnd})");
        if ($c === $lastCol) break;
        $c++;
    }
}
$c = 'C';
while (true) {
    $sheet->getStyle("{$c}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    if ($c === $lastCol) break;
    $c++;
}
$sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(20);
$c = 'C';
while (true) {
    $sheet->getColumnDimension($c)->setWidth(14);
    if ($c === $lastCol) break;
    $c++;
}

$sheet->freezePane('C4');
$sheet->setAutoFilter("A3:{$lastCol}3");

$filename = 'district_tp_shop_napkin_invoices_jul_aug_sep_2026.xlsx';

if (ob_get_length()) { ob_end_clean(); }

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;

<?php
// TP Sales — Diaper only — split into two sheets: Customer (TP's direct
// counter sale, `invoice` table) and Shop (TP reselling to a shop,
// `user_invoice` table), for Jul/Aug/Sep 2026. Sibling of
// district-shop-invoice-export.php (same TP->Shop napkin export) but for
// diaper-category products, and now also breaking out TP->Customer sales
// separately, per user request (2026-10-17 Tamil chat): "diaper odathu kudu
// tp sales ah kudu customer ku thaniya shop ku thaniya".
//
// Customer invoices (`invoice`/`invoice_items`, user_type='territory_partner')
// carry no district/location data — the `customers` table this joins against
// has no state/district column (confirmed against territory-partner/
// mis-report.php's own comment on this) — so the Customer sheet is
// month-wise totals only, no district breakdown. Shop invoices (`user_invoice`/
// `user_invoice_items`, to shop.district_id) keep the full district x month
// breakdown like the napkin export.
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

$months = [
    ['label' => 'Jul 2026', 'from' => '2026-07-01', 'to' => '2026-07-31'],
    ['label' => 'Aug 2026', 'from' => '2026-08-01', 'to' => '2026-08-31'],
    ['label' => 'Sep 2026', 'from' => '2026-09-01', 'to' => '2026-09-30'],
];

// ---- SHOP: district x month, diaper only -----------------------------------
$shopSql = "WITH RECURSIVE danc AS (
    SELECT id AS node_id, id AS anc_id, name AS anc_name FROM partner_location_nodes WHERE depth=3
    UNION ALL
    SELECT c.id, a.anc_id, a.anc_name FROM partner_location_nodes c JOIN danc a ON c.parent_id=a.node_id
)
SELECT COALESCE(danc.anc_name, 'Unmapped') AS district_name,
       COUNT(DISTINCT ui.inv_id) AS invoice_count,
       COALESCE(SUM(uii.total), 0) AS diaper_amount
FROM user_invoice ui
JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
JOIN products p ON p.id = uii.pr_id
JOIN shop s ON s.temp_id = ui.to_user_id
LEFT JOIN danc ON danc.node_id = s.district_id
WHERE ui.from_user_type = 'territory_partner' AND ui.to_user_type = 'shop'
  AND ui.sub_total > 0 AND ui.date BETWEEN ? AND ?
  AND p.category = 'diaper'
GROUP BY COALESCE(danc.anc_id, -1), district_name
ORDER BY diaper_amount DESC";
$shopStmt = $db_conn->prepare($shopSql);

$shopData  = [];
$shopOrder = [];
foreach ($months as $m) {
    $shopStmt->bind_param('ss', $m['from'], $m['to']);
    $shopStmt->execute();
    $monthRows = $shopStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($monthRows as $r) {
        $dn = $r['district_name'];
        if (!isset($shopData[$dn])) { $shopData[$dn] = []; $shopOrder[] = $dn; }
        $shopData[$dn][$m['label']] = ['cnt' => (int)$r['invoice_count'], 'amt' => (float)$r['diaper_amount']];
    }
}
$shopStmt->close();
usort($shopOrder, function ($a, $b) use ($shopData, $months) {
    $sumA = 0.0; $sumB = 0.0;
    foreach ($months as $m) {
        $sumA += $shopData[$a][$m['label']]['amt'] ?? 0.0;
        $sumB += $shopData[$b][$m['label']]['amt'] ?? 0.0;
    }
    return $sumB <=> $sumA;
});

// ---- CUSTOMER: month-wise only, diaper only (no district data available) --
$custSql = "SELECT COUNT(DISTINCT i.inv_id) AS invoice_count,
                   COALESCE(SUM(ii.total), 0) AS diaper_amount
            FROM invoice i
            JOIN invoice_items ii ON ii.inv_id = i.inv_id
            JOIN products p ON p.id = ii.pr_id
            WHERE i.user_type = 'territory_partner' AND i.sub_total > 0
              AND i.date BETWEEN ? AND ? AND p.category = 'diaper'";
$custStmt = $db_conn->prepare($custSql);
$custData = [];
foreach ($months as $m) {
    $custStmt->bind_param('ss', $m['from'], $m['to']);
    $custStmt->execute();
    $row = $custStmt->get_result()->fetch_assoc();
    $custData[$m['label']] = ['cnt' => (int)$row['invoice_count'], 'amt' => (float)$row['diaper_amount']];
}
$custStmt->close();

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();

// ---- Sheet 1: TP -> Shop (district x month) --------------------------------
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('TP-Shop Diaper');

$cols = ['A', 'B'];
$col = 'C';
foreach ($months as $m) { $cols[] = $col; $col++; $cols[] = $col; $col++; }
$totalCntCol = $col; $col++;
$totalAmtCol = $col;
$lastCol = $totalAmtCol;

$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'District-wise, Month-wise TP to Shop Diaper Invoices | Jul-Sep 2026');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

$c = 'C';
foreach ($months as $m) {
    $startCol = $c; $c++;
    $endCol   = $c; $c++;
    $sheet->mergeCells("{$startCol}2:{$endCol}2");
    $sheet->setCellValue("{$startCol}2", $m['label']);
}
$sheet->mergeCells("{$totalCntCol}2:{$totalAmtCol}2");
$sheet->setCellValue("{$totalCntCol}2", 'Total (Jul-Sep)');
$sheet->mergeCells('A2:A3');
$sheet->mergeCells('B2:B3');
$sheet->setCellValue('A3', 'S.No');
$sheet->setCellValue('B3', 'District');
$c = 'C';
foreach ($months as $m) {
    $sheet->setCellValue("{$c}3", 'Invoices'); $c++;
    $sheet->setCellValue("{$c}3", 'Diaper Amount'); $c++;
}
$sheet->setCellValue("{$totalCntCol}3", 'Invoices');
$sheet->setCellValue("{$totalAmtCol}3", 'Diaper Amount');

$sheet->getStyle("A2:{$lastCol}3")->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$rowNum = 4;
foreach ($shopOrder as $i => $dn) {
    $sheet->setCellValue("A{$rowNum}", $i + 1);
    $sheet->setCellValue("B{$rowNum}", $dn);
    $c = 'C';
    $totalCnt = 0; $totalAmt = 0.0;
    foreach ($months as $m) {
        $cnt = $shopData[$dn][$m['label']]['cnt'] ?? 0;
        $amt = $shopData[$dn][$m['label']]['amt'] ?? 0.0;
        $sheet->setCellValue("{$c}{$rowNum}", $cnt); $c++;
        $sheet->setCellValue("{$c}{$rowNum}", $amt); $c++;
        $totalCnt += $cnt; $totalAmt += $amt;
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
    $ac = 'D';
    while ($ac <= $totalAmtCol) {
        $sheet->getStyle("{$ac}{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
        if ($ac === $totalAmtCol) break;
        $ac++;
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

// ---- Sheet 2: TP -> Customer (month-wise only, no district data) ----------
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('TP-Customer Diaper');

$sheet2->mergeCells('A1:C1');
$sheet2->setCellValue('A1', 'Month-wise TP to Customer Diaper Invoices | Jul-Sep 2026');
$sheet2->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet2->getRowDimension(1)->setRowHeight(30);
$sheet2->mergeCells('A2:C2');
$sheet2->setCellValue('A2', 'Note: Customer invoices carry no district/location data, so only a month-wise total is shown here (unlike the Shop sheet).');
$sheet2->getStyle('A2')->applyFromArray([
    'font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF555555']],
]);
$sheet2->getRowDimension(2)->setRowHeight(24);

$sheet2->setCellValue('A3', 'Month');
$sheet2->setCellValue('B3', 'Invoices');
$sheet2->setCellValue('C3', 'Diaper Amount');
$sheet2->getStyle('A3:C3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$r = 4;
foreach ($months as $m) {
    $d = $custData[$m['label']];
    $sheet2->setCellValue("A{$r}", $m['label']);
    $sheet2->setCellValue("B{$r}", $d['cnt']);
    $sheet2->setCellValue("C{$r}", $d['amt']);
    $sheet2->getStyle("C{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet2->getStyle("B{$r}:C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $bgColor = (($r - 3) % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet2->getStyle("A{$r}:C{$r}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);
    $r++;
}
$totR = $r;
$sheet2->setCellValue("A{$totR}", 'TOTAL');
$sheet2->setCellValue("B{$totR}", "=SUM(B4:B" . ($r - 1) . ")");
$sheet2->setCellValue("C{$totR}", "=SUM(C4:C" . ($r - 1) . ")");
$sheet2->getStyle("C{$totR}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet2->getStyle("B{$totR}:C{$totR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet2->getStyle("A{$totR}:C{$totR}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$sheet2->getColumnDimension('A')->setWidth(16);
$sheet2->getColumnDimension('B')->setWidth(14);
$sheet2->getColumnDimension('C')->setWidth(18);

$spreadsheet->setActiveSheetIndex(0);

$filename = 'tp_customer_shop_diaper_jul_aug_sep_2026.xlsx';

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

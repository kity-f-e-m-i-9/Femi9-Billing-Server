<?php
// Audit trail for why 330mm XL (6 PCS) shows 0 stock for TP-0115
// (P.RaniPeriyasamy, Trichy) — every shop invoice that deducted this
// product from her stock, in order, so she/Company can see it's real
// sales depletion (18 shop invoices + 2 purchase-order credits summing to
// 190 in, 190 out), not a software bug.
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

$tpId = 115;   // P.RaniPeriyasamy, TP-0115
$prId = 14;    // 330mm XL (6 PCS) - Femi9 Premium Sanitary Napkin

$stmt = $db_conn->prepare(
    "SELECT action, qty, qty_before, qty_after, ref_type, ref_id, note, created_at
     FROM territory_partner_stock_ledger
     WHERE territory_partner_id = ? AND product_id = ?
     ORDER BY created_at ASC"
);
$stmt->bind_param('ii', $tpId, $prId);
$stmt->execute();
$ledgerRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Resolve each shop-invoice-type row against user_invoice/user_invoice_items/shop
$rows = [];
foreach ($ledgerRows as $lr) {
    $row = [
        'date'       => $lr['created_at'],
        'action'     => $lr['action'],
        'qty'        => (int)$lr['qty'],
        'qty_before' => (int)$lr['qty_before'],
        'qty_after'  => (int)$lr['qty_after'],
        'ref_id'     => $lr['ref_id'],
        'inv_number' => '',
        'shop_name'  => '',
        'amount'     => null,
    ];
    if ($lr['ref_type'] === 'tp_invoice' && stripos((string)$lr['note'], 'shop invoice') !== false) {
        $invStmt = $db_conn->prepare("SELECT inv_number, to_user_id FROM user_invoice WHERE inv_id = ? LIMIT 1");
        $invStmt->bind_param('s', $lr['ref_id']);
        $invStmt->execute();
        $inv = $invStmt->get_result()->fetch_assoc();
        $invStmt->close();
        if ($inv) {
            $row['inv_number'] = $inv['inv_number'];
            $shopStmt = $db_conn->prepare("SELECT name FROM shop WHERE temp_id = ? LIMIT 1");
            $shopStmt->bind_param('s', $inv['to_user_id']);
            $shopStmt->execute();
            $shop = $shopStmt->get_result()->fetch_assoc();
            $shopStmt->close();
            $row['shop_name'] = $shop['name'] ?? '';

            $itemStmt = $db_conn->prepare("SELECT total FROM user_invoice_items WHERE inv_id = ? AND pr_id = ? LIMIT 1");
            $itemStmt->bind_param('si', $lr['ref_id'], $prId);
            $itemStmt->execute();
            $item = $itemStmt->get_result()->fetch_assoc();
            $itemStmt->close();
            $row['amount'] = $item ? (float)$item['total'] : null;
        }
    } else {
        // Purchase order credit (TP ordering stock in) — ref_id is already the PO number.
        $row['inv_number'] = $lr['ref_id'];
        $row['shop_name']  = ($lr['action'] === 'credit') ? '(Stock received by TP)' : '';
    }
    $rows[] = $row;
}

// SPREADSHEET
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('330 6PCS Stock Trail');

$headers = [
    'A' => 'S.No', 'B' => 'Date', 'C' => 'Type', 'D' => 'Invoice/PO Number',
    'E' => 'Shop / Source', 'F' => 'Qty', 'G' => 'Amount (Rs)', 'H' => 'Stock Before', 'I' => 'Stock After',
];
$lastCol = 'I';

$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'Stock Depletion Trail: 330mm XL (6 PCS) - TP-0115 P.RaniPeriyasamy (Trichy)');
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
    $isCredit = $r['action'] === 'credit';
    $sheet->setCellValue("A{$rowNum}", $i + 1);
    $sheet->setCellValue("B{$rowNum}", date('d/M/Y h:i A', strtotime($r['date'])));
    $sheet->setCellValue("C{$rowNum}", $isCredit ? 'Stock In' : 'Sale (Deduct)');
    $sheet->setCellValue("D{$rowNum}", $r['inv_number'] ?: $r['ref_id']);
    $sheet->setCellValue("E{$rowNum}", $r['shop_name'] ?: '—');
    $sheet->setCellValue("F{$rowNum}", ($isCredit ? '+' : '-') . $r['qty']);
    if ($r['amount'] !== null) {
        $sheet->setCellValue("G{$rowNum}", $r['amount']);
        $sheet->getStyle("G{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
    } else {
        $sheet->setCellValue("G{$rowNum}", '—');
    }
    $sheet->setCellValue("H{$rowNum}", $r['qty_before']);
    $sheet->setCellValue("I{$rowNum}", $r['qty_after']);

    $bgColor = $isCredit ? 'FFDCFCE7' : (($rowNum - 2) % 2 === 0 ? 'FFF0F4FF' : 'FFFFFFFF');
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);
    $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("F{$rowNum}:I{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    if ($isCredit) {
        $sheet->getStyle("F{$rowNum}")->getFont()->getColor()->setARGB('FF166534');
    } else {
        $sheet->getStyle("F{$rowNum}")->getFont()->getColor()->setARGB('FF991B1B');
    }

    $rowNum++;
}

$totalsRow = $rowNum;
$dataStart = 3;
$dataEnd   = $rowNum - 1;
$sheet->setCellValue("A{$totalsRow}", 'FINAL STOCK');
$sheet->mergeCells("A{$totalsRow}:F{$totalsRow}");
if ($dataEnd >= $dataStart) {
    $sheet->setCellValue("I{$totalsRow}", "=I{$dataEnd}");
}
$sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);
$sheet->getStyle("I{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(14);
$sheet->getColumnDimension('D')->setWidth(28);
$sheet->getColumnDimension('E')->setWidth(32);
$sheet->getColumnDimension('F')->setWidth(10);
$sheet->getColumnDimension('G')->setWidth(14);
$sheet->getColumnDimension('H')->setWidth(12);
$sheet->getColumnDimension('I')->setWidth(12);

$sheet->freezePane('A3');
$sheet->setAutoFilter("A2:{$lastCol}2");

$filename = 'rani_periyasamy_330_6pcs_stock_trail.xlsx';

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

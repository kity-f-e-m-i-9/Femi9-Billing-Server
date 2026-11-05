<?php
// Product-wise TP Sales — which Territory Partner billed how much qty of a
// given product (name search, e.g. "245mm") to which buyer, split into two
// sheets since TP has two separate buyer entities in this app:
//   "By Shop"     — user_invoice, to_user_type='shop' (Marketing-captured shops)
//   "By Customer" — legacy invoice/invoice_items + customers table (TP's own
//                   "Manage Invoice - Customer" screen)
// Every sheet gets Excel's own AutoFilter dropdown on each header column so
// the company can search/narrow rows directly inside Excel.
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once("checksession.php");
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

$product = trim($_GET['product'] ?? '');
if ($product === '') { die("Product name is required."); }

function validateDate_ptsx(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}
$defaultFrom = date('Y-m-01', strtotime('-2 months'));
$defaultTo   = date('Y-m-t');
$from_date = validateDate_ptsx($_GET['frdate'] ?? null, $defaultFrom);
$to_date   = validateDate_ptsx($_GET['todate'] ?? null, $defaultTo);
if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

$productLike = '%' . $db_conn->real_escape_string($product) . '%';

// ── By Shop (user_invoice, to_user_type='shop') ─────────────────────────────
$shopRows = [];
$stmtShop = $db_conn->prepare(
    "SELECT
        COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS district,
        tp.tp_id AS tp_code, tp.name AS tp_name,
        s.name AS buyer_name, s.mobile_number AS buyer_mobile,
        ui.inv_number, ui.date AS inv_date,
        p.productName, uii.qty, uii.total AS amount
     FROM territory_partners tp
     JOIN user_invoice ui ON ui.from_user_type = 'territory_partner' AND ui.from_user_id = tp.id AND ui.to_user_type = 'shop'
     JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
     JOIN products p ON p.id = uii.pr_id
     LEFT JOIN shop s ON s.temp_id = ui.to_user_id
     WHERE p.productName LIKE ? AND ui.date BETWEEN ? AND ?
     ORDER BY district, tp.name, ui.date DESC"
);
$stmtShop->bind_param('sss', $productLike, $from_date, $to_date);
$stmtShop->execute();
$shopRows = $stmtShop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtShop->close();

// ── By Customer (legacy invoice/invoice_items + customers) ─────────────────
$custRows = [];
$stmtCust = $db_conn->prepare(
    "SELECT
        COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS district,
        tp.tp_id AS tp_code, tp.name AS tp_name,
        COALESCE(c.name, 'Walking Customer') AS buyer_name, c.mobile AS buyer_mobile,
        i.inv_number, i.date AS inv_date,
        p.productName, ii.qty, ii.total AS amount
     FROM territory_partners tp
     JOIN invoice i ON i.user_type = 'territory_partner' AND i.user_id = tp.id
     JOIN invoice_items ii ON ii.inv_id = i.inv_id
     JOIN products p ON p.id = ii.pr_id
     LEFT JOIN customers c ON c.id = i.customer_id
     WHERE p.productName LIKE ? AND i.date BETWEEN ? AND ?
     ORDER BY district, tp.name, i.date DESC"
);
$stmtCust->bind_param('sss', $productLike, $from_date, $to_date);
$stmtCust->execute();
$custRows = $stmtCust->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCust->close();

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$HEADER_FILL = '2563EB';
$NUMFMT = '#,##0';

$headers = ['District', 'TP Code', 'TP Name', 'Buyer Name', 'Buyer Mobile', 'Invoice Number', 'Invoice Date', 'Product', 'Qty', 'Amount'];

function writeSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers, array $rows, string $headerFill, string $numfmt): void {
    $colLetter = function (int $idx): string { return Coordinate::stringFromColumnIndex($idx + 1); };

    foreach ($headers as $i => $h) {
        $sheet->setCellValue($colLetter($i) . '1', $h);
    }
    $lastCol = $colLetter(count($headers) - 1);
    $headerRange = 'A1:' . $lastCol . '1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFill);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row = 2;
    foreach ($rows as $r) {
        $sheet->setCellValue('A' . $row, $r['district'] ?: '—');
        $sheet->setCellValue('B' . $row, $r['tp_code']);
        $sheet->setCellValue('C' . $row, $r['tp_name']);
        $sheet->setCellValue('D' . $row, $r['buyer_name'] ?: '—');
        $sheet->setCellValue('E' . $row, $r['buyer_mobile'] ?: '');
        $sheet->setCellValue('F' . $row, $r['inv_number']);
        $sheet->setCellValue('G' . $row, $r['inv_date']);
        $sheet->setCellValue('H' . $row, $r['productName']);
        $sheet->setCellValue('I' . $row, (float)$r['qty']);
        $sheet->setCellValue('J' . $row, (float)$r['amount']);
        $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode($numfmt);
        $row++;
    }
    $lastRow = max($row - 1, 1);

    // Excel's own filter dropdown on every header column — lets the company
    // search/narrow rows (by TP, Buyer, Date, etc.) directly inside Excel.
    $sheet->setAutoFilter('A1:' . $lastCol . $lastRow);
    $sheet->freezePane('A2');

    foreach (['A' => 16, 'B' => 10, 'C' => 20, 'D' => 22, 'E' => 14, 'F' => 16, 'G' => 13, 'H' => 24, 'I' => 8, 'J' => 12] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
}

$shopSheet = $spreadsheet->getActiveSheet();
$shopSheet->setTitle('By Shop');
writeSheet($shopSheet, $headers, $shopRows, $HEADER_FILL, $NUMFMT);

$custSheet = $spreadsheet->createSheet();
$custSheet->setTitle('By Customer');
writeSheet($custSheet, $headers, $custRows, $HEADER_FILL, $NUMFMT);

$spreadsheet->setActiveSheetIndex(0);

$filename = 'product_tp_sales_' . preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($product)) . '_' . $from_date . '_to_' . $to_date . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(true);
$writer->save('php://output');
exit;

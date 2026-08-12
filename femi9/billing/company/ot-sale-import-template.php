<?php
/**
 * Downloadable sample Excel template for OT Channel Sales bulk import.
 *
 * One row = one invoice. Every active product gets its own "<Name> Qty" /
 * "<Name> Rate" column pair, generated fresh from the live products list on
 * every download, so the template always matches whatever a Company user
 * can currently sell — no product name typing/matching needed on upload,
 * since the column itself identifies the product (see ot-sale-import-action.php,
 * which maps columns back to product ids by exact column header).
 */

declare(strict_types=1);

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");

ob_clean();

$vendor_paths = [
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
$loaded = false;
foreach ($vendor_paths as $path) {
    if (file_exists($path)) { require_once $path; $loaded = true; break; }
}
if (!$loaded) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Excel library not found.";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$example_godown = '';
$res = $db_conn->query("SELECT gname FROM company_godown ORDER BY id ASC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) { $example_godown = $row['gname']; }

$example_cat = '';
$res = $db_conn->query("SELECT cat FROM ot_cat ORDER BY cat ASC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) { $example_cat = $row['cat']; }

// Active products = not soft-deleted, not the Neksomo (NKS-) catalog —
// same scope ot-sale-add.php offers on the manual "Add Sale" form.
$activeProducts = [];
$res = $db_conn->query(
    "SELECT id, productName, outlet_price FROM products
     WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL)
     ORDER BY productName ASC"
);
while ($row = $res->fetch_assoc()) { $activeProducts[] = $row; }

$fixedHeaders = [
    'Company Profile*', 'Category*', 'Date* (YYYY-MM-DD)', 'Invoice Number*',
    'Customer Name', 'Customer Mobile', 'Customer Address', 'Shipping Address',
    'GST Number', 'Order Number', 'Order Date (YYYY-MM-DD)', 'Ship Date (YYYY-MM-DD)',
    'Courier Charges', 'State', 'Coupon Code',
    'Wallet Amount (Website/ID Concept only)',
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('OT Sales Import');

$col = 1;
foreach ($fixedHeaders as $h) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '1', $h);
    $col++;
}

// One "<Product> Qty" + "<Product> Rate" + "<Product> Discount" triple per
// active product. The exact product id is embedded in a hidden row 2 so the
// importer can match columns back to products even if a product is later
// renamed — the header text alone (row 1) is what the user reads and fills in.
$productColStart = $col;
foreach ($activeProducts as $p) {
    $qtyCol = Coordinate::stringFromColumnIndex($col);
    $rateCol = Coordinate::stringFromColumnIndex($col + 1);
    $discCol = Coordinate::stringFromColumnIndex($col + 2);
    $sheet->setCellValue($qtyCol . '1', $p['productName'] . ' Qty');
    $sheet->setCellValue($rateCol . '1', $p['productName'] . ' Rate');
    $sheet->setCellValue($discCol . '1', $p['productName'] . ' Discount');
    $sheet->setCellValue($qtyCol . '2', 'product_id:' . $p['id']);
    $sheet->setCellValue($rateCol . '2', 'product_id:' . $p['id']);
    $sheet->setCellValue($discCol . '2', 'product_id:' . $p['id']);
    $col += 3;
}
$lastCol = $col - 1;

$headerRange = 'A1:' . Coordinate::stringFromColumnIndex($lastCol) . '1';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D6EFD');

$idRowRange = 'A2:' . Coordinate::stringFromColumnIndex($lastCol) . '2';
$sheet->getStyle($idRowRange)->getFont()->setItalic(true)->setColor(new Color('999999'));
$sheet->getRowDimension(2)->setRowHeight(14);

// Example data row on row 3, pre-filled with each product's current default
// price in its Rate column so the uploader only has to type quantities.
// Written by fixed-header position rather than hardcoded column letters, so
// the mapping stays correct if $fixedHeaders is ever reordered/extended.
$exampleRowNum = 3;
$exampleFixedValues = [
    'Company Profile*' => $example_godown,
    'Category*' => $example_cat,
    'Date* (YYYY-MM-DD)' => date('Y-m-d'),
    'Invoice Number*' => 'EXAMPLE/001',
    'Customer Name' => 'John Doe',
    'Customer Mobile' => '9999999999',
    'Customer Address' => 'Sample address',
    'Shipping Address' => 'Sample address',
    'Coupon Code' => '',
    'Wallet Amount (Website/ID Concept only)' => 0,
];
foreach ($fixedHeaders as $i => $h) {
    if (array_key_exists($h, $exampleFixedValues)) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $exampleRowNum, $exampleFixedValues[$h]);
    }
}

$col = $productColStart;
$firstProduct = true;
foreach ($activeProducts as $p) {
    $qtyCol = Coordinate::stringFromColumnIndex($col);
    $rateCol = Coordinate::stringFromColumnIndex($col + 1);
    $discCol = Coordinate::stringFromColumnIndex($col + 2);
    $sheet->setCellValue($qtyCol . $exampleRowNum, $firstProduct ? 2 : 0);
    $sheet->setCellValue($rateCol . $exampleRowNum, $p['outlet_price']);
    $sheet->setCellValue($discCol . $exampleRowNum, 0);
    $firstProduct = false;
    $col += 3;
}

foreach (range(1, $lastCol) as $i) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}
$sheet->freezePane('E4');

ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="ot_channel_sales_import_template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

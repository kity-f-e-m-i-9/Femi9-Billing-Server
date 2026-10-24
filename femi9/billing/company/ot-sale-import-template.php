<?php
/**
 * Downloadable sample Excel template for OT Channel Sales bulk import.
 *
 * Two variants, picked by ?group=marketplace|tracked (defaults to
 * marketplace): marketplace categories (Amazon, Flipkart, Blinkit, etc. —
 * everything outside OT_TRACKED_INVOICE_CATS) don't need Coupon Code,
 * Customer Mobile, GST Number, Order Date or Ship Date, so those columns are
 * left out entirely; tracked categories (Website, ID Concept, WhatsApp
 * Sales, Digital Orders) keep every column, and their Invoice Number column
 * additionally accepts the literal word "NEXT" (see ot-sale-import-action.php)
 * to auto-generate that category's next sequential number instead of typing
 * one in.
 *
 * One row = one product line, not one invoice. To put several products on
 * the same invoice, repeat the same Invoice Number (and the rest of that
 * invoice's fixed fields) on one row per product — ot-sale-import-action.php
 * groups rows back into invoices by Invoice Number + Category.
 *
 * The Product column is a dropdown (data validation) built from the live,
 * active product list, each shown under its short name (brand text trimmed
 * off — see OtImportProducts.php) so only the Rate/Discount the user
 * actually types for that row's chosen product ever applies to it; nothing
 * is pre-filled for the other products.
 *
 * Every invoice-level column (Company Profile .. Wallet Amount — everything
 * except Product/Qty/Rate/Discount) is pre-filled from row 3 onward with a
 * formula that copies the cell directly above it. Typing a value directly
 * into one of those cells overwrites the formula with that value (normal
 * Excel behaviour), and the chain continues from there — so filling an
 * invoice's details once on its first row and adding more product rows below
 * it carries those details down automatically; starting a new invoice is
 * just typing its own Invoice Number (etc.) on that row to break the chain.
 */

declare(strict_types=1);

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");
require_once("include/OtImportProducts.php");
require_once("include/OtInvoiceNumberGenerator.php");

ob_clean();

$group = ($_GET['group'] ?? '') === 'tracked' ? 'tracked' : 'marketplace';
$isTracked = $group === 'tracked';

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
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

$example_godown = '';
$res = $db_conn->query("SELECT gname FROM company_godown ORDER BY id ASC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) { $example_godown = $row['gname']; }

$example_cat = '';
if ($isTracked) {
    // Prefer Website (simplest, no entity-code suffix) as the example so the
    // "NEXT" demo below is easy to read; falls back to whichever tracked
    // category exists if Website isn't set up.
    $res = $db_conn->query(
        "SELECT cat FROM ot_cat WHERE cat IN ('WEBSITE','WHATSAPP SALES','ID CONCEPT','DIGITAL ORDERS')
         ORDER BY FIELD(cat,'WEBSITE','WHATSAPP SALES','ID CONCEPT','DIGITAL ORDERS') LIMIT 1"
    );
} else {
    $res = $db_conn->query(
        "SELECT cat FROM ot_cat WHERE cat NOT IN ('WEBSITE','WHATSAPP SALES','ID CONCEPT','DIGITAL ORDERS')
         ORDER BY cat ASC LIMIT 1"
    );
}
if ($res && $row = $res->fetch_assoc()) { $example_cat = $row['cat']; }

// Every category valid for this template's group — used as the Category
// column's dropdown source, same tracked/marketplace split as $example_cat.
$groupCategories = [];
if ($isTracked) {
    $res = $db_conn->query(
        "SELECT cat FROM ot_cat WHERE cat IN ('WEBSITE','WHATSAPP SALES','ID CONCEPT','DIGITAL ORDERS')
         ORDER BY FIELD(cat,'WEBSITE','WHATSAPP SALES','ID CONCEPT','DIGITAL ORDERS')"
    );
} else {
    $res = $db_conn->query(
        "SELECT cat FROM ot_cat WHERE cat NOT IN ('WEBSITE','WHATSAPP SALES','ID CONCEPT','DIGITAL ORDERS')
         ORDER BY cat ASC"
    );
}
if ($res) { while ($row = $res->fetch_assoc()) { $groupCategories[] = $row['cat']; } }

// Napkin and Diaper products both go in this one dropdown — same as the
// manual Add Sale form's single product select.
$activeProducts = ot_import_active_products($db_conn);

// Optional per-invoice physical warehouse — same "Godown (physical)" concept
// as the manual Add Sale form. Left blank, stock is checked/deducted against
// the untracked (no-warehouse) stock row, same as before this column existed;
// a company with warehouse-split stock needs the matching code typed in.
$activeWarehouseCodes = [];
$res = $db_conn->query("SELECT code FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
if ($res) { while ($row = $res->fetch_assoc()) { $activeWarehouseCodes[] = $row['code']; } }

$invNumberHeader = $isTracked ? 'Invoice Number* (or type NEXT)' : 'Invoice Number*';

if ($isTracked) {
    $fixedHeaders = [
        'Company Profile*', 'Warehouse Code', 'Category*', 'Date* (YYYY-MM-DD)', $invNumberHeader,
        'Customer Name', 'Customer Mobile', 'Customer Address', 'Shipping Address',
        'GST Number', 'Order Number', 'Order Date (YYYY-MM-DD)', 'Ship Date (YYYY-MM-DD)',
        'Courier Charges', 'State', 'Coupon Code',
        'Wallet Amount (Website/ID Concept only)',
        'Product*', 'Qty*', 'Rate*', 'Discount',
    ];
} else {
    // Marketplace categories (Amazon, Flipkart, Blinkit, etc.) — Coupon Code,
    // Customer Mobile, GST Number, Order Date and Ship Date don't apply to
    // these orders, so they're left out entirely rather than shown blank.
    $fixedHeaders = [
        'Company Profile*', 'Warehouse Code', 'Category*', 'Date* (YYYY-MM-DD)', $invNumberHeader,
        'Customer Name', 'Customer Address', 'Shipping Address',
        'Order Number', 'Courier Charges', 'State',
        'Wallet Amount (Website/ID Concept only)',
        'Product*', 'Qty*', 'Rate*', 'Discount',
    ];
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('OT Sales Import');

$col = 1;
foreach ($fixedHeaders as $h) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '1', $h);
    $col++;
}
$lastCol = $col - 1;
$productColIdx = array_search('Product*', $fixedHeaders, true) + 1;
$productColLetter = Coordinate::stringFromColumnIndex($productColIdx);

$headerRange = 'A1:' . Coordinate::stringFromColumnIndex($lastCol) . '1';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D6EFD');

// ── Hidden reference sheet: one product short-name per row, used as the
//    Product column's dropdown source. ────────────────────────────────────
$refSheet = $spreadsheet->createSheet();
$refSheet->setTitle('Products (do not edit)');
$r = 1;
foreach ($activeProducts as $p) {
    $refSheet->setCellValue('A' . $r, $p['short_name']);
    $r++;
}
$refProductCount = count($activeProducts);
$refSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

// ── Hidden reference sheet: active warehouse codes, used as the Warehouse
//    Code column's dropdown source. ────────────────────────────────────────
$whRefSheet = $spreadsheet->createSheet();
$whRefSheet->setTitle('Warehouses (do not edit)');
$wr = 1;
foreach ($activeWarehouseCodes as $code) {
    $whRefSheet->setCellValue('A' . $wr, $code);
    $wr++;
}
$refWarehouseCount = count($activeWarehouseCodes);
$whRefSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
$warehouseColIdx = array_search('Warehouse Code', $fixedHeaders, true) + 1;
$warehouseColLetter = Coordinate::stringFromColumnIndex($warehouseColIdx);

// ── Hidden reference sheet: this group's valid categories, used as the
//    Category column's dropdown source. ────────────────────────────────────
$catRefSheet = $spreadsheet->createSheet();
$catRefSheet->setTitle('Categories (do not edit)');
$cr = 1;
foreach ($groupCategories as $cat) {
    $catRefSheet->setCellValue('A' . $cr, $cat);
    $cr++;
}
$refCategoryCount = count($groupCategories);
$catRefSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
$categoryColIdx = array_search('Category*', $fixedHeaders, true) + 1;
$categoryColLetter = Coordinate::stringFromColumnIndex($categoryColIdx);

// ── Example rows: one invoice, two product lines, same Invoice Number
//    repeated — demonstrates how a multi-product invoice is entered. ──────
$exampleFixedValues = [
    'Company Profile*' => $example_godown,
    // Defaults to G1 (the common case) but stays a plain typed cell, so
    // changing it (or any continuation row that carries it down) just means
    // typing a different warehouse code over it, same as every other
    // invoice-level column.
    'Warehouse Code' => in_array('G1', $activeWarehouseCodes, true) ? 'G1' : '',
    'Category*' => $example_cat,
    'Date* (YYYY-MM-DD)' => date('Y-m-d'),
    $invNumberHeader => $isTracked ? 'NEXT' : 'EXAMPLE/001',
    'Customer Name' => 'John Doe',
    'Customer Mobile' => '9999999999',
    'Customer Address' => 'Sample address',
    'Shipping Address' => 'Sample address',
    'State' => '',
    'Coupon Code' => '',
    'Wallet Amount (Website/ID Concept only)' => 0,
];

// Row 2 is fully typed (the only row with no formula above it to copy from).
// Row 3 demonstrates the auto-carry: its invoice-level columns are left to
// the formula below (copying row 2's values) and only its own Product/Qty
// get set here, same as a real second product line on the same invoice.
foreach ($fixedHeaders as $colIdx => $h) {
    if (array_key_exists($h, $exampleFixedValues)) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1) . '2', $exampleFixedValues[$h]);
    }
}
foreach ([2, 3] as $i => $rowNum) {
    if (isset($activeProducts[$i])) {
        $p = $activeProducts[$i];
        $sheet->setCellValue($productColLetter . $rowNum, $p['short_name']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($productColIdx + 1) . $rowNum, 2);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($productColIdx + 2) . $rowNum, $p['outlet_price']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($productColIdx + 3) . $rowNum, 0);
    }
}

// ── Auto-carry formulas: every invoice-level column (everything before the
//    Product column), from row 3 down, stays BLANK until THIS row's own
//    Product cell gets a value — the moment a product is picked, that row
//    copies the invoice-level details from the row directly above. This
//    keeps an unused row looking empty (not repeating the last invoice's
//    text hundreds of rows down) and only "arrives" once a product is
//    actually chosen on it. Typing a value directly into one of those cells
//    (instead of leaving the formula) overwrites it, same as before — so a
//    new invoice starts the moment its own Invoice Number (etc.) is typed.
//
//    Tracked categories (Website/ID Concept/WhatsApp Sales/Digital Orders)
//    are the one exception: their Invoice Number column is EXCLUDED from
//    this carry-down on purpose, so it stays genuinely blank on a
//    continuation row instead of repeating a value — ot-sale-import-action.php
//    reads a blank Invoice Number as "another product on the invoice
//    directly above" and reads the literal word NEXT as "auto-generate this
//    category's next invoice number", neither of which would work if a
//    carried-down value were silently sitting in the cell already. ─────────
$invNumberColIdx = array_search($invNumberHeader, $fixedHeaders, true) + 1;
$dataRowsEnd = 500;
for ($rowNum = 3; $rowNum <= $dataRowsEnd; $rowNum++) {
    $aboveRow = $rowNum - 1;
    for ($colIdx = 1; $colIdx < $productColIdx; $colIdx++) {
        if ($isTracked && $colIdx === $invNumberColIdx) {
            continue;
        }
        $colLetter = Coordinate::stringFromColumnIndex($colIdx);
        $sheet->setCellValue(
            "{$colLetter}{$rowNum}",
            "=IF({$productColLetter}{$rowNum}=\"\",\"\",{$colLetter}{$aboveRow})"
        );
    }
}

// ── Product dropdown (data validation) on every data row down to 500. ─────
for ($rowNum = 2; $rowNum <= $dataRowsEnd; $rowNum++) {
    $cell = $sheet->getCell($productColLetter . $rowNum);
    $validation = $cell->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_STOP);
    $validation->setAllowBlank(true);
    $validation->setShowDropDown(true);
    $validation->setShowErrorMessage(true);
    $validation->setErrorTitle('Invalid product');
    $validation->setError('Please pick a product from the dropdown list.');
    $validation->setFormula1("'Products (do not edit)'!\$A\$1:\$A\$" . max(1, $refProductCount));
}

// ── Warehouse Code dropdown (optional, data validation) on every data row
//    down to 500 — left blank, stock is checked against the untracked
//    (no-warehouse) stock row. ──────────────────────────────────────────────
if ($refWarehouseCount > 0) {
    for ($rowNum = 2; $rowNum <= $dataRowsEnd; $rowNum++) {
        $cell = $sheet->getCell($warehouseColLetter . $rowNum);
        $validation = $cell->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid warehouse code');
        $validation->setError('Please pick a warehouse code from the dropdown list, or leave it blank.');
        $validation->setFormula1("'Warehouses (do not edit)'!\$A\$1:\$A\$" . $refWarehouseCount);
    }
}

// ── Category dropdown (data validation) on every data row down to 500. ────
if ($refCategoryCount > 0) {
    for ($rowNum = 2; $rowNum <= $dataRowsEnd; $rowNum++) {
        $cell = $sheet->getCell($categoryColLetter . $rowNum);
        $validation = $cell->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid category');
        $validation->setError('Please pick a category from the dropdown list.');
        $validation->setFormula1("'Categories (do not edit)'!\$A\$1:\$A\$" . $refCategoryCount);
    }
}

// Force a YYYY-MM-DD date display format on every date column, every data
// row -- without this, a continuation row's carry-down formula (e.g.
// "=IF(D3="","",D2)") copies row 2's underlying date value correctly, but
// the new cell's own format defaults to General, so Excel shows the raw
// date serial number (e.g. 46868) instead of a formatted date the moment a
// second product line is added to the same invoice.
foreach (['Date* (YYYY-MM-DD)', 'Order Date (YYYY-MM-DD)', 'Ship Date (YYYY-MM-DD)'] as $dateHeader) {
    $dateColIdx = array_search($dateHeader, $fixedHeaders, true);
    if ($dateColIdx === false) continue;
    $dateColLetter = Coordinate::stringFromColumnIndex($dateColIdx + 1);
    $sheet->getStyle($dateColLetter . '2:' . $dateColLetter . $dataRowsEnd)->getNumberFormat()->setFormatCode('YYYY-MM-DD');
}

foreach (range(1, $lastCol) as $i) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}
$sheet->freezePane('E4');
$spreadsheet->setActiveSheetIndex(0);

ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="ot_channel_sales_import_template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

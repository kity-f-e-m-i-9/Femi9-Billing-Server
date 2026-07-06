<?php
/**
 * Excel Export for OT Channel Sales Report
 * 
 * Exports channel-wise sales data with (Gross - Returns) breakdown
 * Uses PhpSpreadsheet for professional Excel generation
 * 
 * Security: Prepared statements, input validation, proper headers
 * Performance: Optimized queries with minimal memory usage
 * 
 * @version 3.0
 * @author Femi9 Development Team
 */

declare(strict_types=1);

// Start output buffering IMMEDIATELY to catch any unwanted output
ob_start();

// Suppress all errors from displaying (they corrupt the Excel file)
error_reporting(0);
ini_set('display_errors', '0');

// Increase memory limit for large exports
ini_set('memory_limit', '256M');
set_time_limit(300); // 5 minutes

// Include required files with error suppression
@include("checksession.php");
@include("config.php");

// Clear any accumulated output before Excel generation
ob_clean();

// Only proceed if we have a valid session and database connection
if (!isset($_SESSION) || !isset($db_conn)) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Session or database error. Please try again.";
    exit;
}

// Load PhpSpreadsheet with error handling
try {
    // Try multiple possible vendor paths
    $vendor_paths = [
        __DIR__ . '/../../../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
    ];
    
    $loaded = false;
    foreach ($vendor_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        throw new Exception("Composer autoload not found");
    }
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Excel library not found. Please install PhpSpreadsheet: composer require phpoffice/phpspreadsheet";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** Write to a cell using 1-based column index (A=1, B=2, …) */
function xlsx_set(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colIndex, int $row, $value): void {
    $col = Coordinate::stringFromColumnIndex($colIndex);
    $sheet->setCellValue($col . $row, $value);
}

/** Build an A1 range (e.g., A5:R5) from 1-based numeric col/row indexes */
function xlsx_range(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $c1, int $r1, int $c2, int $r2): string {
    $a = Coordinate::stringFromColumnIndex($c1) . $r1;
    $b = Coordinate::stringFromColumnIndex($c2) . $r2;
    return $a . ':' . $b;
}

// Set UTF-8 charset
if (!mysqli_set_charset($db_conn, 'utf8mb4')) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database charset error. Please try again.";
    exit;
}
mysqli_query($db_conn, "SET collation_connection = 'utf8mb4_general_ci'");
mysqli_query($db_conn, "SET collation_server = 'utf8mb4_general_ci'");

// Get and validate date parameters
$from_date = filter_input(INPUT_POST, 'frdate', FILTER_SANITIZE_STRING) ?? date('Y-m-d', strtotime('-7 days'));
$to_date = filter_input(INPUT_POST, 'todate', FILTER_SANITIZE_STRING) ?? date('Y-m-d');

// Date validation
$from_date_obj = DateTime::createFromFormat('Y-m-d', $from_date);
$to_date_obj = DateTime::createFromFormat('Y-m-d', $to_date);

if (!$from_date_obj || !$to_date_obj || $from_date > $to_date) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Invalid date format or date range. Please go back and try again.";
    exit;
}

// --------- Load products (for dynamic columns) ----------
$products = [];
$stmt_products = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
if (!$stmt_products) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database error: Failed to load products.";
    exit;
}

$stmt_products->execute();
$result_products = $stmt_products->get_result();
while ($pr = $result_products->fetch_assoc()) {
    $products[(int)$pr['id']] = $pr['productName'];
}
$stmt_products->close();

if (empty($products)) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "No products found in the system.";
    exit;
}

// --------- Fetch all unique channels from ot_cat ----------
$channels = [];
$stmt_channels = $db_conn->prepare("SELECT id, cat FROM ot_cat WHERE cat IS NOT NULL AND cat != '' ORDER BY cat ASC");
if ($stmt_channels) {
    $stmt_channels->execute();
    $result_channels = $stmt_channels->get_result();
    while ($ch = $result_channels->fetch_assoc()) {
        $channels[$ch['cat']] = [
            'id' => (int)$ch['id'],
            'name' => $ch['cat'],
            'sales_qty' => 0,
            'return_qty' => 0,
            'sales_amount' => 0.0,
            'return_amount' => 0.0,
            'products' => []
        ];
    }
    $stmt_channels->close();
}

// If no channels in ot_cat, fetch from ot_sales
if (empty($channels)) {
    $stmt_sales_channels = $db_conn->prepare("SELECT DISTINCT cat FROM ot_sales WHERE cat IS NOT NULL AND cat != '' ORDER BY cat ASC");
    if ($stmt_sales_channels) {
        $stmt_sales_channels->execute();
        $result_sales_channels = $stmt_sales_channels->get_result();
        while ($ch = $result_sales_channels->fetch_assoc()) {
            $channels[$ch['cat']] = [
                'id' => 0,
                'name' => $ch['cat'],
                'sales_qty' => 0,
                'return_qty' => 0,
                'sales_amount' => 0.0,
                'return_amount' => 0.0,
                'products' => []
            ];
        }
        $stmt_sales_channels->close();
    }
}

// Initialize product arrays for each channel
foreach ($channels as $cat => &$channel_data) {
    foreach ($products as $pr_id => $pr_name) {
        $channel_data['products'][$pr_id] = [
            'sales_qty' => 0,
            'return_qty' => 0
        ];
    }
}
unset($channel_data);

// --------- Fetch sales data ----------
$stmt_sales = $db_conn->prepare("
    SELECT 
        cat,
        prid,
        SUM(qty) as total_qty,
        SUM(CAST(total AS DECIMAL(10,2))) as total_amount
    FROM ot_sales
    WHERE date BETWEEN ? AND ?
        AND cat IS NOT NULL 
        AND cat != ''
    GROUP BY cat, prid
");

if (!$stmt_sales) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database error: Failed to fetch sales data.";
    exit;
}

$stmt_sales->bind_param("ss", $from_date, $to_date);
$stmt_sales->execute();
$result_sales = $stmt_sales->get_result();

while ($sale = $result_sales->fetch_assoc()) {
    $cat = $sale['cat'];
    $prid = (int)$sale['prid'];
    
    // Initialize channel if not exists
    if (!isset($channels[$cat])) {
        $channels[$cat] = [
            'id' => 0,
            'name' => $cat,
            'sales_qty' => 0,
            'return_qty' => 0,
            'sales_amount' => 0.0,
            'return_amount' => 0.0,
            'products' => []
        ];
        foreach ($products as $pr_id => $pr_name) {
            $channels[$cat]['products'][$pr_id] = [
                'sales_qty' => 0,
                'return_qty' => 0
            ];
        }
    }
    
    if (!isset($channels[$cat]['products'][$prid])) {
        $channels[$cat]['products'][$prid] = [
            'sales_qty' => 0,
            'return_qty' => 0
        ];
    }
    
    $channels[$cat]['sales_qty'] += (int)$sale['total_qty'];
    $channels[$cat]['sales_amount'] += (float)$sale['total_amount'];
    $channels[$cat]['products'][$prid]['sales_qty'] += (int)$sale['total_qty'];
}
$stmt_sales->close();

// --------- Fetch returns data ----------
$stmt_returns = $db_conn->prepare("
    SELECT 
        os.cat,
        osr.prid,
        SUM(osr.qty) as total_return_qty,
        SUM(CAST(osr.total AS DECIMAL(10,2))) as total_return_amount
    FROM ot_sales_return osr
    INNER JOIN ot_sales os ON osr.tempid = os.tempid AND osr.prid = os.prid
    WHERE osr.return_date BETWEEN ? AND ?
        AND os.cat IS NOT NULL 
        AND os.cat != ''
    GROUP BY os.cat, osr.prid
");

if (!$stmt_returns) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database error: Failed to fetch returns data.";
    exit;
}

$stmt_returns->bind_param("ss", $from_date, $to_date);
$stmt_returns->execute();
$result_returns = $stmt_returns->get_result();

while ($ret = $result_returns->fetch_assoc()) {
    $cat = $ret['cat'];
    $prid = (int)$ret['prid'];
    
    if (isset($channels[$cat]) && isset($channels[$cat]['products'][$prid])) {
        $channels[$cat]['return_qty'] += (int)$ret['total_return_qty'];
        $channels[$cat]['return_amount'] += (float)$ret['total_return_amount'];
        $channels[$cat]['products'][$prid]['return_qty'] += (int)$ret['total_return_qty'];
    }
}
$stmt_returns->close();

// Sort channels alphabetically
ksort($channels);

// Calculate grand totals
$grand_sales_qty = 0;
$grand_return_qty = 0;
$grand_sales_amount = 0.0;
$grand_return_amount = 0.0;
$grand_product_sales = [];
$grand_product_returns = [];

foreach ($products as $pr_id => $pr_name) {
    $grand_product_sales[$pr_id] = 0;
    $grand_product_returns[$pr_id] = 0;
}

foreach ($channels as $channel) {
    $grand_sales_qty += $channel['sales_qty'];
    $grand_return_qty += $channel['return_qty'];
    $grand_sales_amount += $channel['sales_amount'];
    $grand_return_amount += $channel['return_amount'];
    
    foreach ($channel['products'] as $pr_id => $pr_data) {
        $grand_product_sales[$pr_id] += $pr_data['sales_qty'];
        $grand_product_returns[$pr_id] += $pr_data['return_qty'];
    }
}

// ==================================================================================
// Build Excel with Professional Styling
// ==================================================================================
ob_end_clean();

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('OT Channel Sales');

    // Set default font for entire sheet
    $sheet->getParent()->getDefaultStyle()->getFont()
        ->setName('Calibri')
        ->setSize(11);

    $row = 1;

    // --------- Title Row ---------
    $totalCols = 3 + count($products);
    $sheet->setCellValue("A{$row}", "OT CHANNEL SALES REPORT");
    $sheet->mergeCells("A{$row}:" . Coordinate::stringFromColumnIndex($totalCols) . "{$row}");
    $sheet->getStyle("A{$row}")->getFont()
        ->setBold(true)
        ->setSize(18)
        ->getColor()->setRGB('FFFFFF');
    $sheet->getStyle("A{$row}")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('0d6efd');
    $sheet->getStyle("A{$row}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension($row)->setRowHeight(30);
    $row++;

    // --------- Date Period Row ---------
    $date_period = 'Period: ' . date('d-M-Y', strtotime($from_date)) . ' to ' . date('d-M-Y', strtotime($to_date));
    $sheet->setCellValue("A{$row}", $date_period);
    $sheet->mergeCells("A{$row}:" . Coordinate::stringFromColumnIndex($totalCols) . "{$row}");
    $sheet->getStyle("A{$row}")->getFont()
        ->setBold(true)
        ->setSize(12)
        ->getColor()->setRGB('495057');
    $sheet->getStyle("A{$row}")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('e3f2fd');
    $sheet->getStyle("A{$row}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($row)->setRowHeight(22);
    $row++;

    // Empty row
    $row++;

    // --------- Header Row ---------
    $headerStartRow = $row;
    
    // Define headers
    xlsx_set($sheet, 1, $row, 'Channel');
    xlsx_set($sheet, 2, $row, 'Total Sales (Qty)');
    xlsx_set($sheet, 3, $row, 'Total Amount (₹)');
    
    $col = 4;
    foreach ($products as $pr_id => $pr_name) {
        $short_name = mb_strlen($pr_name) > 30 ? mb_substr($pr_name, 0, 27) . '...' : $pr_name;
        xlsx_set($sheet, $col, $row, $short_name);
        $col++;
    }

    // Style header row
    $headerRange = xlsx_range($sheet, 1, $row, $totalCols, $row);
    $headerStyle = $sheet->getStyle($headerRange);
    $headerStyle->getFont()
        ->setBold(true)
        ->setSize(11)
        ->getColor()->setRGB('FFFFFF');
    $headerStyle->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('4472C4');
    $headerStyle->getBorders()
        ->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);
    $headerStyle->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getRowDimension($row)->setRowHeight(30);
    $row++;

    // --------- Data Rows ---------
    $dataStartRow = $row;
    $rowNum = 0;

    foreach ($channels as $channel) {
        $net_qty = $channel['sales_qty'] - $channel['return_qty'];
        $net_amount = $channel['sales_amount'] - $channel['return_amount'];

        // Channel name
        xlsx_set($sheet, 1, $row, $channel['name']);
        
        // Total Sales Qty with breakdown
        $qty_text = inr_format($net_qty, 0) . "\n(" . inr_format($channel['sales_qty'], 0) . " - " . inr_format($channel['return_qty'], 0) . ")";
        xlsx_set($sheet, 2, $row, $qty_text);
        
        // Total Amount with breakdown
        $amount_text = "₹" . inr_format($net_amount, 2) . "\n(₹" . inr_format($channel['sales_amount'], 2) . " - ₹" . inr_format($channel['return_amount'], 2) . ")";
        xlsx_set($sheet, 3, $row, $amount_text);

        // Product quantities
        $col = 4;
        foreach ($products as $pr_id => $pr_name) {
            $pr_sales = $channel['products'][$pr_id]['sales_qty'] ?? 0;
            $pr_returns = $channel['products'][$pr_id]['return_qty'] ?? 0;
            $pr_net = $pr_sales - $pr_returns;
            
            if ($pr_net != 0 || $pr_sales != 0) {
                $pr_text = inr_format($pr_net, 0) . "\n(" . inr_format($pr_sales, 0) . " - " . inr_format($pr_returns, 0) . ")";
            } else {
                $pr_text = "—";
            }
            xlsx_set($sheet, $col, $row, $pr_text);
            $col++;
        }

        // Style data row
        $rowRange = xlsx_range($sheet, 1, $row, $totalCols, $row);
        $rowStyle = $sheet->getStyle($rowRange);
        
        // Alternating row colors
        if ($rowNum % 2 == 0) {
            $rowStyle->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F8F9FA');
        }
        
        $rowStyle->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $rowStyle->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // Bold channel name
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        
        // Right align quantity and amount columns
        $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Center align product columns
        for ($c = 4; $c <= $totalCols; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            // Product column background
            $sheet->getStyle($colLetter . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('f0f8ff');
        }

        $sheet->getRowDimension($row)->setRowHeight(40);
        $row++;
        $rowNum++;
    }

    // --------- Grand Total Row ---------
    $totalsRow = $row;
    
    xlsx_set($sheet, 1, $totalsRow, 'GRAND TOTAL');
    
    // Grand Total Sales Qty
    $grand_qty_text = inr_format($grand_sales_qty - $grand_return_qty, 0) . "\n(" . inr_format($grand_sales_qty, 0) . " - " . inr_format($grand_return_qty, 0) . ")";
    xlsx_set($sheet, 2, $totalsRow, $grand_qty_text);
    
    // Grand Total Amount
    $grand_amount_text = "₹" . inr_format($grand_sales_amount - $grand_return_amount, 2) . "\n(₹" . inr_format($grand_sales_amount, 2) . " - ₹" . inr_format($grand_return_amount, 2) . ")";
    xlsx_set($sheet, 3, $totalsRow, $grand_amount_text);

    // Grand Total Products
    $col = 4;
    foreach ($products as $pr_id => $pr_name) {
        $grand_pr_net = $grand_product_sales[$pr_id] - $grand_product_returns[$pr_id];
        $grand_pr_text = inr_format($grand_pr_net, 0) . "\n(" . inr_format($grand_product_sales[$pr_id], 0) . " - " . inr_format($grand_product_returns[$pr_id], 0) . ")";
        xlsx_set($sheet, $col, $totalsRow, $grand_pr_text);
        $col++;
    }

    // Style totals row
    $totalsRange = xlsx_range($sheet, 1, $totalsRow, $totalCols, $totalsRow);
    $totalsStyle = $sheet->getStyle($totalsRange);
    $totalsStyle->getFont()
        ->setBold(true)
        ->setSize(12)
        ->getColor()->setRGB('FFFFFF');
    $totalsStyle->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('70AD47');
    $totalsStyle->getBorders()
        ->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);
    $totalsStyle->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getRowDimension($totalsRow)->setRowHeight(40);

    // --------- Summary Row ---------
    $row += 2;
    $sheet->setCellValue("A{$row}", 'Overall Summary:');
    $sheet->setCellValue("B{$row}", count($channels) . ' channels');
    $sheet->setCellValue("C{$row}", array_sum($grand_product_sales) . ' units sold');
    
    $summaryRange = "A{$row}:C{$row}";
    $sheet->getStyle($summaryRange)->getFont()
        ->setBold(true)
        ->setSize(12)
        ->getColor()->setRGB('D83B01');
    $sheet->getStyle($summaryRange)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('FFF2E8');
    $sheet->getStyle($summaryRange)->getBorders()
        ->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);

    // --------- Column widths ---------
    $sheet->getColumnDimension('A')->setWidth(25); // Channel
    $sheet->getColumnDimension('B')->setWidth(22); // Total Sales Qty
    $sheet->getColumnDimension('C')->setWidth(28); // Total Amount
    
    // Product columns
    for ($c = 4; $c <= $totalCols; $c++) {
        $colLetter = Coordinate::stringFromColumnIndex($c);
        $sheet->getColumnDimension($colLetter)->setWidth(20);
    }

    // Freeze panes
    $sheet->freezePane('A' . ($headerStartRow + 1));

    // Add border around entire table
    $tableRange = xlsx_range($sheet, 1, $headerStartRow, $totalCols, $totalsRow);
    $sheet->getStyle($tableRange)->getBorders()
        ->getOutline()->setBorderStyle(Border::BORDER_THICK);

    // Generate filename
    $filename = 'OT_Channel_Sales_Report_' . 
                date('d-M-Y', strtotime($from_date)) . '_to_' . 
                date('d-M-Y', strtotime($to_date)) . '.xlsx';
    
    // Sanitize filename
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

    // Output Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save('php://output');
    
    // Clean up
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

} catch (Exception $e) {
    // If Excel generation fails, show error as text
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error generating Excel file: " . $e->getMessage();
}

exit;
?>
<?php
// District -> Territory Partner monthly sales export (Jun-Sep 2026, fixed
// per request). Two separate sale channels per TP, matching the same
// tables/columns mis-report.php already treats as "TP sales" (customer
// direct sales via `invoice`, shop sales via `user_invoice` -- to_user_type
// is always 'shop' for TP-originated rows, confirmed against live data):
//   - Customer sales: invoice.user_type = 'territory_partner'
//   - Shop sales:     user_invoice.from_user_type = 'territory_partner'
// No status filter on user_invoice, matching mis-report.php's own
// convention (sub_total > 0 is the only real gate there too) -- almost
// every real shop invoice sits at status='draft' in this table, so
// filtering to 'submitted' would show near-zero data.
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

@include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
@include("config.php");

if (!isset($_SESSION) || !isset($db_conn)) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Session or database error. Please try again.";
    exit;
}

try {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Excel library not found. Please install PhpSpreadsheet.";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function xlsx_set(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colIndex, int $row, $value): void {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $row, $value);
}

$months = [
    '2026-06' => 'Jun 2026',
    '2026-07' => 'Jul 2026',
    '2026-08' => 'Aug 2026',
    '2026-09' => 'Sep 2026',
];
$rangeFrom = '2026-06-01';
$rangeTo   = '2026-09-30';

// Active TPs grouped by their assigned district.
$tpRows = $db_conn->query(
    "SELECT id, tp_id, name, COALESCE(NULLIF(assigned_district, ''), 'Unassigned') AS district
     FROM territory_partners
     WHERE deleted_at IS NULL
     ORDER BY district ASC, name ASC"
)->fetch_all(MYSQLI_ASSOC);

// Customer sales, grouped by TP + month.
$customerSales = []; // [tp_id][ym] => total
$stmt1 = $db_conn->prepare(
    "SELECT user_id AS tp_id, DATE_FORMAT(`date`, '%Y-%m') AS ym, SUM(sub_total) AS total
     FROM invoice
     WHERE user_type = 'territory_partner' AND deleted_at IS NULL AND sub_total > 0
       AND `date` BETWEEN ? AND ?
     GROUP BY user_id, ym"
);
$stmt1->bind_param('ss', $rangeFrom, $rangeTo);
$stmt1->execute();
$res1 = $stmt1->get_result();
while ($row = $res1->fetch_assoc()) {
    $customerSales[(int) $row['tp_id']][$row['ym']] = (float) $row['total'];
}
$stmt1->close();

// Shop sales, grouped by TP + month.
$shopSales = []; // [tp_id][ym] => total
$stmt2 = $db_conn->prepare(
    "SELECT from_user_id AS tp_id, DATE_FORMAT(`date`, '%Y-%m') AS ym, SUM(sub_total) AS total
     FROM user_invoice
     WHERE from_user_type = 'territory_partner' AND deleted_at IS NULL AND sub_total > 0
       AND `date` BETWEEN ? AND ?
     GROUP BY from_user_id, ym"
);
$stmt2->bind_param('ss', $rangeFrom, $rangeTo);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) {
    $shopSales[(int) $row['tp_id']][$row['ym']] = (float) $row['total'];
}
$stmt2->close();

ob_end_clean();

// Group TPs by district, in the same order the query already returned.
$districts = [];
$districtOrder = [];
foreach ($tpRows as $tp) {
    $key = $tp['district'];
    if (!isset($districts[$key])) {
        $districts[$key] = ['district' => $key, 'tps' => []];
        $districtOrder[] = $key;
    }

    $tpId = (int) $tp['id'];
    $monthly = [];
    $tpCustomerTotal = 0.0;
    $tpShopTotal = 0.0;
    foreach ($months as $ym => $label) {
        $cust = $customerSales[$tpId][$ym] ?? 0.0;
        $shop = $shopSales[$tpId][$ym] ?? 0.0;
        $monthly[$ym] = ['customer' => $cust, 'shop' => $shop, 'total' => $cust + $shop];
        $tpCustomerTotal += $cust;
        $tpShopTotal += $shop;
    }

    $districts[$key]['tps'][] = [
        'tp_id'    => $tp['tp_id'],
        'name'     => $tp['name'],
        'monthly'  => $monthly,
        'cust_total' => $tpCustomerTotal,
        'shop_total' => $tpShopTotal,
        'grand_total' => $tpCustomerTotal + $tpShopTotal,
    ];
}

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('District TP Monthly Sales');

    $today = date('Y-m-d');
    $sheet->setCellValue('A1', 'District-wise Territory Partner Monthly Sales (Jun-Sep 2026) — as of ' . $today);
    $lastColIndexTitle = 4 + count($months) * 3 + 3;
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($lastColIndexTitle) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    // Two header rows: month name spanning 3 sub-columns, then the
    // Customer/Shop/Total sub-header underneath it.
    $headerRow1 = 3;
    $headerRow2 = 4;
    xlsx_set($sheet, 1, $headerRow1, 'District');
    xlsx_set($sheet, 2, $headerRow1, 'TP Name');
    xlsx_set($sheet, 3, $headerRow1, 'TP ID');
    $sheet->mergeCells('A' . $headerRow1 . ':A' . $headerRow2);
    $sheet->mergeCells('B' . $headerRow1 . ':B' . $headerRow2);
    $sheet->mergeCells('C' . $headerRow1 . ':C' . $headerRow2);

    $col = 4;
    foreach ($months as $ym => $label) {
        xlsx_set($sheet, $col, $headerRow1, $label);
        $startCol = Coordinate::stringFromColumnIndex($col);
        $endCol   = Coordinate::stringFromColumnIndex($col + 2);
        $sheet->mergeCells($startCol . $headerRow1 . ':' . $endCol . $headerRow1);
        xlsx_set($sheet, $col,     $headerRow2, 'Customer');
        xlsx_set($sheet, $col + 1, $headerRow2, 'Shop');
        xlsx_set($sheet, $col + 2, $headerRow2, 'Total');
        $col += 3;
    }
    xlsx_set($sheet, $col,     $headerRow1, 'Grand Total');
    $sheet->mergeCells(Coordinate::stringFromColumnIndex($col) . $headerRow1 . ':' . Coordinate::stringFromColumnIndex($col + 2) . $headerRow1);
    xlsx_set($sheet, $col,     $headerRow2, 'Customer');
    xlsx_set($sheet, $col + 1, $headerRow2, 'Shop');
    xlsx_set($sheet, $col + 2, $headerRow2, 'Total');
    $lastCol = $col + 2;

    $headerRange = 'A' . $headerRow1 . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow2;
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    // Each month gets its own colour (header band darker, data columns a
    // pale tint of the same colour) so the four months are easy to tell
    // apart at a glance while scanning across a row.
    $monthColors = [
        '2026-06' => ['header' => '2E75B6', 'tint' => 'DDEBF7'], // blue
        '2026-07' => ['header' => '548235', 'tint' => 'E2EFDA'], // green
        '2026-08' => ['header' => 'BF8F00', 'tint' => 'FFF2CC'], // amber
        '2026-09' => ['header' => 'C55A11', 'tint' => 'FCE4D6'], // orange
    ];
    $monthColRanges = []; // ym => [startColIndex, endColIndex]
    $mc = 4;
    foreach ($months as $ym => $label) {
        $monthColRanges[$ym] = [$mc, $mc + 2];
        $startCol = Coordinate::stringFromColumnIndex($mc);
        $endCol   = Coordinate::stringFromColumnIndex($mc + 2);
        $sheet->getStyle($startCol . $headerRow1 . ':' . $endCol . $headerRow2)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($monthColors[$ym]['header']);
        $mc += 3;
    }

    $row = $headerRow2 + 1;
    foreach ($districtOrder as $key) {
        $d = $districts[$key];
        $districtStartRow = $row;

        $districtCustTotal = 0.0;
        $districtShopTotal = 0.0;
        $districtMonthlyTotals = array_fill_keys(array_keys($months), ['customer' => 0.0, 'shop' => 0.0]);

        foreach ($d['tps'] as $tp) {
            xlsx_set($sheet, 2, $row, $tp['name']);
            xlsx_set($sheet, 3, $row, $tp['tp_id']);

            $c = 4;
            foreach ($months as $ym => $label) {
                $m = $tp['monthly'][$ym];
                xlsx_set($sheet, $c,     $row, $m['customer']);
                xlsx_set($sheet, $c + 1, $row, $m['shop']);
                xlsx_set($sheet, $c + 2, $row, $m['total']);
                $districtMonthlyTotals[$ym]['customer'] += $m['customer'];
                $districtMonthlyTotals[$ym]['shop'] += $m['shop'];
                $c += 3;
            }
            xlsx_set($sheet, $c,     $row, $tp['cust_total']);
            xlsx_set($sheet, $c + 1, $row, $tp['shop_total']);
            xlsx_set($sheet, $c + 2, $row, $tp['grand_total']);

            $districtCustTotal += $tp['cust_total'];
            $districtShopTotal += $tp['shop_total'];
            $row++;
        }

        if (empty($d['tps'])) {
            xlsx_set($sheet, 2, $row, '— No active TP in this district —');
            $row++;
        }

        $districtEndRow = $row - 1;
        xlsx_set($sheet, 1, $districtStartRow, $d['district']);
        if ($districtEndRow > $districtStartRow) {
            $sheet->mergeCells('A' . $districtStartRow . ':A' . $districtEndRow);
            $sheet->getStyle('A' . $districtStartRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // District subtotal row.
        xlsx_set($sheet, 2, $row, 'District Total');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $c = 4;
        foreach ($months as $ym => $label) {
            $mc = $districtMonthlyTotals[$ym]['customer'];
            $ms = $districtMonthlyTotals[$ym]['shop'];
            xlsx_set($sheet, $c,     $row, $mc);
            xlsx_set($sheet, $c + 1, $row, $ms);
            xlsx_set($sheet, $c + 2, $row, $mc + $ms);
            $c += 3;
        }
        xlsx_set($sheet, $c,     $row, $districtCustTotal);
        xlsx_set($sheet, $c + 1, $row, $districtShopTotal);
        xlsx_set($sheet, $c + 2, $row, $districtCustTotal + $districtShopTotal);
        $totalRowRange = 'A' . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row;
        $sheet->getStyle($totalRowRange)->getFont()->setBold(true);
        $sheet->getStyle($totalRowRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        $row++;
    }

    if ($row > $headerRow2 + 1) {
        $dataRange = 'A' . ($headerRow2 + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        // Currency-style number formatting on every amount column (D onward).
        $sheet->getStyle('D' . ($headerRow2 + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1))
            ->getNumberFormat()->setFormatCode('#,##0');

        // Tint each month's own 3 data columns with its colour, on top of
        // (not instead of) the grey "District Total" row fill already set
        // above -- applying the plain per-cell fill here would otherwise
        // overwrite that bold grey styling row by row.
        foreach ($months as $ym => $label) {
            [$startColIdx, $endColIdx] = $monthColRanges[$ym];
            for ($r = $headerRow2 + 1; $r <= $row - 1; $r++) {
                $isTotalRow = trim((string) $sheet->getCell('B' . $r)->getValue()) === 'District Total';
                if ($isTotalRow) continue;
                $rangeStr = Coordinate::stringFromColumnIndex($startColIdx) . $r . ':' . Coordinate::stringFromColumnIndex($endColIdx) . $r;
                $sheet->getStyle($rangeStr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($monthColors[$ym]['tint']);
            }
        }
    }

    foreach (range(1, $lastCol) as $ci) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
    }

    $sheet->freezePane('D' . ($headerRow2 + 1));

    $filename = "District_TP_Monthly_Sales_Jun-Sep2026_{$today}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save('php://output');
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error generating Excel file: " . $e->getMessage();
    error_log("Excel generation error: " . $e->getMessage());
}

exit;

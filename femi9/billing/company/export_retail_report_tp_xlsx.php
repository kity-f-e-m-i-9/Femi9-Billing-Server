<?php
// Excel export for Retail-Report-Details-TP.php -- same filters (dates,
// District/Firka/TP Name multi-selects, Amount Range, search), same
// seller/sales computation, but dumps every matching row instead of just
// the current page.
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

@include("checksession.php");
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

mysqli_set_charset($db_conn, 'utf8mb4');
mysqli_query($db_conn, "SET collation_connection = 'utf8mb4_general_ci'");
mysqli_query($db_conn, "SET collation_server = 'utf8mb4_general_ci'");

$from_date = $_POST['frdate'] ?? date('Y-m-d', strtotime('-7 days'));
$to_date   = $_POST['todate'] ?? date('Y-m-d');

$selected_district_ids = array_values(array_filter(array_map('intval', (array)($_POST['district_id'] ?? []))));
$selected_firka_ids    = array_values(array_filter(array_map('intval', (array)($_POST['firka_id'] ?? []))));
$selected_tp_ids       = array_values(array_filter(array_map('intval', (array)($_POST['tp_id'] ?? []))));
$selected_amount_range = trim($_POST['amount_range'] ?? '');
$search                = trim($_POST['q'] ?? '');
$search_esc            = $db_conn->real_escape_string($search);

$products = [];
$product_result = mysqli_query($db_conn, "SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY id ASC");
if ($product_result) {
    while ($pr = mysqli_fetch_assoc($product_result)) {
        $products[$pr['id']] = $pr['productName'];
    }
}

// Same location/TP-id filter precedence as Retail-Report-Details-TP.php:
// Firka > District, TP Name narrows further on top of either.
$tp_location_condition = "";
if (!empty($selected_firka_ids)) {
    $firkaIdList = implode(',', $selected_firka_ids);
    $tp_location_condition = " AND EXISTS (
        SELECT 1 FROM territory_partner_locations tpl
        WHERE tpl.territory_partner_id = tp.id AND tpl.location_id IN ($firkaIdList)
    )";
} elseif (!empty($selected_district_ids)) {
    $districtIdList = implode(',', $selected_district_ids);
    $tp_location_condition = " AND EXISTS (
        SELECT 1 FROM territory_partner_locations tpl
        INNER JOIN partner_location_nodes f  ON f.id = tpl.location_id
        INNER JOIN partner_location_nodes t  ON t.id = f.parent_id
        INNER JOIN partner_location_nodes dv ON dv.id = t.parent_id
        WHERE tpl.territory_partner_id = tp.id AND dv.parent_id IN ($districtIdList)
    )";
}
$tp_id_condition = "";
if (!empty($selected_tp_ids)) {
    $tp_id_condition = " AND tp.id IN (" . implode(',', $selected_tp_ids) . ")";
}

$sellers = [];
$query = "SELECT tp.id as seller_id,
                 CONVERT(tp.name USING utf8mb4) COLLATE utf8mb4_general_ci as seller_name,
                 CONVERT(tp.mobile USING utf8mb4) COLLATE utf8mb4_general_ci as seller_mobile
          FROM territory_partners tp
          WHERE tp.deleted_at IS NULL" . $tp_location_condition . $tp_id_condition;
$result = mysqli_query($db_conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $sellers[] = $row;
    }
}

$sellers_with_data = [];
foreach ($sellers as $seller) {
    $total_query = "SELECT COALESCE(SUM(sub_total), 0) as sub_total,
                    COALESCE(SUM(courier_charges), 0) as courier_charges,
                    COALESCE(SUM(total), 0) as total_amount
                    FROM user_invoice
                    WHERE from_user_id = '" . $db_conn->real_escape_string($seller['seller_id']) . "'
                    AND from_user_type = 'territory_partner'
                    AND to_user_type = 'shop'
                    AND date BETWEEN '" . $db_conn->real_escape_string($from_date) . "'
                    AND '" . $db_conn->real_escape_string($to_date) . "'
                    AND sub_total > 0";
    $total_result = mysqli_query($db_conn, $total_query);
    if ($total_result) {
        $total_row = mysqli_fetch_assoc($total_result);
        $seller['total_amount'] = $total_row['total_amount'];
        $seller['sub_total'] = $total_row['sub_total'];
        $seller['courier_charges'] = $total_row['courier_charges'];

        $include_seller = false;
        if (!empty($selected_amount_range)) {
            switch ($selected_amount_range) {
                case '10000-49999':
                    $include_seller = ($seller['total_amount'] >= 10000 && $seller['total_amount'] <= 49999);
                    break;
                case '50000-99999':
                    $include_seller = ($seller['total_amount'] >= 50000 && $seller['total_amount'] <= 99999);
                    break;
                case '100000-149999':
                    $include_seller = ($seller['total_amount'] >= 100000 && $seller['total_amount'] <= 149999);
                    break;
                case '150000-above':
                    $include_seller = ($seller['total_amount'] >= 150000);
                    break;
            }
        } else {
            $include_seller = ($seller['total_amount'] > 0);
        }

        if ($include_seller) {
            if ($search_esc !== '') {
                if (stripos($seller['seller_name'], $search_esc) !== false ||
                    stripos($seller['seller_mobile'], $search_esc) !== false) {
                    $sellers_with_data[] = $seller;
                }
            } else {
                $sellers_with_data[] = $seller;
            }
        }
    }
}

usort($sellers_with_data, function ($a, $b) {
    return $b['total_amount'] <=> $a['total_amount'];
});

$seller_product_quantities = [];
foreach ($sellers_with_data as $seller) {
    $seller_id = $seller['seller_id'];
    $product_qty_query = "
        SELECT uii.pr_id, SUM(uii.qty) as total_qty
        FROM user_invoice ui
        INNER JOIN user_invoice_items uii ON ui.inv_id = uii.inv_id
        WHERE ui.from_user_id = '" . $db_conn->real_escape_string($seller_id) . "'
        AND ui.from_user_type = 'territory_partner'
        AND ui.to_user_type = 'shop'
        AND ui.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "'
        AND '" . $db_conn->real_escape_string($to_date) . "'
        AND ui.sub_total > 0
        GROUP BY uii.pr_id
    ";
    $qty_result = mysqli_query($db_conn, $product_qty_query);
    if ($qty_result) {
        while ($qty_row = mysqli_fetch_assoc($qty_result)) {
            $seller_product_quantities[$seller_id][$qty_row['pr_id']] = $qty_row['total_qty'];
        }
    }
}

ob_end_clean();

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('TP Retail Sales');

    $today = date('Y-m-d');
    $sheet->setCellValue('A1', "Retail Sales Report - Territory Partner ({$from_date} to {$to_date})");
    $lastColIndexTitle = 6 + count($products);
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($lastColIndexTitle) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $headerRow = 3;
    $columns = ['S.No', 'TP Name', 'Mobile Number', 'Sub Total', 'Courier', 'Total Amount'];
    foreach ($products as $pr_name) { $columns[] = $pr_name; }
    $col = 1;
    foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
    $lastCol = $col - 1;
    $headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $row = $headerRow + 1;
    $serial = 1;
    $grand_total = 0; $grand_subtotal = 0; $grand_courier = 0;
    $product_totals = array_fill_keys(array_keys($products), 0);

    foreach ($sellers_with_data as $seller) {
        xlsx_set($sheet, 1, $row, $serial++);
        xlsx_set($sheet, 2, $row, $seller['seller_name']);
        xlsx_set($sheet, 3, $row, $seller['seller_mobile']);
        xlsx_set($sheet, 4, $row, (float) $seller['sub_total']);
        xlsx_set($sheet, 5, $row, (float) $seller['courier_charges']);
        xlsx_set($sheet, 6, $row, (float) $seller['total_amount']);

        $grand_subtotal += $seller['sub_total'];
        $grand_courier += $seller['courier_charges'];
        $grand_total += $seller['total_amount'];

        $c = 7;
        foreach ($products as $pr_id => $pr_name) {
            $qty = $seller_product_quantities[$seller['seller_id']][$pr_id] ?? 0;
            xlsx_set($sheet, $c, $row, (int) $qty);
            $product_totals[$pr_id] += $qty;
            $c++;
        }
        $row++;
    }

    if ($row > $headerRow + 1) {
        xlsx_set($sheet, 3, $row, 'Total:');
        $sheet->getStyle('C' . $row)->getFont()->setBold(true);
        xlsx_set($sheet, 4, $row, $grand_subtotal);
        xlsx_set($sheet, 5, $row, $grand_courier);
        xlsx_set($sheet, 6, $row, $grand_total);
        $c = 7;
        foreach ($product_totals as $total) {
            xlsx_set($sheet, $c, $row, $total);
            $c++;
        }
        $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row)
            ->getFont()->setBold(true);
        $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

        $dataRange = 'A' . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row;
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('D' . ($headerRow + 1) . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0');
    }

    foreach (range(1, $lastCol) as $ci) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
    }
    $sheet->freezePane('A' . ($headerRow + 1));

    $filename = "Retail_Sales_TP_{$today}.xlsx";
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

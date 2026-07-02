<?php 
/**
 * B2B Sales Report Excel Export
 * Exports invoice data with products in PhpSpreadsheet format
 * 
 * Security: XSS protection, SQL injection prevention, CSRF validation recommended
 * Performance: Optimized with prepared statements and efficient queries
 */

// Start output buffering IMMEDIATELY to catch any unwanted output
ob_start();

// Error reporting for debugging (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include required files with error suppression
@include("checksession.php");
@include("config.php");
@require_once("include/GodownAccess.php");

// Only proceed if we have a valid session and database connection
if (!isset($_SESSION) || !isset($db_conn)) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Session or database error. Please try again.";
    exit;
}

// Load PhpSpreadsheet with error handling
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Write to a cell using 1-based column index (A=1, B=2, …)
 * 
 * @param PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
 * @param int $colIndex 1-based column index
 * @param int $row Row number
 * @param mixed $value Value to set
 */
function xlsx_set(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colIndex, int $row, $value): void {
    $col = Coordinate::stringFromColumnIndex($colIndex);
    $sheet->setCellValue($col . $row, $value);
}

/**
 * Build an A1 range (e.g., A5:R5) from 1-based numeric col/row indexes
 * 
 * @param PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
 * @param int $c1 Start column
 * @param int $r1 Start row
 * @param int $c2 End column
 * @param int $r2 End row
 * @return string Range string
 */
function xlsx_range(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $c1, int $r1, int $c2, int $r2): string {
    $a = Coordinate::stringFromColumnIndex($c1) . $r1;
    $b = Coordinate::stringFromColumnIndex($c2) . $r2;
    return $a . ':' . $b;
}

// Set database charset to match report page
mysqli_set_charset($db_conn, 'utf8mb4');
mysqli_query($db_conn, "SET collation_connection = 'utf8mb4_general_ci'");
mysqli_query($db_conn, "SET collation_server = 'utf8mb4_general_ci'");

// --------- Read filters (POST first, fallback to SESSION) ----------
$selected_state_id   = isset($_POST['state_id'])    ? (int)$_POST['state_id'] : (int)($_SESSION['report_state_id'] ?? 0);
$from_date           = $_POST['frdate']   ?? ($_SESSION['report_from_date']  ?? date('Y-m-d', strtotime('-7 days')));
$to_date             = $_POST['todate']   ?? ($_SESSION['report_to_date']    ?? date('Y-m-d'));
$selected_district   = isset($_POST['district_id']) ? (int)$_POST['district_id'] : (int)($_SESSION['report_district_id'] ?? 0);
$selected_taluk      = isset($_POST['taluk_id'])    ? (int)$_POST['taluk_id']    : (int)($_SESSION['report_taluk_id'] ?? 0);
$selected_seller_type= $_POST['seller_type'] ?? ($_SESSION['report_seller_type'] ?? '');
$selected_seller_id  = $_POST['seller_id']   ?? ($_SESSION['report_seller_id']   ?? '');
$selected_buyer_type = $_POST['buyer_type']  ?? ($_SESSION['report_buyer_type']  ?? '');
$selected_buyer_district = isset($_POST['buyer_district_id']) ? (int)$_POST['buyer_district_id'] : (int)($_SESSION['report_buyer_district_id'] ?? 0);

// Date validation
if (!DateTime::createFromFormat('Y-m-d', $from_date)) {
    $from_date = date('Y-m-d', strtotime('-7 days'));
}
if (!DateTime::createFromFormat('Y-m-d', $to_date)) {
    $to_date = date('Y-m-d');
}

// Validate state
if ($selected_state_id <= 0) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Invalid state. Please go back and select a state.";
    exit;
}

// --------- Load products (for dynamic columns) ----------
$products = [];
$stmt_products = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
if ($stmt_products) {
    $stmt_products->execute();
    $result_products = $stmt_products->get_result();
    while ($r = $result_products->fetch_assoc()) {
        $products[(int)$r['id']] = $r['productName'];
    }
    $stmt_products->close();
}

// --------- Build query filters ----------
$seller_type_filter = '';
$seller_id_filter   = '';
$buyer_type_filter  = '';

if (!empty($selected_seller_type)) {
    $seller_type_filter = " AND ui.from_user_type = '" . $db_conn->real_escape_string($selected_seller_type) . "'";
}
if (!empty($selected_seller_id)) {
    $seller_id_filter = " AND ui.from_user_id = '" . $db_conn->real_escape_string($selected_seller_id) . "'";
}
if (!empty($selected_buyer_type)) {
    $buyer_type_filter = " AND ui.to_user_type = '" . $db_conn->real_escape_string($selected_buyer_type) . "'";
}

// --------- Build buyer union with CONVERT function AND buyer district filter ----------
$buyer_district_filter = $selected_buyer_district > 0 ? " AND district_id = " . (int)$selected_buyer_district : "";

$buyer_union_parts = [];
$buyer_union_parts[] = "SELECT 
    temp_id, state_id, district_id, taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name, 
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as mobile_number, 
    'shop' as user_type 
    FROM shop
    WHERE 1=1 $buyer_district_filter";

$buyer_union_parts[] = "SELECT 
    temp_id, state_id, district_id, taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name, 
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as mobile_number, 
    'distributor' as user_type 
    FROM distributor
    WHERE 1=1 $buyer_district_filter";

$buyer_union_parts[] = "SELECT 
    temp_id, state_id, district_id, taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name, 
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as mobile_number, 
    'super_distributor' as user_type 
    FROM super_distributor
    WHERE 1=1 $buyer_district_filter";

$buyer_union_parts[] = "SELECT 
    temp_id, CAST(state_id AS UNSIGNED) as state_id, district_id, CAST(taluk_id AS UNSIGNED) as taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name, 
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as mobile_number, 
    'stockiest' as user_type 
    FROM stockiest
    WHERE 1=1 $buyer_district_filter";

$buyer_union_parts[] = "SELECT 
    temp_id, state_id, district_id, 0 as taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name, 
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as mobile_number, 
    'super_stockiest' as user_type 
    FROM super_stockiest
    WHERE 1=1 $buyer_district_filter";

$buyer_union_parts[] = "SELECT 
    temp_id, CAST(state_id AS UNSIGNED) as state_id, district_id, 0 as taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name, 
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as mobile_number, 
    'candf' as user_type 
    FROM c_and_f
    WHERE 1=1 $buyer_district_filter";

$buyer_union_parts[] = "SELECT 
    temp_id, state_id, district_id, taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name, 
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as mobile_number, 
    'outlet' as user_type 
    FROM outlet
    WHERE 1=1 $buyer_district_filter";

$buyer_union_query = implode(" UNION ALL ", $buyer_union_parts);

// --------- Build seller geographic filter WITH explicit collation ----------
$district_condition = !empty($selected_district) ? " AND district_id = " . (int)$selected_district : "";
$taluk_condition = !empty($selected_taluk) ? " AND taluk_id = " . (int)$selected_taluk : "";
$collate = "COLLATE utf8mb4_general_ci";

$seller_union_parts = [];

// Include company seller type only when allowed
if (empty($selected_seller_type) || $selected_seller_type == 'company') {
    if (empty($selected_district) || $selected_district == 8) {
        $seller_union_parts[] = "
            SELECT CAST(id AS CHAR) $collate AS temp_id, 'company' AS user_type
            FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . "
        ";
    }
}

// Include other seller types
if (empty($selected_seller_type) || $selected_seller_type != 'company') {
    $seller_union_parts[] = "
        SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'shop' AS user_type
        FROM shop WHERE state_id = $selected_state_id $district_condition $taluk_condition
    ";
    $seller_union_parts[] = "
        SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'distributor' AS user_type
        FROM distributor WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition $taluk_condition
    ";
    $seller_union_parts[] = "
        SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'super_distributor' AS user_type
        FROM super_distributor WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition $taluk_condition
    ";
    $seller_union_parts[] = "
        SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'stockiest' AS user_type
        FROM stockiest WHERE state_id = $selected_state_id $district_condition
        " . (!empty($selected_taluk) ? " AND CAST(taluk_id AS UNSIGNED) = $selected_taluk" : "") . "
    ";
    $seller_union_parts[] = "
        SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'super_stockiest' AS user_type
        FROM super_stockiest WHERE state_id = $selected_state_id $district_condition
    ";
    $seller_union_parts[] = "
        SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'candf' AS user_type
        FROM c_and_f WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition
    ";
    $seller_union_parts[] = "
        SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'outlet' AS user_type
        FROM outlet WHERE state_id = $selected_state_id $district_condition $taluk_condition
    ";
}

$seller_geo_join = "";
if (!empty($seller_union_parts)) {
    $seller_union_query = implode(" UNION ALL ", $seller_union_parts);
    $seller_geo_join = " INNER JOIN ($seller_union_query) seller_geo 
                        ON ui.from_user_id = seller_geo.temp_id 
                        AND ui.from_user_type = seller_geo.user_type ";
}

// --------- Build B2C seller filter with explicit collation ----------
$b2c_seller_union_parts = [];

// Include company for B2C
if (empty($selected_seller_type) || $selected_seller_type == 'company') {
    if (empty($selected_district) || $selected_district == 8) {
        $b2c_seller_union_parts[] = "SELECT CAST(id AS CHAR) $collate as user_id, 'company' as user_type FROM company_godown WHERE 1=1 AND " . godown_finance_filter_sql($db_conn);
    }
}

// Include other seller types for B2C
if (empty($selected_seller_type) || $selected_seller_type != 'company') {
    $b2c_seller_union_parts[] = "SELECT CAST(temp_id AS CHAR) $collate as user_id, 'shop' as user_type FROM shop WHERE state_id = $selected_state_id $district_condition $taluk_condition";
    $b2c_seller_union_parts[] = "SELECT CAST(temp_id AS CHAR) $collate as user_id, 'distributor' as user_type FROM distributor WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition $taluk_condition";
    $b2c_seller_union_parts[] = "SELECT CAST(temp_id AS CHAR) $collate as user_id, 'super_distributor' as user_type FROM super_distributor WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition $taluk_condition";
    $b2c_seller_union_parts[] = "SELECT CAST(temp_id AS CHAR) $collate as user_id, 'stockiest' as user_type FROM stockiest WHERE state_id = $selected_state_id $district_condition" . (!empty($selected_taluk) ? " AND CAST(taluk_id AS UNSIGNED) = $selected_taluk" : "");
    $b2c_seller_union_parts[] = "SELECT CAST(temp_id AS CHAR) $collate as user_id, 'super_stockiest' as user_type FROM super_stockiest WHERE state_id = $selected_state_id $district_condition";
    $b2c_seller_union_parts[] = "SELECT CAST(temp_id AS CHAR) $collate as user_id, 'candf' as user_type FROM c_and_f WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition";
    $b2c_seller_union_parts[] = "SELECT CAST(temp_id AS CHAR) $collate as user_id, 'outlet' as user_type FROM outlet WHERE state_id = $selected_state_id $district_condition $taluk_condition";
}

$b2c_seller_geo_join = "";
if (!empty($b2c_seller_union_parts)) {
    $b2c_seller_union_query = implode(" UNION ALL ", $b2c_seller_union_parts);
    $b2c_seller_geo_join = " INNER JOIN ($b2c_seller_union_query) b2c_seller_geo 
                            ON CAST(i.user_id AS CHAR) $collate = b2c_seller_geo.user_id 
                            AND i.user_type = b2c_seller_geo.user_type ";
}

// --------- Build seller meta union for name lookup ----------
$seller_meta_union_parts = [];
$seller_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate as temp_id, 
    'shop' as user_type,
    district_id,
    taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name,
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as mobile
    FROM shop 
    WHERE state_id = " . (int)$selected_state_id;

$seller_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'distributor',
    district_id,
    taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
    FROM distributor 
    WHERE CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id;

$seller_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'super_distributor',
    district_id,
    taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
    FROM super_distributor 
    WHERE CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id;

$seller_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'stockiest',
    district_id,
    CAST(taluk_id AS UNSIGNED) as taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
    FROM stockiest 
    WHERE state_id = " . (int)$selected_state_id;

$seller_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'super_stockiest',
    district_id,
    0 as taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
    FROM super_stockiest 
    WHERE state_id = " . (int)$selected_state_id;

$seller_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'candf',
    district_id,
    0 as taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
    FROM c_and_f 
    WHERE CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id;

$seller_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'outlet',
    district_id,
    taluk_id,
    CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
    CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
    FROM outlet 
    WHERE state_id = " . (int)$selected_state_id;

// Add company godown with district 8
$seller_meta_union_parts[] = "SELECT 
    CAST(id AS CHAR) $collate, 
    'company',
    8 as district_id,
    0 as taluk_id,
    CONVERT(gname USING utf8mb4) COLLATE utf8mb4_general_ci,
    CONVERT(contact USING utf8mb4) COLLATE utf8mb4_general_ci
    FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . "";

$seller_meta_union_query = implode(" UNION ALL ", $seller_meta_union_parts);
$seller_meta_join = " LEFT JOIN ($seller_meta_union_query) seller_meta 
                      ON ui.from_user_id = seller_meta.temp_id 
                      AND ui.from_user_type = seller_meta.user_type ";

// --------- Build BUYER meta union for district/taluk lookup ----------
$buyer_meta_union_parts = [];
$buyer_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate as temp_id, 
    'shop' as user_type,
    district_id,
    taluk_id
    FROM shop";

$buyer_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'distributor',
    district_id,
    taluk_id
    FROM distributor";

$buyer_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'super_distributor',
    district_id,
    taluk_id
    FROM super_distributor";

$buyer_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'stockiest',
    district_id,
    CAST(taluk_id AS UNSIGNED) as taluk_id
    FROM stockiest";

$buyer_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'super_stockiest',
    district_id,
    0 as taluk_id
    FROM super_stockiest";

$buyer_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'candf',
    district_id,
    0 as taluk_id
    FROM c_and_f";

$buyer_meta_union_parts[] = "SELECT 
    CAST(temp_id AS CHAR) $collate, 
    'outlet',
    district_id,
    taluk_id
    FROM outlet";

$buyer_meta_union_query = implode(" UNION ALL ", $buyer_meta_union_parts);
$buyer_meta_join = " LEFT JOIN ($buyer_meta_union_query) buyer_meta 
                     ON ui.to_user_id = buyer_meta.temp_id 
                     AND ui.to_user_type = buyer_meta.user_type ";

// --------- Build main queries ----------
$from = $db_conn->real_escape_string($from_date);
$to   = $db_conn->real_escape_string($to_date);

// B2B Query
$query_b2b = "
SELECT 
  ui.id,
  ui.inv_id,
  ui.inv_number,
  ui.date,
  ui.sub_total,
  ui.to_user_type,
  ui.to_user_id,
  ui.from_user_type,
  ui.from_user_id,
  buyers.name AS buyer_name,
  buyers.mobile_number AS buyer_mobile,
  seller_meta.district_id AS seller_district_id,
  seller_meta.taluk_id AS seller_taluk_id,
  buyer_meta.district_id AS buyer_district_id,
  buyer_meta.taluk_id AS buyer_taluk_id,
  'B2B' AS source_type
FROM user_invoice ui
$seller_geo_join
$seller_meta_join
$buyer_meta_join
INNER JOIN ($buyer_union_query) buyers
        ON ui.to_user_id = buyers.temp_id
       AND ui.to_user_type = buyers.user_type
WHERE ui.date BETWEEN '$from' AND '$to'
  AND ui.sub_total > 0
  $seller_type_filter
  $seller_id_filter
  $buyer_type_filter
";

// B2C Query (only if buyer type is customer or not specified)
$query_b2c = '';
if (empty($selected_buyer_type) || $selected_buyer_type === 'customer') {
    $b2c_seller_type_filter = '';
    $b2c_seller_id_filter = '';
    
    if (!empty($selected_seller_type)) {
        $b2c_seller_type_filter = " AND i.user_type = '" . $db_conn->real_escape_string($selected_seller_type) . "'";
    }
    if (!empty($selected_seller_id)) {
        $b2c_seller_id_filter = " AND i.user_id = '" . $db_conn->real_escape_string($selected_seller_id) . "'";
    }
    
    $query_b2c = "
        SELECT
          i.id,
          i.inv_id,
          i.inv_number,
          i.sub_total,
          i.total,
          'customer' AS to_user_type,
          i.customer_id AS to_user_id,
          i.user_type AS from_user_type,
          i.user_id AS from_user_id,
          COALESCE(c.name, 'Walking Customer') AS buyer_name,
          COALESCE(c.mobile, '') AS buyer_mobile,
          COALESCE(s_meta.district_id, 0) AS seller_district_id,
          COALESCE(s_meta.taluk_id, 0) AS seller_taluk_id,
          0 AS buyer_district_id,
          0 AS buyer_taluk_id,
          'B2C' AS source_type
        FROM invoice i
        $b2c_seller_geo_join
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN ($seller_meta_union_query) s_meta 
            ON CAST(i.user_id AS CHAR) $collate = s_meta.temp_id 
            AND i.user_type = s_meta.user_type
        WHERE i.date BETWEEN '$from' AND '$to'
          AND i.sub_total > 0
          $b2c_seller_type_filter
          $b2c_seller_id_filter
        ";
}

// Combine queries
$final_query = $query_b2b;
if (!empty($query_b2c)) {
    $final_query .= " UNION ALL " . $query_b2c;
}
$final_query .= " ORDER BY date DESC, id DESC";

// --------- Execute & collect data ----------
$res = mysqli_query($db_conn, $final_query);

if (!$res) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database query error: " . mysqli_error($db_conn);
    error_log("Export query error: " . mysqli_error($db_conn));
    exit;
}

$invoices = [];
$invoice_ids_b2b = [];
$invoice_ids_b2c = [];

while ($row = mysqli_fetch_assoc($res)) {
    $invoices[$row['inv_id']] = $row;
    if ($row['source_type'] === 'B2B') {
        $invoice_ids_b2b[] = $row['inv_id'];
    } else {
        $invoice_ids_b2c[] = $row['inv_id'];
    }
}

// --------- Get product quantities AND PRICES ----------
$product_data = [];

// B2B quantities and prices
if (!empty($invoice_ids_b2b)) {
    $inv_list = "'" . implode("','", array_map([$db_conn,'real_escape_string'], $invoice_ids_b2b)) . "'";
    $q = "SELECT inv_id, pr_id, qty, amount FROM user_invoice_items WHERE inv_id IN ($inv_list)";
    $ri = mysqli_query($db_conn, $q);
    if ($ri) {
        while ($r = mysqli_fetch_assoc($ri)) {
            $product_data[$r['inv_id']][(int)$r['pr_id']] = [
                'qty' => (float)$r['qty'],
                'price' => (float)$r['amount']
            ];
        }
    }
}

// B2C quantities and prices
if (!empty($invoice_ids_b2c)) {
    $inv_list = "'" . implode("','", array_map([$db_conn,'real_escape_string'], $invoice_ids_b2c)) . "'";
    $q = "SELECT inv_id, pr_id, qty, amount FROM invoice_items WHERE inv_id IN ($inv_list)";
    $ri = mysqli_query($db_conn, $q);
    if ($ri) {
        while ($r = mysqli_fetch_assoc($ri)) {
            $product_data[$r['inv_id']][(int)$r['pr_id']] = [
                'qty' => (float)$r['qty'],
                'price' => (float)$r['amount']
            ];
        }
    }
}

// ── Returns ──────────────────────────────────────────────────────
$returns_by_invoice = [];
$return_product_data = [];

$all_inv_ids = array_merge($invoice_ids_b2b, $invoice_ids_b2c);
if (!empty($all_inv_ids)) {
    $inv_list = "'" . implode("','", array_map([$db_conn,'real_escape_string'], $all_inv_ids)) . "'";
    
    $ret_r = mysqli_query($db_conn, "
        SELECT rs.id AS return_id, rs.invnumber AS original_inv_id,
               rs.date AS return_date, rs.total AS return_total,
               rs.from_usertype,
               COALESCE(ss.name,st.name,dt.name,sd.name,sh.name,cf.name,ot.name,c2.name) AS returner_name
        FROM user_return_stock rs
        LEFT JOIN super_stockiest   ss ON rs.from_usertype='super_stockiest'    AND ss.temp_id=rs.from_userid
        LEFT JOIN stockiest         st ON rs.from_usertype='stockiest'          AND st.temp_id=rs.from_userid
        LEFT JOIN distributor       dt ON rs.from_usertype='distributor'        AND dt.temp_id=rs.from_userid
        LEFT JOIN super_distributor sd ON rs.from_usertype='super_distributor'  AND sd.temp_id=rs.from_userid
        LEFT JOIN shop              sh ON rs.from_usertype='shop'               AND sh.temp_id=rs.from_userid
        LEFT JOIN c_and_f           cf ON rs.from_usertype='candf'              AND cf.temp_id=rs.from_userid
        LEFT JOIN outlet            ot ON rs.from_usertype='outlet'             AND ot.temp_id=rs.from_userid
        LEFT JOIN customers         c2 ON rs.from_usertype='customer'           AND c2.id=rs.from_userid
        WHERE rs.total > 0
          AND rs.date BETWEEN '$from_date' AND '$to_date'
    ");
    
    $return_ids = [];
    if ($ret_r) while ($rrow = mysqli_fetch_assoc($ret_r)) {
        $returns_by_invoice[$rrow['original_inv_id']][] = $rrow;
        $return_ids[] = $rrow['return_id'];
    }
    
    if (!empty($return_ids)) {
        $rid_list = implode(',', array_map('intval', $return_ids));
        $rpq = mysqli_query($db_conn, "SELECT returnid, prid, qty, amount FROM user_return_stock_items WHERE returnid IN ($rid_list)");
        if ($rpq) while ($rp = mysqli_fetch_assoc($rpq))
            $return_product_data[$rp['returnid']][$rp['prid']] = ['qty' => $rp['qty'], 'price' => $rp['amount']];
    }
}


// Load any parent invoices that aren't already in $invoices
$missing_inv_ids = array_diff(array_keys($returns_by_invoice), array_keys($invoices));

if (!empty($missing_inv_ids)) {
    $missing_list = "'".implode("','", array_map([$db_conn,'real_escape_string'], $missing_inv_ids))."'";
    
    // Try user_invoice first
    $mq = mysqli_query($db_conn, "
        SELECT ui.id, ui.inv_id, ui.inv_number, ui.date, ui.sub_total,
               ui.courier_charges, ui.total, ui.to_user_type, ui.to_user_id,
               ui.from_user_type, ui.from_user_id,
               COALESCE(ss.name,st.name,dt.name,sd.name,sh.name,cf.name,ot.name) AS buyer_name,
               COALESCE(ss.mobile_number,st.mobile_number,dt.mobile_number,sd.mobile_number,sh.mobile_number,cf.mobile_number,ot.mobile_number) AS buyer_mobile,
               'B2B' AS source_type
        FROM user_invoice ui
        LEFT JOIN super_stockiest   ss ON ui.to_user_type='super_stockiest'    AND ss.temp_id=ui.to_user_id
        LEFT JOIN stockiest         st ON ui.to_user_type='stockiest'          AND st.temp_id=ui.to_user_id
        LEFT JOIN distributor       dt ON ui.to_user_type='distributor'        AND dt.temp_id=ui.to_user_id
        LEFT JOIN super_distributor sd ON ui.to_user_type='super_distributor'  AND sd.temp_id=ui.to_user_id
        LEFT JOIN shop              sh ON ui.to_user_type='shop'               AND sh.temp_id=ui.to_user_id
        LEFT JOIN c_and_f           cf ON ui.to_user_type='candf'              AND cf.temp_id=ui.to_user_id
        LEFT JOIN outlet            ot ON ui.to_user_type='outlet'             AND ot.temp_id=ui.to_user_id
        WHERE ui.inv_id IN ($missing_list)
    ");
    if ($mq) while ($row = mysqli_fetch_assoc($mq)) {
        $invoices[$row['inv_id']] = $row;
        $invoice_ids_b2b[] = $row['inv_id'];
    }
    
    // Also try invoice table (B2C)
    $mq2 = mysqli_query($db_conn, "
        SELECT i.id, i.inv_id, i.inv_number, i.date, i.sub_total,
               i.courier_charges, i.total, 'customer' AS to_user_type,
               i.customer_id AS to_user_id, i.user_type AS from_user_type,
               i.user_id AS from_user_id,
               COALESCE(c.name,'Walking Customer') AS buyer_name,
               COALESCE(c.mobile,'') AS buyer_mobile,
               'B2C' AS source_type
        FROM invoice i
        LEFT JOIN customers c ON c.id = i.customer_id
        WHERE i.inv_id IN ($missing_list)
    ");
    if ($mq2) while ($row = mysqli_fetch_assoc($mq2)) {
        if (!isset($invoices[$row['inv_id']])) {
            $invoices[$row['inv_id']] = $row;
            $invoice_ids_b2c[] = $row['inv_id'];
        }
    }
}

// --------- Get seller details ----------
$seller_details = [];
if (!empty($invoices)) {
    $by_type = [];
    foreach ($invoices as $inv) {
        $by_type[$inv['from_user_type']][] = $inv['from_user_id'];
    }

    $table_map = [
        'company'          => ['table'=>'company_godown',    'id'=>'id',       'name'=>'gname', 'mobile'=>'contact'],
        'super_stockiest'  => ['table'=>'super_stockiest',   'id'=>'temp_id',  'name'=>'name',  'mobile'=>'mobile_number'],
        'stockiest'        => ['table'=>'stockiest',         'id'=>'temp_id',  'name'=>'name',  'mobile'=>'mobile_number'],
        'distributor'      => ['table'=>'distributor',       'id'=>'temp_id',  'name'=>'name',  'mobile'=>'mobile_number'],
        'super_distributor'=> ['table'=>'super_distributor', 'id'=>'temp_id',  'name'=>'name',  'mobile'=>'mobile_number'],
        'candf'            => ['table'=>'c_and_f',           'id'=>'temp_id',  'name'=>'name',  'mobile'=>'mobile_number'],
        'outlet'           => ['table'=>'outlet',            'id'=>'temp_id',  'name'=>'name',  'mobile'=>'mobile_number'],
        'shop'             => ['table'=>'shop',              'id'=>'temp_id',  'name'=>'name',  'mobile'=>'mobile_number'],
    ];
    
    foreach ($by_type as $type => $ids) {
        if (!isset($table_map[$type])) continue;
        $ids = array_unique($ids);
        $id_list = "'" . implode("','", array_map([$db_conn,'real_escape_string'], $ids)) . "'";
        $m = $table_map[$type];
        $extra_filter = ($type === 'company') ? (" AND " . godown_finance_filter_sql($db_conn)) : "";
        $sql = "SELECT {$m['id']} AS id, {$m['name']} AS name, {$m['mobile']} AS mobile FROM {$m['table']} WHERE {$m['id']} IN ($id_list)$extra_filter";
        $r = mysqli_query($db_conn, $sql);
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $seller_details[$type][$row['id']] = $row;
            }
        }
    }
}

// --------- Build location name lookup cache for SELLERS AND BUYERS ----------
$district_names = [];
$taluk_names = [];

// Collect unique district and taluk IDs from SELLERS AND BUYERS
$district_ids = [];
$taluk_ids = [];
foreach ($invoices as $inv) {
    // Seller districts and taluks
    if (isset($inv['seller_district_id']) && $inv['seller_district_id'] > 0) {
        $district_ids[] = (int)$inv['seller_district_id'];
    }
    if (isset($inv['seller_taluk_id']) && $inv['seller_taluk_id'] > 0) {
        $taluk_ids[] = (int)$inv['seller_taluk_id'];
    }
    
    // Buyer districts and taluks
    if (isset($inv['buyer_district_id']) && $inv['buyer_district_id'] > 0) {
        $district_ids[] = (int)$inv['buyer_district_id'];
    }
    if (isset($inv['buyer_taluk_id']) && $inv['buyer_taluk_id'] > 0) {
        $taluk_ids[] = (int)$inv['buyer_taluk_id'];
    }
}

// Fetch district names
if (!empty($district_ids)) {
    $district_ids = array_unique($district_ids);
    $id_list = implode(',', $district_ids);
    $dist_query = "SELECT id, dist_name FROM district WHERE id IN ($id_list)";
    $dist_res = mysqli_query($db_conn, $dist_query);
    if ($dist_res) {
        while ($row = mysqli_fetch_assoc($dist_res)) {
            $district_names[(int)$row['id']] = $row['dist_name'];
        }
    }
}

// Fetch taluk names
if (!empty($taluk_ids)) {
    $taluk_ids = array_unique($taluk_ids);
    $id_list = implode(',', $taluk_ids);
    $taluk_query = "SELECT id, taluk FROM taluk WHERE id IN ($id_list)";
    $taluk_res = mysqli_query($db_conn, $taluk_query);
    if ($taluk_res) {
        while ($row = mysqli_fetch_assoc($taluk_res)) {
            $taluk_names[(int)$row['id']] = $row['taluk'];
        }
    }
}

// --------- If no invoices, exit ----------
if (empty($invoices)) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "No invoices found for the selected criteria.";
    exit;
}

// Clear output buffer before Excel generation
ob_end_clean();

// --------- Build Excel file ----------
try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $row = 1;

    // Title
    $sheet->setCellValue('A1', 'B2B Sales Report');
    $sheet->mergeCells('A1:L1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $row += 2;

    // Headers (UPDATED with Buyer District and Buyer Taluk)
    $columns = [
        'S.No',
        'Seller Name',
        'Seller Type',
        'Seller Mobile',
        'Seller District',
        'Seller Taluk',
        'Invoice Number',
        'Buyer Type',
        'Buyer Name',
        'Buyer Mobile',
        'Buyer District',
        'Buyer Taluk',
        'Date',
        'Sub Amount',
        'Return Amount',
        'Net Amount'
    ];

    // Add product columns
    foreach ($products as $pid => $pname) {
        $columns[] = $pname . ' Qty';
        $columns[] = $pname . ' Price';
    }

    // Write headers
    $colIndex = 1;
    foreach ($columns as $title) {
        xlsx_set($sheet, $colIndex, $row, $title);
        $colIndex++;
    }

    // Style header row
    $lastCol = $colIndex - 1;
    $headerRange = xlsx_range($sheet, 1, $row, $lastCol, $row);
    
    $headerStyle = $sheet->getStyle($headerRange);
    $headerStyle->getFont()
        ->setBold(true)
        ->setSize(12)
        ->getColor()->setRGB('FFFFFF');
    $headerStyle->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('4472C4');
    $headerStyle->getBorders()
        ->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);
    $headerStyle->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $sheet->getRowDimension($row)->setRowHeight(25);
    $row++;

    // Data rows
    $serial = 1;
    $grand_total = 0.0;
    $product_totals_qty = array_fill_keys(array_keys($products), 0.0);
    $product_totals_amount = array_fill_keys(array_keys($products), 0.0);

    foreach ($invoices as $inv_id => $inv) {
        $sellerType = $inv['from_user_type'];
        $seller = $seller_details[$sellerType][$inv['from_user_id']] ?? ['name'=>'N/A','mobile'=>''];
        
        // Get SELLER district and taluk names
        $seller_district_name = 'N/A';
        $seller_taluk_name = 'N/A';
        
        if (isset($inv['seller_district_id']) && $inv['seller_district_id'] > 0) {
            $seller_district_name = $district_names[(int)$inv['seller_district_id']] ?? 'N/A';
        }
        
        if (isset($inv['seller_taluk_id']) && $inv['seller_taluk_id'] > 0) {
            $seller_taluk_name = $taluk_names[(int)$inv['seller_taluk_id']] ?? 'N/A';
        }
        
        // Get BUYER district and taluk names
        $buyer_district_name = 'N/A';
        $buyer_taluk_name = 'N/A';
        
        if (isset($inv['buyer_district_id']) && $inv['buyer_district_id'] > 0) {
            $buyer_district_name = $district_names[(int)$inv['buyer_district_id']] ?? 'N/A';
        }
        
        if (isset($inv['buyer_taluk_id']) && $inv['buyer_taluk_id'] > 0) {
            $buyer_taluk_name = $taluk_names[(int)$inv['buyer_taluk_id']] ?? 'N/A';
        }
        
        $inv_return_total = 0;
        
        if (!empty($returns_by_invoice[$inv_id]))
            foreach ($returns_by_invoice[$inv_id] as $ret)
                $inv_return_total += $ret['return_total'];
        $grand_return_total = ($grand_return_total ?? 0) + $inv_return_total;
        
        $cellValues = [
            $serial++,
            $seller['name'],
            ucwords(str_replace('_',' ', $sellerType)),
            $seller['mobile'],
            $seller_district_name,
            $seller_taluk_name,
            $inv['inv_number'],
            ucwords(str_replace('_',' ', $inv['to_user_type'])),
            $inv['buyer_name'],
            $inv['buyer_mobile'],
            $buyer_district_name,
            $buyer_taluk_name,
            date('d/M/Y', strtotime($inv['date'])),
            (float)$inv['sub_total'],
            $inv_return_total > 0 ? -(float)$inv_return_total : 0,
            (float)$inv['sub_total'] - $inv_return_total
        ];

        $grand_total += (float)$inv['sub_total'];
        
        // Add product quantities AND PRICES
        foreach ($products as $pid => $pname) {
            $qty = isset($product_data[$inv_id][$pid]) ? $product_data[$inv_id][$pid]['qty'] : 0.0;
            $price = isset($product_data[$inv_id][$pid]) ? $product_data[$inv_id][$pid]['price'] : 0.0;
            
            $product_totals_qty[$pid] += $qty;
            $product_totals_amount[$pid] += ($qty * $price);
            
            $cellValues[] = $qty;
            $cellValues[] = $price;
        }

        // Write row
        $colIndex = 1;
        foreach ($cellValues as $value) {
            xlsx_set($sheet, $colIndex, $row, $value);
            $colIndex++;
        }

        // Style data rows
        $rowRange = xlsx_range($sheet, 1, $row, $lastCol, $row);
        $rowStyle = $sheet->getStyle($rowRange);
        
        if ($serial % 2 == 0) {
            $rowStyle->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F8F9FA');
        }
        
        $rowStyle->getFont()->setSize(11);
        $rowStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Format currency (column 14 is now Total Amount)
        $totalColLetter = Coordinate::stringFromColumnIndex(14);
        $sheet->getStyle($totalColLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle($totalColLetter . $row)->getFont()->setBold(true)->getColor()->setRGB('0070C0');

        // Format product columns (starting from column 15)
        if (!empty($products)) {
            $productStartCol = 17;
            $colIdx = $productStartCol;
            foreach ($products as $pid => $pname) {
                // Quantity
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx) . $row)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx) . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $colIdx++;
                
                // Price
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx) . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx) . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $colIdx++;
            }
        }

        $sheet->getRowDimension($row)->setRowHeight(20);
        
        // Return sub-rows for this invoice
if (!empty($returns_by_invoice[$inv_id])) {
    foreach ($returns_by_invoice[$inv_id] as $ret) {
        $ret_id = $ret['return_id'];
        $retValues = [
            '',                                          // S.No
            '',                                          // Seller Name
            '',                                          // Seller Type
            '',                                          // Seller Mobile
            '',                                          // Seller District
            '',                                          // Seller Taluk
            '↩ Return: ' . $inv['inv_number'],          // Invoice
            ucwords(str_replace('_',' ',$ret['from_usertype'])), // Buyer Type
            $ret['returner_name'] ?? '',                 // Buyer/Returner Name
            '',                                          // Mobile
            '',                                          // Buyer District
            '',                                          // Buyer Taluk
            date('d/M/Y', strtotime($ret['return_date'])), // Return Date
            -(float)$ret['return_total'],                // Total Amount (negative)
            0,                                           // Return Amount (self)
            -(float)$ret['return_total'],                // Net Amount
        ];
        // Add return product qty/price (negative)
        foreach ($products as $pid => $pname) {
            $rqty  = $return_product_data[$ret_id][$pid]['qty']   ?? 0;
            $rprice= $return_product_data[$ret_id][$pid]['price']  ?? 0;
            $retValues[] = $rqty  > 0 ? -(float)$rqty  : 0;
            $retValues[] = $rprice > 0 ? (float)$rprice : 0;
        }
        $colIndex = 1;
        foreach ($retValues as $value) { xlsx_set($sheet, $colIndex, $row, $value); $colIndex++; }
        
        // Style return row (light red background)
        $retRange = xlsx_range($sheet, 1, $row, $lastCol, $row);
        $sheet->getStyle($retRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEBEE');
        $sheet->getStyle($retRange)->getFont()->setSize(10)->setItalic(true)->getColor()->setRGB('C0392B');
        $sheet->getStyle($retRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;
    }
}

        $row++;
    }

    // Totals row
    $totalsRow = $row;
    $sheet->setCellValue("A{$totalsRow}", 'GRAND TOTAL');
    $sheet->mergeCells("A{$totalsRow}:M{$totalsRow}");
    xlsx_set($sheet, 14, $totalsRow, $grand_total);
    xlsx_set($sheet, 15, $totalsRow, -($grand_return_total ?? 0));
    xlsx_set($sheet, 16, $totalsRow, $grand_total - ($grand_return_total ?? 0));

    // Product totals
    $col = 17;
    foreach ($products as $pid => $pname) {
        xlsx_set($sheet, $col, $totalsRow, $product_totals_qty[$pid]);
        $col++;
        xlsx_set($sheet, $col, $totalsRow, $product_totals_amount[$pid]);
        $col++;
    }

    // Style totals row
    $totalsRange = xlsx_range($sheet, 1, $totalsRow, $lastCol, $totalsRow);
    $totalsStyle = $sheet->getStyle($totalsRange);
    $totalsStyle->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
    $totalsStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('70AD47');
    $totalsStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);
    $totalsStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    // Format totals
    $totalColLetter = Coordinate::stringFromColumnIndex(14);
    $sheet->getStyle($totalColLetter . $totalsRow)->getNumberFormat()->setFormatCode('#,##0.00');
        
    $colIdx = 15;
    foreach ($products as $pid => $pname) {
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx) . $totalsRow)->getNumberFormat()->setFormatCode('#,##0');
        $colIdx++;
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx) . $totalsRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $colIdx++;
    }

    $sheet->getRowDimension($totalsRow)->setRowHeight(25);

    // Summary
    $row += 2;
    $sheet->setCellValue("A{$row}", 'Overall Products Sold:');
    $sheet->setCellValue("C{$row}", array_sum($product_totals_qty) . ' units');
    
    $row++;
    $sheet->setCellValue("A{$row}", 'Total Product Revenue:');
    $sheet->setCellValue("C{$row}", '₹ ' . number_format(array_sum($product_totals_amount), 2));
    
    $summaryStartRow = $row - 1;
    for ($i = $summaryStartRow; $i <= $row; $i++) {
        $sheet->getStyle("A{$i}")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('D83B01');
        $sheet->getStyle("C{$i}")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('D83B01');
    }
    
    $sheet->getStyle("A{$summaryStartRow}:C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2E8');
    $sheet->getStyle("A{$summaryStartRow}:C{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);

    // Auto-size columns
    for ($c = 1; $c <= $lastCol; $c++) {
        $letter = Coordinate::stringFromColumnIndex($c);
        $sheet->getColumnDimension($letter)->setAutoSize(true);
        
        switch ($c) {
            case 1:
                $sheet->getColumnDimension($letter)->setWidth(8);
                break;
            case 2:
            case 5:
            case 6:
            case 9:
            case 11:
            case 12:
                $sheet->getColumnDimension($letter)->setWidth(20);
                break;
            case 7:
            case 14:
                $sheet->getColumnDimension($letter)->setWidth(15);
                break;
            default:
                if ($c >= 15) {
                    $sheet->getColumnDimension($letter)->setWidth(12);
                }
        }
    }

    // Generate filename
    $filename = "B2B_Sales_Report_{$from_date}_to_{$to_date}.xlsx";

    // Output Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
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
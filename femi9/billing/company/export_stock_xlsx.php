<?php
/**
 * Stock Report Excel Export
 * Exports stock details to Excel format with all filters applied
 * Performance optimized for large datasets
 */

ob_start();

include("checksession.php");
include("config.php");

mysqli_set_charset($db_conn, 'utf8mb4');
mysqli_query($db_conn, "SET collation_connection = 'utf8mb4_general_ci'");
mysqli_query($db_conn, "SET collation_server = 'utf8mb4_general_ci'");

// Get filter parameters from POST
$selected_state_id = isset($_POST['state_id']) ? (int)$_POST['state_id'] : 0;
$selected_district = isset($_POST['district_id']) ? (int)$_POST['district_id'] : 0;
$selected_taluk = isset($_POST['taluk_id']) ? (int)$_POST['taluk_id'] : 0;
$selected_user_type = isset($_POST['user_type']) ? trim($_POST['user_type']) : '';
$selected_user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
$search = isset($_POST['q']) ? trim($_POST['q']) : '';

if($selected_state_id <= 0) {
    die("Error: State ID is required");
}

// Get state name for filename
$state_name = "Stock";
$stmt = $db_conn->prepare("SELECT st_name FROM state WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $selected_state_id);
$stmt->execute();
$result = $stmt->get_result();
if($row = $result->fetch_assoc()) {
    $state_name = $row['st_name'];
}
$stmt->close();

// Prepare filename
$filename = "Stock_Report_" . str_replace(' ', '_', $state_name) . "_" . date('Y-m-d_His') . ".xls";

// Clear output buffer
ob_end_clean();

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Start Excel output
echo "\xEF\xBB\xBF"; // UTF-8 BOM

// Get all products
$products = [];
$product_query = "SELECT id, productName FROM products ORDER BY id ASC";
$product_result = mysqli_query($db_conn, $product_query);

if($product_result) {
    while($pr = mysqli_fetch_assoc($product_result)) {
        $products[$pr['id']] = $pr['productName'];
    }
}

// Build filter conditions
$user_type_filter = "";
$user_id_filter = "";

if(!empty($selected_user_type)) {
    $user_type_filter = " AND s.user_type = '" . $db_conn->real_escape_string($selected_user_type) . "'";
}

if(!empty($selected_user_id)) {
    $user_id_filter = " AND s.user_id = '" . $db_conn->real_escape_string($selected_user_id) . "'";
}

// Build user table UNION for geo filtering
$district_condition = !empty($selected_district) ? " AND district_id = " . (int)$selected_district : "";
$taluk_condition = !empty($selected_taluk) ? " AND taluk_id = " . (int)$selected_taluk : "";

$user_union_parts = [];
$user_union_parts[] = "SELECT temp_id, 'distributor' AS user_type, 
                       CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci AS name,
                       CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci AS mobile
                       FROM distributor 
                       WHERE CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id . $district_condition . $taluk_condition;

$user_union_parts[] = "SELECT temp_id, 'super_distributor',
                       CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                       CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                       FROM super_distributor 
                       WHERE CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id . $district_condition . $taluk_condition;

$user_union_parts[] = "SELECT temp_id, 'stockiest',
                       CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                       CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                       FROM stockiest 
                       WHERE state_id = " . (int)$selected_state_id . $district_condition . 
                       (!empty($selected_taluk) ? " AND CAST(taluk_id AS UNSIGNED) = " . (int)$selected_taluk : "");

$user_union_parts[] = "SELECT temp_id, 'super_stockiest',
                       CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                       CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                       FROM super_stockiest 
                       WHERE state_id = " . (int)$selected_state_id . $district_condition;

$user_union_query = implode(" UNION ALL ", $user_union_parts);

// Search filter
$search_esc = $db_conn->real_escape_string($search);
$search_filter = '';
if ($search_esc !== '') {
    $search_filter = "
      AND (
          users.name LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
       OR users.mobile LIKE '%$search_esc%'
       OR users.user_type LIKE '%$search_esc%'
       OR s.user_id LIKE '%$search_esc%'
       OR EXISTS (
            SELECT 1
            FROM products p
            WHERE p.id = s.product_id
              AND p.productName LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
       )
      )
    ";
}

// Get stock users
$stock_query = "
    SELECT 
        s.user_type,
        s.user_id,
        users.name as user_name,
        users.mobile as user_mobile
    FROM stock s
    INNER JOIN ($user_union_query) users 
        ON s.user_id = users.temp_id AND s.user_type = users.user_type
    WHERE 1=1
    $user_type_filter
    $user_id_filter
    $search_filter
    GROUP BY s.user_type, s.user_id, users.name, users.mobile
    ORDER BY users.user_type ASC, users.name ASC
";

$result = mysqli_query($db_conn, $stock_query);

$stock_users = [];
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $stock_users[] = $row;
    }
}

// Get stock quantities for these users
$stock_data = [];
if(!empty($stock_users)) {
    $user_conditions = [];
    foreach($stock_users as $user) {
        $user_type_esc = $db_conn->real_escape_string($user['user_type']);
        $user_id_esc = $db_conn->real_escape_string($user['user_id']);
        $user_conditions[] = "(user_type = '$user_type_esc' AND user_id = '$user_id_esc')";
    }
    
    $user_filter = implode(" OR ", $user_conditions);
    
    $stock_detail_query = "
        SELECT 
            user_type,
            user_id,
            product_id,
            opening_qty,
            input_qty,
            sales_qty,
            sent_qty,
            returnqty,
            closing_qty
        FROM stock
        WHERE ($user_filter)
    ";
    
    $stock_detail_result = mysqli_query($db_conn, $stock_detail_query);
    
    if($stock_detail_result) {
        while($row = mysqli_fetch_assoc($stock_detail_result)) {
            $key = $row['user_type'] . '-' . $row['user_id'];
            $stock_data[$key][$row['product_id']] = $row;
        }
    }
}

// Output Excel Header Row
echo '<table border="1">';
echo '<thead>';
echo '<tr style="background-color: #4472C4; color: white; font-weight: bold;">';
echo '<th>S.No</th>';
echo '<th>User Type</th>';
echo '<th>User Name</th>';
echo '<th>Mobile Number</th>';
echo '<th>User ID</th>';

// Product columns - Closing Qty
foreach($products as $pr_id => $pr_name) {
    echo '<th>' . htmlspecialchars($pr_name) . ' (Closing)</th>';
}

echo '<th>Total Stock</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

// Output data rows
$serial = 1;
$product_totals = array_fill_keys(array_keys($products), 0);
$grand_total_stock = 0;

foreach($stock_users as $user) {
    $user_type_display = ucwords(str_replace('_', ' ', $user['user_type']));
    $key = $user['user_type'] . '-' . $user['user_id'];
    $user_total = 0;
    
    echo '<tr>';
    echo '<td>' . $serial++ . '</td>';
    echo '<td>' . htmlspecialchars($user_type_display) . '</td>';
    echo '<td>' . htmlspecialchars($user['user_name']) . '</td>';
    echo '<td>' . htmlspecialchars($user['user_mobile']) . '</td>';
    echo '<td>' . htmlspecialchars($user['user_id']) . '</td>';
    
    // Product quantities
    foreach($products as $pr_id => $pr_name) {
        $stock_info = isset($stock_data[$key][$pr_id]) ? $stock_data[$key][$pr_id] : null;
        $closing_qty = $stock_info ? (int)$stock_info['closing_qty'] : 0;
        $product_totals[$pr_id] += $closing_qty;
        $user_total += $closing_qty;
        
        // Color code based on stock level
        if($closing_qty > 10) {
            echo '<td style="background-color: #C6EFCE; color: #006100;">' . $closing_qty . '</td>';
        } elseif($closing_qty > 0) {
            echo '<td style="background-color: #FFEB9C; color: #9C5700;">' . $closing_qty . '</td>';
        } else {
            echo '<td style="background-color: #FFC7CE; color: #9C0006;">0</td>';
        }
    }
    
    echo '<td style="font-weight: bold;">' . $user_total . '</td>';
    echo '</tr>';
    
    $grand_total_stock += $user_total;
}

// Footer totals
echo '<tr style="background-color: #D9E1F2; font-weight: bold;">';
echo '<td colspan="5">TOTAL</td>';

foreach($product_totals as $pr_id => $total) {
    echo '<td>' . $total . '</td>';
}

echo '<td>' . $grand_total_stock . '</td>';
echo '</tr>';

echo '</tbody>';
echo '</table>';

// Summary information
echo '<br><br>';
echo '<table border="1">';
echo '<tr><td colspan="2" style="background-color: #4472C4; color: white; font-weight: bold;">EXPORT SUMMARY</td></tr>';
echo '<tr><td><b>State:</b></td><td>' . htmlspecialchars($state_name) . '</td></tr>';
echo '<tr><td><b>Export Date:</b></td><td>' . date('Y-m-d H:i:s') . '</td></tr>';
echo '<tr><td><b>Total Users:</b></td><td>' . count($stock_users) . '</td></tr>';
echo '<tr><td><b>Total Products:</b></td><td>' . count($products) . '</td></tr>';
echo '<tr><td><b>Total Stock Units:</b></td><td>' . $grand_total_stock . '</td></tr>';

// Filters applied
if(!empty($selected_district)) {
    $dist_query = "SELECT dist_name FROM district WHERE id = ?";
    $stmt = $db_conn->prepare($dist_query);
    $stmt->bind_param("i", $selected_district);
    $stmt->execute();
    $res = $stmt->get_result();
    if($row = $res->fetch_assoc()) {
        echo '<tr><td><b>District Filter:</b></td><td>' . htmlspecialchars($row['dist_name']) . '</td></tr>';
    }
    $stmt->close();
}

if(!empty($selected_taluk)) {
    $taluk_query = "SELECT taluk FROM taluk WHERE id = ?";
    $stmt = $db_conn->prepare($taluk_query);
    $stmt->bind_param("i", $selected_taluk);
    $stmt->execute();
    $res = $stmt->get_result();
    if($row = $res->fetch_assoc()) {
        echo '<tr><td><b>Taluk Filter:</b></td><td>' . htmlspecialchars($row['taluk']) . '</td></tr>';
    }
    $stmt->close();
}

if(!empty($selected_user_type)) {
    echo '<tr><td><b>User Type Filter:</b></td><td>' . htmlspecialchars(ucwords(str_replace('_', ' ', $selected_user_type))) . '</td></tr>';
}

if($search !== '') {
    echo '<tr><td><b>Search Filter:</b></td><td>' . htmlspecialchars($search) . '</td></tr>';
}

echo '</table>';

exit;
?>
<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Increase memory and execution time for large exports
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

ob_start();

include("checksession.php");
include("config.php");

// Set charset
if (!$db_conn->set_charset('utf8mb4')) {
    die("Error loading character set utf8mb4: " . $db_conn->error);
}

// Get filters from POST
$from_date = $_POST['frdate'] ?? date('Y-m-d', strtotime('-7 days'));
$to_date = $_POST['todate'] ?? date('Y-m-d');
$selected_seller_type = $_POST['seller_type'] ?? '';
$selected_distributor_category = isset($_POST['distributor_category']) ? (int)$_POST['distributor_category'] : 0;
$selected_super_distributor_category = isset($_POST['super_distributor_category']) ? (int)$_POST['super_distributor_category'] : 0;
$selected_amount_range = $_POST['amount_range'] ?? '';
$search = trim($_POST['q'] ?? '');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    die('Invalid date format');
}

// Validate seller type
$allowed_seller_types = ['distributor', 'super_distributor'];
if ($selected_seller_type !== '' && !in_array($selected_seller_type, $allowed_seller_types)) {
    die('Invalid seller type');
}

// Validate amount range
$allowed_ranges = ['50000-99999', '100000-149999', '150000-above'];
if ($selected_amount_range !== '' && !in_array($selected_amount_range, $allowed_ranges)) {
    die('Invalid amount range');
}

// Get all products
$products = [];
$stmt_products = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
$stmt_products->execute();
$product_result = $stmt_products->get_result();

while ($pr = $product_result->fetch_assoc()) {
    $products[(int)$pr['id']] = $pr['productName'];
}
$stmt_products->close();

// Fetch sellers
$sellers = [];
$seller_table_map = [
    'distributor' => 'distributor',
    'super_distributor' => 'super_distributor'
];

// Determine which seller types to query
$seller_types_to_query = [];
if ($selected_seller_type === '') {
    $seller_types_to_query = array_keys($seller_table_map);
} else {
    $seller_types_to_query = [$selected_seller_type];
}

// Fetch sellers from each applicable table
foreach ($seller_types_to_query as $type) {
    $table = $seller_table_map[$type];
    
    if ($type === 'distributor') {
        $sql = "SELECT d.temp_id as seller_id,
                       d.name as seller_name,
                       d.mobile_number as seller_mobile,
                       ? as seller_type,
                       dc.name as category_name
                FROM {$table} d
                LEFT JOIN distributor_category dc ON d.category_id = dc.id
                WHERE 1=1";
        
        $params = [$type];
        $types = "s";
        
        if ($selected_distributor_category > 0) {
            $sql .= " AND d.category_id = ?";
            $params[] = $selected_distributor_category;
            $types .= "i";
        }
        
    } else {
        $sql = "SELECT sd.temp_id as seller_id,
                       sd.name as seller_name,
                       sd.mobile_number as seller_mobile,
                       ? as seller_type,
                       sdc.name as category_name
                FROM {$table} sd
                LEFT JOIN super_distributor_category sdc ON sd.category_id = sdc.id
                WHERE 1=1";
        
        $params = [$type];
        $types = "s";
        
        if ($selected_super_distributor_category > 0) {
            $sql .= " AND sd.category_id = ?";
            $params[] = $selected_super_distributor_category;
            $types .= "i";
        }
    }
    
    $stmt_sellers = $db_conn->prepare($sql);
    if (!$stmt_sellers) {
        die("Prepare failed for {$type}: " . $db_conn->error);
    }
    
    $stmt_sellers->bind_param($types, ...$params);
    $stmt_sellers->execute();
    $seller_result = $stmt_sellers->get_result();
    
    while ($row = $seller_result->fetch_assoc()) {
        $sellers[] = $row;
    }
    $stmt_sellers->close();
}

// Calculate totals for each seller
$sellers_with_data = [];

foreach ($sellers as $seller) {
    $seller_id = $seller['seller_id'];
    $seller_type = $seller['seller_type'];
    
    // Get total amount
    $stmt_total = $db_conn->prepare("
        SELECT COALESCE(SUM(total), 0) as total_amount
        FROM user_invoice
        WHERE from_user_id = ?
        AND from_user_type = ?
        AND to_user_type = 'shop'
        AND date BETWEEN ? AND ?
        AND sub_total > 0
    ");
    
    $stmt_total->bind_param("ssss", $seller_id, $seller_type, $from_date, $to_date);
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total_row = $total_result->fetch_assoc();
    $seller['total_amount'] = (float)$total_row['total_amount'];
    $stmt_total->close();
    
    // Apply amount range filter
    $include_seller = false;
    if ($selected_amount_range !== '') {
        switch ($selected_amount_range) {
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
    
    // Only include sellers with sales
    if ($include_seller) {
        // Apply search filter
        if ($search !== '') {
            if (mb_stripos($seller['seller_name'], $search) !== false || 
                mb_stripos($seller['seller_mobile'], $search) !== false ||
                mb_stripos($seller['seller_type'], $search) !== false) {
                $sellers_with_data[] = $seller;
            }
        } else {
            $sellers_with_data[] = $seller;
        }
    }
}

// Sort by total amount DESCENDING
usort($sellers_with_data, function($a, $b) {
    return $b['total_amount'] <=> $a['total_amount'];
});

// Get product quantities for all sellers
$seller_product_quantities = [];

foreach ($sellers_with_data as $seller) {
    $seller_id = $seller['seller_id'];
    $seller_type = $seller['seller_type'];
    
    $stmt_qty = $db_conn->prepare("
        SELECT uii.pr_id, SUM(uii.qty) as total_qty
        FROM user_invoice ui
        INNER JOIN user_invoice_items uii ON ui.inv_id = uii.inv_id
        WHERE ui.from_user_id = ?
        AND ui.from_user_type = ?
        AND ui.to_user_type = 'shop'
        AND ui.date BETWEEN ? AND ?
        AND ui.sub_total > 0
        GROUP BY uii.pr_id
    ");
    
    $stmt_qty->bind_param("ssss", $seller_id, $seller_type, $from_date, $to_date);
    $stmt_qty->execute();
    $qty_result = $stmt_qty->get_result();
    
    while ($qty_row = $qty_result->fetch_assoc()) {
        $seller_product_quantities[$seller_id][(int)$qty_row['pr_id']] = (int)$qty_row['total_qty'];
    }
    $stmt_qty->close();
}

// Generate filename
$filename = "Distributor_Sales_Report_" . date('Y-m-d_His') . ".xls";

// Clear any previous output
ob_end_clean();

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

// Start output buffering for Excel
ob_start();

// Output BOM for UTF-8
echo "\xEF\xBB\xBF";

$seller_type_labels = [
    'distributor' => 'Distributor',
    'super_distributor' => 'Super Distributor'
];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        .product-header {
            background-color: #B4C7E7;
            font-weight: bold;
            text-align: center;
        }
        .title {
            background-color: #4472C4;
            color: white;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            padding: 15px;
        }
        .subtitle {
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }
        .total-row {
            background-color: #E7E6E6;
            font-weight: bold;
        }
        .number {
            text-align: right;
        }
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    <table>
        <!-- Title Row -->
        <tr>
            <td colspan="<?= 6 + count($products) ?>" class="title">
                Distributor &amp; Super Distributor Sales Report
            </td>
        </tr>
        
        <!-- Date Range Row -->
        <tr>
            <td colspan="<?= 6 + count($products) ?>" class="subtitle">
                Period: <?= htmlspecialchars($from_date) ?> to <?= htmlspecialchars($to_date) ?>
            </td>
        </tr>
        
        <!-- Empty Row -->
        <tr>
            <td colspan="<?= 6 + count($products) ?>">&nbsp;</td>
        </tr>
        
        <!-- Header Row -->
        <tr>
            <th>S.No</th>
            <th>Seller Name</th>
            <th>Seller Type</th>
            <th>Category</th>
            <th>Mobile Number</th>
            <th>Total Amount (₹)</th>
            <?php foreach ($products as $pr_id => $pr_name): ?>
                <th class="product-header"><?= htmlspecialchars($pr_name) ?></th>
            <?php endforeach; ?>
        </tr>
        
        <!-- Data Rows -->
        <?php
        $serial = 1;
        $grand_total = 0.0;
        $product_totals = array_fill_keys(array_keys($products), 0);
        
        foreach ($sellers_with_data as $seller):
            $seller_type_display = $seller_type_labels[$seller['seller_type']] ?? ucwords(str_replace('_', ' ', $seller['seller_type']));
            $grand_total += $seller['total_amount'];
            $category_display = !empty($seller['category_name']) ? $seller['category_name'] : '-';
        ?>
        <tr>
            <td class="center"><?= $serial++ ?></td>
            <td><?= htmlspecialchars($seller['seller_name']) ?></td>
            <td><?= htmlspecialchars($seller_type_display) ?></td>
            <td><?= htmlspecialchars($category_display) ?></td>
            <td><?= htmlspecialchars($seller['seller_mobile']) ?></td>
            <td class="number"><?= inr_format($seller['total_amount'], 2) ?></td>
            
            <?php foreach ($products as $pr_id => $pr_name): 
                $qty = $seller_product_quantities[$seller['seller_id']][$pr_id] ?? 0;
                $product_totals[$pr_id] += $qty;
            ?>
            <td class="center"><?= $qty > 0 ? $qty : '-' ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        
        <!-- Total Row -->
        <tr class="total-row">
            <td colspan="5" class="number">Grand Total:</td>
            <td class="number"><?= inr_format($grand_total, 2) ?></td>
            <?php foreach ($product_totals as $pr_id => $total): ?>
            <td class="center"><?= $total ?></td>
            <?php endforeach; ?>
        </tr>
        
        <!-- Summary Row -->
        <tr>
            <td colspan="<?= 6 + count($products) ?>">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="<?= 6 + count($products) ?>" class="subtitle">
                Total Records: <?= count($sellers_with_data) ?> | Generated on: <?= date('Y-m-d H:i:s') ?>
            </td>
        </tr>
    </table>
</body>
</html>
<?php
// Get the buffer content and send it
$content = ob_get_clean();
echo $content;
exit;
?>
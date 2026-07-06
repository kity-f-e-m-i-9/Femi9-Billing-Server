<?php
/**
 * Export Category & Channel Summary to Excel (with Returns)
 * 
 * Exports aggregated sales data by:
 * - User Type Categories (Super Stockist, Stockist, etc.)
 * - OT Channels
 * - Product-wise breakdown
 * - Returns data with net calculations
 * 
 * @version 2.0
 * @author Femi9 Development Team
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Session and config
require_once("checksession.php");
require_once("config.php");

// Set UTF-8 charset
if (!mysqli_set_charset($db_conn, 'utf8mb4')) {
    die("Error loading character set utf8mb4: " . mysqli_error($db_conn));
}

// ============================================================================
// INPUT VALIDATION
// ============================================================================

function validateDate(?string $date, string $default): string {
    if (empty($date)) {
        return $default;
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $default;
    }
    
    return date('Y-m-d', $timestamp);
}

// Get date range
$numberOfDays = (int)date('t');
$current_month = date('m');
$default_from_date = date("Y-{$current_month}-01");
$default_to_date = date("Y-{$current_month}-{$numberOfDays}");

$from_date = validateDate($_REQUEST['frdate'] ?? null, $default_from_date);
$to_date = validateDate($_REQUEST['todate'] ?? null, $default_to_date);

// View type
$view_type = 'net';
if (isset($_REQUEST['view_type']) && in_array($_REQUEST['view_type'], ['net', 'sales', 'returns'], true)) {
    $view_type = $_REQUEST['view_type'];
}

// ============================================================================
// FETCH PRODUCTS
// ============================================================================
$products = [];
$stmt_products = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
if (!$stmt_products) {
    die("Product query preparation failed: " . htmlspecialchars($db_conn->error, ENT_QUOTES, 'UTF-8'));
}
$stmt_products->execute();
$result_products = $stmt_products->get_result();
while ($pr = $result_products->fetch_assoc()) {
    $products[(int)$pr['id']] = $pr['productName'];
}
$stmt_products->close();

// ============================================================================
// INITIALIZE DATA STRUCTURE
// ============================================================================
$categories = [];

// User Type Categories
$user_types = [
    'super_stockiest' => 'Super Stockist',
    'stockiest' => 'Stockist',
    'super_distributor' => 'Super Distributor',
    'distributor' => 'Distributor',
    'customer' => 'Customer',
    'shop' => 'Shop'
];

foreach ($user_types as $type_key => $type_label) {
    $categories[$type_key] = [
        'category_type' => 'User Type',
        'category_name' => $type_label,
        'category_key' => $type_key,
        'sales_amount' => 0,
        'return_amount' => 0,
        'sales_products' => array_fill_keys(array_keys($products), 0),
        'return_products' => array_fill_keys(array_keys($products), 0)
    ];
}

// ============================================================================
// FETCH USER TYPE SALES DATA
// ============================================================================
$query_user_sales = "
    SELECT 
        ui.to_user_type,
        uii.pr_id,
        SUM(uii.qty) as total_qty,
        SUM(CAST(uii.subtotal AS DECIMAL(10,2))) as total_amount
    FROM user_invoice ui
    INNER JOIN user_invoice_items uii ON ui.inv_id = uii.inv_id
    WHERE ui.date BETWEEN ? AND ?
        AND ui.from_user_type = ?
        AND ui.sub_total > 0
    GROUP BY ui.to_user_type, uii.pr_id
";

$stmt_user_sales = $db_conn->prepare($query_user_sales);
if (!$stmt_user_sales) {
    die("User sales query failed: " . htmlspecialchars($db_conn->error, ENT_QUOTES, 'UTF-8'));
}

$stmt_user_sales->bind_param("sss", $from_date, $to_date, $Login_user_TYPEvl);
$stmt_user_sales->execute();
$result_user_sales = $stmt_user_sales->get_result();

while ($row = $result_user_sales->fetch_assoc()) {
    $user_type = $row['to_user_type'];
    $pr_id = (int)$row['pr_id'];
    $qty = (int)$row['total_qty'];
    $amount = (float)$row['total_amount'];
    
    if (isset($categories[$user_type])) {
        $categories[$user_type]['sales_amount'] += $amount;
        if (isset($categories[$user_type]['sales_products'][$pr_id])) {
            $categories[$user_type]['sales_products'][$pr_id] += $qty;
        }
    }
}
$stmt_user_sales->close();

// ============================================================================
// FETCH CUSTOMER SALES DATA
// ============================================================================
$query_customer_sales = "
    SELECT 
        ii.pr_id,
        SUM(ii.qty) as total_qty,
        SUM(CAST(ii.subtotal AS DECIMAL(10,2))) as total_amount
    FROM invoice i
    INNER JOIN invoice_items ii ON i.inv_id = ii.inv_id
    WHERE i.date BETWEEN ? AND ?
        AND i.user_type = ?
        AND i.sub_total > 0
    GROUP BY ii.pr_id
";

$stmt_customer_sales = $db_conn->prepare($query_customer_sales);
if (!$stmt_customer_sales) {
    die("Customer sales query failed: " . htmlspecialchars($db_conn->error, ENT_QUOTES, 'UTF-8'));
}

$stmt_customer_sales->bind_param("sss", $from_date, $to_date, $Login_user_TYPEvl);
$stmt_customer_sales->execute();
$result_customer_sales = $stmt_customer_sales->get_result();

while ($row = $result_customer_sales->fetch_assoc()) {
    $pr_id = (int)$row['pr_id'];
    $qty = (int)$row['total_qty'];
    $amount = (float)$row['total_amount'];
    
    $categories['customer']['sales_amount'] += $amount;
    if (isset($categories['customer']['sales_products'][$pr_id])) {
        $categories['customer']['sales_products'][$pr_id] += $qty;
    }
}
$stmt_customer_sales->close();

// ============================================================================
// FETCH OT CHANNELS
// ============================================================================
$stmt_ot_channels = $db_conn->prepare("SELECT id, cat FROM ot_cat ORDER BY cat ASC");
if ($stmt_ot_channels) {
    $stmt_ot_channels->execute();
    $result_ot_channels = $stmt_ot_channels->get_result();
    
    while ($ch = $result_ot_channels->fetch_assoc()) {
        $cat_key = 'ot_' . $ch['id'];
        $categories[$cat_key] = [
            'category_type' => 'OT Channel',
            'category_name' => $ch['cat'],
            'category_key' => $ch['cat'],
            'sales_amount' => 0,
            'return_amount' => 0,
            'sales_products' => array_fill_keys(array_keys($products), 0),
            'return_products' => array_fill_keys(array_keys($products), 0)
        ];
    }
    $stmt_ot_channels->close();
}

// ============================================================================
// FETCH OT CHANNEL SALES DATA
// ============================================================================
$query_ot_sales = "
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
";

$stmt_ot_sales = $db_conn->prepare($query_ot_sales);
if ($stmt_ot_sales) {
    $stmt_ot_sales->bind_param("ss", $from_date, $to_date);
    $stmt_ot_sales->execute();
    $result_ot_sales = $stmt_ot_sales->get_result();
    
    while ($row = $result_ot_sales->fetch_assoc()) {
        $cat_name = $row['cat'];
        $pr_id = (int)$row['prid'];
        $qty = (int)$row['total_qty'];
        $amount = (float)$row['total_amount'];
        
        foreach ($categories as $key => &$cat) {
            if ($cat['category_type'] === 'OT Channel' && $cat['category_key'] === $cat_name) {
                $cat['sales_amount'] += $amount;
                if (isset($cat['sales_products'][$pr_id])) {
                    $cat['sales_products'][$pr_id] += $qty;
                }
                break;
            }
        }
        unset($cat);
    }
    $stmt_ot_sales->close();
}

// ============================================================================
// FETCH OT CHANNEL RETURNS DATA
// ============================================================================
$query_ot_returns = "
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
";

$stmt_ot_returns = $db_conn->prepare($query_ot_returns);
if ($stmt_ot_returns) {
    $stmt_ot_returns->bind_param("ss", $from_date, $to_date);
    $stmt_ot_returns->execute();
    $result_ot_returns = $stmt_ot_returns->get_result();
    
    while ($row = $result_ot_returns->fetch_assoc()) {
        $cat_name = $row['cat'];
        $pr_id = (int)$row['prid'];
        $qty = (int)$row['total_return_qty'];
        $amount = (float)$row['total_return_amount'];
        
        foreach ($categories as $key => &$cat) {
            if ($cat['category_type'] === 'OT Channel' && $cat['category_key'] === $cat_name) {
                $cat['return_amount'] += $amount;
                if (isset($cat['return_products'][$pr_id])) {
                    $cat['return_products'][$pr_id] += $qty;
                }
                break;
            }
        }
        unset($cat);
    }
    $stmt_ot_returns->close();
}

// ============================================================================
// CALCULATE TOTALS
// ============================================================================
$user_type_sales_amount = 0;
$user_type_return_amount = 0;
$user_type_sales_products = array_fill_keys(array_keys($products), 0);
$user_type_return_products = array_fill_keys(array_keys($products), 0);

$ot_channel_sales_amount = 0;
$ot_channel_return_amount = 0;
$ot_channel_sales_products = array_fill_keys(array_keys($products), 0);
$ot_channel_return_products = array_fill_keys(array_keys($products), 0);

$grand_sales_amount = 0;
$grand_return_amount = 0;
$grand_sales_products = array_fill_keys(array_keys($products), 0);
$grand_return_products = array_fill_keys(array_keys($products), 0);

foreach ($categories as $cat) {
    if ($cat['category_type'] === 'User Type') {
        $user_type_sales_amount += $cat['sales_amount'];
        $user_type_return_amount += $cat['return_amount'];
        foreach ($cat['sales_products'] as $pr_id => $qty) {
            $user_type_sales_products[$pr_id] += $qty;
        }
        foreach ($cat['return_products'] as $pr_id => $qty) {
            $user_type_return_products[$pr_id] += $qty;
        }
    } else {
        $ot_channel_sales_amount += $cat['sales_amount'];
        $ot_channel_return_amount += $cat['return_amount'];
        foreach ($cat['sales_products'] as $pr_id => $qty) {
            $ot_channel_sales_products[$pr_id] += $qty;
        }
        foreach ($cat['return_products'] as $pr_id => $qty) {
            $ot_channel_return_products[$pr_id] += $qty;
        }
    }
    
    $grand_sales_amount += $cat['sales_amount'];
    $grand_return_amount += $cat['return_amount'];
    foreach ($cat['sales_products'] as $pr_id => $qty) {
        $grand_sales_products[$pr_id] += $qty;
    }
    foreach ($cat['return_products'] as $pr_id => $qty) {
        $grand_return_products[$pr_id] += $qty;
    }
}

// Filter out empty categories
$categories = array_filter($categories, function($cat) {
    return $cat['sales_amount'] > 0 || $cat['return_amount'] > 0 || 
           array_sum($cat['sales_products']) > 0 || array_sum($cat['return_products']) > 0;
});

// ============================================================================
// GENERATE EXCEL OUTPUT
// ============================================================================

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"Sales_Category_Summary_" . date('Y-m-d_His') . ".xls\"");
header("Pragma: no-cache");
header("Expires: 0");

// View type label
$view_label = 'All (Net)';
if ($view_type === 'sales') {
    $view_label = 'Sales Only';
} elseif ($view_type === 'returns') {
    $view_label = 'Returns Only';
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
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
        
        .header-info {
            background-color: #E7E6E6;
            font-weight: bold;
            padding: 5px;
            margin-bottom: 10px;
        }
        
        .category-type-user {
            background-color: #DEEBF7;
            font-weight: bold;
        }
        
        .category-type-ot {
            background-color: #FFF2CC;
            font-weight: bold;
        }
        
        .product-col {
            background-color: #E7E6E6;
            text-align: center;
        }
        
        .subtotal-row {
            background-color: #FFC000;
            font-weight: bold;
        }
        
        .total-row {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }
        
        .number {
            text-align: right;
        }
        
        .center {
            text-align: center;
        }
        
        .breakdown {
            font-size: 0.85em;
            color: #666;
        }
    </style>
</head>
<body>

<!-- Report Header -->
<div class="header-info">
    <h2>Sales From Company - Category & Channel Summary</h2>
    <p><strong>Business:</strong> <?= htmlspecialchars($business_name ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>Period:</strong> <?= date('d M Y', strtotime($from_date)); ?> to <?= date('d M Y', strtotime($to_date)); ?></p>
    <p><strong>View Type:</strong> <?= htmlspecialchars($view_label, ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>Generated On:</strong> <?= date('d M Y H:i:s'); ?></p>
</div>

<table>
    <thead>
        <tr>
            <th>Category Type</th>
            <th>Category Name</th>
            <th><?php 
                if ($view_type === 'sales') echo 'Sales Amount (₹)';
                elseif ($view_type === 'returns') echo 'Return Amount (₹)';
                else echo 'Net Amount (₹)';
            ?></th>
            <?php if ($view_type === 'net'): ?>
            <th>Sales Amount (₹)</th>
            <th>Return Amount (₹)</th>
            <?php endif; ?>
            <?php foreach ($products as $pr_id => $pr_name): ?>
            <th><?= htmlspecialchars($pr_name, ENT_QUOTES, 'UTF-8'); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php 
        if (!empty($categories)) {
            $current_type = '';
            foreach ($categories as $cat): 
                $is_new_type = ($current_type !== $cat['category_type']);
                $current_type = $cat['category_type'];
                
                $row_class = $cat['category_type'] === 'User Type' ? 'category-type-user' : 'category-type-ot';
                
                // Calculate display values
                if ($view_type === 'sales') {
                    $display_amount = $cat['sales_amount'];
                } elseif ($view_type === 'returns') {
                    $display_amount = $cat['return_amount'];
                } else {
                    $display_amount = $cat['sales_amount'] - $cat['return_amount'];
                }
        ?>
        <tr>
            <td class="<?= $row_class; ?>">
                <?php if ($is_new_type): ?>
                    <?= htmlspecialchars($cat['category_type'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="number"><?= inr_format($display_amount, 2); ?></td>
            <?php if ($view_type === 'net'): ?>
            <td class="number"><?= inr_format($cat['sales_amount'], 2); ?></td>
            <td class="number"><?= inr_format($cat['return_amount'], 2); ?></td>
            <?php endif; ?>
            <?php foreach ($products as $pr_id => $pr_name): 
                $sales_qty = $cat['sales_products'][$pr_id] ?? 0;
                $return_qty = $cat['return_products'][$pr_id] ?? 0;
                
                if ($view_type === 'sales') {
                    $display_qty = $sales_qty;
                } elseif ($view_type === 'returns') {
                    $display_qty = $return_qty;
                } else {
                    $display_qty = $sales_qty - $return_qty;
                }
            ?>
            <td class="center">
                <?= $display_qty != 0 ? inr_format($display_qty, 0) : '—'; ?>
                <?php if ($view_type === 'net' && ($sales_qty != 0 || $return_qty != 0)): ?>
                <br><span class="breakdown">(<?= $sales_qty; ?> - <?= $return_qty; ?>)</span>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php 
            endforeach;
            
            // Subtotal: User Types
            if ($user_type_sales_amount > 0 || $user_type_return_amount > 0):
                if ($view_type === 'sales') {
                    $user_subtotal = $user_type_sales_amount;
                } elseif ($view_type === 'returns') {
                    $user_subtotal = $user_type_return_amount;
                } else {
                    $user_subtotal = $user_type_sales_amount - $user_type_return_amount;
                }
        ?>
        <tr class="subtotal-row">
            <td colspan="2">SUBTOTAL - USER TYPES</td>
            <td class="number"><?= inr_format($user_subtotal, 2); ?></td>
            <?php if ($view_type === 'net'): ?>
            <td class="number"><?= inr_format($user_type_sales_amount, 2); ?></td>
            <td class="number"><?= inr_format($user_type_return_amount, 2); ?></td>
            <?php endif; ?>
            <?php foreach ($products as $pr_id => $pr_name): 
                if ($view_type === 'sales') {
                    $user_product_total = $user_type_sales_products[$pr_id];
                } elseif ($view_type === 'returns') {
                    $user_product_total = $user_type_return_products[$pr_id];
                } else {
                    $user_product_total = $user_type_sales_products[$pr_id] - $user_type_return_products[$pr_id];
                }
            ?>
            <td class="center"><?= inr_format($user_product_total, 0); ?></td>
            <?php endforeach; ?>
        </tr>
        <?php 
            endif;
            
            // Subtotal: OT Channels
            if ($ot_channel_sales_amount > 0 || $ot_channel_return_amount > 0):
                if ($view_type === 'sales') {
                    $ot_subtotal = $ot_channel_sales_amount;
                } elseif ($view_type === 'returns') {
                    $ot_subtotal = $ot_channel_return_amount;
                } else {
                    $ot_subtotal = $ot_channel_sales_amount - $ot_channel_return_amount;
                }
        ?>
        <tr class="subtotal-row">
            <td colspan="2">SUBTOTAL - OT CHANNELS</td>
            <td class="number"><?= inr_format($ot_subtotal, 2); ?></td>
            <?php if ($view_type === 'net'): ?>
            <td class="number"><?= inr_format($ot_channel_sales_amount, 2); ?></td>
            <td class="number"><?= inr_format($ot_channel_return_amount, 2); ?></td>
            <?php endif; ?>
            <?php foreach ($products as $pr_id => $pr_name): 
                if ($view_type === 'sales') {
                    $ot_product_total = $ot_channel_sales_products[$pr_id];
                } elseif ($view_type === 'returns') {
                    $ot_product_total = $ot_channel_return_products[$pr_id];
                } else {
                    $ot_product_total = $ot_channel_sales_products[$pr_id] - $ot_channel_return_products[$pr_id];
                }
            ?>
            <td class="center"><?= inr_format($ot_product_total, 0); ?></td>
            <?php endforeach; ?>
        </tr>
        <?php 
            endif;
        } else {
            $colspan = 3 + ($view_type === 'net' ? 2 : 0) + count($products);
            echo '<tr><td colspan="' . $colspan . '" class="center">No data found for the selected period.</td></tr>';
        }
        ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="2">GRAND TOTAL</td>
            <td class="number">
                <?php 
                if ($view_type === 'sales') {
                    $grand_display = $grand_sales_amount;
                } elseif ($view_type === 'returns') {
                    $grand_display = $grand_return_amount;
                } else {
                    $grand_display = $grand_sales_amount - $grand_return_amount;
                }
                echo inr_format($grand_display, 2);
                ?>
            </td>
            <?php if ($view_type === 'net'): ?>
            <td class="number"><?= inr_format($grand_sales_amount, 2); ?></td>
            <td class="number"><?= inr_format($grand_return_amount, 2); ?></td>
            <?php endif; ?>
            <?php foreach ($products as $pr_id => $pr_name): 
                if ($view_type === 'sales') {
                    $grand_product_total = $grand_sales_products[$pr_id];
                } elseif ($view_type === 'returns') {
                    $grand_product_total = $grand_return_products[$pr_id];
                } else {
                    $grand_product_total = $grand_sales_products[$pr_id] - $grand_return_products[$pr_id];
                }
            ?>
            <td class="center"><?= inr_format($grand_product_total, 0); ?></td>
            <?php endforeach; ?>
        </tr>
    </tfoot>
</table>

<br><br>

<!-- Summary Statistics -->
<table style="width: 60%;">
    <thead>
        <tr>
            <th colspan="2">Summary Statistics</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Report Period:</strong></td>
            <td><?= date('d M Y', strtotime($from_date)); ?> to <?= date('d M Y', strtotime($to_date)); ?></td>
        </tr>
        <tr>
            <td><strong>View Type:</strong></td>
            <td><?= htmlspecialchars($view_label, ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <tr>
            <td><strong>Total User Type Categories:</strong></td>
            <td class="number"><?= count(array_filter($categories, fn($c) => $c['category_type'] === 'User Type')); ?></td>
        </tr>
        <tr>
            <td><strong>Total OT Channels:</strong></td>
            <td class="number"><?= count(array_filter($categories, fn($c) => $c['category_type'] === 'OT Channel')); ?></td>
        </tr>
        <tr>
            <td><strong>Total Products:</strong></td>
            <td class="number"><?= count($products); ?></td>
        </tr>
        <tr class="total-row">
            <td><strong>Grand Total Sales Amount:</strong></td>
            <td class="number"><strong>₹<?= inr_format($grand_sales_amount, 2); ?></strong></td>
        </tr>
        <tr class="total-row">
            <td><strong>Grand Total Returns Amount:</strong></td>
            <td class="number"><strong>₹<?= inr_format($grand_return_amount, 2); ?></strong></td>
        </tr>
        <tr class="total-row">
            <td><strong>Net Amount:</strong></td>
            <td class="number"><strong>₹<?= inr_format($grand_sales_amount - $grand_return_amount, 2); ?></strong></td>
        </tr>
    </tbody>
</table>

<br><br>

<!-- Product-wise Grand Totals -->
<table style="width: 70%;">
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Sales Quantity</th>
            <th>Returns Quantity</th>
            <th>Net Quantity</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $pr_id => $pr_name): 
            $sales_qty = $grand_sales_products[$pr_id];
            $return_qty = $grand_return_products[$pr_id];
            $net_qty = $sales_qty - $return_qty;
        ?>
        <tr>
            <td><?= htmlspecialchars($pr_name, ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="number"><?= inr_format($sales_qty, 0); ?></td>
            <td class="number"><?= inr_format($return_qty, 0); ?></td>
            <td class="number"><strong><?= inr_format($net_qty, 0); ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td><strong>TOTAL</strong></td>
            <td class="number"><strong><?= inr_format(array_sum($grand_sales_products), 0); ?></strong></td>
            <td class="number"><strong><?= inr_format(array_sum($grand_return_products), 0); ?></strong></td>
            <td class="number"><strong><?= inr_format(array_sum($grand_sales_products) - array_sum($grand_return_products), 0); ?></strong></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
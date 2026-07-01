<?php
/**
 * Load Price - SUPER STOCKIST VERSION
 * Returns product price based on customer type
 * 
 * Returns JSON with price and readonly status
 * - Readonly: stockiest, distributor, super_distributor
 * - Editable: customer, shop
 */

include("checksession.php");

$prid = $_GET['q'] ?? ''; 
$invuser = $_GET['invuser'] ?? '';

error_reporting(0);

if (empty($prid)) {
    echo json_encode(['price' => '0.00', 'readonly' => true]);
    exit;
}

// Get product details
$select_ProductsPrice = "select * from products where id='$prid'";
$fetch_ProductsPrice = mysqli_query($db_conn, $select_ProductsPrice);
$Result_ProductsPrice = mysqli_fetch_array($fetch_ProductsPrice);

if (!$Result_ProductsPrice) {
    echo json_encode(['price' => '0.00', 'readonly' => true]);
    exit;
}

// ✅ Determine price based on customer type
$mrpamount = 0;

switch ($invuser) {
    case "stockiest":
        $mrpamount = $Result_ProductsPrice['stockist_price'];
        break;
    case "distributor":
        $mrpamount = $Result_ProductsPrice['distributor_price'];
        break;
    case "super_distributor":
        // Use distributor price or create super_distributor_price column
        $mrpamount = $Result_ProductsPrice['distributor_price'];
        break;
    case "customer":
    case "shop":
        $mrpamount = $Result_ProductsPrice['mrp'];
        break;
    default:
        $mrpamount = $Result_ProductsPrice['mrp'];
        break;
}

// ✅ Determine if field should be readonly
// Readonly: stockiest, distributor, super_distributor (fixed prices)
// Editable: customer, shop (can negotiate prices)
$isReadonly = !in_array($invuser, ['customer', 'shop']);

// ✅ Return JSON with price and readonly status
echo json_encode([
    'price' => number_format((float)$mrpamount, 2, '.', ''),
    'readonly' => $isReadonly
]);
?>
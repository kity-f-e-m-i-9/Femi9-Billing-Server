<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

if (!isset($_POST['submit_po'])) {
    header("Location: add-purchase-order.php");
    exit;
}

$cp_id = (int)$Login_user_IDvl;
$order_date = date("Y-m-d");
$productType = tpResolveProductType($_POST['product_type'] ?? null);

cpEnsurePurchaseOrderTables($db_conn);

$pr_ids = $_POST['pr_id'] ?? [];
$qtys   = $_POST['qty']   ?? [];

// Price is never trusted from the client — re-read each product's current
// MRP here, same principle as territory-partner/purchase-order-action.php
// re-validating everything server-side rather than trusting the cart total
// the browser computed.
$items = [];
foreach ($pr_ids as $i => $rpid) {
    $pid = (int)$rpid;
    $qty = (int)($qtys[$i] ?? 0);
    if ($pid < 1 || $qty < 1) continue;

    $pStmt = $db_conn->prepare("SELECT mrp FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $pStmt->bind_param("i", $pid);
    $pStmt->execute();
    $pRow = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();
    if (!$pRow) continue;

    $price = round((float)$pRow['mrp'], 2);
    $amount = round($qty * $price, 2);
    $items[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price, 'amount' => $amount];
}

if (empty($items)) {
    $_SESSION['errorMessage'] = 'Please add at least one product before submitting.';
    header("Location: add-purchase-order.php");
    exit;
}

// Product-type re-classification — the picker on add-purchase-order.php
// only offers the declared type's products, but that's UX only; a raw POST
// could bypass it.
$cartClassification = tpProductTypeOfProducts($db_conn, array_column($items, 'pid'));
if ($cartClassification['mixed'] || ($cartClassification['type'] !== null && $cartClassification['type'] !== $productType)) {
    $_SESSION['errorMessage'] = 'This order contains a product that doesn\'t match its declared type (' . tpProductTypeLabel($productType) . '). Please start the order again.';
    header("Location: add-purchase-order.php");
    exit;
}

// Delivery address
$useDefaultDelivery = isset($_POST['use_default_delivery_address']) ? 1 : 0;
$customDeliveryLine1    = trim($_POST['custom_delivery_line1']    ?? '');
$customDeliveryLine2    = trim($_POST['custom_delivery_line2']    ?? '');
$customDeliveryCity     = trim($_POST['custom_delivery_city']     ?? '');
$customDeliveryDistrict = trim($_POST['custom_delivery_district'] ?? '');
$customDeliveryState    = trim($_POST['custom_delivery_state']    ?? '');
$customDeliveryCountry  = trim($_POST['custom_delivery_country']  ?? '');
$customDeliveryPincode  = trim($_POST['custom_delivery_pincode']  ?? '');

if (!$useDefaultDelivery && $customDeliveryLine1 === '') {
    $_SESSION['errorMessage'] = 'Please enter a delivery address, or use the existing delivery address.';
    header("Location: add-purchase-order.php");
    exit;
}
if ($useDefaultDelivery) {
    $customDeliveryLine1 = $customDeliveryLine2 = $customDeliveryCity = $customDeliveryDistrict = $customDeliveryState = $customDeliveryCountry = $customDeliveryPincode = null;
}

$grandTotal = array_sum(array_column($items, 'amount'));

// Authoritative headroom check — never trust the client's displayed total.
$headroom = cpAvailableHeadroom($db_conn, $cp_id);
if ($grandTotal > $headroom + 0.001) {
    $_SESSION['errorMessage'] = 'Your order total (₹' . number_format($grandTotal, 2) . ') exceeds your available headroom (₹' . number_format($headroom, 2) . '). Remove items or wait for your held stock to sell before ordering more.';
    header("Location: add-purchase-order.php");
    exit;
}

$db_conn->begin_transaction();
try {
    $s = $db_conn->prepare(
        "INSERT INTO channel_partner_purchase_orders
            (channel_partner_id, product_type, order_date, status, use_default_delivery_address,
             custom_delivery_line1, custom_delivery_line2, custom_delivery_city, custom_delivery_district,
             custom_delivery_state, custom_delivery_country, custom_delivery_pincode)
         VALUES (?, ?, ?, 'waiting', ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $s->bind_param(
        "ississsssss", $cp_id, $productType, $order_date, $useDefaultDelivery,
        $customDeliveryLine1, $customDeliveryLine2, $customDeliveryCity, $customDeliveryDistrict,
        $customDeliveryState, $customDeliveryCountry, $customDeliveryPincode
    );
    $s->execute();
    $po_id = $db_conn->insert_id;
    $s->close();

    $si = $db_conn->prepare("INSERT INTO channel_partner_purchase_order_items (po_id, product_id, qty, price, amount) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $si->bind_param("iiidd", $po_id, $item['pid'], $item['qty'], $item['price'], $item['amount']);
        $si->execute();
    }
    $si->close();

    $db_conn->commit();

    $_SESSION['successMessage'] = 'Purchase order submitted successfully.';
    header("Location: manage-purchase-orders.php");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = 'Failed to submit purchase order. Please try again.';
    header("Location: add-purchase-order.php");
    exit;
}

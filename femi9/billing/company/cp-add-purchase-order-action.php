<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: cp-today-orders.php"); exit;
}

$cp_id = (int) ($_POST['cp_id'] ?? 0);
if ($cp_id < 1) {
    header("Location: cp-add-purchase-order.php"); exit;
}
$backUrl = "cp-add-purchase-order.php?cp_id=" . $cp_id . "&type=" . urlencode($_POST['product_type'] ?? '');

$cpCheck = $db_conn->prepare("SELECT id FROM channel_partners WHERE id = ?");
$cpCheck->bind_param("i", $cp_id);
$cpCheck->execute();
if (!$cpCheck->get_result()->fetch_assoc()) {
    $cpCheck->close();
    header("Location: cp-add-purchase-order.php"); exit;
}
$cpCheck->close();

if (!isset($_POST['submit_po'])) {
    header("Location: " . $backUrl); exit;
}

$order_date = date("Y-m-d");
$productType = tpResolveProductType($_POST['product_type'] ?? null);

cpEnsurePurchaseOrderTables($db_conn);

$pr_ids = $_POST['pr_id'] ?? [];
$qtys   = $_POST['qty']   ?? [];

// Price is never trusted from the client — same principle as
// channel-partner/purchase-order-action.php, which this mirrors for the
// company-initiated flow (company/cp-add-purchase-order.php).
$items = [];
foreach ($pr_ids as $i => $rpid) {
    $pid = (int) $rpid;
    $qty = (int) ($qtys[$i] ?? 0);
    if ($pid < 1 || $qty < 1) continue;

    $pStmt = $db_conn->prepare("SELECT mrp FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $pStmt->bind_param("i", $pid);
    $pStmt->execute();
    $pRow = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();
    if (!$pRow) continue;

    $price = round((float) $pRow['mrp'], 2);
    $amount = round($qty * $price, 2);
    $items[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price, 'amount' => $amount];
}

if (empty($items)) {
    $_SESSION['errorMessage'] = 'Please add at least one product before submitting.';
    header("Location: " . $backUrl); exit;
}

$cartClassification = tpProductTypeOfProducts($db_conn, array_column($items, 'pid'));
if ($cartClassification['mixed'] || ($cartClassification['type'] !== null && $cartClassification['type'] !== $productType)) {
    $_SESSION['errorMessage'] = 'This order contains a product that doesn\'t match its declared type (' . tpProductTypeLabel($productType) . '). Please start the order again.';
    header("Location: " . $backUrl); exit;
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
    $_SESSION['errorMessage'] = 'Please enter a delivery address, or use the CP\'s existing delivery address.';
    header("Location: " . $backUrl); exit;
}
if ($useDefaultDelivery) {
    $customDeliveryLine1 = $customDeliveryLine2 = $customDeliveryCity = $customDeliveryDistrict = $customDeliveryState = $customDeliveryCountry = $customDeliveryPincode = null;
}

$grandTotal = array_sum(array_column($items, 'amount'));

// Headroom cap is skipped only for an actual admin/finance login — never
// trust the page's own canExceedHeadroom JS flag, re-derive it here the
// same way cp-add-purchase-order.php does. A "users" sub-account that only
// has the channel_partner permission (and so can still reach this page via
// requirePermission() above) stays bound by the same cap the CP's own
// self-service order form enforces.
$canExceedHeadroom = in_array($result_LoGuserDtails['usertype'] ?? '', ['admin', 'finance'], true);
if (!$canExceedHeadroom) {
    $headroom = cpAvailableHeadroom($db_conn, $cp_id);
    if ($grandTotal > $headroom + 0.001) {
        $_SESSION['errorMessage'] = 'Order total (₹' . number_format($grandTotal, 2) . ') exceeds this CP\'s available headroom (₹' . number_format($headroom, 2) . '). Remove items before submitting.';
        header("Location: " . $backUrl); exit;
    }
}

$created_by = $_SESSION['LOGIN_USER'] ?? '';

$db_conn->begin_transaction();
try {
    $s = $db_conn->prepare(
        "INSERT INTO channel_partner_purchase_orders
            (channel_partner_id, product_type, order_date, status, use_default_delivery_address,
             custom_delivery_line1, custom_delivery_line2, custom_delivery_city, custom_delivery_district,
             custom_delivery_state, custom_delivery_country, custom_delivery_pincode,
             created_by, created_by_user_type)
         VALUES (?, ?, ?, 'waiting', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'company')"
    );
    $s->bind_param(
        "ississssssss", $cp_id, $productType, $order_date, $useDefaultDelivery,
        $customDeliveryLine1, $customDeliveryLine2, $customDeliveryCity, $customDeliveryDistrict,
        $customDeliveryState, $customDeliveryCountry, $customDeliveryPincode, $created_by
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

    $_SESSION['successMessage'] = 'Purchase order created for this Channel Partner.';
    header("Location: cp-today-orders.php?created=1");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = 'Failed to create purchase order. Please try again.';
    header("Location: " . $backUrl); exit;
}

<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

if (!isset($_POST['submit_po'])) {
    header("Location: manage-purchase-orders.php");
    exit;
}

$cp_id = (int)$Login_user_IDvl;
$po_id = (int)($_POST['po_id'] ?? 0);

cpEnsurePurchaseOrderTables($db_conn);

// Reload + re-check ownership/status server-side — never trust that the
// form we handed out a moment ago still reflects reality (another tab could
// have deleted/completed it meanwhile), same defensive stance
// delete-purchase-order.php takes.
$poStmt = mysqli_prepare($db_conn,
    "SELECT product_type FROM channel_partner_purchase_orders WHERE id = ? AND channel_partner_id = ? AND status = 'waiting'"
);
mysqli_stmt_bind_param($poStmt, "ii", $po_id, $cp_id);
mysqli_stmt_execute($poStmt);
$po = mysqli_stmt_get_result($poStmt)->fetch_assoc();
mysqli_stmt_close($poStmt);

if (!$po) {
    $_SESSION['errorMessage'] = 'That purchase order can no longer be edited (it may already be completed or cancelled).';
    header("Location: manage-purchase-orders.php");
    exit;
}
$productType = $po['product_type'];

$pr_ids = $_POST['pr_id'] ?? [];
$qtys   = $_POST['qty']   ?? [];

// Price is never trusted from the client — re-read each product's current
// MRP, same principle purchase-order-action.php uses for a new order.
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
    $_SESSION['errorMessage'] = 'Please keep at least one product on this order.';
    header("Location: edit-purchase-order.php?po_id=" . $po_id);
    exit;
}

$cartClassification = tpProductTypeOfProducts($db_conn, array_column($items, 'pid'));
if ($cartClassification['mixed'] || ($cartClassification['type'] !== null && $cartClassification['type'] !== $productType)) {
    $_SESSION['errorMessage'] = 'This order contains a product that doesn\'t match its original type (' . tpProductTypeLabel($productType) . ').';
    header("Location: edit-purchase-order.php?po_id=" . $po_id);
    exit;
}

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
    header("Location: edit-purchase-order.php?po_id=" . $po_id);
    exit;
}
if ($useDefaultDelivery) {
    $customDeliveryLine1 = $customDeliveryLine2 = $customDeliveryCity = $customDeliveryDistrict = $customDeliveryState = $customDeliveryCountry = $customDeliveryPincode = null;
}

$grandTotal = array_sum(array_column($items, 'amount'));

// Headroom check excluding this PO's own current (pre-edit) value — otherwise
// an unchanged order would appear to exceed headroom against its own total.
$headroom = cpAvailableHeadroom($db_conn, $cp_id, $po_id);
if ($grandTotal > $headroom + 0.001) {
    $_SESSION['errorMessage'] = 'Your order total (₹' . number_format($grandTotal, 2) . ') exceeds your available headroom (₹' . number_format($headroom, 2) . '). Remove items or wait for your held stock to sell before saving.';
    header("Location: edit-purchase-order.php?po_id=" . $po_id);
    exit;
}

$db_conn->begin_transaction();
try {
    $u = $db_conn->prepare(
        "UPDATE channel_partner_purchase_orders
         SET use_default_delivery_address = ?, custom_delivery_line1 = ?, custom_delivery_line2 = ?,
             custom_delivery_city = ?, custom_delivery_district = ?, custom_delivery_state = ?,
             custom_delivery_country = ?, custom_delivery_pincode = ?
         WHERE id = ? AND channel_partner_id = ? AND status = 'waiting'"
    );
    $u->bind_param(
        "issssssiii", $useDefaultDelivery, $customDeliveryLine1, $customDeliveryLine2,
        $customDeliveryCity, $customDeliveryDistrict, $customDeliveryState,
        $customDeliveryCountry, $customDeliveryPincode, $po_id, $cp_id
    );
    $u->execute();
    $u->close();

    // Simplest correct way to apply an edited cart: drop the old lines and
    // insert the new ones fresh, inside the same transaction as the header
    // update above — nothing has shipped yet (status is still 'waiting'),
    // so there's no stock/transfer row referencing these item rows to keep in sync.
    $del = $db_conn->prepare("DELETE FROM channel_partner_purchase_order_items WHERE po_id = ?");
    $del->bind_param("i", $po_id);
    $del->execute();
    $del->close();

    $si = $db_conn->prepare("INSERT INTO channel_partner_purchase_order_items (po_id, product_id, qty, price, amount) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $si->bind_param("iiidd", $po_id, $item['pid'], $item['qty'], $item['price'], $item['amount']);
        $si->execute();
    }
    $si->close();

    $db_conn->commit();

    $_SESSION['successMessage'] = 'Purchase order updated successfully.';
    header("Location: manage-purchase-orders.php");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = 'Failed to update purchase order. Please try again.';
    header("Location: edit-purchase-order.php?po_id=" . $po_id);
    exit;
}

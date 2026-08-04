<?php
include("checksession.php");
include("config.php");
error_reporting(0);

if (!isset($_POST['submit_po'])) {
    header("Location: add-purchase-order.php");
    exit;
}

$tp_id      = (int)$Login_user_IDvl;
$order_date = date("Y-m-d");

$pr_ids   = $_POST['pr_id']   ?? [];
$qtys     = $_POST['qty']     ?? [];
$prices   = $_POST['price']   ?? [];
$disc_pcts = $_POST['discount_percentage'] ?? [];
$disc_amts = $_POST['discount_amount']     ?? [];

$items = [];
foreach ($pr_ids as $i => $rpid) {
    $pid   = (int)$rpid;
    $qty   = (int)($qtys[$i] ?? 0);
    $price = round((float)($prices[$i] ?? 0), 2);
    $dpct  = round((float)($disc_pcts[$i] ?? 0), 2);
    $damt  = round((float)($disc_amts[$i] ?? 0), 2);
    if ($pid < 1 || $qty < 1) continue;
    $amount = round(($qty * $price) - $damt, 2);
    $items[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price, 'dpct' => $dpct, 'damt' => $damt, 'amount' => $amount];
}

if (empty($items)) {
    $_SESSION['errorMessage'] = 'Please add at least one product before submitting.';
    header("Location: add-purchase-order.php");
    exit;
}

// Delivery address — either the TP's existing registered address, or a
// one-off address typed for this order. Stored on the PO itself (not just a
// pointer back to territory_partners) so it stays correct even if the TP's
// master address changes later.
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
    // Ignore any typed address when the default is selected.
    $customDeliveryLine1 = $customDeliveryLine2 = $customDeliveryCity = $customDeliveryDistrict = $customDeliveryState = $customDeliveryCountry = $customDeliveryPincode = null;
}

$grandTotal = array_sum(array_column($items, 'amount'));

// Available advance balance — recomputed server-side, never trust the client total.
$balStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM tp_advance_payments WHERE territory_partner_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL"
);
mysqli_stmt_bind_param($balStmt, "i", $tp_id);
mysqli_stmt_execute($balStmt);
$advBalance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($balStmt);

// Orders still "waiting" already have their advance-covered portion (total
// minus any excess covered by uploaded payment proof) implicitly earmarked,
// even though balance_amount is only decremented once an order is fulfilled.
// Without this, a TP could submit several pending orders that each pass this
// check individually while cumulatively over-committing the real balance.
$reservedStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(poi.total - po.excess_amount), 0) AS reserved
     FROM tp_purchase_orders po
     JOIN (SELECT po_id, SUM(amount) AS total FROM tp_purchase_order_items GROUP BY po_id) poi
       ON poi.po_id = po.id
     WHERE po.territory_partner_id = ? AND po.status = 'waiting'"
);
mysqli_stmt_bind_param($reservedStmt, "i", $tp_id);
mysqli_stmt_execute($reservedStmt);
$reservedAmount = (float)(mysqli_stmt_get_result($reservedStmt)->fetch_assoc()['reserved'] ?? 0);
mysqli_stmt_close($reservedStmt);

$advBalance = max(0, round($advBalance - $reservedAmount, 2));

$excessAmount = round(max(0, $grandTotal - $advBalance), 2);

if ($excessAmount > 0) {
    // Authoritative server-side gate — the total shown to the TP in the
    // browser is a courtesy preview, not the source of truth. This only
    // requires SOME non-rejected upload to exist, not that its amount adds
    // up to the excess — a pending_review screenshot often has no
    // detected_amount at all (OCR found it ambiguous, not necessarily
    // wrong), and gating on a sum here just blocks genuine attempts.
    // Company can still see and act on every screenshot via
    // tp-today-orders.php before ever invoicing, so this isn't the only
    // checkpoint. Rejected screenshots never count.
    $scrStmt = mysqli_prepare($db_conn,
        "SELECT COUNT(*) AS cnt
         FROM tp_purchase_order_screenshots
         WHERE territory_partner_id = ? AND po_id IS NULL AND status IN ('accepted', 'pending_review')"
    );
    mysqli_stmt_bind_param($scrStmt, "i", $tp_id);
    mysqli_stmt_execute($scrStmt);
    $eligibleCount = (int)(mysqli_stmt_get_result($scrStmt)->fetch_assoc()['cnt'] ?? 0);
    mysqli_stmt_close($scrStmt);

    if ($eligibleCount < 1) {
        $_SESSION['errorMessage'] = 'Your order total exceeds your available advance balance by ₹' . number_format($excessAmount, 2)
            . '. Please upload at least one payment screenshot for the excess amount before submitting.';
        header("Location: add-purchase-order.php");
        exit;
    }
}

$db_conn->begin_transaction();
try {
    $s = $db_conn->prepare(
        "INSERT INTO tp_purchase_orders
            (territory_partner_id, order_date, status, excess_amount, use_default_delivery_address,
             custom_delivery_line1, custom_delivery_line2, custom_delivery_city, custom_delivery_district,
             custom_delivery_state, custom_delivery_country, custom_delivery_pincode)
         VALUES (?, ?, 'waiting', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $s->bind_param(
        "isdisssssss", $tp_id, $order_date, $excessAmount, $useDefaultDelivery,
        $customDeliveryLine1, $customDeliveryLine2, $customDeliveryCity, $customDeliveryDistrict,
        $customDeliveryState, $customDeliveryCountry, $customDeliveryPincode
    );
    $s->execute();
    $po_id = $db_conn->insert_id;
    $s->close();

    $si = $db_conn->prepare("INSERT INTO tp_purchase_order_items (po_id, product_id, qty, price, discount_percentage, discount_amount, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $si->bind_param("iiidddd", $po_id, $item['pid'], $item['qty'], $item['price'], $item['dpct'], $item['damt'], $item['amount']);
        $si->execute();
    }
    $si->close();

    // Link everything tried for this order (accepted, pending, and rejected)
    // to the new PO, preserving a full audit trail of what was submitted.
    $link = $db_conn->prepare(
        "UPDATE tp_purchase_order_screenshots SET po_id = ? WHERE territory_partner_id = ? AND po_id IS NULL"
    );
    $link->bind_param("ii", $po_id, $tp_id);
    $link->execute();
    $link->close();

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

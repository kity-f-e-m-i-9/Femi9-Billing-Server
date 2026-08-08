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

// Self-migrating: see add-purchase-order.php for the full rationale — this
// column tells "already unlocked a different order" apart from "still
// available," so one old submission can't silently cover every subsequent
// over-balance order forever.
$_usedForPoCol = $db_conn->query("SHOW COLUMNS FROM tp_advance_payment_submissions LIKE 'used_for_po_id'");
if ($_usedForPoCol && $_usedForPoCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_advance_payment_submissions ADD COLUMN used_for_po_id INT UNSIGNED NULL AFTER advance_payment_id");
}

$claimSubmissionIds = [];
if ($excessAmount > 0) {
    // Authoritative server-side gate — the total shown to the TP in the
    // browser is a courtesy preview, not the source of truth. Only
    // submissions not yet claimed by another order (used_for_po_id IS NULL)
    // count; company reviews the actual payment before invoicing, so this
    // isn't the only checkpoint. Rejected/draft submissions never count.
    //
    // Deliberately excludes status='accepted': its amount is already inside
    // $advBalance above (an accepted submission becomes a real
    // tp_advance_payments row via advance_payment_id) — counting it again
    // here would double-count the same money against a second, unrelated
    // order's excess. See the matching comment in add-purchase-order.php.
    //
    // Smallest-first so a small excess doesn't needlessly tie up a large
    // submission that a bigger future order might actually need.
    $advSubStmt = mysqli_prepare($db_conn,
        "SELECT id, amount FROM tp_advance_payment_submissions
         WHERE territory_partner_id = ? AND status = 'pending_review' AND used_for_po_id IS NULL
         ORDER BY amount ASC"
    );
    mysqli_stmt_bind_param($advSubStmt, "i", $tp_id);
    mysqli_stmt_execute($advSubStmt);
    $eligibleSubs = mysqli_stmt_get_result($advSubStmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($advSubStmt);

    if (empty($eligibleSubs)) {
        $_SESSION['errorMessage'] = 'Your order total exceeds your available advance balance by ₹' . number_format($excessAmount, 2)
            . '. Please submit an advance payment for review before submitting this order.';
        header("Location: add-purchase-order.php");
        exit;
    }
    $claimSubmissionIds = array_column($eligibleSubs, 'id');
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

    // Claim every advance-payment submission this order's excess-balance
    // gate relied on, so none of them can silently unlock a later,
    // unrelated order too. Re-checks used_for_po_id IS NULL here (not just
    // in the SELECT above) to close the race window between that read and
    // this write — if another request already claimed one in between, its
    // UPDATE simply affects 0 rows for that id, which is fine.
    if (!empty($claimSubmissionIds)) {
        $claimStmt = $db_conn->prepare(
            "UPDATE tp_advance_payment_submissions SET used_for_po_id = ? WHERE id = ? AND used_for_po_id IS NULL"
        );
        foreach ($claimSubmissionIds as $subId) {
            $claimStmt->bind_param("ii", $po_id, $subId);
            $claimStmt->execute();
        }
        $claimStmt->close();
    }

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

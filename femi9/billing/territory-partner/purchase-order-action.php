<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpApproverContext.php';
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/TpCourierPayment.php';
require_once __DIR__ . '/../shared/TpCourierAmountRequest.php';
error_reporting(0);

if (!isset($_POST['submit_po'])) {
    header("Location: add-purchase-order.php");
    exit;
}

$tp_id      = (int)$Login_user_IDvl;
$order_date = date("Y-m-d");
$productType = tpResolveProductType($_POST['product_type'] ?? null);

// Must exist before the balance/reserved/eligible-submission queries below,
// which now scope by product_type — see
// db_migrations/2026_08_18_tp_napkin_diaper_advance_wallet.sql.
tpEnsureAdvanceWalletColumns($db_conn);

// Never trust the client-submitted approver type on its own — re-verify the
// TP actually has that SS assignment before honoring 'ss'.
$approver = tpResolveApprover($db_conn, $tp_id, $_POST['approver_type'] ?? null);

$pr_ids   = $_POST['pr_id']   ?? [];
$qtys     = $_POST['qty']     ?? [];
$prices   = $_POST['price']   ?? [];
$disc_pcts = $_POST['discount_percentage'] ?? [];
$disc_amts = $_POST['discount_amount']     ?? [];
$methods  = $_POST['pickup_method'] ?? [];

$items = [];
foreach ($pr_ids as $i => $rpid) {
    $pid   = (int)$rpid;
    $qty   = (int)($qtys[$i] ?? 0);
    $price = round((float)($prices[$i] ?? 0), 2);
    $dpct  = round((float)($disc_pcts[$i] ?? 0), 2);
    $damt  = round((float)($disc_amts[$i] ?? 0), 2);
    if ($pid < 1 || $qty < 1) continue;
    $amount = round(($qty * $price) - $damt, 2);
    // Defaults to 'courier' for a missing/unrecognized value — nothing is
    // silently exempted from the courier fee unless the TP explicitly
    // marked it "pick up myself" via add-purchase-order.php's modal.
    $method = (($methods[$i] ?? 'courier') === 'pickup') ? 'pickup' : 'courier';
    $items[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price, 'dpct' => $dpct, 'damt' => $damt, 'amount' => $amount, 'method' => $method];
}

if (empty($items)) {
    $_SESSION['errorMessage'] = 'Please add at least one product before submitting.';
    header("Location: add-purchase-order.php");
    exit;
}

// Courier payment gate — authoritative check, never trust the earlier
// courtesy check on add-purchase-order.php. Required amount is recomputed
// here from the ACTUAL submitted cart (not whatever pay-courier-payment.php
// showed when the TP paid, which could have been a smaller/larger draft),
// so a since-changed cart can under- or over-shoot what was already paid.
tpEnsureCourierPaymentTables($db_conn);
tpEnsureCourierAmountRequestTable($db_conn);
tpEnsurePickupColumn($db_conn);
// Only lines still marked 'courier' feed the box/fee calc — a line the TP
// picks up in person was never charged for, so it's excluded here too, not
// just at pay-courier-payment.php.
$courierItems = array_map(
    fn($it) => ['pid' => $it['pid'], 'qty' => $it['qty']],
    array_filter($items, fn($it) => $it['method'] !== 'pickup')
);
$courierShipment = tpCourierComputeShipmentForItems($db_conn, $courierItems);
$courierTotalBoxes = $courierShipment['boxes'];
$courierTotalCovers = $courierShipment['covers'];
$courierRequiredAmount = tpCourierComputeAmount($db_conn, $productType, $courierTotalBoxes, $courierTotalCovers);

// A Sales BDM-approved amount-change request overrides the raw calculation
// here too — resolved through the SAME session-draft id flag
// pay-courier-payment.php uses (see stash-po-draft.php), never by
// TP/type/box-cover matching, so what the TP was actually shown/charged is
// what this gate requires. Consumed (tpCourierAmountRequestMarkApplied) once
// this specific PO is created below, so it's a one-time correction for THIS
// order only — it must never silently reduce every future cart's fee too.
$courierRequestId = $_SESSION['po_draft_' . $tp_id]['courier_request_id'] ?? null;
$courierAmountRequest = $courierRequestId ? tpCourierAmountRequestGetById($db_conn, (int)$courierRequestId, $tp_id) : null;
if ($courierAmountRequest && $courierAmountRequest['status'] === 'approved') {
    $courierRequiredAmount = (float)$courierAmountRequest['approved_amount'];
}

$courierPoolTotal = tpCourierPoolTotal($db_conn, $tp_id, $productType);

if ($courierRequiredAmount > 0 && $courierPoolTotal < $courierRequiredAmount) {
    $courierDesc = [];
    if ($courierTotalBoxes > 0) $courierDesc[] = $courierTotalBoxes . ' box' . ($courierTotalBoxes !== 1 ? 'es' : '');
    if ($courierTotalCovers > 0) $courierDesc[] = $courierTotalCovers . ' cover' . ($courierTotalCovers !== 1 ? 's' : '');
    $_SESSION['errorMessage'] = 'Please pay the courier amount (₹' . number_format($courierRequiredAmount, 2) . ' for ' . implode(' + ', $courierDesc) . ') before submitting this order.';
    header("Location: add-purchase-order.php");
    exit;
}

// The product picker on add-purchase-order.php already only offers the
// declared type's products, but that's UX only — re-classify every
// submitted line here, since a raw POST could bypass the picker entirely.
$cartClassification = tpProductTypeOfProducts($db_conn, array_column($items, 'pid'));
if ($cartClassification['mixed'] || ($cartClassification['type'] !== null && $cartClassification['type'] !== $productType)) {
    $_SESSION['errorMessage'] = 'This order contains a product that doesn\'t match its declared type (' . tpProductTypeLabel($productType) . '). Please start the order again.';
    header("Location: add-purchase-order.php");
    exit;
}

// GST products stay under Company regardless of which approver the TP
// picked — same rule applied to tp_invoices (see
// db_migrations/2026_08_12_tp_invoice_approver_gst_backfill.sql) and
// backfilled onto tp_purchase_orders in
// db_migrations/2026_08_12_tp_purchase_order_gst_backfill.sql.
$pidPlaceholders = implode(',', array_fill(0, count($items), '?'));
$pidTypes = str_repeat('i', count($items));
$gstStmt = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE id IN ($pidPlaceholders) AND gst > 0");
$gstStmt->bind_param($pidTypes, ...array_column($items, 'pid'));
$gstStmt->execute();
$hasGstItem = (int)($gstStmt->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
$gstStmt->close();

if ($hasGstItem) {
    $approver = ['type' => 'company', 'ss_id' => null];
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
// Scoped to the resolved approver's own pool — Company and SS balances are
// independent, so this must never sum across both.
$balStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM tp_advance_payments WHERE territory_partner_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL
       AND approver_type = ? AND approver_ss_id <=> ? AND product_type = ?"
);
mysqli_stmt_bind_param($balStmt, "isis", $tp_id, $approver['type'], $approver['ss_id'], $productType);
mysqli_stmt_execute($balStmt);
$advBalance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($balStmt);

// Orders still "waiting" already have their advance-covered portion (total
// minus any excess covered by uploaded payment proof) implicitly earmarked,
// even though balance_amount is only decremented once an order is fulfilled.
// Without this, a TP could submit several pending orders that each pass this
// check individually while cumulatively over-committing the real balance.
// Scoped to the same approver pool this new order is being routed to.
$reservedStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(poi.total - po.excess_amount), 0) AS reserved
     FROM tp_purchase_orders po
     JOIN (SELECT po_id, SUM(amount) AS total FROM tp_purchase_order_items GROUP BY po_id) poi
       ON poi.po_id = po.id
     WHERE po.territory_partner_id = ? AND po.status = 'waiting'
       AND po.approver_type = ? AND po.approver_ss_id <=> ? AND po.product_type = ?"
);
mysqli_stmt_bind_param($reservedStmt, "isis", $tp_id, $approver['type'], $approver['ss_id'], $productType);
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

// Self-migrating: see db_migrations/2026_08_18_tp_napkin_diaper_purchase_order_type.sql
// for the full rationale — every PO belongs to exactly one product type.
$_productTypeCol = $db_conn->query("SHOW COLUMNS FROM tp_purchase_orders LIKE 'product_type'");
if ($_productTypeCol && $_productTypeCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_purchase_orders ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id");
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
           AND approver_type = ? AND approver_ss_id <=> ? AND product_type = ?
         ORDER BY amount ASC"
    );
    mysqli_stmt_bind_param($advSubStmt, "isis", $tp_id, $approver['type'], $approver['ss_id'], $productType);
    mysqli_stmt_execute($advSubStmt);
    $eligibleSubs = mysqli_stmt_get_result($advSubStmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($advSubStmt);

    if (empty($eligibleSubs)) {
        $_SESSION['errorMessage'] = 'Your order total exceeds your available ' . tpProductTypeLabel($productType) . ' advance balance by ₹' . number_format($excessAmount, 2)
            . '. Please submit a ' . tpProductTypeLabel($productType) . ' advance payment for review before submitting this order.';
        header("Location: add-purchase-order.php");
        exit;
    }
    $claimSubmissionIds = array_column($eligibleSubs, 'id');
}

$db_conn->begin_transaction();
try {
    $s = $db_conn->prepare(
        "INSERT INTO tp_purchase_orders
            (territory_partner_id, product_type, approver_type, approver_ss_id, order_date, status, excess_amount, use_default_delivery_address,
             custom_delivery_line1, custom_delivery_line2, custom_delivery_city, custom_delivery_district,
             custom_delivery_state, custom_delivery_country, custom_delivery_pincode)
         VALUES (?, ?, ?, ?, ?, 'waiting', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $s->bind_param(
        "issisdisssssss", $tp_id, $productType, $approver['type'], $approver['ss_id'], $order_date, $excessAmount, $useDefaultDelivery,
        $customDeliveryLine1, $customDeliveryLine2, $customDeliveryCity, $customDeliveryDistrict,
        $customDeliveryState, $customDeliveryCountry, $customDeliveryPincode
    );
    $s->execute();
    $po_id = $db_conn->insert_id;
    $s->close();

    if ($courierAmountRequest && $courierAmountRequest['status'] === 'approved') {
        tpCourierAmountRequestMarkApplied($db_conn, (int)$courierAmountRequest['id'], $po_id);
    }

    $si = $db_conn->prepare("INSERT INTO tp_purchase_order_items (po_id, product_id, qty, price, discount_percentage, discount_amount, amount, delivery_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $si->bind_param("iiidddds", $po_id, $item['pid'], $item['qty'], $item['price'], $item['dpct'], $item['damt'], $item['amount'], $item['method']);
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

    // Same claim-everything-tried pattern for the courier payment pool —
    // every screenshot uploaded toward this cart (accepted, pending, or
    // rejected) gets tied to the PO it actually paid for, not just the
    // accepted ones that met the gate above.
    $courierLink = $db_conn->prepare(
        "UPDATE tp_courier_payments SET po_id = ? WHERE territory_partner_id = ? AND product_type = ? AND po_id IS NULL"
    );
    $courierLink->bind_param("iis", $po_id, $tp_id, $productType);
    $courierLink->execute();
    $courierLink->close();

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

    // This draft (and any courier-amount-request tied to it) is fully spent
    // now that a real PO exists for it — clear it so the TP's NEXT cart
    // starts clean, never silently inheriting this one's approved amount
    // just because it happens to end up with identical line items (e.g. a
    // simple repeat order of the same single product/qty).
    unset($_SESSION['po_draft_' . $tp_id]);

    $_SESSION['successMessage'] = 'Purchase order submitted successfully.';
    header("Location: manage-purchase-orders.php");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = 'Failed to submit purchase order. Please try again.';
    header("Location: add-purchase-order.php");
    exit;
}

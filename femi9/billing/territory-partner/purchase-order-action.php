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

$grandTotal = array_sum(array_column($items, 'amount'));

// Available advance balance — recomputed server-side, never trust the client total.
$balStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM tp_advance_payments WHERE territory_partner_id = ? AND status = 'active'"
);
mysqli_stmt_bind_param($balStmt, "i", $tp_id);
mysqli_stmt_execute($balStmt);
$advBalance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($balStmt);

$excessAmount = round(max(0, $grandTotal - $advBalance), 2);

if ($excessAmount > 0) {
    // Authoritative server-side gate — the total shown to the TP in the
    // browser is a courtesy preview, not the source of truth. Screenshots
    // still "pending_review" count too: that status just means OCR couldn't
    // confidently read the amount/reference, not that anything is wrong —
    // company can still see and act on pending items via tp-today-orders.php
    // before ever invoicing, so this isn't the only checkpoint. Rejected
    // screenshots never count.
    $scrStmt = mysqli_prepare($db_conn,
        "SELECT COALESCE(SUM(detected_amount), 0) AS total
         FROM tp_purchase_order_screenshots
         WHERE territory_partner_id = ? AND po_id IS NULL AND status IN ('accepted', 'pending_review')"
    );
    mysqli_stmt_bind_param($scrStmt, "i", $tp_id);
    mysqli_stmt_execute($scrStmt);
    $acceptedTotal = (float)(mysqli_stmt_get_result($scrStmt)->fetch_assoc()['total'] ?? 0);
    mysqli_stmt_close($scrStmt);

    if ($acceptedTotal + 0.001 < $excessAmount) {
        $_SESSION['errorMessage'] = 'Your order total exceeds your available advance balance by ₹' . number_format($excessAmount, 2)
            . ', but only ₹' . number_format($acceptedTotal, 2) . ' of uploaded payment proof covers it so far. '
            . 'Please upload more screenshots for the remaining amount.';
        header("Location: add-purchase-order.php");
        exit;
    }
}

$db_conn->begin_transaction();
try {
    $s = $db_conn->prepare("INSERT INTO tp_purchase_orders (territory_partner_id, order_date, status, excess_amount) VALUES (?, ?, 'waiting', ?)");
    $s->bind_param("isd", $tp_id, $order_date, $excessAmount);
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

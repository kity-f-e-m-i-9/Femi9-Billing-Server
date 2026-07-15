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

$db_conn->begin_transaction();
try {
    $s = $db_conn->prepare("INSERT INTO tp_purchase_orders (territory_partner_id, order_date, status) VALUES (?, ?, 'waiting')");
    $s->bind_param("is", $tp_id, $order_date);
    $s->execute();
    $po_id = $db_conn->insert_id;
    $s->close();

    $si = $db_conn->prepare("INSERT INTO tp_purchase_order_items (po_id, product_id, qty, price, discount_percentage, discount_amount, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $si->bind_param("iiidddd", $po_id, $item['pid'], $item['qty'], $item['price'], $item['dpct'], $item['damt'], $item['amount']);
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

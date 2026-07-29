<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Turns a "Got Order" field visit (tp_orders rows sharing one order_id) into
// a shop invoice: creates user_invoice + one user_invoice_items row per
// product/qty line already captured on add-order.php, then hands off to the
// normal shop-invoice-add.php "existing invoice" view so the TP finishes the
// invoice (receipt amount, method, Submit Invoice) exactly as usual — stock
// is only deducted there, by the unmodified shop-invoice-submit.php.

$invuser  = 'shop';
$tp_id    = (int)$Login_user_IDvl;
$order_id = $_GET['order_id'] ?? '';

if ($order_id === '') {
    header('Location: manage-orders.php');
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT id, shop_id, order_date, new_order, pr_id, qty, discount_percentage, discount_amount, invoiced_inv_id
     FROM tp_orders WHERE order_id=? AND tp_id=? ORDER BY id ASC"
);
$stmt->bind_param('si', $order_id, $tp_id);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($lines) || $lines[0]['new_order'] !== 'yes') {
    $_SESSION['errorMessage'] = "This visit has no order to invoice.";
    header('Location: manage-orders.php');
    exit;
}

// Already converted earlier — just resume that invoice.
if (!empty($lines[0]['invoiced_inv_id'])) {
    header('Location: shop-invoice-add.php?InvoiceID=' . base64_encode($lines[0]['invoiced_inv_id']) . '&invuser=shop&action=edit');
    exit;
}

$shop_id = (int)$lines[0]['shop_id'];

$stmtShop = $db_conn->prepare(
    "SELECT temp_id, gstin, state_id FROM shop WHERE id=? AND onboard_userID=? AND onboard_userTYPE='territory_partner' LIMIT 1"
);
$stmtShop->bind_param('is', $shop_id, $tp_id);
$stmtShop->execute();
$shopRow = $stmtShop->get_result()->fetch_assoc();
$stmtShop->close();

if (!$shopRow) {
    $_SESSION['errorMessage'] = "Shop not found for this order.";
    header('Location: manage-orders.php');
    exit;
}

$customer_id   = $shopRow['temp_id'];
$buyer_GSTIN   = $shopRow['gstin'] ?? '';
$buyer_gsttype = strlen($buyer_GSTIN) === 15 ? 'register' : 'unregister';
$state_id      = (int)($shopRow['state_id'] ?? 0);

$stmtState = $db_conn->prepare("SELECT st_name FROM state WHERE id=? LIMIT 1");
$stmtState->bind_param('i', $state_id);
$stmtState->execute();
$shop_state_name = $stmtState->get_result()->fetch_assoc()['st_name'] ?? '';
$stmtState->close();

$stmtTP = $db_conn->prepare("SELECT branch_state FROM territory_partners WHERE id=? LIMIT 1");
$stmtTP->bind_param('i', $tp_id);
$stmtTP->execute();
$tp_state = $stmtTP->get_result()->fetch_assoc()['branch_state'] ?? '';
$stmtTP->close();

$gst_type = (strtolower($shop_state_name) === strtolower($tp_state)) ? 'inner' : 'outer';

// Validate every line up front — matches shop-invoice-action.php's per-item
// stock check, but here we refuse to create a half-stocked invoice: either
// every line has enough closing_qty, or nothing is created and the TP is
// told what's short so they can restock or trim the order first.
$shortfalls = [];
$validLines = [];
foreach ($lines as $ln) {
    $pr_id = (int)$ln['pr_id'];
    $qty   = (int)$ln['qty'];
    if ($pr_id <= 0 || $qty <= 0) continue;

    $stmtProd = $db_conn->prepare("SELECT productName, gst, hsn, rwpoints, outlet_price FROM products WHERE id=?");
    $stmtProd->bind_param('i', $pr_id);
    $stmtProd->execute();
    $prod = $stmtProd->get_result()->fetch_assoc();
    $stmtProd->close();
    if (!$prod) continue;

    $stmtStk = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
    $stmtStk->bind_param('ii', $tp_id, $pr_id);
    $stmtStk->execute();
    $available = (int)($stmtStk->get_result()->fetch_assoc()['closing_qty'] ?? 0);
    $stmtStk->close();

    if ($available < $qty) {
        $shortfalls[] = $prod['productName'] . " (need $qty, have $available)";
        continue;
    }

    $validLines[] = [
        'pr_id' => $pr_id, 'qty' => $qty, 'prod' => $prod,
        'discount_percentage' => (float)$ln['discount_percentage'],
        'discount_amount'     => (float)$ln['discount_amount'],
    ];
}

if (!empty($shortfalls)) {
    $_SESSION['errorMessage'] = "Not enough stock to invoice: " . implode(', ', $shortfalls) . ". Restock or edit the order, then try again.";
    header('Location: manage-orders.php');
    exit;
}

if (empty($validLines)) {
    $_SESSION['errorMessage'] = "This visit has no valid product lines to invoice.";
    header('Location: manage-orders.php');
    exit;
}

function GeraHashInvOrder($qtd) {
    $chars = '123456789';
    $len = strlen($chars) - 1;
    $hash = '';
    for ($x = 1; $x <= $qtd; $x++) { $hash .= substr($chars, rand(0, $len), 1); }
    return $hash;
}
$inv_id     = GeraHashInvOrder(10) . 'CMPSHP' . date('dmygis');
$inv_number = $inv_id;
$inv_date   = date('Y-m-d');
$inv_year   = date('Y', strtotime($inv_date));

$db_conn->begin_transaction();
try {
    $zero = '0'; $one = '1'; $nil = 'Nil';
    $stmtIns = $db_conn->prepare(
        "INSERT INTO user_invoice
         (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,
          from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmtIns->bind_param('ssssssssssssssssssss',
        $inv_id, $zero, $inv_number, $inv_date, $inv_year, $zero, $zero, $zero,
        $invuser, $customer_id, $Login_user_TYPEvl, $tp_id,
        $gst_type, $zero, $zero, $zero, $one, $buyer_gsttype, $nil, $nil
    );
    $stmtIns->execute();
    $stmtIns->close();

    foreach ($validLines as $vl) {
        $pr_id  = $vl['pr_id'];
        $qty    = $vl['qty'];
        $prod   = $vl['prod'];

        $amount             = (float)$prod['outlet_price'];
        $gst_percentage     = $prod['gst'] ?? 0;
        $hsn                = $prod['hsn'] ?? '';
        $rwpoints_i         = (int)(($prod['rwpoints'] ?? 0) * $qty);
        $totalamount        = $amount * $qty;

        // Same precedence as shop-invoice-action.php: Disc(%) (if set) wins
        // and Disc(Rs.) is derived from it; otherwise the entered Disc(Rs.)
        // is used as-is.
        if ($vl['discount_percentage'] > 0) {
            $discount_percentage = $vl['discount_percentage'];
            $discount_amount     = number_format($totalamount * $discount_percentage / 100, 2, '.', '');
        } else {
            $discount_amount     = $vl['discount_amount'];
            $discount_percentage = $totalamount > 0 ? round($discount_amount * 100 / $totalamount, 2) : 0;
        }

        $subtotal           = number_format($totalamount - $discount_amount, 2, '.', '');
        $gstamount_total    = $subtotal * $gst_percentage / 100;
        $total              = $subtotal + $gstamount_total;
        $gstamount_singlepr = '0';
        $rwpoints_sls_i     = $rwpoints_i;

        $stmtItem = $db_conn->prepare(
            "INSERT INTO user_invoice_items
             (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
              gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
              discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,buyer_gsttype,rwpoints_sls)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmtItem->bind_param(
            'sididsssi' . 'dsddddsssisi',
            $inv_id, $pr_id, $amount, $qty, $total,
            $invuser, $customer_id, $Login_user_TYPEvl, $tp_id,
            $gst_percentage, $gstamount_singlepr, $gstamount_total, $subtotal,
            $discount_percentage, $discount_amount, $gst_type, $hsn, $inv_date,
            $rwpoints_i, $buyer_gsttype, $rwpoints_sls_i
        );
        $stmtItem->execute();
        $stmtItem->close();
    }

    $stmtMark = $db_conn->prepare("UPDATE tp_orders SET invoiced_inv_id=? WHERE order_id=? AND tp_id=?");
    $stmtMark->bind_param('ssi', $inv_id, $order_id, $tp_id);
    $stmtMark->execute();
    $stmtMark->close();

    $db_conn->commit();
} catch (Throwable $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Could not create invoice for this visit. Please try again.";
    header('Location: manage-orders.php');
    exit;
}

header('Location: shop-invoice-add.php?InvoiceID=' . base64_encode($inv_id) . '&invuser=shop&action=edit');
exit;

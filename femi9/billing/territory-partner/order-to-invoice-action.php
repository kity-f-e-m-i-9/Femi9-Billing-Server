<?php
include("checksession.php");
include("config.php");
require_once("include/ShopInvoiceHistory.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Replaces convert-order-invoice.php's "pick an already-raised invoice"
// step with a direct create — same product/qty-line invoice-creation logic
// as order-to-invoice.php, except the shop billed is whichever shop
// order-confirm-shop.php's Yes/No step resolved to (the order's own shop,
// or a different one the TP searched for), not always the order's own
// shop_id. 2026-12-01, user-requested.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-orders.php');
    exit;
}

$invuser  = 'shop';
$tp_id    = (int)$Login_user_IDvl;
$order_id = $_POST['order_id'] ?? '';
$shop_id  = (int)($_POST['shop_id'] ?? 0);

if ($order_id === '' || $shop_id <= 0) {
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

// The chosen shop must be one of this TP's own real shop records — never
// trust the raw POSTed shop_id otherwise (same validate-against-owner
// pattern used throughout this codebase for TP-submitted ids).
$stmtShop = $db_conn->prepare(
    "SELECT temp_id, gstin, state_id, firka_id FROM shop WHERE id=? AND onboard_userID=? AND onboard_userTYPE='territory_partner' LIMIT 1"
);
$stmtShop->bind_param('is', $shop_id, $tp_id);
$stmtShop->execute();
$shopRow = $stmtShop->get_result()->fetch_assoc();
$stmtShop->close();

if (!$shopRow) {
    $_SESSION['errorMessage'] = "Shop not found.";
    header('Location: order-confirm-shop.php?order_id=' . urlencode($order_id));
    exit;
}

$customer_id   = $shopRow['temp_id'];
$buyer_GSTIN   = $shopRow['gstin'] ?? '';
$buyer_gsttype = strlen($buyer_GSTIN) === 15 ? 'register' : 'unregister';

// Same firka-based state resolution as shop-invoice-action.php /
// order-to-invoice.php.
function otia_resolve_state_via_firka(mysqli $db, int $nodeId): ?string {
    static $stateDepth = null;
    if ($stateDepth === null) {
        $r = mysqli_fetch_assoc(mysqli_query($db, "SELECT depth FROM partner_location_layers WHERE layer_name='STATE' LIMIT 1"));
        $stateDepth = $r ? (int)$r['depth'] : 2;
    }
    $cur = $nodeId;
    for ($i = 0; $i < 10 && $cur > 0; $i++) {
        $stmt = $db->prepare("SELECT parent_id, depth, name FROM partner_location_nodes WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $cur);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;
        if ((int)$row['depth'] === $stateDepth) return $row['name'];
        $cur = $row['parent_id'] !== null ? (int)$row['parent_id'] : 0;
    }
    return null;
}
function otia_norm_state(string $s): string {
    return preg_replace('/\s+/', '', strtolower($s));
}

$shop_state_norm = null;
$shop_firka_id = (int)($shopRow['firka_id'] ?? 0);
if ($shop_firka_id > 0) {
    $st = otia_resolve_state_via_firka($db_conn, $shop_firka_id);
    if ($st !== null) $shop_state_norm = otia_norm_state($st);
}
if ($shop_state_norm === null) {
    $state_id = (int)($shopRow['state_id'] ?? 0);
    if ($state_id === 2) {
        $shop_state_norm = otia_norm_state('Tamilnadu');
    } else {
        $stmtState = $db_conn->prepare("SELECT st_name FROM state WHERE id=? LIMIT 1");
        $stmtState->bind_param('i', $state_id);
        $stmtState->execute();
        $shopStateRow = $stmtState->get_result()->fetch_assoc();
        $stmtState->close();
        $shop_state_norm = otia_norm_state($shopStateRow['st_name'] ?? '');
    }
}

$tp_state_norms = [];
$stmtTPLoc = $db_conn->prepare("SELECT location_id FROM territory_partner_locations WHERE territory_partner_id=?");
$stmtTPLoc->bind_param('i', $tp_id);
$stmtTPLoc->execute();
$tpLocRes = $stmtTPLoc->get_result();
while ($locRow = $tpLocRes->fetch_assoc()) {
    $st = otia_resolve_state_via_firka($db_conn, (int)$locRow['location_id']);
    if ($st !== null) $tp_state_norms[otia_norm_state($st)] = true;
}
$stmtTPLoc->close();

if (!empty($tp_state_norms)) {
    $gst_type = ($shop_state_norm !== '' && isset($tp_state_norms[$shop_state_norm])) ? 'inner' : 'outer';
} else {
    $stmtTP = $db_conn->prepare("SELECT branch_state FROM territory_partners WHERE id=? LIMIT 1");
    $stmtTP->bind_param('i', $tp_id);
    $stmtTP->execute();
    $tpRow = $stmtTP->get_result()->fetch_assoc();
    $stmtTP->close();
    $norm_tp_state = otia_norm_state($tpRow['branch_state'] ?? '');
    $gst_type = ($shop_state_norm !== '' && $shop_state_norm === $norm_tp_state) ? 'inner' : 'outer';
}

$shortfalls = [];
$validLines = [];
foreach ($lines as $ln) {
    $pr_id = (int)$ln['pr_id'];
    $qty   = (int)$ln['qty'];
    if ($pr_id <= 0 || $qty <= 0) continue;

    $stmtProd = $db_conn->prepare("SELECT productName, gst, gst_type, hsn, rwpoints, outlet_price FROM products WHERE id=?");
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

if (empty($validLines)) {
    $_SESSION['errorMessage'] = !empty($shortfalls)
        ? "None of the products in this order have enough stock to invoice: " . implode(', ', $shortfalls) . ". Restock, then try again."
        : "This visit has no valid product lines to invoice.";
    header('Location: manage-orders.php');
    exit;
}
if (!empty($shortfalls)) {
    $_SESSION['errorMessage'] = "Out of stock — left out of this invoice: " . implode(', ', $shortfalls) . ". Restock and add separately if needed.";
}

function GeraHashInvOrderAction($qtd) {
    $chars = '123456789';
    $len = strlen($chars) - 1;
    $hash = '';
    for ($x = 1; $x <= $qtd; $x++) { $hash .= substr($chars, rand(0, $len), 1); }
    return $hash;
}
$inv_id     = GeraHashInvOrderAction(10) . 'CMPSHP' . date('dmygis');
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
        $gst_type_item      = $prod['gst_type'] ?: 'exclusive';
        $hsn                = $prod['hsn'] ?? '';
        $rwpoints_i         = (int)(($prod['rwpoints'] ?? 0) * $qty);
        $totalamount        = $amount * $qty;

        if ($vl['discount_percentage'] > 0) {
            $discount_percentage = $vl['discount_percentage'];
            $discount_amount     = number_format($totalamount * $discount_percentage / 100, 2, '.', '');
        } else {
            $discount_amount     = $vl['discount_amount'];
            $discount_percentage = $totalamount > 0 ? round($discount_amount * 100 / $totalamount, 2) : 0;
        }

        $subtotal = number_format($totalamount - $discount_amount, 2, '.', '');

        if ($gst_type_item === 'inclusive' && $gst_percentage > 0) {
            $taxable_value   = $subtotal * 100 / (100 + $gst_percentage);
            $gstamount_total = $subtotal - $taxable_value;
            $total           = $subtotal;
        } else {
            $gstamount_total = $subtotal * $gst_percentage / 100;
            $total           = $subtotal + $gstamount_total;
        }
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

        logShopInvoiceChange($db_conn, $inv_id, $pr_id, 'initial', null, $qty, $Login_user_TYPEvl, (string)$tp_id, 'From field/DM order visit');
    }

    // Same invoiced_inv_id/invoiced_at pair convert-order-invoice.php used to
    // set, so manage-orders.php's/marketing's existing Pending/Converted
    // status logic keeps working unchanged.
    $chkInvoicedAt = $db_conn->query("SHOW COLUMNS FROM tp_orders LIKE 'invoiced_at'");
    if ($chkInvoicedAt && $chkInvoicedAt->num_rows === 0) {
        $db_conn->query("ALTER TABLE tp_orders ADD COLUMN invoiced_at DATETIME NULL DEFAULT NULL AFTER invoiced_inv_id");
    }
    $stmtMark = $db_conn->prepare("UPDATE tp_orders SET invoiced_inv_id=?, invoiced_at=NOW() WHERE order_id=? AND tp_id=?");
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

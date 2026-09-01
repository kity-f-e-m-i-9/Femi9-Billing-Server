<?php
include("checksession.php");
include("config.php");
require_once("include/ShopInvoiceHistory.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (!isset($_REQUEST['addInvoice'])) { header("Location: shop-invoice-add.php"); exit; }

$inv_id      = $_REQUEST['inv_id']      ?? '';
$invuser     = $_REQUEST['invuser']     ?? 'shop';

// A voided invoice is read-only.
$stmtVoidChk = $db_conn->prepare("SELECT status FROM user_invoice WHERE inv_id=? LIMIT 1");
$stmtVoidChk->bind_param('s', $inv_id);
$stmtVoidChk->execute();
$voidChkRow = $stmtVoidChk->get_result()->fetch_assoc();
$stmtVoidChk->close();
if (($voidChkRow['status'] ?? '') === 'cancelled') {
    $_SESSION['errorMessage'] = "This invoice has been voided and can no longer be edited.";
    echo "<script>window.location='shop-manage-invoice.php';</script>";
    exit;
}
$customer_id = $_REQUEST['customer_id'] ?? '';
$date        = date("Y-m-d", strtotime($_REQUEST['date'] ?? date("Y-m-d")));
$inv_year    = date("Y", strtotime($date));
$tp_id       = (int)$Login_user_IDvl;

// A shop with an un-invoiced field order ("Get Order" visit) must be
// invoiced from that order (order-to-invoice.php via manage-orders.php),
// not started fresh here — otherwise the field order is silently orphaned
// and never shows as fulfilled. Only blocks the very first item on a brand
// new invoice (inv_id not yet in user_invoice); adding more items to an
// invoice already in progress is unaffected.
$stmtChkNew = $db_conn->prepare("SELECT COUNT(*) AS n FROM user_invoice WHERE inv_id=?");
$stmtChkNew->bind_param('s', $inv_id);
$stmtChkNew->execute();
$isBrandNewInvoice = ((int)($stmtChkNew->get_result()->fetch_assoc()['n'] ?? 0)) === 0;
$stmtChkNew->close();

if ($isBrandNewInvoice && $customer_id !== '') {
    // tp_orders.shop_id is shop.id (the PK), but $customer_id here is
    // shop.temp_id (what invoices key shops by) — resolve PK first.
    $stmtPending = $db_conn->prepare(
        "SELECT 1 FROM tp_orders o
         INNER JOIN shop s ON s.id = o.shop_id
         WHERE o.tp_id=? AND s.temp_id=? AND o.new_order='yes' AND o.voided_at IS NULL
           AND (o.invoiced_inv_id IS NULL OR o.invoiced_inv_id='')
         LIMIT 1"
    );
    $stmtPending->bind_param('is', $tp_id, $customer_id);
    $stmtPending->execute();
    $hasPendingOrder = (bool)$stmtPending->get_result()->fetch_assoc();
    $stmtPending->close();
    if ($hasPendingOrder) {
        $_SESSION['errorMessage'] = "This shop has a pending field order. Please invoice it from Manage Orders instead of adding a new invoice directly.";
        echo "<script>window.location='shop-invoice-add.php?invuser=$invuser';</script>";
        exit;
    }
}

// Check invoice number duplicate (set by AJAX in form)
if (($_REQUEST['invoice_number_accept'] ?? '1') == '0') {
    $_SESSION['errorMessage'] = "Invoice Number already exists!";
    echo "<script>window.location='shop-invoice-add.php?invoicealready&&invuser=$invuser';</script>";
    exit;
}


$inv_number = str_replace("'", '', $_REQUEST['inv_number'] ?? '');

$pr_id  = (int)($_REQUEST['pr_id']  ?? 0);
$amount = (float)($_REQUEST['amount'] ?? 0);
$qty    = (int)($_REQUEST['qty']    ?? 0);

// Product details
$stmtProd = $db_conn->prepare("SELECT gst,gst_type,hsn,rwpoints FROM products WHERE id=?");
$stmtProd->bind_param('i', $pr_id);
$stmtProd->execute();
$prod = $stmtProd->get_result()->fetch_assoc();
$stmtProd->close();
$gst_percentage  = $prod['gst']      ?? 0;
$gst_type_item   = $prod['gst_type'] ?: 'exclusive';
$hsn             = $prod['hsn']      ?? '';
$rwpoints        = ($prod['rwpoints'] ?? 0) * $qty;

$totalamount = $amount * $qty;

if (($_REQUEST['discount_percentage'] ?? 0) > 0) {
    $discount_percentage = (float)$_REQUEST['discount_percentage'];
    $discount_amount     = number_format($totalamount * $discount_percentage / 100, 2, '.', '');
} else {
    $discount_amount     = (float)($_REQUEST['discount_amount'] ?? 0);
    $discount_percentage = $totalamount > 0 ? inr_format($discount_amount * 100 / $totalamount, 2) : 0;
}

$subtotal = number_format($totalamount - $discount_amount, 2, '.', '');

// Inclusive-tax products already have GST baked into the entered price, so
// the tax is carved out of subtotal (and NOT added again into total);
// exclusive-tax products get GST added on top — same convention as
// tp-invoice-print.php / shop-invoice-print.php.
if ($gst_type_item === 'inclusive' && $gst_percentage > 0) {
    $taxable_value   = $subtotal * 100 / (100 + $gst_percentage);
    $gstamount_total = $subtotal - $taxable_value;
    $total           = $subtotal;
} else {
    $gstamount_total = $subtotal * $gst_percentage / 100;
    $total           = $subtotal + $gstamount_total;
}
$gstamount_singlepr = '0';

// Customer/GST type
$stmtShop = $db_conn->prepare("SELECT gstin, state_id, firka_id FROM shop WHERE temp_id=? LIMIT 1");
$stmtShop->bind_param('s', $customer_id);
$stmtShop->execute();
$shopRow = $stmtShop->get_result()->fetch_assoc();
$stmtShop->close();

$buyer_GSTIN   = $shopRow['gstin'] ?? '';
$buyer_gsttype = strlen($buyer_GSTIN) === 15 ? 'register' : 'unregister';

// Walks a partner_location_nodes id up its parent chain to the STATE-depth
// ancestor and returns that node's name — the firka hierarchy (see
// territory-partner/geo_layers.php) is authoritative for where a TP/shop is
// actually assigned, unlike the free-text branch_state / state master fields
// below which have inconsistent spelling/spacing ("Tamil Nadu" vs
// "Tamilnadu") and caused every intra-state shop invoice to be wrongly taxed
// as inter-state (IGST instead of CGST/SGST).
function tp_invoice_resolve_state_via_firka(mysqli $db, int $nodeId): ?string {
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
function tp_invoice_norm_state(string $s): string {
    return preg_replace('/\s+/', '', strtolower($s));
}

// Shop's state — prefer its assigned firka, fall back to the state master
// lookup (only ~19% of shops have a firka assigned so far).
$shop_state_norm = null;
$shop_firka_id = (int)($shopRow['firka_id'] ?? 0);
if ($shop_firka_id > 0) {
    $st = tp_invoice_resolve_state_via_firka($db_conn, $shop_firka_id);
    if ($st !== null) $shop_state_norm = tp_invoice_norm_state($st);
}
if ($shop_state_norm === null) {
    $state_id = (int)($shopRow['state_id'] ?? 0);
    // Legacy data bug: a large batch of shops (~7,300) were stamped with
    // state_id=2, which is not a valid `state` table id (Tamilnadu is id=7
    // there) — it's a stray partner_location_nodes id (node 2 = "Tamilnadu")
    // written into the wrong column by an old import/bulk-assignment. Since
    // this business operates in Tamil Nadu, treat it as Tamilnadu rather than
    // letting the lookup silently fail and force every such shop to IGST.
    if ($state_id === 2) {
        $shop_state_norm = tp_invoice_norm_state('Tamilnadu');
    } else {
        $stmtState = $db_conn->prepare("SELECT st_name FROM state WHERE id=? LIMIT 1");
        $stmtState->bind_param('i', $state_id);
        $stmtState->execute();
        $shopStateRow = $stmtState->get_result()->fetch_assoc();
        $stmtState->close();
        $shop_state_norm = tp_invoice_norm_state($shopStateRow['st_name'] ?? '');
    }
}

// TP's state(s) — every firka they're assigned to, resolved up to STATE
// depth (almost always a single state, but handled as a set just in case).
$tp_state_norms = [];
$stmtTPLoc = $db_conn->prepare("SELECT location_id FROM territory_partner_locations WHERE territory_partner_id=?");
$stmtTPLoc->bind_param('i', $tp_id);
$stmtTPLoc->execute();
$tpLocRes = $stmtTPLoc->get_result();
while ($locRow = $tpLocRes->fetch_assoc()) {
    $st = tp_invoice_resolve_state_via_firka($db_conn, (int)$locRow['location_id']);
    if ($st !== null) $tp_state_norms[tp_invoice_norm_state($st)] = true;
}
$stmtTPLoc->close();

if (!empty($tp_state_norms)) {
    $gst_type = ($shop_state_norm !== '' && isset($tp_state_norms[$shop_state_norm])) ? 'inner' : 'outer';
} else {
    // TP has no firka assignment at all (rare) — fall back to the free-text
    // branch_state field, still normalized.
    $stmtTP = $db_conn->prepare("SELECT branch_state FROM territory_partners WHERE id=? LIMIT 1");
    $stmtTP->bind_param('i', $tp_id);
    $stmtTP->execute();
    $tpRow = $stmtTP->get_result()->fetch_assoc();
    $stmtTP->close();
    $norm_tp_state = tp_invoice_norm_state($tpRow['branch_state'] ?? '');
    $gst_type = ($shop_state_norm !== '' && $shop_state_norm === $norm_tp_state) ? 'inner' : 'outer';
}

// Create invoice if not exists
$stmtChk = $db_conn->prepare("SELECT COUNT(*) AS n FROM user_invoice WHERE inv_id=? AND from_user_type=? AND from_user_id=? AND to_user_type=? AND to_user_id=?");
$stmtChk->bind_param('ssiss', $inv_id, $Login_user_TYPEvl, $tp_id, $invuser, $customer_id);
$stmtChk->execute();
$chk = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if ((int)$chk['n'] === 0) {
    $zero = '0'; $one = '1'; $nil = 'Nil';
    $stmtIns = $db_conn->prepare(
        "INSERT INTO user_invoice
         (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,
          from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmtIns->bind_param('ssssssssssssssssssss',
        $inv_id, $zero, $inv_number, $date, $inv_year, $zero, $zero, $zero,
        $invuser, $customer_id, $Login_user_TYPEvl, $tp_id,
        $gst_type, $zero, $zero, $zero, $one, $buyer_gsttype, $nil, $nil
    );
    $stmtIns->execute(); $stmtIns->close();
}

// Check TP stock
$stmtStk = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
$stmtStk->bind_param('ii', $tp_id, $pr_id);
$stmtStk->execute();
$stockRow = $stmtStk->get_result()->fetch_assoc();
$stmtStk->close();
$available = $stockRow ? (int)$stockRow['closing_qty'] : 0;

if ($available < $qty) {
    echo "<script>window.location='shop-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&InvalidStock&&invuser=$invuser&&action=" . ($_SESSION['ACTIONEDIT'] ?? '') . "&&AlertStockError';</script>";
    exit;
}

// Check duplicate item
$stmtDup = $db_conn->prepare("SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id=? AND pr_id=? AND from_user_type=? AND from_user_id=? AND to_user_type=? AND to_user_id=?");
$stmtDup->bind_param('siisss', $inv_id, $pr_id, $Login_user_TYPEvl, $Login_user_IDvl, $invuser, $customer_id);
$stmtDup->execute();
$dupChk = $stmtDup->get_result()->fetch_assoc();
$stmtDup->close();
if ((int)$dupChk['n'] > 0) {
    echo "<script>window.location='shop-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&ItemAlreadyExists&&invuser=$invuser&&action=" . ($_SESSION['ACTIONEDIT'] ?? '') . "&&AlertMessage';</script>";
    exit;
}

// Insert item
$rwpoints_sls = $rwpoints;
$rwpoints_i = (int)$rwpoints;
$rwpoints_sls_i = (int)$rwpoints_sls;
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
    $discount_percentage, $discount_amount, $gst_type, $hsn, $date,
    $rwpoints_i, $buyer_gsttype, $rwpoints_sls_i
);
$stmtItem->execute(); $stmtItem->close();

logShopInvoiceChange($db_conn, $inv_id, $pr_id, 'added', null, $qty, $Login_user_TYPEvl, (string)$Login_user_IDvl);

echo "<script>window.location='shop-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&AddedSuccess&&invuser=$invuser&&action=" . ($_SESSION['ACTIONEDIT'] ?? '') . "&&FemiAdded';</script>";

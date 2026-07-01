<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: manage-tp-invoices"); exit;
}
if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices"); exit;
}

$inv_id    = (int)($_POST['inv_id'] ?? 0);
$returnid  = trim($_POST['returnid'] ?? '');
$prids     = $_POST['prid']  ?? [];
$qtys      = $_POST['qty']   ?? [];
$rates     = $_POST['rate']  ?? [];
$created_by = $_SESSION['LOGIN_USER'] ?? 'system';

if (!$inv_id) { header("Location: manage-tp-invoices"); exit; }

// Load TP invoice
$s = $db_conn->prepare("SELECT tpi.*, tp.id AS tp_db_id FROM tp_invoices tpi JOIN territory_partners tp ON tp.id=tpi.territory_partner_id WHERE tpi.id=? LIMIT 1");
$s->bind_param('i', $inv_id);
$s->execute();
$invoice = $s->get_result()->fetch_assoc();
$s->close();
if (!$invoice) { header("Location: manage-tp-invoices"); exit; }

$inv_number = $invoice['invoice_number'];
$tp_db_id   = (int)$invoice['tp_db_id'];

// Build items array (only non-zero quantities)
$items = [];
foreach ($prids as $i => $pid) {
    $pid = (int)$pid;
    $qty = (int)($qtys[$i] ?? 0);
    $rate = (float)($rates[$i] ?? 0);
    if ($pid > 0 && $qty > 0) {
        $items[] = [
            'prid'  => $pid,
            'qty'   => $qty,
            'rate'  => $rate,
            'total' => round($qty * $rate, 2),
        ];
    }
}

if (empty($items)) {
    $_SESSION['errorMessage'] = "No items selected for return.";
    $enc = $returnid ? '&returnid=' . base64_encode($returnid) : '';
    header("Location: tp-cnote-new?inv_id={$inv_id}{$enc}"); exit;
}

// Validate max returnable per product
$alreadyReturned = [];
$s = $db_conn->prepare("SELECT ursi.prid, SUM(ursi.qty) AS rqty FROM user_return_stock_items ursi JOIN user_return_stock urs ON urs.returnid=ursi.returnid WHERE urs.invnumber=? AND urs.from_usertype='territory_partner' AND urs.status='accept' GROUP BY ursi.prid");
$s->bind_param('s', $inv_number);
$s->execute();
foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $alreadyReturned[(int)$r['prid']] = (int)$r['rqty'];
}
$s->close();

$origQty = [];
$s = $db_conn->prepare("SELECT product_id, quantity FROM tp_invoice_items WHERE tp_invoice_id=?");
$s->bind_param('i', $inv_id);
$s->execute();
foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $origQty[(int)$r['product_id']] = (int)$r['quantity'];
}
$s->close();

foreach ($items as $item) {
    $avail = ($origQty[$item['prid']] ?? 0) - ($alreadyReturned[$item['prid']] ?? 0);
    if ($item['qty'] > $avail) {
        $_SESSION['errorMessage'] = "Return qty exceeds available qty for one or more products.";
        $enc = $returnid ? '&returnid=' . base64_encode($returnid) : '';
        header("Location: tp-cnote-new?inv_id={$inv_id}{$enc}"); exit;
    }
}

// Create or update CN header
$today = date('Y-m-d');

if (!$returnid) {
    // Generate a new return ID
    $rand = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    $returnid = 'TPCN-' . $rand;
    $subtotal = 0; $discount = 0; $total = 0;
    $s = $db_conn->prepare("INSERT INTO user_return_stock (returnid, invnumber, date, from_usertype, from_userid, to_usertype, to_userid, subtotal, discount, total, status, rwpoints_enable, buyer_gsttype, gst_type) VALUES (?, ?, ?, 'territory_partner', ?, 'company', 'company', 0, 0, 0, 'pending', 0, '', '')");
    $tp_id_str = (string)$tp_db_id;
    $s->bind_param('ssss', $returnid, $inv_number, $today, $tp_id_str);
    $s->execute(); $s->close();
} else {
    // Verify it exists and is still pending
    $s = $db_conn->prepare("SELECT id FROM user_return_stock WHERE returnid=? AND status='pending' LIMIT 1");
    $s->bind_param('s', $returnid);
    $s->execute();
    if (!$s->get_result()->fetch_assoc()) {
        $_SESSION['errorMessage'] = "This credit note has already been finalised.";
        header("Location: tp-cnote-manage"); exit;
    }
    $s->close();
    // Delete existing items — will re-insert fresh
    $s = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE returnid=?");
    $s->bind_param('s', $returnid);
    $s->execute(); $s->close();
}

// Insert items
$tp_id_str = (string)$tp_db_id;
$s = $db_conn->prepare("INSERT INTO user_return_stock_items (returnid, invnumber, prid, amount, qty, subtotal, gst_percentage, gstamount_total, total, from_usertype, from_userid, to_usertype, to_userid, date, status, hsn, damaged_qty, rwpoints, buyer_gsttype, gst_type, rwpoints_sls) VALUES (?,?,?,?,?,?,0,0,?,'territory_partner',?,'company','company',?,'pending','',0,0,'','',0)");

foreach ($items as $item) {
    $line_sub = round($item['qty'] * $item['rate'], 2);
    $s->bind_param('ssididdss',
        $returnid, $inv_number,
        $item['prid'], $item['rate'],
        $item['qty'], $line_sub,
        $item['total'],
        $tp_id_str,
        $today
    );
    $s->execute();
}
$s->close();

$enc_returnid = base64_encode($returnid);
header("Location: tp-cnote-new?inv_id={$inv_id}&returnid={$enc_returnid}");
exit;

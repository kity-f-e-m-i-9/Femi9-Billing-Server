<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid     = base64_decode($_REQUEST['returnid'] ?? '');
$returnid     = mysqli_real_escape_string($db_conn, $returnid);
$SubTotal     = (float)($_REQUEST['SubTotal'] ?? 0);
$discount     = (float)($_REQUEST['discount'] ?? 0);
$total_amount = $SubTotal - $discount;

if (empty($returnid)) {
    header("Location: manage-return.php?error=invalid_returnid"); exit;
}

// Fetch return master
$stmt = $db_conn->prepare("SELECT invnumber, from_usertype, from_userid, to_usertype, to_userid FROM user_return_stock WHERE returnid=? LIMIT 1");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$return = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$return) { header("Location: manage-return.php?error=not_found"); exit; }

$invid         = $return['invnumber'];
$from_usertype = $return['from_usertype'];
$from_userid   = $return['from_userid'];
$to_usertype   = $return['to_usertype'];   // 'territory_partner'
$to_userid     = $return['to_userid'];     // integer TP id

// Finalize status
$stmt = $db_conn->prepare("UPDATE user_return_stock SET subtotal=?, discount=?, total=?, status='accept' WHERE returnid=?");
$stmt->bind_param('ddds', $SubTotal, $discount, $total_amount, $returnid);
$stmt->execute();
$stmt->close();

$stmt = $db_conn->prepare("UPDATE user_return_stock_items SET status='accept' WHERE returnid=?");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$stmt->close();

// Stock adjustments per item
$stmt = $db_conn->prepare("SELECT prid, qty FROM user_return_stock_items WHERE returnid=?");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$tp_id = (int)$to_userid;

foreach ($items as $item) {
    $prid      = $item['prid'];
    $returnqty = (int)$item['qty'];

    // ── RECEIVER = TP: add returned stock back to territory_partner_stock ──
    $stmt = $db_conn->prepare(
        "UPDATE territory_partner_stock SET closing_qty = closing_qty + ?
         WHERE territory_partner_id=? AND product_id=?"
    );
    $stmt->bind_param('iii', $returnqty, $tp_id, $prid);
    $stmt->execute();
    $stmt->close();

    // ── SENDER: reduce their stock if trackable ────────────────────────────
    if (in_array($from_usertype, ['super_stockiest','stockiest','super_distributor','distributor'])) {
        $stmt = $db_conn->prepare(
            "UPDATE stock SET input_qty=input_qty-?, closing_qty=closing_qty-?
             WHERE product_id=? AND user_type=? AND user_id=? LIMIT 1"
        );
        $stmt->bind_param('iisss', $returnqty, $returnqty, $prid, $from_usertype, $from_userid);
        $stmt->execute();
        $stmt->close();
    }
}

// Get invoice number for message
$inv_table = ($from_usertype === 'customer') ? 'invoice' : 'user_invoice';
$stmt = $db_conn->prepare("SELECT inv_number FROM $inv_table WHERE inv_id=? LIMIT 1");
$stmt->bind_param('s', $invid);
$stmt->execute();
$invdata = $stmt->get_result()->fetch_assoc();
$stmt->close();

$_SESSION['successMessage'] = "Credit Note Added Successfully against Invoice: " . htmlspecialchars($invdata['inv_number'] ?? '');
echo "<script>window.location='manage-return.php?returnaddedsuccess';</script>";
exit;
?>

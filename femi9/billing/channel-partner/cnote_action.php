<?php
include("checksession.php");
include("config.php");
include("return-validation-functions.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (isset($_REQUEST['add-return'])) {

    $from_usertype = mysqli_real_escape_string($db_conn, trim($_REQUEST['from_usertype'] ?? ''));
    $from_userid   = mysqli_real_escape_string($db_conn, trim($_REQUEST['from_userid']   ?? ''));
    $to_usertype   = mysqli_real_escape_string($db_conn, trim($_REQUEST['to_usertype']   ?? ''));
    $to_userid     = mysqli_real_escape_string($db_conn, trim($_REQUEST['to_userid']     ?? ''));
    $returnid      = mysqli_real_escape_string($db_conn, trim($_REQUEST['returnid']      ?? ''));
    $invid         = mysqli_real_escape_string($db_conn, trim($_REQUEST['invid']         ?? ''));
    $invnumber     = $invid;
    $prid          = mysqli_real_escape_string($db_conn, trim($_REQUEST['prid']          ?? ''));
    $returnqty     = (int)($_REQUEST['returnqty']  ?? 0);
    $damaged_qty   = (int)($_REQUEST['damaged_qty'] ?? 0);

    if (empty($from_usertype) || empty($from_userid) || empty($to_usertype) ||
        empty($to_userid) || empty($returnid) || empty($invid) || empty($prid)) {
        header("Location: cnote_new.php?error=missing_fields"); exit;
    }
    if ($returnqty <= 0) {
        header("Location: cnote_new.php?error=invalid_quantity"); exit;
    }

    // Validate available qty
    $availability = getReturnAvailability($db_conn, $invid, $prid, $from_usertype, $returnid);
    if ($availability['error']) {
        header("Location: cnote_new.php?error=product_not_in_invoice"); exit;
    }
    if ($returnqty > $availability['available_qty']) {
        $enc_rid = base64_encode($returnid);
        $enc_iid = base64_encode($invnumber);
        header("Location: cnote_new.php?returnid=$enc_rid&InvoiceID=$enc_iid&invalidqty&available={$availability['available_qty']}&requested=$returnqty&already_returned={$availability['returned_qty']}");
        exit;
    }

    // Buyer GST type
    $table_map = [
        'super_stockiest'  => 'super_stockiest',
        'stockiest'        => 'stockiest',
        'super_distributor'=> 'super_distributor',
        'distributor'      => 'distributor',
        'outlet'           => 'outlet',
        'shop'             => 'shop',
        'customer'         => 'customers',
    ];
    $table = $table_map[$from_usertype] ?? 'customers';
    if ($table === 'customers') {
        $stmt = $db_conn->prepare("SELECT gstin FROM $table WHERE id=? LIMIT 1");
    } else {
        $stmt = $db_conn->prepare("SELECT gstin FROM $table WHERE temp_id=? LIMIT 1");
    }
    $stmt->bind_param('s', $from_userid);
    $stmt->execute();
    $cust = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $buyer_gsttype = (strlen($cust['gstin'] ?? '') === 15) ? 'register' : 'unregister';

    // Invoice details
    $inv_table = ($from_usertype === 'customer') ? 'invoice' : 'user_invoice';
    $stmt = $db_conn->prepare("SELECT rwpoints_enable, gst_type FROM $inv_table WHERE inv_id=? LIMIT 1");
    $stmt->bind_param('s', $invid);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$inv) { header("Location: cnote_new.php?error=invoice_not_found"); exit; }
    $rwpoints_enable = (int)($inv['rwpoints_enable'] ?? 0);
    $gst_type        = $inv['gst_type'];

    // Product details
    $stmt = $db_conn->prepare("SELECT gst, hsn, rwpoints FROM products WHERE id=? LIMIT 1");
    $stmt->bind_param('s', $prid);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$product) { header("Location: cnote_new.php?error=product_not_found"); exit; }

    // Invoice item price
    $item_table = ($from_usertype === 'customer') ? 'invoice_items' : 'user_invoice_items';
    $stmt = $db_conn->prepare("SELECT qty, amount FROM $item_table WHERE inv_id=? AND pr_id=? LIMIT 1");
    $stmt->bind_param('ss', $invid, $prid);
    $stmt->execute();
    $inv_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$inv_item) { header("Location: cnote_new.php?error=product_not_in_invoice_items"); exit; }

    $subtotal   = (float)$inv_item['amount'] * $returnqty;
    $gst_amt    = ($subtotal * (float)$product['gst']) / 100;
    $total      = $subtotal + $gst_amt;
    $rwpoints   = (float)$product['rwpoints'] * $returnqty;
    $return_date = date('Y-m-d');

    // Create/update master return record
    $stmt = $db_conn->prepare("
        INSERT INTO user_return_stock
          (returnid, invnumber, date, subtotal, discount, total,
           from_usertype, from_userid, to_usertype, to_userid,
           status, rwpoints_enable, buyer_gsttype, gst_type)
        VALUES (?,?,?, 0,0,0, ?,?,?,?, 'pending',?,?,?)
        ON DUPLICATE KEY UPDATE
          from_usertype=VALUES(from_usertype), from_userid=VALUES(from_userid),
          to_usertype=VALUES(to_usertype),     to_userid=VALUES(to_userid)
    ");
    $stmt->bind_param('sssssssiss',
        $returnid, $invnumber, $return_date,
        $from_usertype, $from_userid, $to_usertype, $to_userid,
        $rwpoints_enable, $buyer_gsttype, $gst_type);
    $stmt->execute();
    $stmt->close();

    // Duplicate product check
    $stmt = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM user_return_stock_items WHERE returnid=? AND prid=?");
    $stmt->bind_param('ss', $returnid, $prid);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ((int)$exists['cnt'] > 0) {
        $enc_rid = base64_encode($returnid);
        $enc_iid = base64_encode($invnumber);
        header("Location: cnote_new.php?returnid=$enc_rid&InvoiceID=$enc_iid&productalreadyexists"); exit;
    }

    // Insert return item
    $stmt = $db_conn->prepare("
        INSERT INTO user_return_stock_items
          (returnid, invnumber, prid, amount, qty, subtotal,
           gst_percentage, gstamount_total, total,
           from_usertype, from_userid, to_usertype, to_userid,
           date, status, hsn, damaged_qty, rwpoints, buyer_gsttype, gst_type, rwpoints_sls)
        VALUES (?,?,?, ?,?,?, ?,?,?, ?,?,?,?, ?,'pending', ?,?,?, ?,?,?)
    ");
    $stmt->bind_param('sssidddddssssssidssd',
        $returnid, $invnumber, $prid,
        $inv_item['amount'], $returnqty, $subtotal,
        $product['gst'], $gst_amt, $total,
        $from_usertype, $from_userid, $to_usertype, $to_userid,
        $return_date,
        $product['hsn'], $damaged_qty, $rwpoints,
        $buyer_gsttype, $gst_type, $rwpoints);
    $stmt->execute();
    $stmt->close();

    $enc_rid = base64_encode($returnid);
    $enc_iid = base64_encode($invnumber);
    header("Location: cnote_new.php?returnid=$enc_rid&InvoiceID=$enc_iid&addedsuccess");
    exit;
}

header("Location: manage-return.php");
exit;
?>

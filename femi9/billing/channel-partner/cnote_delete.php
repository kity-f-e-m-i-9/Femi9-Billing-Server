<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$returnid = base64_decode($_REQUEST['returnid'] ?? '');
$returnid = mysqli_real_escape_string($db_conn, $returnid);
$rowid    = base64_decode($_REQUEST['rowid']    ?? '');
$rowid    = mysqli_real_escape_string($db_conn, $rowid);

if (empty($returnid) || empty($rowid)) {
    header("Location: manage-return.php"); exit;
}

// Fetch return master
$stmt = $db_conn->prepare("SELECT from_usertype, from_userid, to_usertype, to_userid, status FROM user_return_stock WHERE returnid=? LIMIT 1");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$return = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch item
$stmt = $db_conn->prepare("SELECT prid, qty FROM user_return_stock_items WHERE id=? LIMIT 1");
$stmt->bind_param('s', $rowid);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($return && $item) {
    $from_usertype = $return['from_usertype'];
    $from_userid   = $return['from_userid'];
    $to_userid     = $return['to_userid'];   // TP integer id
    $return_status = $return['status'];
    $prid          = $item['prid'];
    $returnqty     = (int)$item['qty'];
    $tp_id         = (int)$to_userid;

    // Reverse stock if already finalized
    if (in_array($return_status, ['accept', 'completed'])) {
        // Receiver (TP): reduce closing_qty back
        $stmt = $db_conn->prepare(
            "UPDATE territory_partner_stock SET closing_qty = closing_qty - ?
             WHERE territory_partner_id=? AND product_id=?"
        );
        $stmt->bind_param('iii', $returnqty, $tp_id, $prid);
        $stmt->execute();
        $stmt->close();

        // Sender: restore their stock
        if (in_array($from_usertype, ['super_stockiest','stockiest','super_distributor','distributor'])) {
            $stmt = $db_conn->prepare(
                "UPDATE stock SET input_qty=input_qty+?, closing_qty=closing_qty+?
                 WHERE product_id=? AND user_type=? AND user_id=? LIMIT 1"
            );
            $stmt->bind_param('iisss', $returnqty, $returnqty, $prid, $from_usertype, $from_userid);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Delete the item
$stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id=?");
$stmt->bind_param('s', $rowid);
$stmt->execute();
$stmt->close();

$enc_returnid = base64_encode($returnid);
if (isset($_REQUEST['redirurl']) && $_REQUEST['redirurl'] === 'cnote_details') {
    header("Location: cnote_details.php?returnid=$enc_returnid&DeleteSuccess");
} else {
    $enc_invid = $_REQUEST['InvoiceID'] ?? '';
    header("Location: cnote_new.php?returnid=$enc_returnid&InvoiceID=$enc_invid&DeleteSuccess");
}
exit;
?>

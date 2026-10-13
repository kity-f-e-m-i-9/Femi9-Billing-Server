<?php
include("checksession.php");
include("config.php");
error_reporting(0);

// Lets a TP set/update the printed invoice's Terms of Delivery from an
// already-finalised credit note (cnote_details.php), independent of
// cnote_finish.php's one-time "still pending" guard — a finalised return
// should still be able to correct what prints on the regenerated invoice.

$returnid = mysqli_real_escape_string($db_conn, base64_decode($_REQUEST['returnid'] ?? ''));
$terms    = trim($_POST['terms'] ?? '');

if (empty($returnid)) {
    header("Location: manage-return.php?error=invalid_returnid"); exit;
}

$stmt = $db_conn->prepare("SELECT invnumber, from_usertype FROM user_return_stock WHERE returnid=? LIMIT 1");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$return = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$return) {
    header("Location: manage-return.php?error=not_found"); exit;
}

$invid         = $return['invnumber'];
$from_usertype = $return['from_usertype'];

if ($terms !== '') {
    $inv_table_dn = ($from_usertype === 'customer') ? 'invoice' : 'user_invoice';
    $stmtInvNum = $db_conn->prepare("SELECT inv_number FROM $inv_table_dn WHERE inv_id=? LIMIT 1");
    $stmtInvNum->bind_param('s', $invid);
    $stmtInvNum->execute();
    $inv_number_dn = $stmtInvNum->get_result()->fetch_assoc()['inv_number'] ?? $invid;
    $stmtInvNum->close();

    $dn_table_tag = ($from_usertype === 'customer') ? 'customer' : 'shop';

    $stmtDnChk = $db_conn->prepare("SELECT id FROM delivery_note WHERE inv_id=? LIMIT 1");
    $stmtDnChk->bind_param('s', $invid);
    $stmtDnChk->execute();
    $dnRow = $stmtDnChk->get_result()->fetch_assoc();
    $stmtDnChk->close();

    if ($dnRow) {
        $stmtDnUpd = $db_conn->prepare("UPDATE delivery_note SET terms=? WHERE inv_id=?");
        $stmtDnUpd->bind_param('ss', $terms, $invid);
        $stmtDnUpd->execute();
        $stmtDnUpd->close();
    } else {
        // '0000-00-00' is rejected under strict SQL mode (production), so
        // use a real, always-valid placeholder date instead.
        $stmtDnIns = $db_conn->prepare(
            "INSERT INTO delivery_note
             (inv_id, inv_number, inv_table, dl_note, mode_pmnt, ref_no, ref_date, ot_ref,
              order_no, dated, dispatch_doc_no, dlnote_date, dispatch_through, destination, terms)
             VALUES (?, ?, ?, '', '', '', '1970-01-01', '', '', '1970-01-01', '', '1970-01-01', '', '', ?)"
        );
        $stmtDnIns->bind_param('ssss', $invid, $inv_number_dn, $dn_table_tag, $terms);
        $stmtDnIns->execute();
        $stmtDnIns->close();
    }
}

header("Location: cnote_details.php?returnid=" . urlencode($_REQUEST['returnid'] ?? '') . "&termsUpdated=1");
exit;

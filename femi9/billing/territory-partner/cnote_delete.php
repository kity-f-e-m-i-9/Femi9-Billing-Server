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
$stmt = $db_conn->prepare("SELECT prid, qty, total FROM user_return_stock_items WHERE id=? LIMIT 1");
$stmt->bind_param('s', $rowid);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($return && $item) {
    $from_usertype = $return['from_usertype'];
    $from_userid   = $return['from_userid'];
    $to_userid     = $return['to_userid'];   // TP integer id
    $return_status = $return['status'];
    $prid          = (int)$item['prid'];
    $returnqty     = (int)$item['qty'];
    $tp_id         = (int)$to_userid;

    // Reverse stock only if already finalized — draft items never moved stock
    if (in_array($return_status, ['accept', 'completed'])) {
        $created_by = $_SESSION['LOGIN_USER'] ?? 'system';

        $db_conn->begin_transaction();
        try {

            // Receiver (TP): reduce closing_qty back — FOR UPDATE prevents race
            $stmtBefore = $db_conn->prepare(
                "SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=? LIMIT 1 FOR UPDATE"
            );
            $stmtBefore->bind_param('ii', $tp_id, $prid);
            $stmtBefore->execute();
            $before = (int)($stmtBefore->get_result()->fetch_assoc()['closing_qty'] ?? 0);
            $stmtBefore->close();

            $after = max(0, $before - $returnqty);

            $stmt = $db_conn->prepare(
                "UPDATE territory_partner_stock SET closing_qty=? WHERE territory_partner_id=? AND product_id=?"
            );
            $stmt->bind_param('iii', $after, $tp_id, $prid);
            $stmt->execute();
            $stmt->close();

            // Ledger: record the reversal of the credit note
            $stmt = $db_conn->prepare(
                "INSERT INTO territory_partner_stock_ledger
                 (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
                 VALUES (?, ?, 'deduct', ?, ?, ?, 'credit_note', ?, 'credit note item removed', ?)"
            );
            $stmt->bind_param('iiiiiss', $tp_id, $prid, $returnqty, $before, $after, $returnid, $created_by);
            $stmt->execute();
            $stmt->close();

            // Sender: restore their stock — FOR UPDATE + floor
            if (in_array($from_usertype, ['super_stockiest','stockiest','super_distributor','distributor'])) {
                $stmtSdrLock = $db_conn->prepare(
                    "SELECT id, input_qty, closing_qty FROM stock
                     WHERE product_id=? AND user_type=? AND user_id=? LIMIT 1 FOR UPDATE"
                );
                $stmtSdrLock->bind_param('iss', $prid, $from_usertype, $from_userid);
                $stmtSdrLock->execute();
                $sdrRow = $stmtSdrLock->get_result()->fetch_assoc();
                $stmtSdrLock->close();

                if ($sdrRow) {
                    $new_input   = (int)$sdrRow['input_qty']   + $returnqty;
                    $new_closing = (int)$sdrRow['closing_qty'] + $returnqty;
                    $stmtSdr = $db_conn->prepare("UPDATE stock SET input_qty=?, closing_qty=? WHERE id=? LIMIT 1");
                    $stmtSdr->bind_param('iii', $new_input, $new_closing, $sdrRow['id']);
                    $stmtSdr->execute();
                    $stmtSdr->close();
                }
            }

            // Reduce CN receipt by this item's amount
            $item_credit  = (int)round((float)($item['total'] ?? 0));
            $cn_receiptid = 'CN-' . $returnid;
            $stmtRGet = $db_conn->prepare("SELECT id, received FROM receipt WHERE receiptid=? LIMIT 1");
            $stmtRGet->bind_param('s', $cn_receiptid);
            $stmtRGet->execute();
            $rcptRow = $stmtRGet->get_result()->fetch_assoc();
            $stmtRGet->close();

            if ($rcptRow) {
                $new_received = max(0, (int)$rcptRow['received'] - $item_credit);
                if ($new_received === 0) {
                    $stmtRDel = $db_conn->prepare("DELETE FROM receipt WHERE id=?");
                    $stmtRDel->bind_param('i', $rcptRow['id']);
                    $stmtRDel->execute();
                    $stmtRDel->close();
                } else {
                    $stmtRUpd = $db_conn->prepare("UPDATE receipt SET received=? WHERE id=?");
                    $stmtRUpd->bind_param('ii', $new_received, $rcptRow['id']);
                    $stmtRUpd->execute();
                    $stmtRUpd->close();
                }
            }

            // Delete the item
            $stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id=?");
            $stmt->bind_param('s', $rowid);
            $stmt->execute();
            $stmt->close();

            $db_conn->commit();

        } catch (\Throwable $e) {
            $db_conn->rollback();
            error_log('[TP cnote_delete] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $_SESSION['errorMessage'] = "Failed to remove credit note item. Please try again.";
            $enc_returnid = base64_encode($returnid);
            header("Location: cnote_details.php?returnid=$enc_returnid&DeleteFailed"); exit;
        }

    } else {
        // Pending (draft) — no stock was ever moved; just delete the row
        $stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id=?");
        $stmt->bind_param('s', $rowid);
        $stmt->execute();
        $stmt->close();
    }
}

$enc_returnid = base64_encode($returnid);
if (isset($_REQUEST['redirurl']) && $_REQUEST['redirurl'] === 'cnote_details') {
    header("Location: cnote_details.php?returnid=$enc_returnid&DeleteSuccess");
} else {
    $enc_invid = $_REQUEST['InvoiceID'] ?? '';
    header("Location: cnote_new.php?returnid=$enc_returnid&InvoiceID=$enc_invid&DeleteSuccess");
}
exit;
?>

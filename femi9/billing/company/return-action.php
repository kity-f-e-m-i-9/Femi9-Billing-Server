<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

$returnid       = base64_decode($_REQUEST['returnid'] ?? '');
$urlname        = $_REQUEST['urlname'] ?? 'stock_return_pending';
$updatestatus   = $_REQUEST['updatestatus'] ?? '';
$createdBy      = $_SESSION['LOGIN_USER'] ?? 'system';
$stockService   = new StockService($db_conn);

if (empty($returnid)) {
    die("Error: return ID required");
}

// ── Update damaged qty (accept path only) ────────────────────────────────────
if ($updatestatus === 'accept' && !empty($_REQUEST['rtnid'])) {
    $rtnids       = explode(',', implode(',', (array)$_REQUEST['rtnid']));
    $damaged_qtys = explode(',', implode(',', (array)$_REQUEST['damaged_qty']));
    foreach ($rtnids as $idx => $rid) {
        $rid = (int)$rid;
        $dmg = (int)($damaged_qtys[$idx] ?? 0);
        if (!$rid) continue;
        $s = $db_conn->prepare(
            "UPDATE user_return_stock_items SET damaged_qty = ? WHERE id = ?"
        );
        $s->bind_param('ii', $dmg, $rid);
        $s->execute();
        $s->close();
    }
}

// ── Load return header ────────────────────────────────────────────────────────
$stmt = $db_conn->prepare(
    "SELECT * FROM user_return_stock WHERE returnid = ?"
);
$stmt->bind_param('s', $returnid);
$stmt->execute();
$returnHeader = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$returnHeader) {
    die("Return not found: $returnid");
}

// Idempotency guard — don't process if already accepted or rejected
if ($returnHeader['status'] !== 'pending') {
    echo "<script>window.location='{$urlname}?updatedsuccess';</script>";
    exit;
}

$fromusertype = $returnHeader['from_usertype'];
$fromuserid   = $returnHeader['from_userid'];
$to_usertype  = $returnHeader['to_usertype'];
$to_userid    = $returnHeader['to_userid'];
$total_amount = (int)$returnHeader['total'];

// ── Load return items ─────────────────────────────────────────────────────────
$stmt = $db_conn->prepare(
    "SELECT * FROM user_return_stock_items WHERE returnid = ? AND status = 'pending'"
);
$stmt->bind_param('s', $returnid);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─────────────────────────────────────────────────────────────────────────────
// REJECT — return refused, restore buyer's stock
// ─────────────────────────────────────────────────────────────────────────────
if ($updatestatus === 'reject') {

    $db_conn->begin_transaction();
    try {
        foreach ($items as $item) {
            $prid = (int) $item['prid'];
            $qty  = (int) $item['qty'];

            // Restore buyer's returnqty ↓ and closing_qty ↑
            $stockService->rejectReturn(
                $prid, $fromusertype, $fromuserid,
                $qty, $returnid, $createdBy,
                true // externalTransaction
            );

            $s = $db_conn->prepare(
                "UPDATE user_return_stock_items SET status = 'reject'
                  WHERE returnid = ? AND prid = ?"
            );
            $s->bind_param('si', $returnid, $prid);
            $s->execute();
            $s->close();
        }

        // Decrement credit balance if it was recorded
        $s = $db_conn->prepare(
            "SELECT credit_amount FROM return_credit
              WHERE usertype = ? AND userid = ?"
        );
        $s->bind_param('ss', $fromusertype, $fromuserid);
        $s->execute();
        $creditRow = $s->get_result()->fetch_assoc();
        $s->close();

        if ($creditRow) {
            $newCredit = max(0, (int)$creditRow['credit_amount'] - $total_amount);
            $s = $db_conn->prepare(
                "UPDATE return_credit SET credit_amount = ?
                  WHERE usertype = ? AND userid = ?"
            );
            $s->bind_param('iss', $newCredit, $fromusertype, $fromuserid);
            $s->execute();
            $s->close();
        }

        $s = $db_conn->prepare(
            "UPDATE user_return_stock SET status = 'reject' WHERE returnid = ?"
        );
        $s->bind_param('s', $returnid);
        $s->execute();
        $s->close();

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("return-action reject error: " . $e->getMessage());
        die("Return rejection failed. Please try again.");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ACCEPT — return approved, credit stock back to receiver (to_user = company)
//           AND remove returned goods from the sender's (from_user) ledger
// ─────────────────────────────────────────────────────────────────────────────
if ($updatestatus === 'accept') {

    $db_conn->begin_transaction();
    try {
        foreach ($items as $item) {
            $prid = (int) $item['prid'];
            $qty  = (int) $item['qty'];

            // Credit receiver's input_qty ↑ and closing_qty ↑ (goods physically returned)
            $stockService->acceptReturn(
                $prid, $to_usertype, $to_userid,
                $qty, $returnid, $createdBy,
                true // externalTransaction
            );

            // Remove buyer's (from_user) credited stock — they physically handed goods back
            // (input_qty ↓, closing_qty ↓).  Only applies when buyer maintains a ledger.
            if (in_array($fromusertype, StockService::STOCK_MAINTAINING_TYPES, true)) {
                $stockService->reverseCredit(
                    $prid, $fromusertype, $fromuserid,
                    $qty, 'return', $returnid, $createdBy,
                    true // externalTransaction
                );
            }

            $s = $db_conn->prepare(
                "UPDATE user_return_stock_items SET status = 'accept'
                  WHERE returnid = ? AND prid = ?"
            );
            $s->bind_param('si', $returnid, $prid);
            $s->execute();
            $s->close();
        }

        $s = $db_conn->prepare(
            "UPDATE user_return_stock SET status = 'accept' WHERE returnid = ?"
        );
        $s->bind_param('s', $returnid);
        $s->execute();
        $s->close();

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("return-action accept error: " . $e->getMessage());
        die("Return acceptance failed. Please try again.");
    }
}

echo "<script>window.location='{$urlname}?updatedsuccess';</script>";
?>

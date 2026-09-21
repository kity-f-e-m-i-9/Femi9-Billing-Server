<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
require_once("include/GodownAccess.php");
error_reporting(0);

// Confirms one or more draft OT sale invoices — the only place in the
// codebase that ever transitions ot_sales_invoice.status from 'draft' to
// 'confirmed' (see ot-sale-action.php's INSERT branch, which only ever sets
// it once, at creation, and never updates it again). A draft is saved with
// no stock effect (see that file's comment on $isDraft); confirming it now
// is the point where it actually becomes a real sale — every product line
// under the tempid gets deducted via StockService::otDeduct(), exactly as
// if it had been submitted as Confirmed in the first place, deducting from
// whatever stock the order's own godown holds RIGHT NOW (never a stored
// snapshot) — correct regardless of anything Auto Transfer may or may not
// have already pre-positioned for this same draft.
//
// Accepts either a single tempid ($_REQUEST['tempid']) or several
// ($_REQUEST['tempids'][] from the bulk "Confirm Selected" button). Each
// order is confirmed independently in its own transaction — one order's
// insufficient stock never blocks the others in a bulk batch.

$tempids = [];
if (isset($_REQUEST['tempids']) && is_array($_REQUEST['tempids'])) {
    $tempids = array_map('strval', $_REQUEST['tempids']);
} elseif (isset($_REQUEST['tempid'])) {
    $tempids = [(string) $_REQUEST['tempid']];
}
$tempids = array_values(array_unique(array_filter($tempids, fn($t) => $t !== '')));

if (empty($tempids)) {
    $_SESSION['errorMessage'] = "No order selected to confirm.";
    echo "<script>window.location='ot-sale-view';</script>";
    exit;
}

$stockService = new StockService($db_conn);
$createdBy     = $_SESSION['LOGIN_USER'] ?? 'system';

$confirmedCount = 0;
$skippedAlready = 0;
$failed         = []; // tempid => reason

foreach ($tempids as $tempid) {
    $stmt = $db_conn->prepare("SELECT status FROM ot_sales_invoice WHERE tempid = ?");
    $stmt->bind_param('s', $tempid);
    $stmt->execute();
    $invRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$invRow) {
        $failed[$tempid] = "Order not found.";
        continue;
    }
    if (($invRow['status'] ?? 'confirmed') !== 'draft') {
        // Already confirmed (e.g. double-click, or included twice in a
        // bulk batch) — not an error, just nothing to do.
        $skippedAlready++;
        continue;
    }

    $linesStmt = $db_conn->prepare(
        "SELECT id, prid, qty, godownid, warehouse_id FROM ot_sales WHERE tempid = ?"
    );
    $linesStmt->bind_param('s', $tempid);
    $linesStmt->execute();
    $lines = $linesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $linesStmt->close();

    if (empty($lines)) {
        $failed[$tempid] = "No product lines found.";
        continue;
    }

    $db_conn->begin_transaction();
    try {
        foreach ($lines as $line) {
            $pid         = (int) $line['prid'];
            $qty         = (int) $line['qty'];
            $godownid    = (string) $line['godownid'];
            $warehouseId = $line['warehouse_id'] !== null ? (int) $line['warehouse_id'] : null;
            if ($qty <= 0) continue;

            $available = $stockService->getClosingQty($pid, $Login_user_TYPEvl, $godownid, $warehouseId);
            if ($available === null || $available < $qty) {
                throw new StockException(
                    "Insufficient stock for product #$pid. Available: " . ($available ?? 0) . ", Requested: $qty"
                );
            }

            $stockService->otDeduct(
                $pid, $Login_user_TYPEvl, $godownid, $qty,
                $tempid, $createdBy, true, $warehouseId
            );
        }

        $upd = $db_conn->prepare("UPDATE ot_sales_invoice SET status = 'confirmed' WHERE tempid = ?");
        $upd->bind_param('s', $tempid);
        $upd->execute();
        $upd->close();

        $db_conn->commit();
        $confirmedCount++;
    } catch (StockException $e) {
        $db_conn->rollback();
        $failed[$tempid] = $e->getMessage();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("ot-sale-confirm-action error for tempid=$tempid: " . $e->getMessage());
        $failed[$tempid] = "An error occurred.";
    }
}

$parts = [];
if ($confirmedCount > 0) $parts[] = "$confirmedCount order(s) confirmed.";
if ($skippedAlready > 0) $parts[] = "$skippedAlready already confirmed.";
if (!empty($failed)) {
    $parts[] = count($failed) . " failed:";
    foreach ($failed as $t => $reason) {
        $parts[] = "$t — $reason";
    }
}

if (!empty($failed) && $confirmedCount === 0 && $skippedAlready === 0) {
    $_SESSION['errorMessage'] = implode(' ', $parts);
} else {
    $_SESSION['sucMessage'] = implode(' ', $parts);
}

echo "<script>window.location='ot-sale-view';</script>";

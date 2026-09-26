<?php
// AJAX backend for internal_transfer_auto.php's "Already Transferred Today"
// tab's "Re-add to TP Purchase Orders" button. Deletes ONLY the
// auto_transfer_skip_today row for one (order, product) — it never touches
// stock or internal_transfer/stock_ledger (unlike undo-auto-transfer.php).
// Staff use this when the earlier transfer's quantity/rate was wrong and
// they want that PO's product to show up as outstanding demand again so
// they can run a fresh Transfer Now for it, while deliberately leaving the
// stock that already moved exactly where it is (they'll reconcile the
// difference manually). Confirmed 2026-09-24.
//
// Also un-skips reason='excluded' rows (created by delete-auto-transfer-
// order.php's "Delete Selected") the same way — that button never touched
// stock either, so re-adding it here is identical: just make the order
// count as outstanding demand again.
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
header('Content-Type: application/json');

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'reason' => 'unauthorized']);
    exit;
}
error_reporting(0);

// source_id is "tp:<orderKey>:<productId>" / "ot:<orderKey>:<productId>",
// exactly as get_auto_transfer_skipped_today() hands it back to the client.
$sourceId = (string) ($_POST['source_id'] ?? '');
$parts = explode(':', $sourceId, 2);
if (count($parts) !== 2 || !in_array($parts[0], ['tp', 'ot'], true)) {
    echo json_encode(['success' => false, 'reason' => 'invalid_source_id']);
    exit;
}
[$sourceType, $sourceRef] = $parts;

ensure_auto_transfer_skip_table($db_conn);
$stmt = $db_conn->prepare(
    "DELETE FROM auto_transfer_skip_today
     WHERE skip_date = CURDATE() AND reason IN ('transferred', 'excluded') AND source_type = ? AND source_ref = ?"
);
$stmt->bind_param('ss', $sourceType, $sourceRef);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode(['success' => true, 'deleted' => $deleted]);

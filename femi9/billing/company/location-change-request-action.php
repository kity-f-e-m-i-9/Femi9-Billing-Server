<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once __DIR__ . '/../shared/ShopLocationChangeRequest.php';
header('Content-Type: application/json');
error_reporting(0);

function respond(bool $ok, string $message = ''): void
{
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$reason = trim($_POST['reason'] ?? '');
if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    respond(false, 'Invalid request.');
}
if ($decision === 'approved' && $reason === '') {
    respond(false, 'Please enter a reason for accepting this request.');
}

ensureShopLocationChangeRequestsTable($db_conn);

$row = $db_conn->query("SELECT shop_id, status FROM ms_shop_location_requests WHERE id = $id")->fetch_assoc();
if (!$row) { respond(false, 'Request not found.'); }
if ($row['status'] !== 'pending') { respond(false, 'This request has already been reviewed.'); }

$shop_id = (int)$row['shop_id'];

// Company reviewing directly bypasses the assigned Sales BDM entirely — no
// bdm_id to attach, but responded_by_name still records that Company acted,
// distinguished from a BDM's own name so the audit trail stays unambiguous.
$reviewerName = trim((string)($_SESSION['LOGIN_USER_NAME'] ?? 'Company')) . ' (Company)';

$db_conn->begin_transaction();
try {
    $stmt = $db_conn->prepare("UPDATE ms_shop_location_requests SET status=?, accept_reason=?, responded_at=NOW(), responded_by_bdm_id=NULL, responded_by_name=? WHERE id=?");
    $stmt->bind_param('sssi', $decision, $reason, $reviewerName, $id);
    $stmt->execute();
    $stmt->close();

    if ($decision === 'approved') {
        $db_conn->query("UPDATE ms_shop SET location_recapture_count=0 WHERE id='$shop_id'");
    }

    $db_conn->commit();
} catch (Throwable $e) {
    $db_conn->rollback();
    respond(false, 'Could not save — please refresh and try again.');
}

respond(true);
